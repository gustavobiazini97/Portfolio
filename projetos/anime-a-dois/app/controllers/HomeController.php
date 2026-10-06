<?php
// Dashboard: separadores por série, o último episódio do par e o Vs da série escolhida.

class HomeController extends Controller
{
    // GET ?c=home[&serie=<slug>]
    public function index(): void
    {
        $user     = $this->exigirLogin();
        $parceiro = $user->parceiro();
        $series   = Serie::ordenadas();

        // Último episódio de cada um (em qualquer série)
        $ultimoPar = $parceiro ? $parceiro->ultimoVisto() : null;
        $ultimoTu  = $user->ultimoVisto();

        // Série aberta: a do URL; senão a do último episódio do par (para o Vs ser da mesma série);
        // senão a tua; senão a primeira
        $serie = Serie::porSlug($_GET['serie'] ?? null)
              ?? ($ultimoPar ? $ultimoPar->serie : null)
              ?? ($ultimoTu ? $ultimoTu->serie : null)
              ?? $series->first();

        // Null guard: base de dados sem séries (seed por correr)
        if ($serie === null) {
            exit('Ainda não há séries na base de dados. Corre: php database/seed.php');
        }

        $meu  = $serie->progressoDe($user);
        $dele = $serie->progressoDe($parceiro);

        $this->render('home/index', [
            'titulo'    => 'Início',
            'serieSlug' => $serie->slug,
            'user'      => $user,
            'parceiro'  => $parceiro,
            'series'    => $series,
            'serie'     => $serie,
            'ultimoPar' => $ultimoPar,
            'meu'       => $meu,
            'dele'      => $dele,
            'resumo'    => Serie::resumoVs($meu, $dele, $parceiro),   // frase do Vs (vive no Model)
        ]);
    }
}
