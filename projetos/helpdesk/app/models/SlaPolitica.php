<?php
// Prazos do SLA (em minutos de horário de trabalho) para cada prioridade.
declare(strict_types=1);

final class SlaPolitica
{
    private static ?array $cache = null;

    // Todas as políticas, indexadas pela prioridade (1..4)
    public static function todas(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::linhas('SELECT * FROM sla_politicas ORDER BY prioridade') as $p) {
                self::$cache[(int) $p['prioridade']] = $p;
            }
        }
        return self::$cache;
    }

    public static function porPrioridade(int $p): ?array
    {
        return self::todas()[$p] ?? null;
    }

    public static function guardar(int $prioridade, int $respostaMin, int $resolucaoMin): void
    {
        Database::q(
            'INSERT INTO sla_politicas (prioridade, resposta_min, resolucao_min) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE resposta_min = VALUES(resposta_min), resolucao_min = VALUES(resolucao_min)',
            [$prioridade, $respostaMin, $resolucaoMin]
        );
        self::$cache = null;
    }
}
