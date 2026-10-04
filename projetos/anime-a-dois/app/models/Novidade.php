<?php
// Novidades da app (popup "O que há de novo" no Início).
// A lista vive em database/novidades.json; cada pessoa guarda o id da última que viu (users.novidades_vistas).
// Assim o popup aparece uma vez por atualização, para cada um, mesmo sem voltar a fazer login.

class Novidade
{
    // Todas as novidades, pela ordem do ficheiro (ids crescentes)
    public static function todas(): array
    {
        static $lista = null;
        if ($lista === null) {
            $json = json_decode((string) @file_get_contents(__DIR__ . '/../../database/novidades.json'), true);
            $lista = array_values(array_filter($json['novidades'] ?? [], fn ($n) => isset($n['id'], $n['titulo'])));
        }
        return $lista;
    }

    // Id da novidade mais recente (0 se não houver nenhuma)
    public static function ultima(): int
    {
        $ids = array_map(fn ($n) => (int) $n['id'], self::todas());
        return $ids === [] ? 0 : max($ids);
    }

    // As que esta pessoa ainda não viu, das mais recentes para as mais antigas
    public static function porVer(User $user): array
    {
        $vistas = (int) $user->novidades_vistas;
        $lista = array_filter(self::todas(), fn ($n) => (int) $n['id'] > $vistas);
        return array_reverse(array_values($lista));
    }

    // "4 out" (data curta para o popup)
    public static function dataCurta(?string $data): string
    {
        if (!$data || !($t = strtotime($data))) {
            return '';
        }
        $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        return (int) date('j', $t) . ' ' . $meses[(int) date('n', $t) - 1];
    }
}
