<?php
// Página de erro genérica (403, 404, 405, 419, 500). Nunca mostra detalhes internos.
// Funciona com ou sem layout: se o erro acontecer cedo, desenha uma página mínima.
$codigo = $codigo ?? http_response_code() ?: 500;
$textos = [
    403 => ['Sem acesso', 'Não tens permissão para ver esta página.'],
    404 => ['Não encontrado', 'Esta página ou pedido não existe (ou não é teu).'],
    419 => ['A página expirou', 'O formulário ficou aberto demasiado tempo. Volta atrás, atualiza e tenta outra vez.'],
    500 => ['Algo correu mal', 'Houve um erro do nosso lado. Já ficou registado; tenta outra vez daqui a pouco.'],
];
[$titulo, $texto] = $textos[$codigo] ?? $textos[500];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="css/app.css">
    <script src="js/tema.js"></script>
</head>
<body class="auth">
<main class="auth-caixa erro-pagina">
    <p class="erro-codigo"><?= (int) $codigo ?></p>
    <h1><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') ?></p>
    <a class="botao" href="index.php">Voltar ao início</a>
</main>
</body>
</html>
