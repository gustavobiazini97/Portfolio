<?php
// Entrar, criar conta (as duas do casal, ou por link de convite de um amigo) e sair.

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

        // Com link de convite válido o registo abre, e a conta fica amiga de quem convidou
        $codigo  = $_GET['convite'] ?? null;
        $convite = Amizade::convite($codigo);
        if ($codigo !== null && $convite === null) {
            $this->flash('erro', 'Esse link de convite já não é válido. Pede um novo.');
            $this->redirect('auth', 'login');
        }
        if ($convite === null && !User::registoAberto()) {
            $this->flash('info', 'O registo é só por convite. Pede um link a um amigo que já use a app.');
            $this->redirect('auth', 'login');
        }

        // Sem convite e com uma conta já criada, esta é a segunda do casal: mostramos com quem vai ficar ligada
        $primeiro = $convite === null ? User::orderBy('id')->first() : null;

        $this->render('auth/registo', [
            'titulo'   => 'Criar conta',
            'primeiro' => $primeiro,
            'convite'  => $convite,
        ]);
    }

    // POST: cria a conta e entra logo
    public function registar(): void
    {
        $this->exigirPost();

        try {
            $convite = isset($_POST['convite']) && $_POST['convite'] !== '' ? Amizade::convite($_POST['convite']) : null;
            if (isset($_POST['convite']) && $_POST['convite'] !== '' && $convite === null) {
                throw new InvalidArgumentException('Esse link de convite já não é válido. Pede um novo.');
            }
            $user = User::registar($_POST, $convite);

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
            $this->redirect('auth', 'registo', !empty($_POST['convite']) ? ['convite' => $_POST['convite']] : []);
        } catch (PDOException $e) {
            $this->flash('erro', 'Não foi possível criar a conta. Tenta outra vez.');
            $this->redirect('auth', 'registo', !empty($_POST['convite']) ? ['convite' => $_POST['convite']] : []);
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
