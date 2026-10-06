<?php
// Configuração da app. Copiar para config/config.php e preencher.
// O config.php está no .gitignore: as credenciais nunca vão para o GitHub.

return [
    // Ligação à base de dados (WAMP: root sem palavra-passe, por defeito)
    'db' => [
        'driver'    => 'mysql',
        'host'      => '127.0.0.1',
        'port'      => '3306',
        'database'  => 'anime_a_dois',
        'username'  => 'root',
        'password'  => '',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // Nome mostrado no topo e no título das páginas
    'app_nome' => 'Anime a Dois',

    // Número máximo de contas: o registo fecha quando chega a este valor
    'max_contas' => 2,

    // Remetente dos emails de notificação (no alwaysdata, o email da conta)
    'email_de' => 'animeadois@alwaysdata.net',

    // APIs de anime, chamadas pelo browser (o servidor não as usa):
    //   anilist → pesquisa, capas e número de episódios; kitsu → títulos dos episódios;
    //   jikan   → fillers e recaps (lê o MyAnimeList; quando está em baixo, a app tenta mais tarde)
    'apis' => [
        'anilist' => 'https://graphql.anilist.co',
        'kitsu'   => 'https://kitsu.app/api/edge',
        'jikan'   => 'https://api.jikan.moe/v4',
    ],
];
