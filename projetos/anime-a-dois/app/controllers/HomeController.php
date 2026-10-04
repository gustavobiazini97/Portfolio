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
        $biblioteca = Serie::biblioteca($user);                              // a tua (vistas com alguém e só tuas)
        $doPar      = $par ? Serie::biblioteca($par, $user) : [];            // só dele: "A Andreia está a ver"

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

        // Da série aberta: com quem a vês? (só esse companheiro aparece no Vs)
        $ligados  = $user->ligados();
        $convites = Serie::convites($user);
        $parVs    = $serie ? $serie->companheiroDe($user) : null;
        $conjunta = $parVs !== null;
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
            'convites'    => $convites,
            'ligados'     => $ligados,                                   // par + amigos: a quem convidar para ver contigo
            // Convites que enviaste para a série aberta (ainda por responder)
            'enviadosSerie' => $serie ? array_values(array_filter($convites, fn ($c) => !$c['recebido'] && $c['serie']->id === $serie->id)) : [],
            'serie'       => $serie,
            'meuEstado'   => $serie?->estadoDe($user),
            'conjunta'    => $conjunta,
            'ultimoPar'   => $ultimoPar,
            'meu'         => $meu,
            'dele'        => $dele,
            'resumo'      => $serie ? $this->resumoVs($meu, $dele, $parVs, $ligados->isNotEmpty()) : '',
            'podeRemover' => $serie ? $serie->podeSerRemovidaPor($user) : false,
            // Para a pesquisa marcar o que já cá está: mal_id → biblioteca | convite | par
            'jaCa'        => Serie::situacaoNaPesquisa($user),
            'novidades'   => Novidade::porVer($user),
            'pedidosAmigos' => Amizade::pedidosRecebidos($user)->count(),   // bolinha no ícone dos amigos
            'esperaPar'   => $user->esperaPar(),   // o que mudou na app desde a última vez que viste
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
    private function resumoVs(array $meu, array $dele, ?User $parceiro, bool $temLigados): string
    {
        if ($parceiro === null) {
            return $temLigados ? 'Vês esta série sozinho. Convida o teu par ou um amigo para verem juntos.' : 'Série só tua por agora.';
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
