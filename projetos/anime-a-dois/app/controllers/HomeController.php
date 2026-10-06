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
        $companheiros = $serie ? $serie->companheirosDe($user) : collect();
        $conjunta = $companheiros->isNotEmpty();
        $meu      = $serie ? $serie->progressoDe($user) : null;
        $deles    = $companheiros->map(fn ($c) => $serie->progressoDe($c))->all();   // na ordem dos companheiros

        $this->render('home/index', [
            'titulo'      => 'Início',
            'classeBody'  => 'pagina-inicio',   // no computador o Início fica em duas colunas (app.css)
            'serieSlug'   => $serie?->slug,
            'serieAcento' => $serie?->acento,
            'user'        => $user,
            'parceiro'    => $par,
            'companheiros' => $companheiros,
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
            'deles'       => $deles,
            'resumo'      => $serie ? $this->resumoVs($meu, $companheiros, $deles, $ligados->isNotEmpty()) : '',
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

    // Frase por baixo das barras: quem vai à frente e por quantos episódios (com vários companheiros,
    // compara-te com o que vai mais avançado)
    private function resumoVs(array $meu, $companheiros, array $deles, bool $temLigados): string
    {
        if ($companheiros->isEmpty()) {
            return $temLigados ? 'Vês esta série sozinho. Convida o teu par ou um amigo para verem juntos.' : 'Série só tua por agora.';
        }

        // O companheiro mais avançado
        $lider = 0;
        foreach ($deles as $i => $d) {
            if ($d['posicao'] > $deles[$lider]['posicao']) {
                $lider = $i;
            }
        }
        $dif = $meu['posicao'] - $deles[$lider]['posicao'];
        if ($dif > 0) {
            return 'Vais ' . plural($dif, 'episódio', 'episódios') . ' à frente.';
        }
        if ($dif < 0) {
            return $companheiros[$lider]->nome . ' vai ' . plural(-$dif, 'episódio', 'episódios') . ' à frente.';
        }
        return $companheiros->count() === 1 ? 'Estão os dois no mesmo episódio.' : 'Estão todos no mesmo episódio.';
    }
}
