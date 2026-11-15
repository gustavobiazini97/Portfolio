<?php
// Histórico de cada pedido: estados, atribuições, prioridade, categoria.
declare(strict_types=1);

final class Historico
{
    public static function registar(int $ticketId, ?int $uid, string $acao, ?string $de, ?string $para): void
    {
        Database::q(
            'INSERT INTO historico (ticket_id, utilizador_id, acao, de, para, criado_em) VALUES (?, ?, ?, ?, ?, ?)',
            [$ticketId, $uid, $acao, $de, $para, agora()]
        );
    }

    public static function doTicket(int $ticketId): array
    {
        return Database::linhas(
            'SELECT h.*, u.nome FROM historico h LEFT JOIN utilizadores u ON u.id = h.utilizador_id
              WHERE h.ticket_id = ? ORDER BY h.criado_em, h.id',
            [$ticketId]
        );
    }

    // Frase legível: "Ana mudou o estado de Novo para Em curso"
    public static function frase(array $h): string
    {
        $quem = $h['nome'] ?: 'O sistema';
        return match ($h['acao']) {
            'criado'     => "$quem abriu o pedido ({$h['para']})",
            'estado'     => "$quem mudou o estado de {$h['de']} para {$h['para']}",
            'atribuido'  => $h['utilizador_id'] ? "$quem atribuiu a {$h['para']}" : "Atribuído automaticamente a {$h['para']}",
            'prioridade' => "$quem mudou a prioridade de {$h['de']} para {$h['para']}",
            'categoria'  => "$quem mudou a categoria de {$h['de']} para {$h['para']}",
            'fecho_auto' => 'Fechado automaticamente (sem resposta depois de resolvido)',
            'avaliado'   => "$quem avaliou o atendimento: {$h['para']}",
            default      => "$quem: {$h['acao']}",
        };
    }
}
