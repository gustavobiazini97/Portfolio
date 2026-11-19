<?php
// Backoffice (só contas admin): números da app e gestão de contas (ver, criar, editar, repor palavra-passe, apagar).
// Quem é admin define-se no database/migrate.php (a conta mais antiga); aqui não se promove ninguém.
// Todas as páginas pedem antes o código de 2 fatores (exigirAdmin → codigo/confirmarCodigo).

class AdminController extends Controller
{
    // GET: pede o código de 6 dígitos; na primeira vez mostra o segredo para pôr na app de autenticação
    public function codigo(): void
    {
        $user = $this->exigirAdminSemCodigo();
        if (DoisFatores::sessaoValida($user)) {
            $this->redirect('admin');
        }

        // Ainda sem 2FA: segredo novo guardado só na sessão até o primeiro código o confirmar
        $configurar = $user->totp_segredo === null;
        if ($configurar) {
            $_SESSION['_2fa_novo'] ??= DoisFatores::novoSegredo();
        }

        $this->render('admin/codigo', [
            'titulo'     => 'Código de acesso',
            'configurar' => $configurar,
            'segredo'    => $configurar ? $_SESSION['_2fa_novo'] : null,
            'link'       => $configurar ? DoisFatores::link($_SESSION['_2fa_novo'], $user->username) : null,
        ]);
    }

    // POST: verifica o código (e, na primeira vez, grava o segredo)
    public function confirmarCodigo(): void
    {
        $this->exigirPost();
        $user = $this->exigirAdminSemCodigo();

        if (DoisFatores::bloqueado($user)) {
            $this->flash('erro', 'Demasiados códigos errados. Espera 15 minutos.');
            $this->redirect('admin', 'codigo');
        }

        $codigo = (string) ($_POST['codigo'] ?? '');
        $segredo = $user->totp_segredo ?? ($_SESSION['_2fa_novo'] ?? null);
        $passo = $segredo === null ? null
            : DoisFatores::verificar($segredo, $codigo, $user->totp_passo !== null ? (int) $user->totp_passo : null);

        if ($passo === null) {
            DoisFatores::falhou($user);
            $this->flash('erro', 'Código errado ou expirado. Usa o que a app mostra agora.');
            $this->redirect('admin', 'codigo');
        }

        try {
            $user->totp_segredo = $segredo;   // na primeira vez fica gravado aqui
            $user->totp_passo = $passo;
            $user->save();
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível guardar. Tenta outra vez.');
            $this->redirect('admin', 'codigo');
        }
        unset($_SESSION['_2fa_novo']);
        Limite::limpar('2fa:' . $user->id);
        DoisFatores::marcarSessao($user);
        $this->redirect('admin');
    }

    // GET: números + lista de contas (?q= filtra por nome ou utilizador)
    public function index(): void
    {
        $user = $this->exigirAdmin();
        $q = (string) ($_GET['q'] ?? '');

        $this->render('admin/index', [
            'titulo' => 'Backoffice',
            'user'   => $user,
            'q'      => $q,
            'n'      => Admin::numeros(),
            'top'    => Admin::topSeries(),
            'contas' => Admin::contas($q),
            'fcm'    => Fcm::estado(),   // notificações da app: ligado? último envio certo e último erro
        ]);
    }

    // GET: formulário de conta nova
    public function novo(): void
    {
        $this->exigirAdmin();
        $this->render('admin/form', ['titulo' => 'Nova conta', 'conta' => null]);
    }

    // POST: cria a conta (sem par e sem séries, como um amigo convidado; não conta para o limite do registo)
    public function criar(): void
    {
        $this->exigirPost();
        $this->exigirAdmin();

        try {
            $dados = User::validarNovos($_POST);
            User::create([
                'nome'          => $dados['nome'],
                'username'      => $dados['username'],
                'password_hash' => password_hash($dados['password'], PASSWORD_DEFAULT),
                'novidades_vistas' => Novidade::ultima(),
            ]);
            $this->flash('sucesso', 'Conta criada.');
            $this->redirect('admin');
        } catch (InvalidArgumentException $e) {
            $this->guardarAntigo(['nome' => $_POST['nome'] ?? '', 'username' => $_POST['username'] ?? '']);
            $this->flash('erro', $e->getMessage());
            $this->redirect('admin', 'novo');
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível criar a conta.');
            $this->redirect('admin', 'novo');
        }
    }

    // GET ?id=: formulário de editar
    public function editar(): void
    {
        $this->exigirAdmin();
        $conta = User::find((int) ($_GET['id'] ?? 0));
        if ($conta === null) {   // null guard
            $this->flash('erro', 'Essa conta não existe.');
            $this->redirect('admin');
        }
        $this->render('admin/form', ['titulo' => 'Editar conta', 'conta' => $conta]);
    }

    // POST: guarda nome, utilizador e (se preenchida) uma palavra-passe nova
    public function guardar(): void
    {
        $this->exigirPost();
        $this->exigirAdmin();
        $conta = User::find((int) ($_POST['id'] ?? 0));
        if ($conta === null) {
            $this->flash('erro', 'Essa conta não existe.');
            $this->redirect('admin');
        }

        try {
            $conta->alterarNome($_POST['nome'] ?? '');
            $conta->alterarUsername($_POST['username'] ?? $conta->username);

            $nova = $_POST['password'] ?? '';
            if ($nova !== '') {
                if (strlen($nova) < 8) {
                    throw new InvalidArgumentException('A palavra-passe nova tem de ter pelo menos 8 caracteres.');
                }
                $conta->password_hash = password_hash($nova, PASSWORD_DEFAULT);
                $conta->save();
                // Palavra-passe reposta: todas as sessões dessa conta (site mantido e app) terminam
                Token::where('user_id', $conta->id)->delete();
                \Illuminate\Database\Capsule\Manager::table('sessoes_longas')->where('user_id', $conta->id)->delete();
            }
            $this->flash('sucesso', 'Conta atualizada.');
            $this->redirect('admin');
        } catch (InvalidArgumentException $e) {
            $this->flash('erro', $e->getMessage());
            $this->redirect('admin', 'editar', ['id' => $conta->id]);
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível guardar.');
            $this->redirect('admin', 'editar', ['id' => $conta->id]);
        }
    }

    // POST: apaga uma conta e tudo o que é dela (a tua própria apaga-se no Perfil; nunca se apaga um admin aqui)
    public function apagar(): void
    {
        $this->exigirPost();
        $admin = $this->exigirAdmin();
        $conta = User::find((int) ($_POST['id'] ?? 0));

        if ($conta === null) {
            $this->flash('erro', 'Essa conta não existe.');
        } elseif ($conta->id === $admin->id || $conta->ehAdmin()) {
            $this->flash('erro', 'Contas admin não se apagam aqui.');
        } else {
            try {
                $nome = $conta->nome;
                $conta->eliminar();
                $this->flash('sucesso', 'Conta de ' . $nome . ' apagada.');
            } catch (PDOException $e) {
                $this->flash('erro', 'Não foi possível apagar a conta.');
            }
        }
        $this->redirect('admin');
    }
}
