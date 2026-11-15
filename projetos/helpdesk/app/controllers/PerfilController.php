<?php
// Perfil: nome, notificações por email, palavra-passe e verificação em dois passos (2FA).
declare(strict_types=1);

final class PerfilController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $this->view('perfil/index', ['u' => $u], 'O meu perfil');
    }

    // POST: nome e preferência de email
    public function guardar(): void
    {
        $u = $this->exigirLogin();
        $nome = post('nome');
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
            flash('erro', 'O nome precisa de 2 a 100 caracteres.');
            redirecionar('perfil');
        }
        Utilizador::mudarNome((int) $u['id'], $nome);
        Utilizador::preferenciaEmail((int) $u['id'], post('notif_email') === '1');
        flash('ok', 'Perfil guardado.');
        redirecionar('perfil');
    }

    // POST: mudar a palavra-passe (pede a atual)
    public function password(): void
    {
        $u = $this->exigirLogin();
        if (!password_verify((string) ($_POST['atual'] ?? ''), $u['password_hash'])) {
            Auditoria::registar('password_atual_errada');
            flash('erro', 'A palavra-passe atual não está certa.');
            redirecionar('perfil');
        }
        $nova = (string) ($_POST['nova'] ?? '');
        $erro = erro_password($nova, (string) ($_POST['confirmacao'] ?? ''));
        if (!$erro && hash_equals($nova, (string) $_POST['atual'])) {
            $erro = 'A palavra-passe nova tem de ser diferente da atual.';
        }
        if ($erro) {
            flash('erro', $erro);
            redirecionar('perfil');
        }
        Utilizador::definirPassword((int) $u['id'], $nova);
        session_regenerate_id(true);   // privilégios/credenciais mudaram: ID de sessão novo
        Auditoria::registar('password_alterada');
        flash('ok', 'Palavra-passe alterada.');
        redirecionar('painel');
    }

    // GET: configurar o 2FA (o segredo fica na sessão até ser confirmado com um código)
    public function configurar2fa(): void
    {
        $u = $this->exigirLogin();
        if ((int) $u['totp_ativo'] === 1) {
            flash('aviso', 'A verificação em dois passos já está ativa.');
            redirecionar('perfil');
        }
        $_SESSION['2fa_novo'] ??= Totp::novoSegredo();
        $this->view('perfil/2fa', [
            'segredo' => Totp::legivel($_SESSION['2fa_novo']),
            'uri' => Totp::uri($_SESSION['2fa_novo'], $u['email']),
            'obrigatorio' => $u['papel'] === 'admin',
        ], 'Verificação em dois passos');
    }

    // POST: confirma o primeiro código e ativa
    public function ativar2fa(): void
    {
        $u = $this->exigirLogin();
        $segredo = (string) ($_SESSION['2fa_novo'] ?? '');
        if ($segredo === '') {
            redirecionar('perfil', 'configurar2fa');
        }
        if (Limite::excedido('ativar2fa:' . $u['id'], 8, 15)) {
            flash('erro', 'Demasiadas tentativas. Espera uns minutos.');
            redirecionar('perfil', 'configurar2fa');
        }
        $passo = Totp::verificar($segredo, post('codigo'));
        if ($passo === null) {
            Limite::registar('ativar2fa:' . $u['id']);
            flash('erro', 'O código não bate certo. Confirma que a hora do telemóvel está automática e tenta o código atual.');
            redirecionar('perfil', 'configurar2fa');
        }
        Utilizador::guardarSegredo2fa((int) $u['id'], $segredo);
        Utilizador::ativar2fa((int) $u['id'], $passo);
        unset($_SESSION['2fa_novo']);
        session_regenerate_id(true);
        Auditoria::registar('2fa_ativado');
        flash('ok', 'Verificação em dois passos ativada. A partir de agora o login pede o código da app.');
        redirecionar('painel');
    }

    // POST: desativar o 2FA (não disponível para admins; pede a palavra-passe)
    public function desativar2fa(): void
    {
        $u = $this->exigirLogin();
        if ($u['papel'] === 'admin') {
            flash('erro', 'Nas contas de administrador o 2FA é obrigatório.');
            redirecionar('perfil');
        }
        if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
            flash('erro', 'Palavra-passe errada.');
            redirecionar('perfil');
        }
        Utilizador::desativar2fa((int) $u['id']);
        Auditoria::registar('2fa_desativado');
        flash('ok', 'Verificação em dois passos desativada.');
        redirecionar('perfil');
    }
}
