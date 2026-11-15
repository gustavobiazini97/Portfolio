<?php
// Contas do SLA em horário de trabalho: somar minutos úteis a uma data e contar minutos úteis entre duas datas.
// Um pedido aberto sexta às 17:30 com 1 h de prazo vence segunda às 09:30, não sexta às 18:30.
declare(strict_types=1);

final class Sla
{
    // Data/hora em que acabam $minutos de trabalho contados a partir de $inicio
    public static function somar(string $inicio, int $minutos): string
    {
        $t = strtotime($inicio);
        $resta = max(0, $minutos) * 60;   // em segundos
        for ($guarda = 0; $guarda < 3700; $guarda++) {
            [$abre, $fecha] = self::janela($t);
            if ($abre === null || $t >= $fecha) {
                $t = self::proximoDia($t);
                continue;
            }
            if ($t < $abre) {
                $t = $abre;
            }
            $disponivel = $fecha - $t;
            if ($resta <= $disponivel) {
                return date('Y-m-d H:i:s', $t + $resta);
            }
            $resta -= $disponivel;
            $t = self::proximoDia($t);
        }
        // Configuração impossível (ex.: nenhum dia útil): conta em tempo corrido
        return date('Y-m-d H:i:s', strtotime($inicio) + $minutos * 60);
    }

    // Minutos de trabalho entre duas datas (0 se $fim for antes de $inicio)
    public static function minutosEntre(string $inicio, string $fim): int
    {
        $a = strtotime($inicio);
        $b = strtotime($fim);
        if ($b <= $a) {
            return 0;
        }
        $total = 0;
        $t = $a;
        for ($guarda = 0; $guarda < 3700 && $t < $b; $guarda++) {
            [$abre, $fecha] = self::janela($t);
            if ($abre !== null) {
                $de = max($t, $abre);
                $ate = min($b, $fecha);
                if ($ate > $de) {
                    $total += $ate - $de;
                }
            }
            $t = self::proximoDia($t);
        }
        return intdiv($total, 60);
    }

    // Abertura e fecho (timestamps) do dia de $t; [null, null] se for fim de semana ou feriado
    private static function janela(int $t): array
    {
        $dia = date('Y-m-d', $t);
        $diasUteis = array_map('intval', (array) config('horario.dias', [1, 2, 3, 4, 5]));
        $feriados = (array) config('horario.feriados', []);
        if (!in_array((int) date('N', $t), $diasUteis, true)
            || in_array(date('m-d', $t), $feriados, true)
            || in_array($dia, $feriados, true)) {
            return [null, null];
        }
        $abre = strtotime($dia . ' ' . config('horario.inicio', '09:00'));
        $fecha = strtotime($dia . ' ' . config('horario.fim', '18:00'));
        return $fecha > $abre ? [$abre, $fecha] : [null, null];
    }

    // Meia-noite do dia seguinte
    private static function proximoDia(int $t): int
    {
        return strtotime(date('Y-m-d', $t) . ' +1 day');
    }

    // Prioridade P1–P4 a partir do impacto e da urgência (matriz ITIL simplificada, 3×3):
    //                 urgência 1   2   3
    //   impacto 3 (empresa)    P3  P2  P1
    //   impacto 2 (equipa)     P4  P3  P2
    //   impacto 1 (só eu)      P4  P4  P3
    public static function prioridade(int $impacto, int $urgencia): int
    {
        $soma = max(1, min(3, $impacto)) + max(1, min(3, $urgencia));
        return match (true) {
            $soma >= 6 => 1,
            $soma === 5 => 2,
            $soma === 4 => 3,
            default => 4,
        };
    }

    // Estado do relógio de um pedido, para mostrar nas listas e na página do pedido.
    // Devolve ['tipo' => ok|risco|violado|pausado|cumprido|falhado, 'texto' => '...'] para resposta e resolução.
    public static function estado(array $t): array
    {
        $agora = agora();
        $politica = SlaPolitica::porPrioridade((int) $t['prioridade']);
        $resultado = [];

        // 1.ª resposta: o relógio não pausa (a pausa só acontece depois de o técnico responder)
        if ($t['primeira_resposta_em']) {
            $resultado['resposta'] = $t['primeira_resposta_em'] <= $t['prazo_resposta']
                ? ['tipo' => 'cumprido', 'texto' => 'Respondido a tempo']
                : ['tipo' => 'falhado', 'texto' => 'Respondido fora do prazo'];
        } elseif (in_array($t['estado'], ['resolvido', 'fechado'], true)) {
            $resultado['resposta'] = ['tipo' => 'cumprido', 'texto' => 'Sem resposta necessária'];
        } else {
            $resultado['resposta'] = self::relogio($agora, $t['prazo_resposta'], (int) ($politica['resposta_min'] ?? 60));
        }

        // Resolução: pausa nos estados "à espera"
        if ($t['resolvido_em']) {
            $resultado['resolucao'] = $t['resolvido_em'] <= $t['prazo_resolucao']
                ? ['tipo' => 'cumprido', 'texto' => 'Resolvido dentro do prazo']
                : ['tipo' => 'falhado', 'texto' => 'Resolvido fora do prazo'];
        } elseif ($t['sla_pausado_em']) {
            $resta = self::minutosEntre($t['sla_pausado_em'], $t['prazo_resolucao']);
            $resultado['resolucao'] = ['tipo' => 'pausado', 'texto' => 'Pausado · faltam ' . duracao($resta)];
        } else {
            $resultado['resolucao'] = self::relogio($agora, $t['prazo_resolucao'], (int) ($politica['resolucao_min'] ?? 540));
        }
        return $resultado;
    }

    // Quanto falta (ou quanto passou) até um prazo; "risco" quando restam menos de 20% do tempo
    private static function relogio(string $agora, string $prazo, int $totalMin): array
    {
        if ($agora >= $prazo) {
            $atraso = self::minutosEntre($prazo, $agora);
            return ['tipo' => 'violado', 'texto' => 'Prazo ultrapassado' . ($atraso ? ' há ' . duracao($atraso) : '')];
        }
        $resta = self::minutosEntre($agora, $prazo);
        $tipo = $resta <= $totalMin * 0.2 ? 'risco' : 'ok';
        return ['tipo' => $tipo, 'texto' => 'Faltam ' . duracao($resta)];
    }
}
