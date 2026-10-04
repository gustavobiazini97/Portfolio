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
            'resumo'    => $this->resumoVs($meu, $dele, $parceiro),
        ]);
    }

    // Frase por baixo das barras: quem vai à frente e por quantos episódios
    private function resumoVs(array $meu, array $dele, ?User $parceiro): string
    {
        if ($parceiro === null) {
            return 'Quando o teu par criar conta, aparece aqui ao teu lado.';
        }

        $dif = $meu['posicao'] - $dele['posicao'];
        if ($dif > 0) {
            return 'Vais ' . plural($dif, 'episódio', 'episódios') . ' à frente.';
        }
        if ($dif < 0) {
            return $parceiro->nome . ' vai ' . plural(-$dif, 'episódio', 'episódios') . ' à frente.';
        }
        return 'Estão os dois no mesmo episódio.';
    }
}
