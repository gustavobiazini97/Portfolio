<?php
// Login: email + palavra-passe. O campo "site" é um honeypot (escondido; só bots o preenchem).
?>
<section class="cartao auth-cartao">
    <h1>Entrar</h1>
    <p class="sub">Usa o email da empresa.</p>

    <form method="post" action="<?= e(url('auth', 'entrar')) ?>" class="form" autocomplete="on">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e(antigo('email')) ?>" autofocus>
        </div>
        <div class="campo">
            <label for="password">Palavra-passe</label>
            <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">
        </div>
        <!-- Honeypot: fora do ecrã e fora do Tab; uma pessoa nunca o preenche -->
        <div class="pote" aria-hidden="true">
            <label for="site">Não preencher</label>
            <input id="site" name="site" type="text" tabindex="-1" autocomplete="off">
        </div>
        <button class="botao botao-largo" type="submit">Entrar</button>
    </form>
    <p class="auth-link"><a href="<?= e(url('auth', 'recuperar')) ?>">Esqueci-me da palavra-passe</a></p>
</section>
