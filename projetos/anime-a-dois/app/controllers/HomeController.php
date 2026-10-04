<?php
// Início: o último episódio do par, a tua biblioteca (fila de capas), a fila "A <par> está a ver",
// os convites "Quero ver contigo", o Vs da série escolhida com o teu estado (a ver, em pausa, acabado)
// e o popup das novidades.

class HomeController extends Controller
{
    // GET ?c=home[&serie=<slug>]
    public function index(): void
    {
        $user       = $this->exigirLogin();
        $par        = $user->parceiro();
        $biblioteca = Serie::biblioteca($user, $par);                        // a tua (conjuntas e só tuas)
        $doPar      = $par ? Serie::biblioteca($par, null, $user) : [];      // só dele: "A Andreia está a ver"

        // Último episódio de cada um (em qualquer série)
        $ultimoPar = $par ? $par->ultimoVisto() : null;
        $ultimoTu  = $user->ultimoVisto();

        // Série aberta: a do URL; senão a do último episódio do par; senão a tua; senão a primeira
        // da fila. Tem de estar na TUA biblioteca. null = biblioteca vazia.
        $candidatas = [Serie::porSlug($_GET['serie'] ?? null), $ultimoPar?->serie, $ultimoTu?->serie, $biblioteca[0]['serie'] ?? null];
        $serie = null;
        foreach ($candidatas as $c) {
            if ($c !== null && $c->naBibliotecaDe($user)) {
                $serie = $c;
                break;
            }
        }

        // Da série aberta: é dos dois? (só nesse caso o par aparece no Vs) e em que estado está o convite
        $conjunta = $serie ? $serie->conjunta($user, $par) : false;
        $parVs    = $conjunta ? $par : null;
        $meu      = $serie ? $serie->progressoDe($user) : null;
        $dele     = $serie ? $serie->progressoDe($parVs) : null;

        $this->render('home/index', [
            'titulo'      => 'Início',
            'serieSlug'   => $serie?->slug,
            'serieAcento' => $serie?->acento,
            'user'        => $user,
            'parceiro'    => $par,
            'parVs'       => $parVs,
            'biblioteca'  => $biblioteca,
            'doPar'       => $doPar,
            'convites'    => Serie::convites($user, $par),
            'serie'       => $serie,
            'meuEstado'   => $serie?->estadoDe($user),
            'conjunta'    => $conjunta,
            'estadoPar'   => $serie && $par ? $serie->estadoDe($par) : null,   // null | convite | a_ver...
            'ultimoPar'   => $ultimoPar,
            'meu'         => $meu,
            'dele'        => $dele,
            'resumo'      => $serie ? $this->resumoVs($meu, $dele, $parVs, $par) : '',
            'podeRemover' => $serie ? $serie->podeSerRemovidaPor($user) : false,
            // Para a pesquisa marcar o que já cá está: mal_id → biblioteca | convite | par
            'jaCa'        => Serie::situacaoNaPesquisa($user, $par),
            'novidades'   => Novidade::porVer($user),   // o que mudou na app desde a última vez que viste
        ]);
    }

    // POST: fechou o popup das novidades → não volta a aparecer até à próxima atualização
    public function novidadesVistas(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $user->marcarNovidadesVistas();

        if ($this->querJson()) {
            $this->json(['ok' => true]);
        }
        $this->redirect('home');
    }

    // Frase por baixo das barras: quem vai à frente e por quantos episódios
    private function resumoVs(array $meu, array $dele, ?User $parceiro, ?User $par): string
    {
        if ($par === null) {
            return 'Quando o teu par criar conta, aparece aqui ao teu lado.';
        }
        if ($parceiro === null) {
            return 'Só tu tens esta série. Convida ' . $par->nome . ' para verem os dois.';
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
