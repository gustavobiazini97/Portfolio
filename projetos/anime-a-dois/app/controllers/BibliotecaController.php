<?php
// Biblioteca: adicionar séries (ou propor ao par), atualizá-las, mudar o estado, aceitar propostas
// e tirar séries. A pesquisa e os pedidos às APIs de anime (AniList, Kitsu, Jikan) são feitos no
// browser (js/app.js); aqui chegam os dados já recebidos. A lógica vive no Model Serie.

class BibliotecaController extends Controller
{
    // POST: mal_id + modo ("ver" = só para ti, "propor" = para ti + convite ao par: Quero ver contigo)
    //       + info (JSON), episodios (JSON) e fonte ("jikan" = com fillers), que o browser foi buscar
    public function adicionar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $malId     = (int) ($_POST['mal_id'] ?? 0);
            $info      = DadosAnime::info($this->lerJson('info'), $malId);
            $episodios = DadosAnime::episodios($this->lerJson('episodios'));

            // "propor" = fica na tua biblioteca e o par recebe um convite; sem par, só na tua
            $convidar = ($_POST['modo'] ?? 'ver') === 'propor' && $user->parceiro() !== null;
            $serie = Serie::adicionarDoMal($user, $info, $episodios, $convidar, ($_POST['fonte'] ?? '') === 'jikan');

            // Séries só tuas não incomodam o par; o convite sim
            if ($convidar) {
                Notificador::serie($user, $serie, 'convite');
            }

            $this->responder(true,
                $convidar ? $serie->nomeCurto() . ' entrou na tua biblioteca e o convite foi enviado.' : $serie->nomeCurto() . ' entrou na tua biblioteca.',
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

    // POST: convida o par a ver contigo uma série que já tens (passa a conjunta quando ele aceitar)
    public function convidar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $serie->convidar($user);
            Notificador::serie($user, $serie, 'convite');
            $this->responder(true, 'Convite enviado: ' . $serie->nomeCurto() . '.', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: aceitas o convite → a série entra na tua biblioteca e passa a ser dos dois
    public function aceitar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $serie->aceitar($user);
            Notificador::serie($user, $serie, 'aceite');
            $this->responder(true, $serie->nomeCurto() . ' agora é dos dois.', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: recusar um convite recebido, ou cancelar um enviado
    public function recusar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $nome = $serie->nomeCurto();
            $serie->retirarConvite($user);
            $this->responder(true, 'Convite de ' . $nome . ' retirado.', url('home'));
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
