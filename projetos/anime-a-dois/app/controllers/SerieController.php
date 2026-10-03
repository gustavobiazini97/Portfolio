<?php
// Página de uma série: o mapa dos dois, a pista de episódios e o marcar/desmarcar (um ou vários).

class SerieController extends Controller
{
    // GET ?c=serie&a=ver&serie=<slug>[&ep=<número>]
    public function ver(): void
    {
        $user  = $this->exigirLogin();
        $serie = Serie::porSlug($_GET['serie'] ?? 'naruto');

        // Null guard: slug desconhecido volta ao início
        if ($serie === null) {
            $this->flash('erro', 'Essa série não existe.');
            $this->redirect('home');
        }

        $parceiro = $user->parceiro();
        $meu      = $serie->progressoDe($user);

        // Cartão que abre ao centro: o pedido no URL ou o seguinte ao teu
        $inicial = (int) ($_GET['ep'] ?? 0);
        if ($inicial < 1 || $inicial > $serie->total_episodios) {
            $inicial = min($meu['posicao'] + 1, $serie->total_episodios);
        }

        $this->render('serie/ver', [
            'titulo'    => $serie->nome,
            'serieSlug' => $serie->slug,
            'user'      => $user,
            'parceiro'  => $parceiro,
            'serie'     => $serie,
            'fillers'   => $serie->totalFillers(),
            'meu'       => $meu,
            'dele'      => $serie->progressoDe($parceiro),
            'episodios' => $serie->episodiosPara($user, $parceiro),
            'inicial'   => $inicial,
        ]);
    }

    // POST: marca ou desmarca episódios de uma série.
    //   serie            slug da série
    //   episodio_ids[]   um ou vários IDs
    //   acao             marcar | desmarcar | alternar (alternar só para um episódio)
    // Com fetch (Accept: application/json) responde JSON; sem JavaScript faz Post/Redirect/Get.
    public function marcar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $serie = Serie::porSlug($_POST['serie'] ?? null);
            $ids   = array_map('intval', (array) ($_POST['episodio_ids'] ?? []));

            // Só episódios que existem E pertencem a esta série
            $episodios = $serie === null ? collect() : Episodio::whereIn('id', $ids)->where('serie_id', $serie->id)->orderBy('numero')->get();

            // Null guard: série inválida ou nenhum episódio válido
            if ($serie === null || $episodios->isEmpty()) {
                $this->falhar('Não encontrei esses episódios.');
            }

            // "alternar" decide pelo estado atual do único episódio
            $acao = $_POST['acao'] ?? 'alternar';
            if ($acao === 'alternar') {
                $acao = $user->viu($episodios->first()) ? 'desmarcar' : 'marcar';
            }
            $visto = $acao === 'marcar';

            $user->definirVistos($episodios->pluck('id')->all(), $visto);

            // Mensagem curta para o aviso
            $n = $episodios->count();
            $mensagem = $n === 1
                ? 'Episódio ' . $episodios->first()->numero . ($visto ? ' marcado como visto.' : ' desmarcado.')
                : $n . ' episódios ' . ($visto ? 'marcados como vistos.' : 'desmarcados.');

            if ($this->querJson()) {
                $progresso = $serie->progressoDe($user);
                $this->json([
                    'ok'       => true,
                    'visto'    => $visto,
                    'numeros'  => $episodios->pluck('numero')->map(fn ($x) => (int) $x)->all(),
                    'pct'      => $progresso['pct'],
                    'mensagem' => $mensagem,
                ]);
            }

            // Sem JavaScript: PRG, com o último episódio mexido ao centro
            $this->flash('sucesso', $mensagem);
            $this->redirect('serie', 'ver', ['serie' => $serie->slug, 'ep' => $episodios->last()->numero]);
        } catch (PDOException $e) {
            $this->falhar('Não foi possível guardar. Tenta outra vez.');
        }
    }

    // Erro no marcar: JSON para o fetch, aviso + início para o formulário normal
    private function falhar(string $mensagem): never
    {
        if ($this->querJson()) {
            $this->json(['ok' => false, 'mensagem' => $mensagem], 422);
        }
        $this->flash('erro', $mensagem);
        $this->redirect('home');
    }
}
