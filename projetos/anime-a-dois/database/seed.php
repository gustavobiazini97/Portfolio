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
    fwrite(STDERR, "Não encontrei o fillers.json (nem no projeto naruto-fillers, nem em database/)\n");
    exit(1);   // código de erro: o deploy pára aqui em vez de seguir sem episódios
}
$dados = json_decode(file_get_contents($ficheiro), true);

// Títulos de TODOS os episódios (gerado pelo database/titulos.py no GitHub Actions).
// Se não existir, ficam só os títulos dos fillers, como antes.
$todos = [];
$ficheiroTitulos = __DIR__ . '/episodios.json';
if (is_file($ficheiroTitulos)) {
    foreach (json_decode(file_get_contents($ficheiroTitulos), true) as $slugApp => $lista) {
        foreach ($lista as $ep) {
            $todos[$slugApp][(int) $ep['n']] = $ep['titulo'] ?? null;
        }
    }
}

// Slug no JSON → slug curto usado na app, pela ordem da biblioteca
$mapa = [
    'naruto'                         => 'naruto',
    'naruto-shippuden'               => 'shippuden',
    'boruto-naruto-next-generations' => 'boruto',
];

// Id de cada uma no MyAnimeList: impede que a pesquisa as adicione outra vez e permite ir buscar a capa
$malIds = ['naruto' => 20, 'shippuden' => 1735, 'boruto' => 34566];

Capsule::connection()->transaction(function () use ($dados, $mapa, $todos, $malIds) {
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
                'mal_id'          => $malIds[$slug],
                'adicionada_em'   => '2026-10-03 00:00:00',   // o dia em que a app nasceu: as séries novas ficam à frente
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
                'titulo'   => $todos[$slug][$n] ?? $fillers[$n] ?? null,   // título de todos; senão só o do filler
                'filler'   => array_key_exists($n, $fillers) ? 1 : 0,
            ];
        }
        Episodio::upsert($linhas, ['serie_id', 'numero'], ['titulo', 'filler']);

        printf("%-18s %3d episódios, %3d fillers, %3d títulos\n", $show['name'], $show['total'], count($fillers), count(array_filter($todos[$slug] ?? [])));
    }
});

// As capas destas três são preenchidas pelo browser na primeira visita ao Início
// (o servidor não fala com as APIs de anime; ver app/models/DadosAnime.php).

echo "Feito.\n";
