<?php
// Envia ao par as notificações de "marcou episódios", "comentou" e "adicionou/propôs uma série",
// por telemóvel (Web Push) e/ou email, conforme as preferências DELE. Uma falha aqui nunca estraga o marcar/comentar.

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

        self::paraPar($autor, 'ep', [
            'titulo' => $titulo,
            'corpo'  => $corpo,
            'url'    => url('serie', 'ver', ['serie' => $serie->slug, 'ep' => $numeros[$n - 1]]),
            'tag'    => 'ep-' . $serie->slug,          // várias marcações seguidas substituem-se em vez de empilhar
        ]);
    }

    // Escreveste um comentário
    public static function comentario(User $autor, Comentario $comentario): void
    {
        $episodio = $comentario->episodio()->with('serie')->first();
        if ($episodio === null) {
            return;
        }
        $texto = mb_strlen($comentario->texto) > 140 ? mb_substr($comentario->texto, 0, 139) . '…' : $comentario->texto;

        self::paraPar($autor, 'com', [
            'titulo' => $autor->nome . ' comentou o episódio ' . $episodio->numero,
            'corpo'  => $texto,
            'url'    => url('serie', 'ver', ['serie' => $episodio->serie->slug, 'ep' => $episodio->numero]),
            'tag'    => 'com-' . $episodio->id,
        ]);
    }

    // Séries: $evento = 'adicionada' (já está na biblioteca), 'proposta' (Quero ver contigo) ou 'aceite'
    public static function serie(User $autor, Serie $serie, string $evento): void
    {
        $episodios = plural((int) $serie->total_episodios, 'episódio', 'episódios');
        [$titulo, $corpo, $url] = match ($evento) {
            'proposta' => [$autor->nome . ' quer ver ' . $serie->nome . ' contigo', $episodios . ' · abre a app para aceitar', url('home') . '#propostas'],
            'aceite'   => [$autor->nome . ' aceitou ' . $serie->nome, 'Já está na biblioteca, em "a ver"', url('home', 'index', ['serie' => $serie->slug])],
            default    => [$autor->nome . ' adicionou ' . $serie->nome, 'Já está na biblioteca · ' . $episodios, url('home', 'index', ['serie' => $serie->slug])],
        };

        self::paraPar($autor, 'serie', [
            'titulo' => $titulo,
            'corpo'  => $corpo,
            'url'    => $url,
            'tag'    => 'serie-' . $serie->id,
            'imagem' => $serie->capa,   // no Android aparece a capa em grande na notificação
        ]);
    }

    // ---------- Envio ----------

    // Envia ao par pelos canais que ele escolheu; $tipo: 'ep', 'com' ou 'serie'
    private static function paraPar(User $autor, string $tipo, array $msg): void
    {
        $par = $autor->parceiro();
        if ($par === null) {
            return;
        }
        $pref = Preferencia::de($par);

        // Links absolutos (a notificação abre fora da página)
        $msg['url'] = url_absoluto() . $msg['url'];

        // Depois de a resposta chegar ao telemóvel de quem marcou (não atrasa o botão)
        depois(function () use ($par, $pref, $tipo, $msg) {
            if ($pref->{$tipo . '_push'}) {
                self::push($par, $msg);
            }
            if ($pref->{$tipo . '_email'} && $pref->email) {
                self::email($pref->email, $msg);
            }
        });
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

    // Email simples em texto, pelo correio do alojamento
    public static function email(string $para, array $msg): bool
    {
        $de = Database::config()['email_de'] ?? 'animeadois@alwaysdata.net';
        $cabecalhos = implode("\r\n", [
            'From: Anime a Dois <' . $de . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);
        $corpo = $msg['corpo'] . "\n\nAbrir: " . $msg['url'] . "\n\n— Anime a Dois (podes desligar estes emails no teu perfil)";

        return @mail($para, mb_encode_mimeheader($msg['titulo'], 'UTF-8'), $corpo, $cabecalhos);
    }
}
