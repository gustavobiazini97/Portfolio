<?php
// API: Início (biblioteca, fila do par, convites), página da série (companheiros e episódios),
// marcar vistos e estatísticas. Mesmas regras do site: só se abre o que está na TUA biblioteca
// e só aparecem as pessoas que vêem cada série contigo.

class SeriesApi extends ApiController
{
    // GET /inicio — tudo o que o ecrã de Início precisa num só pedido
    public function inicio(): void
    {
        $user = $this->exigirToken();
        $par  = $user->parceiro();

        // A tua biblioteca (fila de capas) e a fila "O teu par está a ver" (só as dele que não tens)
        $biblioteca = Serie::biblioteca($user);
        $doPar      = $par ? Serie::biblioteca($par, $user) : [];

        // Último episódio do par (respeita a privacidade dele) e o teu
        $ultimoPar = $par ? $par->ultimoVistoPara($user) : null;
        $ultimoTu  = $user->ultimoVisto();

        // Série aberta por defeito (igual ao site): a do último do par, senão a tua, senão a primeira da fila
        $atual = null;
        foreach ([$ultimoPar?->serie, $ultimoTu?->serie, $biblioteca[0]['serie'] ?? null] as $c) {
            if ($c !== null && $c->naBibliotecaDe($user)) {
                $atual = $c->slug;
                break;
            }
        }

        // Convites "Quero ver contigo": recebidos (aceitar/recusar) e enviados (cancelar)
        $convites = array_map(fn ($c) => [
            'serie'    => $this->serieBase($c['serie']),
            'recebido' => $c['recebido'],
            'de'       => $this->userJson($c['de']),
            'para'     => $this->userJson($c['para']),
        ], Serie::convites($user));

        $this->json([
            'ok'            => true,
            'user'          => $this->userJson($user),
            'parceiro'      => $this->userJson($par),
            'esperaPar'     => $user->esperaPar(),                // ainda falta o par criar conta
            'ultimoPar'     => $this->ultimoJson($ultimoPar),
            'ultimoTu'      => $this->ultimoJson($ultimoTu),
            'serieAtual'    => $atual,
            'biblioteca'    => array_map(fn ($i) => $this->itemBibliotecaJson($i), $biblioteca),
            'doPar'         => array_map(fn ($i) => $this->itemBibliotecaJson($i), $doPar),
            'convites'      => $convites,
            'ligados'       => $user->ligados()->map(fn ($u) => $this->userJson($u))->values()->all(),
            'pedidosAmigos' => Amizade::pedidosRecebidos($user)->count(),
            // Para a pesquisa marcar o que já cá está: mal_id → biblioteca | convite
            'jaCa'          => (object) Serie::situacaoNaPesquisa($user),
        ]);
    }

    // GET /series — a tua biblioteca (para separadores, estatísticas, …)
    public function lista(): void
    {
        $user = $this->exigirToken();
        $this->json([
            'ok'     => true,
            'series' => array_map(fn ($i) => $this->itemBibliotecaJson($i), Serie::biblioteca($user)),
        ]);
    }

    // GET /series/{slug} — série da tua biblioteca: o teu estado, os companheiros, o Vs e os episódios
    public function ver(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieDaBiblioteca($slug, $user);

        // Os companheiros só aparecem (mapa, avatares, comentários) se vês esta série com alguém
        $companheiros = $serie->companheirosDe($user)->values();
        $meu   = $serie->progressoDe($user);
        $deles = $companheiros->map(fn ($c) => $serie->progressoDe($c))->all();   // na ordem dos companheiros

        $this->json([
            'ok'           => true,
            'serie'        => $this->serieBase($serie) + ['totalFillers' => $serie->totalFillers()],
            'estado'       => $serie->estadoDe($user),
            'podeRemover'  => $serie->podeSerRemovidaPor($user),
            'user'         => $this->userJson($user),
            'tu'           => $meu,
            'companheiros' => $companheiros->map(fn ($c, $i) => ['user' => $this->userJson($c), 'progresso' => $deles[$i]])->all(),
            'resumo'       => self::resumoVs($meu, $companheiros, $deles, $user->ligados()->isNotEmpty()),
            // Cada episódio: id, numero, titulo, filler, recap, tu, com (um bool por companheiro, pela mesma ordem), coment
            'episodios'    => $serie->episodiosPara($user, $companheiros),
        ]);
    }

    // POST /series/{slug}/vistos  { episodios: [ids], acao: marcar | desmarcar | alternar }
    public function marcar(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieDaBiblioteca($slug, $user);

        // Só IDs inteiros, e só episódios que pertencem mesmo a esta série
        $ids = array_map('intval', (array) $this->campo('episodios', []));
        $episodios = Episodio::whereIn('id', $ids)->where('serie_id', $serie->id)->orderBy('numero')->get();

        // Null guard: nenhum episódio válido
        if ($episodios->isEmpty()) {
            $this->erro('Não encontrei esses episódios.');
        }

        // "alternar" decide pelo estado atual do primeiro episódio (como no site)
        $acao = (string) $this->campo('acao', 'alternar');
        if (!in_array($acao, ['marcar', 'desmarcar', 'alternar'], true)) {
            $this->erro('Ação inválida: usa marcar, desmarcar ou alternar.');
        }
        if ($acao === 'alternar') {
            $acao = $user->viu($episodios->first()) ? 'desmarcar' : 'marcar';
        }
        $visto = $acao === 'marcar';

        $mudaram = $user->definirVistos($episodios->pluck('id')->all(), $visto);

        // Avisa os companheiros só dos episódios que ficaram vistos agora (como no site)
        if ($visto && $mudaram !== []) {
            $novos = $episodios->whereIn('id', $mudaram);
            Notificador::episodios($user, $serie, $novos->pluck('numero')->map(fn ($x) => (int) $x)->all(),
                                   $novos->pluck('titulo', 'numero')->all());
        }

        $n = $episodios->count();
        $this->json([
            'ok'        => true,
            'visto'     => $visto,
            'episodios' => $episodios->pluck('id')->map(fn ($x) => (int) $x)->all(),
            'numeros'   => $episodios->pluck('numero')->map(fn ($x) => (int) $x)->all(),
            'progresso' => $serie->progressoDe($user),
            'mensagem'  => $n === 1
                ? 'Episódio ' . $episodios->first()->numero . ($visto ? ' marcado como visto.' : ' desmarcado.')
                : $n . ' episódios ' . ($visto ? 'marcados como vistos.' : 'desmarcados.'),
        ]);
    }

    // GET /series/{slug}/estatisticas — números (tu + companheiros), ritmo, previsão, arcos e curiosidades
    public function estatisticas(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieDaBiblioteca($slug, $user);
        $companheiros = $serie->companheirosDe($user);

        $est = (new Estatisticas($serie, $user, $companheiros))->calcular();

        // As "pessoas" trazem o Model User: troca-se pelo formato público da API
        $est['pessoas'] = array_map(function (array $p) {
            $p['user'] = $this->userJson($p['user']);
            return $p;
        }, $est['pessoas']);

        // Recorde: "tu" ou o Model do companheiro → nome
        if (is_array($est['recorde'] ?? null) && ($est['recorde']['quem'] ?? null) instanceof User) {
            $est['recorde']['quem'] = $est['recorde']['quem']->nome;
        }

        $this->json([
            'ok'           => true,
            'serie'        => $this->serieBase($serie),
            'minutosPorEp' => (int) ($serie->minutos_ep ?: Estatisticas::MINUTOS_EP),
            'estatisticas' => $est,
        ]);
    }
}
