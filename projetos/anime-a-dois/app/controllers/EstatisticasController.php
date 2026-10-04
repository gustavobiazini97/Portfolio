<?php
// Estatísticas de uma série: números dos dois (tu e o companheiro desta série), ritmo semanal, arcos e curiosidades.

class EstatisticasController extends Controller
{
    // GET ?c=estatisticas[&serie=<slug>]
    public function index(): void
    {
        $user     = $this->exigirLogin();
        $series   = Serie::naBiblioteca($user);   // a tua biblioteca (sem convites)

        // Série do URL; senão a do último episódio que viste; senão a primeira
        $ultimo = $user->ultimoVisto();
        $serie  = Serie::porSlug($_GET['serie'] ?? null)
               ?? ($ultimo ? $ultimo->serie : null)
               ?? $series->first();

        // Série do URL fora da tua biblioteca → a primeira da tua
        if ($serie !== null && !$serie->naBibliotecaDe($user)) {
            $serie = $series->first();
        }

        // Null guard: biblioteca vazia
        if ($serie === null) {
            $this->redirect('home');
        }

        // Série só tua: as estatísticas mostram só os teus números; com companheiros, os de todos
        $companheiros = $serie->companheirosDe($user);

        $this->render('estatisticas/index', [
            'titulo'    => 'Estatísticas',
            'serieSlug' => $serie->slug,
            'serieAcento' => $serie->acento,
            'user'      => $user,
            'companheiros' => $companheiros,
            'series'    => $series,
            'serie'     => $serie,
            'est'       => (new Estatisticas($serie, $user, $companheiros))->calcular(),
        ]);
    }
}
