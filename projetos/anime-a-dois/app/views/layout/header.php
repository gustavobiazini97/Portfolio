<?php /* Cabeçalho comum a todas as páginas. Variáveis: $titulo, $appNome, $flash, $user (opcional) */ ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b1226">
<title><?= e($titulo === $appNome ? $appNome : $titulo . ' · ' . $appNome) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/app.css">
</head>
<body>

<?php if (isset($user)): ?>
  <!-- Barra de topo: só nas páginas com sessão iniciada -->
  <header class="topo">
    <a class="marca" href="<?= e(url('home')) ?>" aria-label="<?= e($appNome) ?>">
      <span class="marca-bolas" aria-hidden="true"><i class="bola tu"></i><i class="bola par"></i></span>
      <span class="marca-nome"><?= e($appNome) ?></span>
    </a>
    <form method="post" action="<?= e(url('auth', 'sair')) ?>">
      <?= csrf_campo() ?>
      <button class="btn-texto" type="submit">Sair</button>
    </form>
  </header>
<?php endif; ?>

<main class="conteudo">
<?php if ($flash): ?>
  <!-- Mensagem do pedido anterior (Post/Redirect/Get) -->
  <p class="flash flash-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['mensagem']) ?></p>
<?php endif; ?>
