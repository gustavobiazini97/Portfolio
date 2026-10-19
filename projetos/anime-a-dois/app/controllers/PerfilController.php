<?php
// Zona de perfil: o perfil (o teu, do par ou de um amigo), as medalhas e as definições
// (foto, nome, palavra-passe, cores, notificações, terminar sessão).

class PerfilController extends Controller
{
    // GET: página do perfil
    public function index(): void
    {
        $user = $this->exigirLogin();

        $this->render('perfil/index', [
            'titulo'      => 'Perfil',
            'user'        => $user,
            'versao'      => Novidade::ultima(),   // "nível de atualização" mostrado no botão de procurar atualizações
            'pref'        => Preferencia::de($user),
            'vapidPublica' => Notificador::vapid()['publicKey'],   // o browser precisa dela para subscrever
            'telemoveis'  => Subscricao::where('user_id', $user->id)->count(),
        ]);
    }

    // GET ?id=<user>: perfil do teu par, de um amigo ou o teu (como na app): foto, último episódio,
    // números, medalhas em destaque e a biblioteca com o último episódio e o dia de cada um.
    // Se ele escolheu "só o que vemos juntos", aparecem só as séries que vê contigo e as medalhas ficam privadas.
    public function pessoa(): void
    {
        $user = $this->exigirLogin();
        $par  = $user->parceiro();
        $id   = (int) ($_GET['id'] ?? 0);
        $proprio = $id === $user->id;

        // Null guard: só se vê o teu perfil, o do par e o dos amigos
        $pessoa = $proprio ? $user : User::find($id);
        if ($pessoa === null || (!$proprio && $par?->id !== $id && !Amizade::sao($user, $pessoa))) {
            http_response_code(404);
            exit('Página não encontrada.');
        }
        $ehPar    = !$proprio && $par?->id === $id;
        $soJuntos = !$pessoa->veTudo($user);   // ele só mostra o que vê contigo

        // A biblioteca dele (cada item diz com quem a vê); com "só juntos" ficam só as que vê contigo
        $biblioteca = Serie::biblioteca($pessoa);
        if ($soJuntos) {
            $biblioteca = array_values(array_filter($biblioteca, fn ($i) => in_array($user->id, array_map(fn ($c) => $c['user']->id, $i['companheiros']), true)));
        }

        // Para cada série: o último episódio dele e o dia; e, se também a tens, a tua % e o teu último
        foreach ($biblioteca as &$item) {
            $s = $item['serie'];
            $item['naTua']     = $s->naBibliotecaDe($user);
            $item['vesCom']    = !$proprio && $s->verCom($user, $pessoa);
            $item['ultimo']    = $s->ultimoDe($pessoa);
            $item['teuPct']    = $item['naTua'] && !$proprio ? $s->progressoDe($user)['pct'] : null;
            $item['teuUltimo'] = $item['naTua'] && !$proprio ? $s->ultimoDe($user) : null;
        }
        unset($item);

        $this->render('perfil/pessoa', [
            'titulo'     => $proprio ? 'Perfil' : $pessoa->nome,
            'user'       => $user,
            'pessoa'     => $pessoa,
            'proprio'    => $proprio,
            'ehPar'      => $ehPar,
            'ultimo'     => $proprio ? $user->ultimoVisto() : $pessoa->ultimoVistoPara($user),
            'biblioteca' => $biblioteca,
            'soJuntos'   => $soJuntos,
            'numeros'    => Perfil::numeros($pessoa, $user, $biblioteca, $soJuntos, $proprio),
            'medalhas'   => $soJuntos ? null : Perfil::medalhas($pessoa),
        ]);
    }

    // GET [?id=<user>]: todas as medalhas (as tuas, ou as do par/amigo se ele mostra tudo), por grupo
    public function medalhas(): void
    {
        $user = $this->exigirLogin();
        $id   = (int) ($_GET['id'] ?? $user->id);
        $proprio = $id === $user->id;
        $pessoa  = $proprio ? $user : User::find($id);

        // Null guard: tu, o par ou um amigo — e só se ele não tiver "só o que vemos juntos"
        if ($pessoa === null || (!$proprio && $user->parceiro()?->id !== $id && !Amizade::sao($user, $pessoa)) || !$pessoa->veTudo($user)) {
            http_response_code(404);
            exit('Página não encontrada.');
        }

        // Por grupo, pela ordem em que aparecem
        $medalhas = Perfil::medalhas($pessoa);
        $grupos = [];
        foreach ($medalhas['lista'] as $m) {
            $grupos[$m['grupo']][] = $m;
        }

        $this->render('perfil/medalhas', [
            'titulo'   => $proprio ? 'As tuas medalhas' : 'Medalhas de ' . $pessoa->nome,
            'user'     => $user,
            'pessoa'   => $pessoa,
            'proprio'  => $proprio,
            'medalhas' => $medalhas,
            'grupos'   => $grupos,
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
            SessaoLonga::terminarTodas($user);   // os outros telemóveis têm de entrar outra vez...
            SessaoLonga::criar($user);           // ...e este mantém-se
            $this->responder(true, 'Palavra-passe alterada.');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível alterar a palavra-passe.');
        }
    }

    // POST: privacidade — os amigos só veem as séries que vês com eles (o par vê sempre tudo)
    public function guardarPrivacidade(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->alterarSoJuntos(!empty($_POST['so_juntos']));
            $this->responder(true, 'Privacidade guardada.');
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar.');
        }
    }

    // POST: escolhe as cores da app
    public function guardarPaleta(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->alterarPaleta((int) ($_POST['paleta'] ?? 0));
            $this->responder(true, 'Cores guardadas.');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível guardar as cores.');
        }
    }

    // POST: apaga a conta e tudo o que lhe pertence (pede a palavra-passe) e termina a sessão
    public function apagarConta(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        try {
            $user->apagarConta($_POST['password'] ?? '');
        } catch (InvalidArgumentException $e) {
            $this->responder(false, $e->getMessage());
        } catch (PDOException $e) {
            $this->responder(false, 'Não foi possível apagar a conta. Tenta outra vez.');
        }

        // Conta apagada: sessão nova e vazia
        SessaoLonga::terminar();
        $_SESSION = [];
        session_regenerate_id(true);
        $this->flash('info', 'A tua conta foi apagada.');
        $this->redirect('auth', 'login');
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
