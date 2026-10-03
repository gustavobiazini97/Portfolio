<?php
// Entrar, criar conta (só enquanto houver menos de 2) e sair.

class AuthController extends Controller
{
    // GET: formulário de login
    public function login(): void
    {
        // Quem já tem sessão vai direto para a dashboard
        if ($this->utilizador() !== null) {
            $this->redirect('home');
        }

        $this->render('auth/login', [
            'titulo'        => 'Entrar',
            'registoAberto' => User::registoAberto(),
        ]);
    }

    // POST: verifica as credenciais
    public function entrar(): void
    {
        $this->exigirPost();

        try {
            $user = User::autenticar($_POST['username'] ?? '', $_POST['password'] ?? '');

            if ($user === null) {
                $this->guardarAntigo(['username' => $_POST['username'] ?? '']);
                $this->flash('erro', 'Utilizador ou palavra-passe errados.');
                $this->redirect('auth', 'login');
            }

            // Novo ID de sessão ao entrar (evita fixação de sessão)
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->id;
            $this->redirect('home');
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível ligar à base de dados.');
            $this->redirect('auth', 'login');
        }
    }

    // GET: formulário de criar conta (fecha quando já existem as duas)
    public function registo(): void
    {
        if ($this->utilizador() !== null) {
            $this->redirect('home');
        }
        if (!User::registoAberto()) {
            $this->flash('info', 'Já existem as duas contas. Entra com a tua.');
            $this->redirect('auth', 'login');
        }

        // Se já há uma conta, esta é a segunda: mostramos com quem vai ficar ligada
        $primeiro = User::orderBy('id')->first();

        $this->render('auth/registo', [
            'titulo'   => 'Criar conta',
            'primeiro' => $primeiro,
        ]);
    }

    // POST: cria a conta e entra logo
    public function registar(): void
    {
        $this->exigirPost();

        try {
            $user = User::registar($_POST);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->id;
            $this->flash('sucesso', 'Conta criada. Bem-vindo(a), ' . $user->nome . '!');
            $this->redirect('home');
        } catch (InvalidArgumentException $e) {
            // Erro de validação: volta ao formulário com a mensagem e o que foi escrito
            $this->guardarAntigo([
                'nome'     => $_POST['nome'] ?? '',
                'username' => $_POST['username'] ?? '',
            ]);
            $this->flash('erro', $e->getMessage());
            $this->redirect('auth', 'registo');
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível criar a conta. Tenta outra vez.');
            $this->redirect('auth', 'registo');
        }
    }

    // POST: termina a sessão
    public function sair(): void
    {
        $this->exigirPost();

        $_SESSION = [];
        session_regenerate_id(true);
        $this->flash('info', 'Sessão terminada.');
        $this->redirect('auth', 'login');
    }
}
