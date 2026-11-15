<?php
// Página inicial: o funcionário vê os seus pedidos e a FAQ; o TI vê as filas e o estado do SLA.
declare(strict_types=1);

final class PainelController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $contadores = Ticket::contadores($u);

        if (self::eTecnico()) {
            $this->view('painel/tecnico', [
                'contadores' => $contadores,
                'meus' => Ticket::listar(['estado' => 'abertos', 'tecnico' => 'meus'], $u, 1, 8)['linhas'],
                'semTecnico' => Ticket::listar(['estado' => 'abertos', 'tecnico' => 'nenhum'], $u, 1, 8)['linhas'],
            ], 'Painel');
        }

        $this->view('painel/utilizador', [
            'contadores' => $contadores,
            'pedidos' => Ticket::listar(['estado' => 'todos'], $u, 1, 8)['linhas'],
            'populares' => Faq::pesquisar('', 0, false, 5),
        ], 'Início');
    }
}
