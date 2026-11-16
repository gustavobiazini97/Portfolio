<?php
// Configuração da app. Copiar para config/config.php e preencher.
// O config.php está no .gitignore: as credenciais nunca vão para o GitHub.

return [
    // true só no PC de desenvolvimento: mostra os erros no ecrã. Em produção fica SEMPRE false.
    'debug' => false,

    // 2FA obrigatório para administradores. Pôr false SÓ num PC de testes (nunca em produção).
    'admin_2fa_obrigatorio' => true,

    // Nome mostrado no topo, no título das páginas e nos emails
    'app_nome' => 'Helpdesk TI',
    'empresa'  => 'Macolis',

    // Endereço público da app, sem barra no fim (usado nos links dos emails)
    'url_base' => 'http://localhost:8000',

    // Fuso horário de todas as datas (BD, SLA, emails)
    'timezone' => 'Europe/Lisbon',

    // Base de dados. Em produção usar um utilizador só com SELECT, INSERT, UPDATE, DELETE (ver README)
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'helpdesk',
        'username' => 'root',
        'password' => '',
    ],

    // Sessão: termina após X minutos parada e, em qualquer caso, ao fim de Y horas
    'sessao' => [
        'inatividade_min' => 30,
        'absoluta_horas'  => 10,
    ],

    // Horário de trabalho do departamento de TI: os prazos do SLA só contam dentro dele.
    // dias: 1 = segunda … 7 = domingo. feriados: 'MM-DD' (todos os anos) ou 'AAAA-MM-DD' (só nesse ano)
    'horario' => [
        'dias'     => [1, 2, 3, 4, 5],
        'inicio'   => '09:00',
        'fim'      => '18:00',
        'feriados' => ['01-01', '04-25', '05-01', '06-10', '08-15', '10-05', '11-01', '12-01', '12-08', '12-25'],
    ],

    // Pedidos "Resolvidos" que o utilizador não confirma passam a "Fechados" ao fim de X dias
    'fechar_auto_dias' => 3,

    // Anexos: tamanho máximo por ficheiro (MB) e número de ficheiros por envio
    'anexos' => [
        'max_mb'        => 5,
        'max_por_envio' => 3,
    ],

    // Emails das notificações.
    //   modo 'log'  → não envia nada, escreve em storage/logs/emails.log (bom para testar no WAMP)
    //   modo 'smtp' → envia pelo servidor SMTP abaixo (Outlook/Microsoft 365: smtp.office365.com, 587, tls)
    'email' => [
        'modo'    => 'log',
        'de'      => 'helpdesk@exemplo.pt',
        'de_nome' => 'Helpdesk TI',
        'smtp'    => [
            'host'       => 'smtp.office365.com',
            'porta'      => 587,
            'seguranca'  => 'tls',   // 'tls' (STARTTLS, porta 587) ou 'ssl' (porta 465)
            'utilizador' => '',
            'password'   => '',
        ],
    ],
];
