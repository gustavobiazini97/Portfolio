<?php
// Cria as tabelas na base de dados definida no config/config.php.
// Uso (na pasta da app): php database/migrate.php
// Pode correr-se sempre: o schema.sql só usa CREATE TABLE IF NOT EXISTS.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Só faz sentido na linha de comandos (no alojamento corre pelo deploy, via SSH)
if (PHP_SAPI !== 'cli') {
    exit('Corre este script no terminal: php database/migrate.php');
}

$sql = file_get_contents(__DIR__ . '/schema.sql');

// Tira os comentários "-- ..." (a linha toda ou o resto da linha) antes de dividir por ";"
$sql = preg_replace('/--.*$/m', '', $sql);

// Cada comando termina em ";"; ignora os pedaços vazios
$comandos = array_filter(array_map('trim', explode(';', $sql)));

foreach ($comandos as $comando) {
    Capsule::connection()->unprepared($comando);

    // Mostra o nome da tabela para se ver o progresso no log do deploy
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $comando, $m)) {
        echo "Tabela ok: {$m[1]}\n";
    }
}

// Colunas que chegaram depois da primeira versão. O CREATE TABLE IF NOT EXISTS não mexe em
// tabelas que já existem, por isso cada coluna em falta é acrescentada aqui (uma vez só).
// A definição tem de ser igual à do schema.sql.
$colunas = [
    ['series', 'mal_id',          'INT UNSIGNED NULL UNIQUE'],
    ['series', 'capa',            'VARCHAR(255) NULL'],
    ['series', 'tipo',            'VARCHAR(20) NULL'],
    ['series', 'minutos_ep',      'TINYINT UNSIGNED NULL'],
    ['series', 'em_emissao',      'TINYINT(1) NOT NULL DEFAULT 0'],
    ['series', 'estado',          "VARCHAR(10) NOT NULL DEFAULT 'a_ver'"],
    ['series', 'acento',          'TINYINT UNSIGNED NULL'],
    ['series', 'adicionada_por',  'INT UNSIGNED NULL'],
    ['series', 'adicionada_em',   'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ['series', 'sincronizada_em', 'DATETIME NULL'],
    ['episodios', 'recap',        'TINYINT(1) NOT NULL DEFAULT 0'],
    ['preferencias', 'serie_push',  'TINYINT(1) NOT NULL DEFAULT 1'],
    ['preferencias', 'serie_email', 'TINYINT(1) NOT NULL DEFAULT 0'],
    ['users', 'novidades_vistas',  'SMALLINT UNSIGNED NOT NULL DEFAULT 0'],
    ['users', 'par_id',            'INT UNSIGNED NULL'],
    ['bibliotecas', 'com_id',      'INT UNSIGNED NULL'],
    ['users', 'admin',             'TINYINT(1) NOT NULL DEFAULT 0'],
    ['users', 'ultimo_acesso',     'DATETIME NULL'],
];

$schema = Capsule::schema();
foreach ($colunas as [$tabela, $coluna, $definicao]) {
    if (!$schema->hasColumn($tabela, $coluna)) {
        Capsule::connection()->statement("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao");
        echo "Coluna nova: $tabela.$coluna\n";
    }
}

// Backoffice: se ainda não há nenhum admin, a conta mais antiga (a do dono da app) passa a admin.
// Depois disto só se muda no backoffice.
if (Capsule::table('users')->where('admin', 1)->count() === 0 && Capsule::table('users')->count() > 0) {
    $primeiro = Capsule::table('users')->orderBy('id')->value('id');
    Capsule::table('users')->where('id', $primeiro)->update(['admin' => 1]);
    echo "Admin: utilizador $primeiro.\n";
}

// Passagem para bibliotecas individuais (uma vez só: quando a tabela ainda está vazia).
//   séries do seed (Naruto, Shippuden, Boruto) → conjuntas: entram na biblioteca de todos;
//   séries adicionadas por alguém → só na biblioteca de quem as adicionou;
//   propostas antigas → na biblioteca de quem propôs + convite para o outro.
if (Capsule::table('bibliotecas')->count() === 0 && Capsule::table('users')->count() > 0) {
    $users = Capsule::table('users')->pluck('id')->all();
    $linhas = [];
    foreach (Capsule::table('series')->get() as $s) {
        if ($s->adicionada_por === null) {
            foreach ($users as $u) {
                $linhas[] = ['user_id' => $u, 'serie_id' => $s->id, 'estado' => $s->estado === 'proposta' ? 'a_ver' : $s->estado, 'desde' => $s->adicionada_em];
            }
        } elseif ($s->estado === 'proposta') {
            foreach ($users as $u) {
                $linhas[] = ['user_id' => $u, 'serie_id' => $s->id, 'estado' => $u == $s->adicionada_por ? 'a_ver' : 'convite', 'desde' => $s->adicionada_em];
            }
        } else {
            $linhas[] = ['user_id' => $s->adicionada_por, 'serie_id' => $s->id, 'estado' => $s->estado, 'desde' => $s->adicionada_em];
        }
    }
    Capsule::table('bibliotecas')->insertOrIgnore($linhas);
    echo 'Bibliotecas individuais: ' . count($linhas) . " linhas criadas a partir das séries atuais.\n";
}

// O par passa a ser explícito (users.par_id), porque já não há só duas contas. Uma vez só: se ainda
// ninguém tem par e existem exatamente duas contas, são par uma da outra.
if (Capsule::table('users')->whereNotNull('par_id')->count() === 0 && Capsule::table('users')->count() === 2) {
    [$a, $b] = Capsule::table('users')->orderBy('id')->pluck('id')->all();
    Capsule::table('users')->where('id', $a)->update(['par_id' => $b]);
    Capsule::table('users')->where('id', $b)->update(['par_id' => $a]);
    echo "Par definido: $a e $b.\n";
}

// Companheiro por série (bibliotecas.com_id). Uma vez só (marcador em config_app):
//   séries que o casal tinha as duas → vistas juntos; convites antigos (estado 'convite') → tabela convites_serie.
if (!Capsule::table('config_app')->where('chave', 'com_id_migrado')->exists()) {
    $pares = Capsule::table('users')->whereNotNull('par_id')->get(['id', 'par_id']);
    foreach ($pares as $u) {
        // Cada par só uma vez (a menor das duas ids trata dos dois)
        if ($u->id > $u->par_id) {
            continue;
        }
        $comuns = Capsule::table('bibliotecas as a')
            ->join('bibliotecas as b', 'b.serie_id', '=', 'a.serie_id')
            ->where('a.user_id', $u->id)->where('b.user_id', $u->par_id)
            ->where('a.estado', '!=', 'convite')->where('b.estado', '!=', 'convite')
            ->pluck('a.serie_id')->all();
        if ($comuns !== []) {
            Capsule::table('bibliotecas')->whereIn('serie_id', $comuns)->where('user_id', $u->id)->update(['com_id' => $u->par_id]);
            Capsule::table('bibliotecas')->whereIn('serie_id', $comuns)->where('user_id', $u->par_id)->update(['com_id' => $u->id]);
        }
        echo 'Séries vistas juntas: ' . count($comuns) . "\n";
    }
    // Convites antigos: quem convidou é a outra pessoa que tem a série (com estado a_ver/pausa/acabado)
    foreach (Capsule::table('bibliotecas')->where('estado', 'convite')->get() as $c) {
        $de = Capsule::table('users')->where('id', $c->user_id)->value('par_id');
        if ($de !== null) {
            Capsule::table('convites_serie')->insertOrIgnore(['serie_id' => $c->serie_id, 'de_id' => $de, 'para_id' => $c->user_id]);
        }
        Capsule::table('bibliotecas')->where('user_id', $c->user_id)->where('serie_id', $c->serie_id)->delete();
    }
    Capsule::table('config_app')->insert(['chave' => 'com_id_migrado', 'valor' => '1']);
}

// Várias pessoas por série: o que estava em bibliotecas.com_id passa para series_juntos. Uma vez só.
if (!Capsule::table('config_app')->where('chave', 'juntos_migrado')->exists()) {
    $n = 0;
    foreach (Capsule::table('bibliotecas')->whereNotNull('com_id')->get(['serie_id', 'user_id', 'com_id']) as $l) {
        $n += Capsule::table('series_juntos')->insertOrIgnore(['serie_id' => $l->serie_id, 'user_id' => $l->user_id, 'com_id' => $l->com_id]);
    }
    Capsule::table('config_app')->insert(['chave' => 'juntos_migrado', 'valor' => '1']);
    echo "Ligações entre pessoas: $n.\n";
}

echo "Estrutura atualizada.\n";
