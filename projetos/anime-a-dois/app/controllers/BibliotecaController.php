<?php
// Biblioteca: adicionar séries do MyAnimeList (ou propor ao par), atualizá-las, mudar o estado,
// aceitar propostas e tirar séries. A pesquisa e os pedidos ao Jikan são feitos no browser
// (js/app.js); aqui chegam os dados já recebidos. A lógica vive no Model Serie.

class BibliotecaController extends Controller
{
    // POST: mal_id + modo ("ver" = já na biblioteca, "propor" = Quero ver contigo)
    //       + info (JSON) e episodios (JSON), que o browser foi buscar ao Jikan
    public function adicionar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $malId     = (int) ($_POST['mal_id'] ?? 0);
            $info      = Jikan::info($this->lerJson('info'), $malId);
            $episodios = Jikan::episodios($this->lerJson('episodios'));

            // Sem par ainda não há a quem propor: entra logo na biblioteca
            $proposta = ($_POST['modo'] ?? 'ver') === 'propor' && $user->parceiro() !== null;
            $serie = Serie::adicionarDoMal($user, $info, $episodios, $proposta);

            Notificador::serie($user, $serie, $proposta ? 'proposta' : 'adicionada');

            $this->responder(true,
                $proposta ? 'Proposta enviada: ' . $serie->nomeCurto() . '.' : $serie->nomeCurto() . ' entrou na biblioteca.',
                $proposta ? url('home') . '#propostas' : url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar a série.');
        }
    }

    // POST (só JSON): serie + info [+ episodios] → capa que faltava ou episódios novos de uma série em emissão.
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
            $info = Jikan::info($this->lerJson('info'), (int) $serie->mal_id);
            $episodios = isset($_POST['episodios']) ? Jikan::episodios($this->lerJson('episodios')) : null;
            $novos = $serie->atualizarDoMal($info, $episodios);
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

    // POST: serie (slug) + estado (a_ver | pausa | acabado)
    public function estado(): void
    {
        $this->exigirPost();
        $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $serie->definirEstado($_POST['estado'] ?? '');
            $this->responder(true, $serie->nomeCurto() . ': ' . mb_strtolower($serie->estadoTexto()) . '.',
                url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: o par aceita uma proposta → passa para "a ver"
    public function aceitar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $serie->aceitar($user);
            Notificador::serie($user, $serie, 'aceite');
            $this->responder(true, $serie->nomeCurto() . ' passou para "a ver".', url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        }
    }

    // POST: recusar/cancelar uma proposta, ou tirar da biblioteca uma série ainda por começar
    public function remover(): void
    {
        $this->exigirPost();
        $this->exigirLogin();
        $serie = $this->serieDoPost();

        try {
            $eraProposta = $serie->estado === Serie::PROPOSTA;
            $nome = $serie->nomeCurto();
            $serie->remover();
            $this->responder(true, $eraProposta ? 'Proposta de ' . $nome . ' retirada.' : $nome . ' saiu da biblioteca.', url('home'));
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
