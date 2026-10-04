<?php
// Arranque comum à app web e aos scripts de terminal (seed).

// Autoload do Composer: carrega o Eloquent e todas as classes de app/ (classmap)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    exit("Falta a pasta vendor/ — corre 'composer install' na pasta da app.\n");
}
require $autoload;

// Funções globais das views (e(), url(), csrf_campo()); o classmap só carrega classes
require __DIR__ . '/core/helpers.php';

// Hora de Portugal para o "visto em"
date_default_timezone_set('Europe/Lisbon');

// Liga o Eloquent à base de dados
Database::boot();
