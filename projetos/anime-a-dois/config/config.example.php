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
];
