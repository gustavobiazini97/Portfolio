<?php /* Cabeçalho comum. Variáveis: $titulo, $appNome, $flash e, opcionais, $serieSlug e $serieAcento (cor da série) */ ?>
<!DOCTYPE html>
<html lang="pt" data-tema="claro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#F5F3F8">
<title><?= e($titulo === $appNome ? $appNome : $titulo . ' · ' . $appNome) ?></title>
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
<body class="<?= e($classeBody ?? '') ?>" data-serie="<?= e($serieSlug ?? '') ?>" data-acento="<?= e((string) ($serieAcento ?? '')) ?>">

<!-- Manchas difusas por trás do vidro; a terceira tem a cor da série aberta -->
<div class="fundo" aria-hidden="true">
  <i class="orbe orbe-tu"></i>
  <i class="orbe orbe-par"></i>
  <i class="orbe orbe-serie"></i>
</div>

<main class="ecra">
