<?php
// Sininho (🔔) do site: a mesma lista da app (Model Notificacoes), com os mesmos filtros.
// Como no site não há "telemóvel" onde guardar, o "já vi" e os filtros ficam em cookies deste browser
// (um par de cookies por conta). Abrir a lista marca tudo como visto.

class NotificacoesController extends Controller
{
    private const DURACAO = 365 * 86400;   // os cookies duram um ano

    // GET: a lista (já filtrada); marca tudo como visto
    public function index(): void
    {
        $user    = $this->exigirLogin();
        $filtros = self::filtros($user);
        $lista   = array_values(array_filter(Notificacoes::lista($user), fn ($n) => Notificacoes::deixa($n, $filtros)));
        $vistoAntes = self::vistoEm($user);   // o que for mais recente aparece destacado como "novo"

        // Daqui para a frente, tudo o que está na lista conta como visto
        if ($lista !== []) {
            self::cookie('sino_visto_' . $user->id, (string) strtotime($lista[0]['ts']));
        }

        $this->render('notificacoes/index', [
            'titulo'     => 'Notificações',
            'user'       => $user,
            'lista'      => $lista,
            'vistoAntes' => $vistoAntes,
            'filtros'    => $filtros,
        ]);
    }

    // POST: guarda os filtros (interruptores) e volta à lista
    public function filtrar(): void
    {
        $this->exigirPost();
        $user = $this->exigirLogin();

        // Só os nomes conhecidos (whitelist); o que não vier marcado fica desligado
        $valor = '';
        foreach (array_keys(Notificacoes::FILTROS) as $nome) {
            $valor .= !empty($_POST[$nome]) ? '1' : '0';
        }
        self::cookie('sino_filtros_' . $user->id, $valor);
        $this->redirect('notificacoes');
    }

    // ---------- Para o resto do site (a bolinha no Início) ----------

    // Quantas notificações por ver (respeitando os filtros)
    public static function porVer(User $user): int
    {
        $filtros = self::filtros($user);
        $visto   = self::vistoEm($user);
        return count(array_filter(Notificacoes::lista($user),
            fn ($n) => Notificacoes::deixa($n, $filtros) && ($visto === null || strtotime($n['ts']) > $visto)));
    }

    // Filtros guardados neste browser (nome → bool); sem cookie, os valores por omissão
    public static function filtros(User $user): array
    {
        $guardado = $_COOKIE['sino_filtros_' . $user->id] ?? '';
        $filtros  = [];
        $i = 0;
        foreach (Notificacoes::FILTROS as $nome => [, $omissao]) {
            $filtros[$nome] = preg_match('/^[01]{5}$/', $guardado) ? $guardado[$i] === '1' : $omissao;
            $i++;
        }
        return $filtros;
    }

    // Última vez que abriste a lista neste browser (timestamp) ou null
    private static function vistoEm(User $user): ?int
    {
        $v = $_COOKIE['sino_visto_' . $user->id] ?? '';
        return ctype_digit($v) ? (int) $v : null;
    }

    // Cookie só do servidor (HttpOnly), por HTTPS quando o há, e não enviado por outros sites
    private static function cookie(string $nome, string $valor): void
    {
        $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie($nome, $valor, [
            'expires'  => time() + self::DURACAO,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$nome] = $valor;   // já vale neste pedido
    }
}
