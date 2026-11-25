<?php
// Testes do mascote do casal (app/models/Mascote.php), com base de dados a sério
// (correm no GitHub Actions, a seguir aos outros: anime-a-dois-testes.yml).
// Correr com: php tests/mascote_test.php
//
// Cobre: fases e humor (parte pura), espécie escolhida uma só vez e só depois de o ovo chocar,
// nome e cor validados, "vira as costas" (15+ episódios atrás), troca de par, conta sem par,
// e apagar a conta (a linha do mascote desaparece).

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$falhas = 0;
$verifica = function (string $nome, bool $ok, string $detalhe = '') use (&$falhas) {
    echo ($ok ? 'ok   ' : 'FALHA') . "  $nome" . ($ok || $detalhe === '' ? '' : "  ($detalhe)") . "\n";
    $falhas += $ok ? 0 : 1;
};

// Corre $f e diz se lançou InvalidArgumentException (o que a API converte em 422)
$lanca = function (callable $f): bool {
    try {
        $f();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};

// ---- PURA ----
// ---------- Partes sem base de dados ----------
$verifica('fase: 0 = ovo', Mascote::faseDe(0) === 'ovo');
$verifica('fase: 9 = ovo', Mascote::faseDe(9) === 'ovo');
$verifica('fase: 10 = cria', Mascote::faseDe(10) === 'cria');
$verifica('fase: 99 = cria', Mascote::faseDe(99) === 'cria');
$verifica('fase: 100 = jovem', Mascote::faseDe(100) === 'jovem');
$verifica('fase: 299 = jovem', Mascote::faseDe(299) === 'jovem');
$verifica('fase: 300 = adulta', Mascote::faseDe(300) === 'adulta');
$verifica('fase: 999 = adulta', Mascote::faseDe(999) === 'adulta');
$verifica('fase: 1000 = lendaria', Mascote::faseDe(1000) === 'lendaria');

$verifica('humor: 2 dias = feliz', Mascote::humorDe('2026-11-23', '2026-11-25') === 'feliz');
$verifica('humor: hoje = feliz', Mascote::humorDe('2026-11-25 10:00:00', '2026-11-25') === 'feliz');
$verifica('humor: 3 dias = aborrecida', Mascote::humorDe('2026-11-22', '2026-11-25') === 'aborrecida');
$verifica('humor: 6 dias = aborrecida', Mascote::humorDe('2026-11-19', '2026-11-25') === 'aborrecida');
$verifica('humor: 7 dias = a_dormir', Mascote::humorDe('2026-11-18', '2026-11-25') === 'a_dormir');
$verifica('humor: nunca viram juntos = feliz', Mascote::humorDe(null, '2026-11-25') === 'feliz');
// ---- FIM PURA ----

// ---------- Dados: casal (gil + hana), outra pessoa (ines) e uma conta sem par (sozinho) ----------
$criar = fn (string $u, string $nome) => User::create(['nome' => $nome, 'username' => $u, 'password_hash' => password_hash('segredo123', PASSWORD_DEFAULT)]);
$gil  = $criar('ms_gil', 'Gil');
$hana = $criar('ms_hana', 'Hana');
$ines = $criar('ms_ines', 'Inês');
$ze   = $criar('ms_ze', 'Zé');
$sozinho = $criar('ms_sozinho', 'Sozinho');
$parOf = function (User $a, ?User $b) {
    User::where('id', $a->id)->update(['par_id' => $b?->id]);
    $a->refresh();
};
$parOf($gil, $hana); $parOf($hana, $gil);
$parOf($ines, $ze);  $parOf($ze, $ines);

// Uma série de 60 episódios que o casal vê junto (series_juntos nos dois sentidos)
$serie = Serie::create(['slug' => 'ms-alfa', 'nome' => 'Alfa', 'total_episodios' => 60, 'mal_id' => 999901, 'adicionada_por' => $gil->id]);
for ($n = 1; $n <= 60; $n++) {
    Capsule::table('episodios')->insert(['serie_id' => $serie->id, 'numero' => $n, 'titulo' => "Ep $n"]);
}
Capsule::table('series_juntos')->insert([
    ['serie_id' => $serie->id, 'user_id' => $gil->id,  'com_id' => $hana->id],
    ['serie_id' => $serie->id, 'user_id' => $hana->id, 'com_id' => $gil->id],
]);
$id  = fn (int $n) => (int) Capsule::table('episodios')->where('serie_id', $serie->id)->where('numero', $n)->value('id');
$ver = fn (User $u, int $n, string $quando) => Capsule::table('vistos')->insertOrIgnore(['user_id' => $u->id, 'episodio_id' => $id($n), 'visto_em' => $quando]);
$limparVistos = fn () => Capsule::table('vistos')->whereIn('user_id', [$gil->id, $hana->id])->delete();
$hoje = date('Y-m-d H:i:s');

// ---------- Sem par ----------
$verifica('sem par: estado é null', Mascote::estado($sozinho) === null);
$verifica('sem par: guardar lança', $lanca(fn () => Mascote::guardar($sozinho, ['nome' => 'X'])));
$verifica('sem par: não cria linhas', Capsule::table('mascotes')->count() === 0);

// ---------- Ovo (9 episódios) ----------
foreach (range(1, 9) as $n) { $ver($gil, $n, $hoje); $ver($hana, $n, $hoje); }
$e = Mascote::estado($gil);
$verifica('ovo: fase ovo com 9 juntos', $e['fase'] === 'ovo' && $e['episodiosJuntos'] === 9, json_encode($e));
$verifica('ovo: ainda não pode escolher', $e['podeEscolher'] === false && $e['especie'] === null);
$verifica('ovo: escolher espécie lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => 'gato'])));
$verifica('ovo: a espécie ficou por escolher', Mascote::estado($gil)['especie'] === null);

// ---------- Cria (12 episódios): escolher a espécie ----------
foreach (range(10, 12) as $n) { $ver($gil, $n, $hoje); $ver($hana, $n, $hoje); }
$e = Mascote::estado($gil);
$verifica('cria: fase cria com 12 juntos', $e['fase'] === 'cria' && $e['episodiosJuntos'] === 12, json_encode($e));
$verifica('cria: pode escolher', $e['podeEscolher'] === true);
$verifica('espécie inválida lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => 'dragao'])));
$verifica('espécie vazia lança', $lanca(fn () => Mascote::guardar($gil, ['especie' => ''])));
Mascote::guardar($gil, ['especie' => 'raposa']);
$verifica('escolhe raposa', Mascote::estado($gil)['especie'] === 'raposa');
$verifica('a Hana vê a mesma espécie', Mascote::estado($hana)['especie'] === 'raposa');
$verifica('segunda escolha (da Hana) lança e fica a raposa', $lanca(fn () => Mascote::guardar($hana, ['especie' => 'gato'])) && Mascote::estado($gil)['especie'] === 'raposa');
$verifica('depois de escolher já não pode escolher', Mascote::estado($gil)['podeEscolher'] === false);
$verifica('só há uma linha de mascote para o casal', Capsule::table('mascotes')->count() === 1);

// ---------- Nome e cor ----------
Mascote::guardar($gil, ['nome' => '  Kuro  ']);
$verifica('nome: tira os espaços', Mascote::estado($gil)['nome'] === 'Kuro');
$verifica('nome: 21 letras lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => str_repeat('a', 21)])));
Mascote::guardar($gil, ['nome' => str_repeat('a', 20)]);
$verifica('nome: 20 letras passa', mb_strlen(Mascote::estado($gil)['nome']) === 20);
$verifica('nome: vazio lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => ''])));
$verifica('nome: só espaços lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => '    '])));
$verifica('nome: caracteres de controlo lançam', $lanca(fn () => Mascote::guardar($gil, ['nome' => "a\x00b"])));
$verifica('nome: não texto lança', $lanca(fn () => Mascote::guardar($gil, ['nome' => ['x']])));
Mascote::guardar($gil, ['nome' => '<script>x</script>']);
$verifica('nome: HTML guarda-se como texto (quem desenha é que escapa)', Mascote::estado($gil)['nome'] === '<script>x</script>');
Mascote::guardar($gil, ['nome' => '🦊 Kuro']);
$verifica('nome: emoji passa', Mascote::estado($gil)['nome'] === '🦊 Kuro');
Mascote::guardar($gil, ['cor' => 5]);
$verifica('cor: 5 passa', Mascote::estado($gil)['cor'] === 5);
Mascote::guardar($gil, ['cor' => '2']);
$verifica('cor: "2" (texto) passa como 2', Mascote::estado($gil)['cor'] === 2);
$verifica('cor: 6 lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => 6])));
$verifica('cor: -1 lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => -1])));
$verifica('cor: "a" lança', $lanca(fn () => Mascote::guardar($gil, ['cor' => 'a'])));
$verifica('cor: uma inválida não deixa o nome gravado a meio', $lanca(fn () => Mascote::guardar($gil, ['nome' => 'Outro', 'cor' => 9])) && Mascote::estado($gil)['nome'] === '🦊 Kuro');

// ---------- Costas (15+ episódios atrás numa série juntos) ----------
$limparVistos();
foreach (range(1, 20) as $n) { $ver($gil, $n, $hoje); }
foreach (range(1, 4) as $n) { $ver($hana, $n, $hoje); }
$verifica('costas: a Hana (16 atrás) está de costas', Mascote::estado($hana)['deCostas'] === true);
$verifica('costas: o Gil (à frente) não', Mascote::estado($gil)['deCostas'] === false);
foreach (range(5, 6) as $n) { $ver($hana, $n, $hoje); }
$verifica('costas: 14 atrás já não', Mascote::estado($hana)['deCostas'] === false);
foreach (range(7, 20) as $n) { $ver($hana, $n, $hoje); }

// ---------- Humor com dados ----------
$limparVistos();
$antes = date('Y-m-d H:i:s', strtotime('-8 day'));
foreach (range(1, 12) as $n) { $ver($gil, $n, $antes); $ver($hana, $n, $antes); }
$verifica('humor: último juntos há 8 dias = a_dormir', Mascote::estado($gil)['humor'] === 'a_dormir');
$ver($gil, 13, $hoje); $ver($hana, 13, $hoje);
$verifica('humor: viram juntos hoje = feliz', Mascote::estado($gil)['humor'] === 'feliz');
$limparVistos();
$verifica('humor: nenhum episódio juntos = feliz', Mascote::estado($gil)['humor'] === 'feliz');

// ---------- Troca de par ----------
$idCasal = Mascote::obter($gil)->id;
$parOf($gil, $ines); $parOf($ines, $gil); $parOf($hana, null); $parOf($ze, null);
$novo = Mascote::obter($gil);
$verifica('troca de par: outro mascote, em ovo, sem espécie', $novo->id !== $idCasal && Mascote::estado($gil)['especie'] === null && Mascote::estado($gil)['fase'] === 'ovo');
$verifica('troca de par: o antigo continua guardado', Capsule::table('mascotes')->where('id', $idCasal)->exists());
$parOf($gil, $hana); $parOf($hana, $gil); $parOf($ines, null);
$verifica('voltar ao par antigo devolve o mesmo mascote', Mascote::obter($gil)->id === $idCasal && Mascote::estado($gil)['especie'] === 'raposa');

// ---------- Apagar a conta ----------
$total = Capsule::table('mascotes')->count();
User::where('id', $hana->id)->delete();
$verifica('apagar a conta apaga os mascotes dessa pessoa', Capsule::table('mascotes')->count() < $total, (string) Capsule::table('mascotes')->count());

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo ok\n";
exit($falhas ? 1 : 0);
