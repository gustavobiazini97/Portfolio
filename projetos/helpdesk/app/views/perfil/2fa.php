<?php
// Configurar o 2FA: mostrar o segredo (link otpauth:// no telemóvel ou escrever à mão) e confirmar com um código.
/** @var string $segredo */
/** @var string $uri */
/** @var bool $obrigatorio */
?>
<div class="cab">
    <div>
        <h1>Verificação em dois passos</h1>
        <p class="sub"><?= $obrigatorio ? 'Obrigatória nas contas de administrador.' : 'Um código do telemóvel além da palavra-passe.' ?></p>
    </div>
</div>

<div class="perfil-grid">
    <section class="cartao">
        <h2>1. Adiciona a conta na app</h2>
        <p>Usa o Google Authenticator, Microsoft Authenticator, Aegis ou outra app TOTP.</p>
        <p><a class="botao botao-sec" href="<?= e($uri) ?>">Abrir na app autenticadora</a> <span class="ajuda">(no telemóvel)</span></p>
        <p>Ou escolhe “Introduzir chave” na app e escreve:</p>
        <p class="segredo" data-copiar><?= e($segredo) ?></p>
        <p class="ajuda">Tipo: baseado no tempo · 6 dígitos · 30 segundos.</p>
    </section>

    <form class="form cartao" method="post" action="<?= e(url('perfil', 'ativar2fa')) ?>">
        <?= csrf_campo() ?>
        <h2>2. Confirma com um código</h2>
        <div class="campo">
            <label for="codigo">Código de 6 dígitos</label>
            <input id="codigo" name="codigo" class="codigo-2fa" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required>
        </div>
        <button class="botao" type="submit">Ativar</button>
    </form>
</div>
