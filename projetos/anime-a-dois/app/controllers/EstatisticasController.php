<?php
// Estatísticas de uma série: números dos dois, ritmo semanal, arcos e curiosidades.

class EstatisticasController extends Controller
{
    // GET ?c=estatisticas[&serie=<slug>]
    public function index(): void
    {
        $user     = $this->exigirLogin();
        $parceiro = $user->parceiro();
        $series   = Serie::ordenadas();

        // Série do URL; senão a do último episódio que viste; senão a primeira
        $ultimo = $user->ultimoVisto();
        $serie  = Serie::porSlug($_GET['serie'] ?? null)
               ?? ($ultimo ? $ultimo->serie : null)
               ?? $series->first();

        // Null guard: base de dados sem séries
        if ($serie === null) {
            $this->redirect('home');
        }

        $this->render('estatisticas/index', [
            'titulo'    => 'Estatísticas',
            'serieSlug' => $serie->slug,
            'user'      => $user,
            'parceiro'  => $parceiro,
            'series'    => $series,
            'serie'     => $serie,
            'est'       => (new Estatisticas($serie, $user, $parceiro))->calcular(),
        ]);
    }
}
