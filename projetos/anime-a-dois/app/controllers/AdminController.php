<?php
// Backoffice (só contas admin): números da app e gestão de contas (ver, criar, editar, repor palavra-passe, apagar).
// Quem é admin define-se no database/migrate.php (a conta mais antiga); aqui não se promove ninguém.

class AdminController extends Controller
{
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
