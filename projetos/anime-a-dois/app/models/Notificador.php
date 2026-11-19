<?php
// Envia as notificações de "marcou episódios", "comentou", séries e amizades para o telemóvel de quem as deve
// receber, se essa pessoa as tiver ligadas: app Android (Firebase, ver Fcm.php) e site instalado (Web Push).
// Uma falha aqui nunca estraga o marcar/comentar.

use Illuminate\Database\Capsule\Manager as Capsule;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class Notificador
{
    // ---------- Chaves VAPID (identificam a app junto dos serviços de push) ----------

    // Geradas uma vez no próprio servidor e guardadas em config_app: nunca saem de lá
    public static function vapid(): array
    {
        $ler = fn (string $chave) => Capsule::table('config_app')->where('chave', $chave)->value('valor');

        $publica = $ler('vapid_publica');
        $privada = $ler('vapid_privada');
        if ($publica === null || $privada === null) {
            $chaves = VAPID::createVapidKeys();
            Capsule::table('config_app')->insertOrIgnore([
                ['chave' => 'vapid_publica', 'valor' => $chaves['publicKey']],
                ['chave' => 'vapid_privada', 'valor' => $chaves['privateKey']],
            ]);
            // Relê: se dois pedidos geraram ao mesmo tempo, fica a primeira que entrou
            $publica = $ler('vapid_publica');
            $privada = $ler('vapid_privada');
        }
        return ['publicKey' => $publica, 'privateKey' => $privada];
    }

    // ---------- Eventos ----------

    // Marcaste episódios como vistos (só quando ficam vistos; desmarcar não avisa)
    public static function episodios(User $autor, Serie $serie, array $numeros, array $titulos): void
    {
        // Séries só tuas não incomodam ninguém: só avisa quem vê esta série contigo
        $companheiros = $serie->companheirosDe($autor);
        if ($companheiros->isEmpty()) {
            return;
        }
        sort($numeros);
        $n = count($numeros);
        if ($n === 0) {
            return;
        }

        if ($n === 1) {
            $titulo = $autor->nome . ' viu o episódio ' . $numeros[0];
            $tituloEp = $titulos[$numeros[0]] ?? null;
            $corpo    = $serie->nome . ($tituloEp ? ' · ' . $tituloEp : '');
        } else {
            $titulo = $autor->nome . ' marcou ' . $n . ' episódios';
            $corpo  = $serie->nome . ' · ' . $numeros[0] . '–' . $numeros[$n - 1];
        }

        foreach ($companheiros as $c) {
            self::paraPessoa($c, 'ep', [
                'titulo' => $titulo,
                'corpo'  => $corpo,
                'url'    => url('serie', 'ver', ['serie' => $serie->slug, 'ep' => $numeros[$n - 1]]),
                'tag'    => 'ep-' . $serie->slug,          // várias marcações seguidas substituem-se em vez de empilhar
            ]);
        }
    }

    // Escreveste um comentário
    public static function comentario(User $autor, Comentario $comentario): void
    {
        $episodio = $comentario->episodio()->with('serie')->first();
        $companheiros = $episodio ? $episodio->serie->companheirosDe($autor) : collect();
        if ($companheiros->isEmpty()) {
            return;   // só nas séries vistas com alguém
        }
        $texto = mb_strlen($comentario->texto) > 140 ? mb_substr($comentario->texto, 0, 139) . '…' : $comentario->texto;

        foreach ($companheiros as $c) {
            self::paraPessoa($c, 'com', [
                'titulo' => $autor->nome . ' comentou o episódio ' . $episodio->numero,
                'corpo'  => $texto,
                'url'    => url('serie', 'ver', ['serie' => $episodio->serie->slug, 'ep' => $episodio->numero]),
                'tag'    => 'com-' . $episodio->id,
            ]);
        }
    }

    // Séries: $evento = 'convite' (Quero ver contigo), 'aceite' (o convite foi aceite) ou 'juntou' (juntou-se a uma série tua).
    // $para = quem recebe o aviso.
    public static function serie(User $autor, Serie $serie, string $evento, User $para): void
    {
        $episodios = plural((int) $serie->total_episodios, 'episódio', 'episódios');
        [$titulo, $corpo, $url] = match ($evento) {
            'aceite' => [$autor->nome . ' aceitou ver ' . $serie->nome . ' contigo', 'Agora vêem a série juntos', url('home', 'index', ['serie' => $serie->slug])],
            'juntou' => [$autor->nome . ' juntou-se a ti em ' . $serie->nome, 'Agora vêem a série juntos', url('home', 'index', ['serie' => $serie->slug])],
            default  => [$autor->nome . ' quer ver ' . $serie->nome . ' contigo', $episodios . ' · abre a app para aceitar', url('home') . '#convites'],
        };

        self::paraPessoa($para, 'serie', [
            'titulo' => $titulo,
            'corpo'  => $corpo,
            'url'    => $url,
            'tag'    => 'serie-' . $serie->id,
            'imagem' => $serie->capa,   // no Android aparece a capa em grande na notificação
        ]);
    }

    // Amizades: $evento = 'pedido' (alguém te pediu amizade) ou 'aceite' (aceitaram o teu pedido / ficaram amigos)
    public static function amigos(User $autor, User $para, string $evento): void
    {
        [$titulo, $corpo] = $evento === 'aceite'
            ? [$autor->nome . ' é agora teu amigo', 'Já podem ver o perfil um do outro']
            : [$autor->nome . ' quer ser teu amigo', 'Abre a app para aceitar'];

        self::paraPessoa($para, 'amigo', [
            'titulo' => $titulo,
            'corpo'  => $corpo,
            'url'    => url('amigos'),
            'tag'    => 'amigo-' . $autor->id,
        ]);
    }

    // Notificação de teste para a própria pessoa (botão no perfil, site e app). Devolve [ok, mensagem].
    // No máximo 20 por hora (só vai para os teus telemóveis; o limite trava só um botão a ser martelado).
    public static function teste(User $user): array
    {
        if (!Limite::registar('teste-notif:' . $user->id, 20, 3600)) {
            return [false, 'Já fizeste muitos testes. Tenta daqui a uma hora.'];
        }
        $n = self::telemovel($user, [
            'titulo' => 'Anime a Dois',
            'corpo'  => 'As notificações estão a funcionar 🎉',
            'url'    => url_absoluto() . url('home'),
            'tag'    => 'teste',
        ]);
        return [true, $n > 0
            ? 'Teste enviado para ' . plural($n, 'telemóvel', 'telemóveis') . '.'
            : 'Nenhum telemóvel com notificações ativas.'];
    }

    // ---------- Envio ----------

    // Envia a uma pessoa, se ela tiver este tipo ligado; $tipo: 'ep', 'com', 'serie' ou 'amigo'
    private static function paraPessoa(User $destino, string $tipo, array $msg): void
    {
        if (!Preferencia::de($destino)->{$tipo . '_push'}) {
            return;
        }

        // Links absolutos (a notificação abre fora da página)
        $msg['url'] = url_absoluto() . $msg['url'];

        // Depois de a resposta chegar ao telemóvel de quem marcou (não atrasa o botão)
        depois(fn () => self::telemovel($destino, $msg));
    }

    // Para todos os telemóveis da pessoa: app Android (Firebase) + site instalado (Web Push).
    // Devolve quantos a receberam. Uma falha num canal não impede o outro.
    public static function telemovel(User $destino, array $msg): int
    {
        $n = 0;
        try {
            $n += Fcm::enviar($destino, $msg);
        } catch (Throwable $e) {
            error_log('[fcm] ' . $e->getMessage());
        }
        try {
            $n += self::push($destino, $msg);
        } catch (Throwable $e) {
            error_log('Push falhou: ' . $e->getMessage());
        }
        return $n;
    }

    // Web Push para todos os telemóveis do par; subscrições que já não existem são apagadas
    public static function push(User $destino, array $msg): int
    {
        $subs = Subscricao::where('user_id', $destino->id)->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        $vapid = self::vapid();
        $webPush = new WebPush(['VAPID' => [
            'subject'    => 'mailto:' . (Database::config()['email_de'] ?? 'animeadois@alwaysdata.net'),
            'publicKey'  => $vapid['publicKey'],
            'privateKey' => $vapid['privateKey'],
        ]], ['TTL' => 86400], 8);   // guarda até 1 dia se o telemóvel estiver desligado; 8s de timeout

        $payload = json_encode($msg, JSON_UNESCAPED_UNICODE);
        foreach ($subs as $s) {
            $webPush->queueNotification(Subscription::create([
                'endpoint'        => $s->endpoint,
                'publicKey'       => $s->p256dh,
                'authToken'       => $s->auth,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        $enviados = 0;
        foreach ($webPush->flush() as $relatorio) {
            if ($relatorio->isSuccess()) {
                $enviados++;
            } elseif ($relatorio->isSubscriptionExpired()) {
                // O telemóvel desinstalou a app ou retirou a permissão
                Subscricao::where('chave_hash', hash('sha256', $relatorio->getEndpoint()))->delete();
            } else {
                error_log('Push falhou: ' . $relatorio->getReason());
            }
        }
        return $enviados;
    }
}
