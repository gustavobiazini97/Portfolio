<?php
// Biblioteca: adicionar séries (ou convidar o par/um amigo a vê-las contigo), atualizá-las, mudar o estado,
// aceitar convites, juntar-se a séries de quem está ligado a ti e tirar séries. A pesquisa e os pedidos às APIs de anime (AniList, Kitsu, Jikan) são feitos no
// browser (js/app.js); aqui chegam os dados já recebidos. A lógica vive no Model Serie.

class BibliotecaController extends Controller
{
    // POST: mal_id + modo ("ver" = só para ti, "propor" = para ti + convite a "com": Quero ver contigo)
    //       + com (id do par ou de um amigo, só em "propor") + info (JSON), episodios (JSON) e fonte
    //       ("jikan" = com fillers), que o browser foi buscar
    public function adicionar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $malId     = (int) ($_POST['mal_id'] ?? 0);
            $info      = DadosAnime::info($this->lerJson('info'), $malId);
            $episodios = DadosAnime::episodios($this->lerJson('episodios'));

            // "propor" = fica na tua biblioteca e a pessoa escolhida recebe um convite
            $convidarA = ($_POST['modo'] ?? 'ver') === 'propor' ? $this->ligado((int) ($_POST['com'] ?? 0), $user) : null;
            $serie = Serie::adicionarDoMal($user, $info, $episodios, $convidarA, ($_POST['fonte'] ?? '') === 'jikan');

            // Séries só tuas não incomodam ninguém; o convite sim
            if ($convidarA !== null) {
                Notificador::serie($user, $serie, 'convite', $convidarA);
            }

            $this->responder(true,
                $convidarA ? $serie->nomeCurto() . ' entrou na tua biblioteca e o convite foi enviado a ' . $convidarA->nome . '.' : $serie->nomeCurto() . ' entrou na tua biblioteca.',
                url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar a série.');
        }
    }

    // POST (só JSON): serie + info [+ episodios + fonte] → capa que faltava, fillers que ainda não
    // tinham vindo ou episódios novos de uma série em emissão.
    // Corre em segundo plano no browser; responde quantos episódios novos chegaram.
    public function atualizar(): void
    {
        $this->exigirPost();
        $this->exigirLogin();

        $serie = Serie::porSlug($_POST['serie'] ?? null);
        if ($serie === null || $serie->mal_id === null) {
            $this->json(['ok' => false, 'mensagem' => 'Essa série já não existe.'], 404);
        }

        try {
            $info = DadosAnime::info($this->lerJson('info'), (int) $serie->mal_id);
            $episodios = isset($_POST['episodios']) ? DadosAnime::episodios($this->lerJson('episodios')) : null;
            $novos = $serie->atualizarDoMal($info, $episodios, ($_POST['fonte'] ?? '') === 'jikan');
            $this->json(['ok' => true, 'novos' => $novos, 'capa' => $serie->capa]);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            $this->json(['ok' => false, 'mensagem' => 'Não foi possível guardar.'], 500);
        }
    }

    // Campo do formulário com JSON (vindo do browser) → array; vazio ou inválido → []
    private function lerJson(string $campo): array
    {
        $dados = json_decode((string) ($_POST[$campo] ?? ''), true);
        return is_array($dados) ? $dados : [];
    }

    // POST: serie (slug) + estado (a_ver | pausa | acabado) — na TUA biblioteca
    public function estado(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $estado = $_POST['estado'] ?? '';
            $serie->definirEstado($user, $estado);
            $this->responder(true, $serie->nomeCurto() . ': ' . mb_strtolower(Serie::estadoTexto($estado)) . '.',
                url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: serie + com (id do par ou de um amigo): convida-o a ver contigo uma série que já tens
    public function convidar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $para = $this->ligado((int) ($_POST['com'] ?? 0), $user);
            $serie->convidar($user, $para);
            Notificador::serie($user, $serie, 'convite', $para);
            $this->responder(true, 'Convite enviado a ' . $para->nome . ': ' . $serie->nomeCurto() . '.', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: serie + com (id de quem convidou, opcional: vazio = só para ti): entras numa série de alguém
    // ligado a ti (fila "está a ver", perfil de um amigo). Com "com" passam a ver a série juntos e ele é avisado.
    public function juntar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $com = (int) ($_POST['com'] ?? 0) > 0 ? $this->ligado((int) $_POST['com'], $user) : null;
            $serie->juntarSe($user, $com);
            if ($com !== null) {
                Notificador::serie($user, $serie, 'juntou', $com);
            }
            $this->responder(true,
                $com ? $serie->nomeCurto() . ' entrou na tua biblioteca e vês com ' . $com->nome . '.' : $serie->nomeCurto() . ' entrou na tua biblioteca.',
                url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: serie + de (id de quem convidou): aceitas o convite → a série entra na tua biblioteca e vêem juntos
    public function aceitar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $de = $this->ligado((int) ($_POST['de'] ?? 0), $user);
            $serie->aceitar($user, $de);
            Notificador::serie($user, $serie, 'aceite', $de);
            $this->responder(true, $serie->nomeCurto() . ': agora vês com ' . $de->nome . '.', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: serie + outro (id): recusar um convite recebido, ou cancelar um enviado
    public function recusar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $nome = $serie->nomeCurto();
            $serie->retirarConvite($user, $this->ligado((int) ($_POST['outro'] ?? 0), $user));
            $this->responder(true, 'Convite de ' . $nome . ' retirado.', url('home'));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: serie: deixas de ver esta série com o teu companheiro (cada um fica com a sua)
    public function separar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $serie->deixarDeVerJuntos($user);
            $this->responder(true, $serie->nomeCurto() . ': agora cada um vê a sua.', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: tira uma série da TUA biblioteca (só se ainda não marcaste episódios dela)
    public function remover(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $nome = $serie->nomeCurto();
            $serie->removerDe($user);
            $this->responder(true, $nome . ' saiu da tua biblioteca.', url('home'));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // Utilizador pelo id, só se for o teu par ou um amigo teu (senão, erro para o utilizador)
    private function ligado(int $id, User $tu): User
    {
        $outro = User::find($id);
        if ($outro === null || !$tu->ligadoA($outro)) {
            throw new InvalidArgumentException('Só podes partilhar séries com o teu par e com os teus amigos.');
        }
        return $outro;
    }

    // Série pelo slug do formulário; null guard → volta ao início
    private function serieDoPost(): Serie
    {
        $serie = Serie::porSlug($_POST['serie'] ?? null);
        if ($serie === null) {
            $this->responder(false, 'Essa série já não existe.');
        }
        return $serie;
    }

    // Resposta comum: JSON para o fetch, ou aviso + redirect (Post/Redirect/Get) sem JavaScript
    private function responder(bool $ok, string $mensagem, ?string $destino = null): never
    {
        if ($this->querJson()) {
            // Com sucesso o JavaScript muda de página a seguir: a mensagem aparece lá (flash)
            if ($ok) {
                $this->flash('sucesso', $mensagem);
            }
            $this->json(['ok' => $ok, 'mensagem' => $mensagem, 'url' => $destino], $ok ? 200 : 422);
        }
        $this->flash($ok ? 'sucesso' : 'erro', $mensagem);
        header('Location: ' . ($destino ?? url('home')));
        exit;
    }
}
