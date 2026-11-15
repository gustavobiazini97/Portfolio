<?php
// Login (com bloqueio progressivo e 2FA), logout e recuperação de palavra-passe.
declare(strict_types=1);

final class AuthController extends Controller
{
    // Hash "de enchimento": quando o email não existe, verifica-se contra isto para a resposta
    // demorar o mesmo tempo (não dá para descobrir que emails existem pelo tempo)
    private const HASH_FALSO = '$2y$10$JxRlTsygdPvk61eVfN8CdeLIevBxoV8j.f2QgWA5Jwuj7Y63oMxt6';

    // GET: formulário de login
    public function login(): void
    {
        if (self::eu()) {
            redirecionar('painel');
        }
        $this->view('auth/login', [], 'Entrar');
    }

    // POST: verifica email e palavra-passe
    public function entrar(): void
    {
        $email = mb_strtolower(post('email'));
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['_antigo'] = ['email' => $email];

        // Honeypot: campo invisível que só os bots preenchem
        if (post('site') !== '') {
            Auditoria::registar('bot_login', $email);
            $this->falhaGenerica();
        }

        // Limite por IP: muitas tentativas seguidas (qualquer email) → espera
        if (Limite::excedido('login-ip:' . ip_cliente(), 30, 15)) {
            flash('erro', 'Demasiadas tentativas a partir desta rede. Tenta daqui a 15 minutos.');
            redirecionar('auth', 'login');
        }

        // Bloqueio progressivo por conta: 5 falhas → 1 min, 6 → 2 min, 7 → 4 min… até 60 min
        $falhas = Limite::contar('login:' . $email, 60);
        if ($falhas >= 5) {
            $espera = min(60, 2 ** ($falhas - 5));
            $ultimo = Limite::ultimo('login:' . $email);
            $fim = strtotime((string) $ultimo) + $espera * 60;
            if (time() < $fim) {
                flash('erro', 'Demasiadas tentativas. Tenta outra vez daqui a ' . plural((int) ceil(($fim - time()) / 60), 'minuto', 'minutos') . '.');
                redirecionar('auth', 'login');
            }
        }

        $u = $email !== '' ? Utilizador::porEmail($email) : null;
        $ok = password_verify($password, $u['password_hash'] ?? self::HASH_FALSO) && $u && (int) $u['ativo'] === 1;

        if (!$ok) {
            Limite::registar('login:' . $email);
            Limite::registar('login-ip:' . ip_cliente());
            Auditoria::registar('login_falhado', mb_substr($email, 0, 190), $u['id'] ?? null);
            $this->falhaGenerica();
        }

        // Certo: limpa as falhas e aproveita para atualizar o hash se o algoritmo do PHP mudou
        Limite::limpar('login:' . $email);
        Utilizador::atualizarHashSePreciso($u, $password);
        unset($_SESSION['_antigo']);

        // Com 2FA: falta o código. Sem 2FA: entra.
        if ((int) $u['totp_ativo'] === 1) {
            Sessao::pendente2fa((int) $u['id']);
            redirecionar('auth', 'codigo');
        }
        $this->concluirLogin($u);
    }

    // GET: pede o código de 6 dígitos da app autenticadora
    public function codigo(): void
    {
        if (!$this->pendente()) {
            redirecionar('auth', 'login');
        }
        $this->view('auth/codigo', [], 'Verificação em dois passos');
    }

    // POST: valida o código TOTP
    public function verificarCodigo(): void
    {
        $uid = $this->pendente();
        if (!$uid) {
            redirecionar('auth', 'login');
        }
        // 5 códigos errados → volta ao início do login
        if (Limite::excedido('login2fa:' . $uid, 5, 15)) {
            Auditoria::registar('2fa_bloqueado', '', $uid);
            Sessao::terminar();
            flash('erro', 'Demasiados códigos errados. Entra outra vez daqui a uns minutos.');
            redirecionar('auth', 'login');
        }
        $u = Utilizador::porId($uid);
        $passo = $u && $u['totp_segredo'] ? Totp::verificar($u['totp_segredo'], post('codigo'), $u['totp_ultimo'] !== null ? (int) $u['totp_ultimo'] : null) : null;
        if ($passo === null) {
            Limite::registar('login2fa:' . $uid);
            Auditoria::registar('2fa_falhado', '', $uid);
            flash('erro', 'Código errado ou expirado. Usa o código que aparece agora na app.');
            redirecionar('auth', 'codigo');
        }
        Utilizador::registarPasso2fa($uid, $passo);
        Limite::limpar('login2fa:' . $uid);
        $this->concluirLogin($u);
    }

    // POST: termina a sessão
    public function sair(): void
    {
        if (self::eu()) {
            Auditoria::registar('logout');
        }
        Sessao::terminar();
        flash('ok', 'Sessão terminada.');
        redirecionar('auth', 'login');
    }

    // GET: "Esqueci-me da palavra-passe"
    public function recuperar(): void
    {
        $this->view('auth/recuperar', [], 'Recuperar palavra-passe');
    }

    // POST: envia o link de recuperação (a resposta é sempre igual, exista ou não a conta)
    public function pedirLink(): void
    {
        $email = mb_strtolower(post('email'));
        $resposta = 'Se esse email tiver conta, vais receber um link para definires uma palavra-passe nova (válido 30 minutos).';

        if (post('site') !== '' || Limite::excedido('reset-ip:' . ip_cliente(), 5, 15)) {
            flash('ok', $resposta);
            redirecionar('auth', 'login');
        }
        Limite::registar('reset-ip:' . ip_cliente());

        $u = Utilizador::porEmail($email);
        if ($u && (int) $u['ativo'] === 1 && !Limite::excedido('reset:' . $email, 3, 60)) {
            Limite::registar('reset:' . $email);
            $token = Utilizador::criarTokenReset((int) $u['id']);
            Mailer::enfileirar(
                $u['email'],
                'Recuperar a palavra-passe — ' . config('app_nome', 'Helpdesk TI'),
                "Olá {$u['nome']},\n\nPara definires uma palavra-passe nova, abre este link (válido 30 minutos, só funciona uma vez):\n"
                . url_absoluto('auth', 'redefinir', ['token' => $token])
                . "\n\nSe não foste tu a pedir, ignora este email: a palavra-passe atual continua a funcionar."
            );
            Auditoria::registar('reset_pedido', '', (int) $u['id']);
        }
        flash('ok', $resposta);
        redirecionar('auth', 'login');
    }

    // GET: formulário da palavra-passe nova (a partir do link do email)
    public function redefinir(): void
    {
        $token = get('token');
        if (!Utilizador::tokenResetValido($token)) {
            flash('erro', 'Este link já não é válido. Pede um novo.');
            redirecionar('auth', 'recuperar');
        }
        $this->view('auth/redefinir', ['token' => $token], 'Nova palavra-passe');
    }

    // POST: grava a palavra-passe nova e gasta o token
    public function guardarRedefinicao(): void
    {
        $token = post('token');
        $linha = Utilizador::tokenResetValido($token);
        if (!$linha) {
            flash('erro', 'Este link já não é válido. Pede um novo.');
            redirecionar('auth', 'recuperar');
        }
        $erro = erro_password((string) ($_POST['password'] ?? ''), (string) ($_POST['confirmacao'] ?? ''));
        if ($erro) {
            flash('erro', $erro);
            redirecionar('auth', 'redefinir', ['token' => $token]);
        }
        Utilizador::definirPassword((int) $linha['utilizador_id'], (string) $_POST['password']);
        Utilizador::gastarTokenReset((int) $linha['id']);
        $u = Utilizador::porId((int) $linha['utilizador_id']);
        Limite::limpar('login:' . ($u['email'] ?? ''));
        Auditoria::registar('password_redefinida', '', (int) $linha['utilizador_id']);
        flash('ok', 'Palavra-passe alterada. Já podes entrar.');
        redirecionar('auth', 'login');
    }

    // ─── Internos ──────────────────────────────────────────────────────────

    // Mensagem igual para email inexistente, palavra-passe errada ou conta desativada
    private function falhaGenerica(): never
    {
        flash('erro', 'Email ou palavra-passe incorretos.');
        redirecionar('auth', 'login');
    }

    // Uid à espera do código 2FA (o passo intermédio expira ao fim de 5 minutos)
    private function pendente(): ?int
    {
        $uid = (int) ($_SESSION['2fa_uid'] ?? 0);
        if ($uid && time() - (int) ($_SESSION['2fa_em'] ?? 0) <= 300) {
            return $uid;
        }
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_em']);
        return null;
    }

    // Sessão nova e entra no painel
    private function concluirLogin(array $u): never
    {
        Sessao::entrar((int) $u['id']);
        Utilizador::registarLogin((int) $u['id']);
        Auditoria::registar('login', '', (int) $u['id']);
        redirecionar('painel');
    }
}
