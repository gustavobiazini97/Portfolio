<?php
// API: início (dashboard), séries, episódios, marcar vistos e estatísticas.

class SeriesApi extends ApiController
{
    // GET /inicio — tudo o que o ecrã de Início precisa num só pedido
    public function inicio(): void
    {
        $user     = $this->exigirToken();
        $parceiro = $user->parceiro();

        $ultimoPar = $parceiro ? $parceiro->ultimoVisto() : null;
        $ultimoTu  = $user->ultimoVisto();

        // Série aberta por defeito: a do último episódio do par; senão a tua; senão a primeira (igual ao site)
        $series = Serie::ordenadas();
        $atual  = ($ultimoPar ? $ultimoPar->serie->slug : null)
               ?? ($ultimoTu ? $ultimoTu->serie->slug : null)
               ?? ($series->first()->slug ?? null);

        $this->json([
            'ok'        => true,
            'user'      => $this->userJson($user),
            'parceiro'  => $this->userJson($parceiro),
            'ultimoPar' => $this->ultimoJson($ultimoPar),
            'ultimoTu'  => $this->ultimoJson($ultimoTu),
            'serieAtual'=> $atual,
            'series'    => $series->map(fn (Serie $s) => $this->serieJson($s, $user, $parceiro))->all(),
        ]);
    }

    // GET /series — lista de séries com o progresso dos dois
    public function lista(): void
    {
        $user     = $this->exigirToken();
        $parceiro = $user->parceiro();

        $this->json([
            'ok'     => true,
            'series' => Serie::ordenadas()->map(fn (Serie $s) => $this->serieJson($s, $user, $parceiro))->all(),
        ]);
    }

    // GET /series/{slug} — série + episódios com quem viu cada um (a "pista" da app)
    public function ver(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $parceiro = $user->parceiro();

        $this->json([
            'ok'        => true,
            'serie'     => $this->serieJson($serie, $user, $parceiro) + ['totalFillers' => $serie->totalFillers()],
            'user'      => $this->userJson($user),
            'parceiro'  => $this->userJson($parceiro),
            // Cada episódio: id, numero, titulo, filler, tu, par, coment (nº de comentários)
            'episodios' => $serie->episodiosPara($user, $parceiro),
        ]);
    }

    // POST /series/{slug}/vistos  { episodios: [ids], acao: marcar | desmarcar | alternar }
    public function marcar(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);

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

        // Avisa o par só dos episódios que ficaram vistos agora (Web Push / email, como no site)
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

    // GET /series/{slug}/estatisticas — números, ritmo semanal, previsão, arcos e curiosidades
    public function estatisticas(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $parceiro = $user->parceiro();

        $est = (new Estatisticas($serie, $user, $parceiro))->calcular();

        // As "pessoas" trazem o Model User: troca-se pelo formato público da API
        $est['pessoas'] = array_map(function (array $p) {
            $p['user'] = $this->userJson($p['user']);
            return $p;
        }, $est['pessoas']);

        $this->json([
            'ok'           => true,
            'serie'        => $this->serieJson($serie, $user, $parceiro),
            'minutosPorEp' => Estatisticas::MINUTOS_EP,
            'estatisticas' => $est,
        ]);
    }

    // Série pelo slug, ou 404 em JSON
    private function serieOu404(string $slug): Serie
    {
        $serie = Serie::porSlug($slug);
        if ($serie === null) {
            $this->erro('Essa série não existe.', 404);
        }
        return $serie;
    }
}
