<?php
// Base de todos os controllers: views, JSON, utilizador com sessão e controlo de acesso.
declare(strict_types=1);

abstract class Controller
{
    // Utilizador com sessão (carregado da BD uma vez por pedido)
    private static ?array $eu = null;
    private static bool $carregado = false;

    // Devolve o utilizador com sessão ou null. Contas desativadas perdem a sessão na hora.
    public static function eu(): ?array
    {
        if (!self::$carregado) {
            self::$carregado = true;
            $uid = (int) ($_SESSION['uid'] ?? 0);
            if ($uid > 0) {
                $u = Utilizador::porId($uid);
                if ($u && (int) $u['ativo'] === 1) {
                    self::$eu = $u;
                } else {
                    Sessao::terminar();
                }
            }
        }
        return self::$eu;
    }

    // O utilizador com sessão é técnico ou admin?
    public static function eTecnico(): bool
    {
        $u = self::eu();
        return $u !== null && in_array($u['papel'], ['tecnico', 'admin'], true);
    }

    public static function eAdmin(): bool
    {
        return (self::eu()['papel'] ?? '') === 'admin';
    }

    // Obriga a ter sessão. Também aplica as "portas" obrigatórias:
    //  - palavra-passe temporária → só pode ir ao perfil mudar a palavra-passe
    //  - admin sem 2FA → só pode ir configurar o 2FA
    protected function exigirLogin(): array
    {
        $u = self::eu();
        if ($u === null) {
            flash('aviso', 'Entra primeiro com a tua conta.');
            redirecionar('auth', 'login');
        }
        $rota = ($_GET['c'] ?? '') . '/' . ($_GET['a'] ?? '');
        if ((int) $u['mudar_password'] === 1 && !in_array($rota, ['perfil/index', 'perfil/password'], true)) {
            flash('aviso', 'Define uma palavra-passe tua antes de continuar.');
            redirecionar('perfil', 'index');
        }
        if ($u['papel'] === 'admin' && (int) $u['totp_ativo'] !== 1 && (int) $u['mudar_password'] !== 1
            && !in_array($rota, ['perfil/configurar2fa', 'perfil/ativar2fa'], true)) {
            flash('aviso', 'As contas de administrador precisam de verificação em dois passos (2FA).');
            redirecionar('perfil', 'configurar2fa');
        }
        return $u;
    }

    // Obriga a ter um dos papéis indicados; caso contrário, 403 (e fica registado)
    protected function exigirPapel(string ...$papeis): array
    {
        $u = $this->exigirLogin();
        if (!in_array($u['papel'], $papeis, true)) {
            $this->proibido('papel ' . $u['papel'] . ' tentou ' . ($_GET['c'] ?? '') . '/' . ($_GET['a'] ?? ''));
        }
        return $u;
    }

    // Resposta 403 com registo na auditoria
    protected function proibido(string $detalhe = ''): never
    {
        Auditoria::registar('acesso_negado', $detalhe);
        $this->erro(403);
    }

    // Página de erro (403, 404…) e fim
    protected function erro(int $codigo): never
    {
        http_response_code($codigo);
        require RAIZ . '/app/views/erros/erro.php';
        exit;
    }

    // Mostra uma view dentro do layout. $dados viram variáveis na view.
    protected function view(string $nome, array $dados = [], string $titulo = ''): never
    {
        // O nome vem sempre do código (nunca do utilizador); mesmo assim, só letras, / e _
        if (!preg_match('#^[a-z0-9_/]+$#', $nome)) {
            throw new InvalidArgumentException('View inválida');
        }
        extract($dados, EXTR_SKIP);
        $eu = self::eu();
        $tituloPagina = $titulo;
        // Tudo para um buffer: se rebentar a meio, a página de erro não sai colada a meia página
        ob_start();
        require RAIZ . '/app/views/layout/topo.php';
        require RAIZ . "/app/views/$nome.php";
        require RAIZ . '/app/views/layout/fim.php';
        unset($_SESSION['_antigo']);
        ob_end_flush();
        exit;
    }

    // Resposta JSON (pedidos fetch do JavaScript)
    protected function json(array $dados, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Guarda os campos de um formulário falhado para o voltar a preencher
    protected function guardarAntigo(array $campos): void
    {
        $_SESSION['_antigo'] = array_intersect_key($_POST, array_flip($campos));
    }
}
