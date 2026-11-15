<?php
// Palavra-passe nova a partir do link do email (o token vai escondido no formulário).
/** @var string $token */
?>
<section class="cartao auth-cartao">
    <h1>Nova palavra-passe</h1>
    <p class="sub">Pelo menos 10 caracteres. Uma frase curta é mais fácil de lembrar e mais difícil de adivinhar.</p>

    <form method="post" action="<?= e(url('auth', 'guardarRedefinicao')) ?>" class="form">
        <?= csrf_campo() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="campo">
            <label for="password">Nova palavra-passe</label>
            <input id="password" name="password" type="password" required minlength="10" maxlength="72" autocomplete="new-password" autofocus>
        </div>
        <div class="campo">
            <label for="confirmacao">Repetir</label>
            <input id="confirmacao" name="confirmacao" type="password" required minlength="10" maxlength="72" autocomplete="new-password">
        </div>
        <button class="botao botao-largo" type="submit">Guardar</button>
    </form>
</section>
