<?php
// Página de uma série: o mapa dos dois, a pista de episódios e o marcar/desmarcar.

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

        // Cartão que abre ao centro: o pedido no URL (depois de marcar) ou o seguinte ao teu
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

    // POST: marca ou desmarca o episódio para quem está com sessão iniciada
    public function marcar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $episodio = Episodio::with('serie')->find((int) ($_POST['episodio_id'] ?? 0));

            // Null guard depois do find()
            if ($episodio === null) {
                $this->flash('erro', 'Esse episódio não existe.');
                $this->redirect('home');
            }

            $ficouVisto = $user->alternarVisto($episodio);
            $this->flash('sucesso', 'Episódio ' . $episodio->numero . ($ficouVisto ? ' marcado como visto.' : ' desmarcado.'));

            // PRG: volta à mesma série, com o mesmo episódio ao centro
            $this->redirect('serie', 'ver', ['serie' => $episodio->serie->slug, 'ep' => $episodio->numero]);
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível guardar. Tenta outra vez.');
            $this->redirect('home');
        }
    }
}
