<?php
// Perfil: dados, notificações por email, palavra-passe e 2FA.
// Com palavra-passe temporária só aparece o formulário da palavra-passe.
/** @var array $u */
$temporaria = (int) $u['mudar_password'] === 1;
?>
<div class="cab">
    <div>
        <h1><?= $temporaria ? 'Define a tua palavra-passe' : 'O meu perfil' ?></h1>
        <p class="sub"><?= e($u['email']) ?> · <?= e(PAPEIS[$u['papel']]) ?><?= $u['departamento'] ? ' · ' . e($u['departamento']) : '' ?></p>
    </div>
</div>

<div class="perfil-grid">
    <?php if (!$temporaria): ?>
        <form class="form cartao" method="post" action="<?= e(url('perfil', 'guardar')) ?>">
            <?= csrf_campo() ?>
            <h2>Dados</h2>
            <div class="campo">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" required minlength="2" maxlength="100" value="<?= e($u['nome']) ?>">
            </div>
            <label class="interruptor">
                <input type="checkbox" name="notif_email" value="1"<?= (int) $u['notif_email'] ? ' checked' : '' ?>>
                <span>Receber as notificações também por email</span>
            </label>
            <div class="form-acoes"><button class="botao" type="submit">Guardar</button></div>
        </form>
    <?php endif; ?>

    <form class="form cartao" method="post" action="<?= e(url('perfil', 'password')) ?>">
        <?= csrf_campo() ?>
        <h2>Palavra-passe</h2>
        <?php if ($temporaria): ?>
            <p class="ajuda">Estás a usar uma palavra-passe temporária. Escolhe uma tua (mínimo 10 caracteres) para continuar.</p>
        <?php endif; ?>
        <div class="campo">
            <label for="atual"><?= $temporaria ? 'Palavra-passe temporária' : 'Palavra-passe atual' ?></label>
            <input id="atual" name="atual" type="password" required autocomplete="current-password">
        </div>
        <div class="campo">
            <label for="nova">Nova palavra-passe</label>
            <input id="nova" name="nova" type="password" required minlength="10" maxlength="72" autocomplete="new-password">
        </div>
        <div class="campo">
            <label for="confirmacao">Repetir</label>
            <input id="confirmacao" name="confirmacao" type="password" required minlength="10" maxlength="72" autocomplete="new-password">
        </div>
        <div class="form-acoes"><button class="botao" type="submit">Mudar palavra-passe</button></div>
    </form>

    <?php if (!$temporaria): ?>
        <section class="cartao">
            <h2>Verificação em dois passos</h2>
            <?php if ((int) $u['totp_ativo']): ?>
                <p><span class="chip estado-resolvido">Ativa</span> O login pede o código da app autenticadora.</p>
                <?php if ($u['papel'] !== 'admin'): ?>
                    <form class="form" method="post" action="<?= e(url('perfil', 'desativar2fa')) ?>">
                        <?= csrf_campo() ?>
                        <div class="campo">
                            <label for="pw2fa">Palavra-passe para desativar</label>
                            <input id="pw2fa" name="password" type="password" required autocomplete="current-password">
                        </div>
                        <button class="botao botao-perigo" type="submit">Desativar</button>
                    </form>
                <?php else: ?>
                    <p class="ajuda">Obrigatória para administradores. Se perderes o telemóvel, outro admin pode repor.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Protege a conta mesmo que alguém descubra a palavra-passe.</p>
                <a class="botao" href="<?= e(url('perfil', 'configurar2fa')) ?>"><?= icone('cadeado') ?> Ativar</a>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
