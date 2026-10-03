<?php
// Preenche as séries e os episódios a partir dos dados do Naruto Fillers.
// Uso (na pasta da app): php database/seed.php
// Pode correr-se várias vezes: atualiza em vez de duplicar, e não mexe nos vistos.

require __DIR__ . '/../app/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Só faz sentido na linha de comandos
if (PHP_SAPI !== 'cli') {
    exit('Corre este script no terminal: php database/seed.php');
}

// Os dados vivem no projeto irmão (projetos/naruto-fillers). No alojamento essa pasta
// não existe: o deploy copia o ficheiro para database/fillers.json, que é usado em alternativa.
$ficheiro = __DIR__ . '/../../naruto-fillers/data/fillers.json';
if (!is_file($ficheiro)) {
    $ficheiro = __DIR__ . '/fillers.json';
}
if (!is_file($ficheiro)) {
    exit("Não encontrei o fillers.json (nem no projeto naruto-fillers, nem em database/)\n");
}
$dados = json_decode(file_get_contents($ficheiro), true);

// Slug no JSON → slug curto usado na app, pela ordem do filtro
$mapa = [
    'naruto'                         => 'naruto',
    'naruto-shippuden'               => 'shippuden',
    'boruto-naruto-next-generations' => 'boruto',
];

Capsule::connection()->transaction(function () use ($dados, $mapa) {
    $ordem = 0;
    foreach ($mapa as $origem => $slug) {
        $show = $dados['shows'][$origem] ?? null;
        if ($show === null) {
            echo "Aviso: '$origem' não existe no JSON, ignorado.\n";
            continue;
        }

        // Série: cria ou atualiza pelo slug
        $serie = Serie::updateOrCreate(
            ['slug' => $slug],
            [
                'nome'            => $show['name'],
                'anos'            => $show['years'] ?? null,
                'total_episodios' => $show['total'],
                'ordem'           => ++$ordem,
            ]
        );

        // Fillers indexados pelo número, para marcar cada episódio
        $fillers = [];
        foreach ($show['episodes'] as $ep) {
            $fillers[(int) $ep['n']] = $ep['title'] ?? null;
        }

        // Um episódio por número, de 1 até ao total (upsert evita duplicados)
        $linhas = [];
        for ($n = 1; $n <= $show['total']; $n++) {
            $linhas[] = [
                'serie_id' => $serie->id,
                'numero'   => $n,
                'titulo'   => $fillers[$n] ?? null,
                'filler'   => array_key_exists($n, $fillers) ? 1 : 0,
            ];
        }
        Episodio::upsert($linhas, ['serie_id', 'numero'], ['titulo', 'filler']);

        printf("%-18s %3d episódios, %3d fillers\n", $show['name'], $show['total'], count($fillers));
    }
});

echo "Feito.\n";
