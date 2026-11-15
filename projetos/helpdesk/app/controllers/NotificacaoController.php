<?php
// O sino: lista de notificações, contador (lido pelo JavaScript a cada minuto) e marcar como lidas.
declare(strict_types=1);

final class NotificacaoController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $this->view('notificacoes/index', ['lista' => Notificacao::ultimas((int) $u['id'])], 'Notificações');
    }

    // GET (JSON): número de não lidas (para o badge do sino)
    public function contar(): void
    {
        $u = self::eu();
        if (!$u) {
            $this->json(['erro' => 'sessao'], 401);
        }
        $this->json(['naoLidas' => Notificacao::naoLidas((int) $u['id'])]);
    }

    // GET: abre a notificação (marca como lida e vai para o pedido)
    public function abrir(): void
    {
        $u = $this->exigirLogin();
        $n = Notificacao::marcarLida((int) $u['id'], inteiro(get('id')));
        if ($n && $n['ticket_id']) {
            redirecionar('ticket', 'ver', ['id' => $n['ticket_id']]);
        }
        redirecionar('notificacao');
    }

    // POST: marca todas como lidas
    public function lerTodas(): void
    {
        $u = $this->exigirLogin();
        Notificacao::marcarTodas((int) $u['id']);
        redirecionar('notificacao');
    }
}
