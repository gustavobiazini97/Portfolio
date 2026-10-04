<?php
// Página de uma série: o mapa dos dois, a pista de episódios e o marcar/desmarcar (um ou vários).

class SerieController extends Controller
{
    // GET ?c=serie&a=ver&serie=<slug>[&ep=<número>]
    public function ver(): void
    {
        $user  = $this->exigirLogin();
        $serie = Serie::porSlug($_GET['serie'] ?? null);

        // Null guard: slug desconhecido volta ao início
        if ($serie === null) {
            $this->flash('erro', 'Essa série não existe.');
            $this->redirect('home');
        }

        // Só abre séries da tua biblioteca (um convite aceita-se primeiro no Início)
        if (!$serie->naBibliotecaDe($user)) {
            $this->flash('info', $serie->nomeCurto() . ' não está na tua biblioteca.');
            $this->redirect('home', 'index', [], 'convites');
        }

        // O companheiro só aparece (mapa, avatares, comentários dele) se vês esta série com alguém
        $parceiro = $serie->companheiroDe($user);
        $meu      = $serie->progressoDe($user);

        // Cartão que abre ao centro: o pedido no URL ou o seguinte ao teu
        $inicial = (int) ($_GET['ep'] ?? 0);
        if ($inicial < 1 || $inicial > $serie->total_episodios) {
            $inicial = min($meu['posicao'] + 1, $serie->total_episodios);
        }

        $this->render('serie/ver', [
            'titulo'     => $serie->nome,
            'serieSlug'  => $serie->slug,
            'serieAcento' => $serie->acento,      // cor das séries novas ([data-acento] no CSS)
            'classeBody' => 'pagina-serie',   // ecrã de altura fixa: a pista ocupa o que sobra
            'user'      => $user,
            'parceiro'  => $parceiro,
            'serie'     => $serie,
            'fillers'   => $serie->totalFillers(),
            'meu'       => $meu,
            'dele'      => $serie->progressoDe($parceiro),
            'episodios' => $serie->episodiosPara($user, $parceiro),
            'inicial'   => $inicial,
            // Faltam fillers ou a série em emissão está desatualizada: o browser vai buscar dados novos
            'sincronizar' => $serie->precisaSincronizar(),
        ]);
    }

    // POST: marca ou desmarca episódios de uma série.
    //   serie            slug da série
    //   episodio_ids[]   um ou vários IDs
    //   acao             marcar | desmarcar | alternar (alternar só para um episódio)
    // Com fetch (Accept: application/json) responde JSON; sem JavaScript faz Post/Redirect/Get.
    public function marcar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $serie = Serie::porSlug($_POST['serie'] ?? null);
            $ids   = array_map('intval', (array) ($_POST['episodio_ids'] ?? []));

            // Só séries da tua biblioteca
            if ($serie !== null && !$serie->naBibliotecaDe($user)) {
                $this->falhar($serie->nomeCurto() . ' não está na tua biblioteca.');
            }

            // Só episódios que existem E pertencem a esta série
            $episodios = $serie === null ? collect() : Episodio::whereIn('id', $ids)->where('serie_id', $serie->id)->orderBy('numero')->get();

            // Null guard: série inválida ou nenhum episódio válido
            if ($serie === null || $episodios->isEmpty()) {
                $this->falhar('Não encontrei esses episódios.');
            }

            // "alternar" decide pelo estado atual do único episódio
            $acao = $_POST['acao'] ?? 'alternar';
            if ($acao === 'alternar') {
                $acao = $user->viu($episodios->first()) ? 'desmarcar' : 'marcar';
            }
            $visto = $acao === 'marcar';

            $mudaram = $user->definirVistos($episodios->pluck('id')->all(), $visto);

            // Avisa o par só dos episódios que ficaram vistos agora (não dos que já estavam)
            if ($visto && $mudaram !== []) {
                $novos = $episodios->whereIn('id', $mudaram);
                Notificador::episodios($user, $serie, $novos->pluck('numero')->map(fn ($x) => (int) $x)->all(),
                                       $novos->pluck('titulo', 'numero')->all());
            }

            // Mensagem curta para o aviso
            $n = $episodios->count();
            $mensagem = $n === 1
                ? 'Episódio ' . $episodios->first()->numero . ($visto ? ' marcado como visto.' : ' desmarcado.')
                : $n . ' episódios ' . ($visto ? 'marcados como vistos.' : 'desmarcados.');

            if ($this->querJson()) {
                $progresso = $serie->progressoDe($user);
                $this->json([
                    'ok'       => true,
                    'visto'    => $visto,
                    'numeros'  => $episodios->pluck('numero')->map(fn ($x) => (int) $x)->all(),
                    'pct'      => $progresso['pct'],
                    'mensagem' => $mensagem,
                ]);
            }

            // Sem JavaScript: PRG, com o último episódio mexido ao centro
            $this->flash('sucesso', $mensagem);
            $this->redirect('serie', 'ver', ['serie' => $serie->slug, 'ep' => $episodios->last()->numero]);
        } catch (PDOException $e) {
            $this->falhar('Não foi possível guardar. Tenta outra vez.');
        }
    }

    // GET ?episodio=<id>: comentários de um episódio, em JSON (para a folha de comentários)
    public function comentarios(): void
    {
        $user = $this->exigirLogin();
        $episodio = Episodio::find((int) ($_GET['episodio'] ?? 0));

        // Null guard depois do find()
        if ($episodio === null) {
            $this->json(['ok' => false, 'mensagem' => 'Esse episódio não existe.'], 404);
        }

        // Só séries da tua biblioteca; e só os teus comentários e os de quem vê a série contigo
        $serie = $episodio->serie;
        if ($serie === null || !$serie->naBibliotecaDe($user)) {
            $this->json(['ok' => false, 'mensagem' => 'Essa série não está na tua biblioteca.'], 403);
        }
        $autores = array_filter([$user->id, $serie->companheiroDe($user)?->id]);
        $lista = Comentario::with('autor')->where('episodio_id', $episodio->id)->whereIn('user_id', $autores)->orderBy('criado_em')->orderBy('id')->get();
        $this->json([
            'ok'          => true,
            'comentarios' => $lista->map(fn ($c) => $c->paraJson($user))->all(),
        ]);
    }

    // POST: escreve um comentário (responde com a lista atualizada)
    public function comentar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $episodio = Episodio::find((int) ($_POST['episodio_id'] ?? 0));
            if ($episodio === null) {
                $this->json(['ok' => false, 'mensagem' => 'Esse episódio não existe.'], 404);
            }

            if ($episodio->serie === null || !$episodio->serie->naBibliotecaDe($user)) {
                $this->json(['ok' => false, 'mensagem' => 'Essa série não está na tua biblioteca.'], 403);
            }
            $comentario = Comentario::escrever($user, $episodio, $_POST['texto'] ?? '');
            Notificador::comentario($user, $comentario);   // avisa o companheiro da série (telemóvel/email, conforme ele quiser)
            $_GET['episodio'] = $episodio->id;
            $this->comentarios();
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            $this->json(['ok' => false, 'mensagem' => 'Não foi possível guardar o comentário.'], 500);
        }
    }

    // POST: apaga um comentário — só o próprio autor pode
    public function apagarComentario(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        $comentario = Comentario::find((int) ($_POST['id'] ?? 0));
        if ($comentario === null || $comentario->user_id !== $user->id) {
            $this->json(['ok' => false, 'mensagem' => 'Só podes apagar os teus comentários.'], 403);
        }

        $episodioId = $comentario->episodio_id;
        $comentario->delete();
        $_GET['episodio'] = $episodioId;
        $this->comentarios();
    }

    // Erro no marcar: JSON para o fetch, aviso + início para o formulário normal
    private function falhar(string $mensagem): never
    {
        if ($this->querJson()) {
            $this->json(['ok' => false, 'mensagem' => $mensagem], 422);
        }
        $this->flash('erro', $mensagem);
        $this->redirect('home');
    }
}
