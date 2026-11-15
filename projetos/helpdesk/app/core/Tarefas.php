<?php
// Tarefas em segundo plano: enviar emails da fila, alertas de SLA, fecho automático e limpezas.
// Correm pelo cron (cron/processar.php) e, como reserva, no fim dos pedidos web (no máximo 1× por minuto),
// para funcionar mesmo num WAMP sem agendador configurado.
declare(strict_types=1);

final class Tarefas
{
    // Chamado no fim de cada pedido web: entrega a página primeiro e só depois trabalha
    public static function noFimDoPedido(): void
    {
        $marca = RAIZ . '/storage/logs/.ultima-tarefa';
        if (is_file($marca) && time() - (int) filemtime($marca) < 60) {
            return;
        }
        @touch($marca);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            self::correr();
        } catch (Throwable $e) {
            error_log('Tarefas falharam: ' . $e->getMessage());
        }
    }

    // Tudo de uma vez; devolve um resumo (mostrado pelo cron)
    public static function correr(): array
    {
        return [
            'sla' => self::alertasSla(),
            'fechados' => self::fecharResolvidos(),
            'emails' => Mailer::processarFila(30),
            'limpeza' => self::limpar(),
        ];
    }

    // Aviso aos 80% do prazo e alerta quando passa. Cada nível só é enviado uma vez por pedido.
    public static function alertasSla(): int
    {
        $enviados = 0;
        $agora = agora();
        foreach (Ticket::comRelogioAtivo() as $t) {
            $pol = SlaPolitica::porPrioridade((int) $t['prioridade']);
            if (!$pol) {
                continue;
            }
            // Relógio da 1.ª resposta (só enquanto ninguém do TI respondeu)
            if (!$t['primeira_resposta_em']) {
                $nivel = self::nivel($agora, $t['prazo_resposta'], (int) $pol['resposta_min']);
                if ($nivel > (int) $t['alerta_resposta']) {
                    Ticket::marcarAlerta((int) $t['id'], 'alerta_resposta', $nivel);
                    Notificador::sla($t, 'resposta', $nivel === 2);
                    $enviados++;
                }
            }
            // Relógio da resolução (parado enquanto está "à espera")
            if (!$t['sla_pausado_em']) {
                $nivel = self::nivel($agora, $t['prazo_resolucao'], (int) $pol['resolucao_min']);
                if ($nivel > (int) $t['alerta_resolucao']) {
                    Ticket::marcarAlerta((int) $t['id'], 'alerta_resolucao', $nivel);
                    Notificador::sla($t, 'resolucao', $nivel === 2);
                    $enviados++;
                }
            }
        }
        return $enviados;
    }

    // 0 = tudo bem, 1 = restam ≤ 20% do tempo, 2 = prazo ultrapassado
    private static function nivel(string $agora, string $prazo, int $totalMin): int
    {
        if ($agora >= $prazo) {
            return 2;
        }
        return Sla::minutosEntre($agora, $prazo) <= $totalMin * 0.2 ? 1 : 0;
    }

    // "Resolvido" sem resposta do funcionário durante X dias → "Fechado"
    public static function fecharResolvidos(): int
    {
        $dias = (int) config('fechar_auto_dias', 3);
        $n = 0;
        foreach (Ticket::paraFecharAuto($dias) as $t) {
            Ticket::mudarEstado($t, 'fechado', null);   // fica no histórico como "O sistema mudou o estado…"
            Notificador::fechoAuto($t);
            $n++;
        }
        return $n;
    }

    // Limpezas: rate limiting antigo, tokens de reset gastos, emails enviados há mais de 30 dias
    private static function limpar(): int
    {
        Limite::limparAntigos();
        Database::q('DELETE FROM tokens_reset WHERE expira_em < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        return Database::q('DELETE FROM fila_emails WHERE enviado_em IS NOT NULL AND enviado_em < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)])->rowCount();
    }
}
