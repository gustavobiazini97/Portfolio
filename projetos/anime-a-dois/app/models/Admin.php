<?php
// Números do backoffice e lista de contas (só lê; quem muda contas é o AdminController via User).

use Illuminate\Database\Capsule\Manager as Capsule;

class Admin
{
    // Resumo da app: contas, atividade, conteúdo e notificações
    public static function numeros(): array
    {
        $agora = time();
        $desde = fn (int $dias) => date('Y-m-d H:i:s', $agora - $dias * 86400);
        $contas = Capsule::table('users');

        return [
            'contas'        => (clone $contas)->count(),
            'novas7'        => (clone $contas)->where('criado_em', '>=', $desde(7))->count(),
            'ativas1'       => (clone $contas)->where('ultimo_acesso', '>=', $desde(1))->count(),
            'ativas7'       => (clone $contas)->where('ultimo_acesso', '>=', $desde(7))->count(),
            'ativas30'      => (clone $contas)->where('ultimo_acesso', '>=', $desde(30))->count(),
            'series'        => Capsule::table('series')->count(),
            'emBiblioteca'  => Capsule::table('bibliotecas')->count(),
            'vistos'        => Capsule::table('vistos')->count(),
            'comentarios'   => Capsule::table('comentarios')->count(),
            'amizades'      => intdiv(Capsule::table('amizades')->count(), 2),   // 2 linhas por amizade
            'juntos'        => intdiv(Capsule::table('series_juntos')->count(), 2),
            'comPush'       => Capsule::table('subscricoes')->distinct()->count('user_id'),
            'telemoveis'    => Capsule::table('subscricoes')->count(),
        ];
    }

    // As séries mais vistas na app (quantas pessoas têm cada uma)
    public static function topSeries(int $limite = 5): array
    {
        return Capsule::table('bibliotecas')
            ->join('series', 'series.id', '=', 'bibliotecas.serie_id')
            ->selectRaw('series.nome, COUNT(*) AS pessoas')
            ->groupBy('series.id', 'series.nome')
            ->orderByDesc('pessoas')->orderBy('series.nome')
            ->limit($limite)->get()->all();
    }

    // Lista de contas com contagens, filtrada por nome/utilizador (até 200)
    public static function contas(string $q = ''): array
    {
        $consulta = Capsule::table('users')
            ->select('users.*')
            ->selectRaw('(SELECT COUNT(*) FROM bibliotecas b WHERE b.user_id = users.id) AS n_series')
            ->selectRaw('(SELECT COUNT(*) FROM vistos v WHERE v.user_id = users.id) AS n_vistos')
            ->orderBy('users.id')->limit(200);

        $q = trim($q);
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $consulta->where(fn ($w) => $w->where('users.nome', 'like', $like)->orWhere('users.username', 'like', $like));
        }
        return $consulta->get()->all();
    }
}
