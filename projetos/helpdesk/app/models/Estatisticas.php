<?php
// Números para o admin: volume, cumprimento do SLA, tempos médios, satisfação e pedidos evitados pela FAQ.
declare(strict_types=1);

final class Estatisticas
{
    public static function periodo(int $dias): array
    {
        $desde = date('Y-m-d H:i:s', time() - $dias * 86400);

        // Volume
        $criados = (int) Database::valor('SELECT COUNT(*) FROM tickets WHERE criado_em >= ?', [$desde]);
        $resolvidos = Database::linhas(
            'SELECT criado_em, resolvido_em, prazo_resolucao, sla_pausa_min FROM tickets WHERE resolvido_em >= ?',
            [$desde]
        );
        $abertos = (int) Database::valor("SELECT COUNT(*) FROM tickets WHERE estado IN ('novo','em_curso','aguarda_utilizador','aguarda_terceiros')");

        // Primeira resposta: % a tempo e tempo médio (minutos úteis)
        $respostas = Database::linhas(
            'SELECT criado_em, primeira_resposta_em, prazo_resposta FROM tickets WHERE primeira_resposta_em >= ?',
            [$desde]
        );
        $respATempo = 0;
        $respMin = 0;
        foreach ($respostas as $r) {
            $respATempo += $r['primeira_resposta_em'] <= $r['prazo_resposta'] ? 1 : 0;
            $respMin += Sla::minutosEntre($r['criado_em'], $r['primeira_resposta_em']);
        }

        // Resolução: % a tempo e tempo médio (minutos úteis sem as pausas "à espera")
        $resATempo = 0;
        $resMin = 0;
        foreach ($resolvidos as $r) {
            $resATempo += $r['resolvido_em'] <= $r['prazo_resolucao'] ? 1 : 0;
            $resMin += max(0, Sla::minutosEntre($r['criado_em'], $r['resolvido_em']) - (int) $r['sla_pausa_min']);
        }

        $avaliacao = Database::linha(
            'SELECT AVG(avaliacao) AS media, COUNT(avaliacao) AS total FROM tickets WHERE avaliacao IS NOT NULL AND fechado_em >= ?',
            [$desde]
        );

        return [
            'criados' => $criados,
            'resolvidos' => count($resolvidos),
            'abertos' => $abertos,
            'resposta_pct' => $respostas ? (int) round($respATempo / count($respostas) * 100) : null,
            'resposta_media' => $respostas ? (int) round($respMin / count($respostas)) : null,
            'resolucao_pct' => $resolvidos ? (int) round($resATempo / count($resolvidos) * 100) : null,
            'resolucao_media' => $resolvidos ? (int) round($resMin / count($resolvidos)) : null,
            'avaliacao_media' => $avaliacao && $avaliacao['total'] ? round((float) $avaliacao['media'], 1) : null,
            'avaliacao_total' => (int) ($avaliacao['total'] ?? 0),
            'desvios' => (int) Database::valor('SELECT COUNT(*) FROM faq_desvios WHERE criado_em >= ?', [$desde]),
            'por_categoria' => Database::linhas(
                'SELECT COALESCE(c.nome, \'Sem categoria\') AS nome, COUNT(*) AS total FROM tickets t
              LEFT JOIN categorias c ON c.id = t.categoria_id WHERE t.criado_em >= ? GROUP BY c.nome ORDER BY total DESC',
                [$desde]
            ),
            'por_prioridade' => Database::linhas(
                'SELECT prioridade, COUNT(*) AS total FROM tickets WHERE criado_em >= ? GROUP BY prioridade ORDER BY prioridade',
                [$desde]
            ),
            'por_tecnico' => Database::linhas(
                'SELECT u.nome, COUNT(*) AS resolvidos, AVG(t.avaliacao) AS avaliacao,
                        SUM(t.resolvido_em <= t.prazo_resolucao) AS a_tempo
                   FROM tickets t JOIN utilizadores u ON u.id = t.tecnico_id
                  WHERE t.resolvido_em >= ? GROUP BY u.id, u.nome ORDER BY resolvidos DESC',
                [$desde]
            ),
            'faq_top' => Database::linhas(
                'SELECT f.id, f.titulo, f.visualizacoes, f.util_sim, f.util_nao,
                        (SELECT COUNT(*) FROM faq_desvios d WHERE d.artigo_id = f.id AND d.criado_em >= ?) AS desvios
                   FROM faq_artigos f ORDER BY desvios DESC, f.visualizacoes DESC LIMIT 8',
                [$desde]
            ),
        ];
    }
}
