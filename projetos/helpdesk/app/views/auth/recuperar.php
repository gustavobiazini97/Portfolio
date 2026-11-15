<?php
// Pedir o link de recuperação da palavra-passe.
?>
<section class="cartao auth-cartao">
    <h1>Recuperar palavra-passe</h1>
    <p class="sub">Escreve o teu email. Se tiver conta, enviamos um link para definires uma palavra-passe nova.</p>

    <form method="post" action="<?= e(url('auth', 'pedirLink')) ?>" class="form">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="username" autofocus>
        </div>
        <div class="pote" aria-hidden="true">
            <label for="site">Não preencher</label>
            <input id="site" name="site" type="text" tabindex="-1" autocomplete="off">
        </div>
        <button class="botao botao-largo" type="submit">Enviar link</button>
    </form>
    <p class="auth-link"><a href="<?= e(url('auth', 'login')) ?>">Voltar ao login</a></p>
</section>
