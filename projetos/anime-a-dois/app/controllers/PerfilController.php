<?php
// Zona de perfil: foto, nome, palavra-passe e terminar sessão.

class PerfilController extends Controller
{
    // GET: página do perfil
    public function index(): void
    {
        $user = $this->exigirLogin();

        $this->render('perfil/index', [
            'titulo'      => 'Perfil',
            'user'        => $user,
            'pref'        => Preferencia::de($user),
            'vapidPublica' => Notificador::vapid()['publicKey'],   // o browser precisa dela para subscrever
            'telemoveis'  => Subscricao::where('user_id', $user->id)->count(),
        ]);
    }

    // GET ?id=<user>: devolve a imagem da foto (só para quem tem sessão iniciada)
    public function foto(): void
    {
        $this->exigirLogin();
        $foto = Foto::find((int) ($_GET['id'] ?? 0));

        // Null guard: sem foto → 404
        if ($foto === null) {
            http_response_code(404);
            exit;
        }

        // O URL tem ?v=<data>, por isso pode ficar em cache "para sempre" (só neste browser)
        header('Content-Type: ' . $foto->tipo);
        header('Content-Length: ' . strlen($foto->imagem));
        header('Cache-Control: private, max-age=31536000, immutable');
        echo $foto->imagem;
        exit;
    }

    // POST (multipart): guarda a foto enviada
    public function enviarFoto(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $ficheiro = $_FILES['foto'] ?? null;

            // Erros de envio do PHP (ficheiro grande demais, nada escolhido, ...)
            if ($ficheiro === null || $ficheiro['error'] === UPLOAD_ERR_NO_FILE) {
                throw new InvalidArgumentException('Escolhe uma foto primeiro.');
            }
            if (in_array($ficheiro['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new InvalidArgumentException('Essa foto é grande demais.');
            }
            if ($ficheiro['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($ficheiro['tmp_name'])) {
                throw new InvalidArgumentException('A foto não chegou bem. Tenta outra vez.');
            }

            $user->definirFoto($ficheiro['tmp_name']);
            $this->responder(true, 'Foto atualizada.');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar a foto.');
        }
    }

    // POST: tira a foto
    public function removerFoto(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->removerFoto();
            $this->responder(true, 'Foto removida.');
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível remover a foto.');
        }
    }

    // POST: muda o nome e o nome de utilizador
    public function guardarNome(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->alterarNome($_POST['nome'] ?? '');
            $user->alterarUsername($_POST['username'] ?? $user->username);
            $this->responder(true, 'Dados atualizados.');
        } catch (InvalidArgumentException $e) {
            $this->guardarAntigo(['nome' => $_POST['nome'] ?? '', 'username' => $_POST['username'] ?? '']);
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar o nome.');
        }
    }

    // POST: muda a palavra-passe
    public function guardarPassword(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->alterarPassword($_POST['atual'] ?? '', $_POST['nova'] ?? '', $_POST['confirmar'] ?? '');
            session_regenerate_id(true);   // credenciais novas, sessão nova
            $this->responder(true, 'Palavra-passe alterada.');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível alterar a palavra-passe.');
        }
    }

    // ---------- Notificações ----------

    // POST: o que quero receber e por onde
    public function guardarNotificacoes(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            Preferencia::guardar($user, $_POST);
            $this->responder(true, 'Notificações guardadas.');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar.');
        }
    }

    // POST (JSON do browser): ativa as notificações neste telemóvel
    public function subscrever(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            Subscricao::registar($user, json_decode($_POST['subscricao'] ?? '', true) ?: []);
            $this->json(['ok' => true, 'mensagem' => 'Notificações ativas neste telemóvel.']);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 422);
        }
    }

    // POST: desativa as notificações neste telemóvel
    public function desubscrever(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        Subscricao::remover($user, $_POST['endpoint'] ?? '');
        $this->json(['ok' => true, 'mensagem' => 'Notificações desligadas neste telemóvel.']);
    }

    // POST: envia uma notificação de teste para ti (telemóvel e/ou email)
    public function testarNotificacao(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();
        $pref = Preferencia::de($user);

        $msg = ['titulo' => 'Anime a Dois', 'corpo' => 'As notificações estão a funcionar 🎉',
                'url' => url_absoluto() . url('home'), 'tag' => 'teste'];

        $partes = [];
        try {
            $n = Notificador::push($user, $msg);
            $partes[] = $n > 0 ? 'enviada para ' . plural($n, 'telemóvel', 'telemóveis') : 'nenhum telemóvel ativo';
        } catch (Throwable $e) {
            $partes[] = 'o push falhou';
        }
        if ($pref->email) {
            $partes[] = Notificador::email($pref->email, $msg) ? 'email enviado' : 'o email falhou';
        }
        $this->json(['ok' => true, 'mensagem' => 'Teste: ' . implode(', ', $partes) . '.']);
    }

    // Resposta comum: JSON para o fetch, ou aviso + volta ao perfil (Post/Redirect/Get)
    private function responder(bool $ok, string $mensagem): never
    {
        if ($this->querJson()) {
            $this->json(['ok' => $ok, 'mensagem' => $mensagem], $ok ? 200 : 422);
        }
        $this->flash($ok ? 'sucesso' : 'erro', $mensagem);
        $this->redirect('perfil');
    }
}
