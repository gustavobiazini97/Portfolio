<?php
// Segundo passo do login: código de 6 dígitos da app autenticadora.
?>
<section class="cartao auth-cartao">
    <h1>Verificação em dois passos</h1>
    <p class="sub">Abre a app autenticadora no telemóvel e escreve o código de 6 dígitos.</p>

    <form method="post" action="<?= e(url('auth', 'verificarCodigo')) ?>" class="form">
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="codigo">Código</label>
            <input id="codigo" name="codigo" class="codigo-2fa" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
        </div>
        <button class="botao botao-largo" type="submit">Confirmar</button>
    </form>
    <p class="auth-link"><a href="<?= e(url('auth', 'login')) ?>">Voltar ao login</a></p>
</section>
