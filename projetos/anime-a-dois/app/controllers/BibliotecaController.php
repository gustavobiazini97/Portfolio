<?php
// Biblioteca: pesquisar séries no MyAnimeList, adicionar (ou propor ao par), mudar o estado,
// aceitar propostas e tirar séries. A lógica vive no Model Serie; aqui só há pedidos e respostas.

class BibliotecaController extends Controller
{
    // GET ?c=biblioteca&a=pesquisar&q=<texto> → JSON com os resultados (folha "Adicionar série")
    public function pesquisar(): void
    {
        $this->exigirLogin();

        try {
            $resultados = Jikan::pesquisar((string) ($_GET['q'] ?? ''));
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 502);
        }

        // Marca as que já cá estão, para o botão dizer "já está na biblioteca"
        $ids = array_column($resultados, 'mal_id');
        $jaCa = $ids === [] ? [] : Serie::whereIn('mal_id', $ids)->pluck('estado', 'mal_id')->all();
        foreach ($resultados as &$r) {
            $estado = $jaCa[$r['mal_id']] ?? null;
            $r['ja'] = $estado === null ? null : ($estado === Serie::PROPOSTA ? 'proposta' : 'biblioteca');
        }
        unset($r);

        $this->json(['ok' => true, 'resultados' => $resultados]);
    }

    // POST: mal_id + modo ("ver" = já na biblioteca, "propor" = Quero ver contigo)
    public function adicionar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        set_time_limit(120);   // séries muito longas vêm em várias páginas do Jikan

        try {
            // Sem par ainda não há a quem propor: entra logo na biblioteca
            $proposta = ($_POST['modo'] ?? 'ver') === 'propor' && $user->parceiro() !== null;
            $serie = Serie::adicionarDoMal($user, (int) ($_POST['mal_id'] ?? 0), $proposta);

            Notificador::serie($user, $serie, $proposta ? 'proposta' : 'adicionada');

            $this->responder(true,
                $proposta ? 'Proposta enviada: ' . $serie->nomeCurto() . '.' : $serie->nomeCurto() . ' entrou na biblioteca.',
                $proposta ? url('home') . '#propostas' : url('home', 'index', ['serie' => $serie->slug]));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar a série.');
        }
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
