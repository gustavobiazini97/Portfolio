<?php
// Início: o último episódio do par, a biblioteca (fila de capas), as propostas "Quero ver contigo"
// e o Vs da série escolhida, com o estado dela (a ver, em pausa, acabado).

class HomeController extends Controller
{
    // GET ?c=home[&serie=<slug>]
    public function index(): void
    {
        $user       = $this->exigirLogin();
        $parceiro   = $user->parceiro();
        $biblioteca = Serie::biblioteca($user, $parceiro);   // [['serie', 'tu', 'par', 'atividade'], ...]

        // Último episódio de cada um (em qualquer série)
        $ultimoPar = $parceiro ? $parceiro->ultimoVisto() : null;
        $ultimoTu  = $user->ultimoVisto();

        // Série aberta: a do URL (se não for proposta); senão a do último episódio do par (para o Vs
        // ser da mesma série); senão a tua; senão a primeira da fila. null = biblioteca vazia.
        $doUrl = Serie::porSlug($_GET['serie'] ?? null);
        if ($doUrl !== null && $doUrl->estado === Serie::PROPOSTA) {
            $doUrl = null;
        }
        $serie = $doUrl
              ?? ($ultimoPar ? $ultimoPar->serie : null)
              ?? ($ultimoTu ? $ultimoTu->serie : null)
              ?? ($biblioteca[0]['serie'] ?? null);

        $meu  = $serie ? $serie->progressoDe($user) : null;
        $dele = $serie ? $serie->progressoDe($parceiro) : null;

        $this->render('home/index', [
            'titulo'      => 'Início',
            'serieSlug'   => $serie?->slug,
            'serieAcento' => $serie?->acento,
            'user'        => $user,
            'parceiro'    => $parceiro,
            'biblioteca'  => $biblioteca,
            'propostas'   => Serie::propostas(),
            'serie'       => $serie,
            'ultimoPar'   => $ultimoPar,
            'meu'         => $meu,
            'dele'        => $dele,
            'resumo'      => $serie ? $this->resumoVs($meu, $dele, $parceiro) : '',
            'podeRemover' => $serie ? $serie->podeSerRemovida() : false,
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
