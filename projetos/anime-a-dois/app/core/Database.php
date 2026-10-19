<?php
// Liga o Eloquent (illuminate/database) à base de dados, sem o resto do Laravel.

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    // Configuração carregada de config/config.php (partilhada com o resto da app)
    private static ?array $config = null;

    // Lê o config.php uma única vez; avisa claramente se ainda não foi criado
    public static function config(): array
    {
        if (self::$config === null) {
            $ficheiro = __DIR__ . '/../../config/config.php';
            if (!is_file($ficheiro)) {
                exit('Falta o config/config.php — copia o config.example.php e preenche-o.');
            }
            self::$config = require $ficheiro;
        }
        return self::$config;
    }

    // Arranca o Eloquent com a ligação definida em 'db'
    public static function boot(): void
    {
        $capsule = new Capsule();
        $db = self::config()['db'];
        // A MariaDB do alojamento está noutro fuso (Paris) e as colunas com DEFAULT CURRENT_TIMESTAMP
        // (amizades, pedidos, convites…) ficavam 1 h à frente da hora de Portugal. Cada ligação passa
        // a usar o mesmo fuso do PHP (Europe/Lisbon, definido no bootstrap), incluindo a hora de verão.
        $db['timezone'] ??= date('P');
        $capsule->addConnection($db);
        $capsule->setAsGlobal();   // permite usar Capsule::table(...) em qualquer lado
        $capsule->bootEloquent();  // ativa os Models
    }
}
