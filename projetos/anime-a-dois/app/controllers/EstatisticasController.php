<?php
// Estatísticas de uma série: números dos dois, ritmo semanal, arcos e curiosidades.

class EstatisticasController extends Controller
{
    // GET ?c=estatisticas[&serie=<slug>]
    public function index(): void
    {
        $user     = $this->exigirLogin();
        $parceiro = $user->parceiro();
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

        // Série só tua: as estatísticas mostram só os teus números
        if (!$serie->conjunta($user, $parceiro)) {
            $parceiro = null;
        }

        $this->render('estatisticas/index', [
            'titulo'    => 'Estatísticas',
            'serieSlug' => $serie->slug,
            'serieAcento' => $serie->acento,
            'user'      => $user,
            'parceiro'  => $parceiro,
            'series'    => $series,
            'serie'     => $serie,
            'est'       => (new Estatisticas($serie, $user, $parceiro))->calcular(),
        ]);
    }
}
