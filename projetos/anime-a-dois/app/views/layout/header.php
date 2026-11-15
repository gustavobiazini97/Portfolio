<?php /* Cabeçalho comum. Variáveis: $titulo, $appNome, $flash e, opcionais, $serieSlug e $serieAcento (cor da série) */ ?>
<!DOCTYPE html>
<html lang="pt" data-tema="claro" data-paleta="<?= paleta_atual() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#F5F3F8">
<title><?= e($titulo === $appNome ? $appNome : $titulo . ' · ' . $appNome) ?></title>
<!-- Descrição para motores de busca -->
<meta name="description" content="Regista e compara os episódios de anime que vês a dois. PHP/MySQL, API REST e app Android em Flutter.">
<!-- Open Graph: pré-visualização do link no LinkedIn, WhatsApp, etc. (os URLs têm de ser absolutos e públicos) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Anime a Dois">
<meta property="og:locale" content="pt_PT">
<meta property="og:url" content="<?= e(url_absoluto()) ?>">
<meta property="og:title" content="Anime a Dois — app web + Android">
<meta property="og:description" content="Regista e compara os episódios de anime que vês a dois. PHP/MySQL, API REST e app Android em Flutter.">
<meta property="og:image" content="<?= e(url_absoluto() . 'img/og-image.png') ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="627">
<meta property="og:image:alt" content="Anime a Dois: ecrã inicial da app com a biblioteca de séries">
<!-- Cartão grande no X/Twitter -->
<meta name="twitter:card" content="summary_large_image">
<!-- Tema guardado aplicado antes de pintar a página -->
<script src="<?= e(asset('js/tema.js')) ?>"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<!-- App instalável (PWA): manifest e ícones -->
<link rel="manifest" href="manifest.json">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<!-- data-apis: endereços das APIs de anime que o js/app.js usa (config.php → 'apis') -->
<body class="<?= e($classeBody ?? '') ?>" data-serie="<?= e($serieSlug ?? '') ?>" data-acento="<?= e((string) ($serieAcento ?? '')) ?>"
      data-apis="<?= e(json_encode(Database::config()['apis'] ?? new stdClass())) ?>">

<!-- Manchas difusas por trás do vidro; a terceira tem a cor da série aberta -->
<div class="fundo" aria-hidden="true">
  <i class="orbe orbe-tu"></i>
  <i class="orbe orbe-par"></i>
  <i class="orbe orbe-serie"></i>
</div>

<main class="ecra">
