<?php
// Criar/editar conta. Contas novas recebem uma palavra-passe temporária (mostrada uma vez ao admin).
/** @var array $u */
$id = (int) $u['id'];
?>
<div class="cab">
    <div>
        <h1><?= $id ? e($u['nome']) : 'Nova conta' ?></h1>
        <p class="sub"><?= $id ? e($u['email']) : 'A pessoa recebe uma palavra-passe temporária e escolhe a sua no primeiro login.' ?></p>
    </div>
</div>
<?php require RAIZ . '/app/views/admin/_tabs.php'; ?>

<div class="perfil-grid">
    <form class="form cartao" method="post" action="<?= e(url('admin', 'guardarUtilizador')) ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="campo">
            <label for="nome">Nome</label>
            <input id="nome" name="nome" required minlength="2" maxlength="100" value="<?= e($u['nome']) ?>">
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="190" value="<?= e($u['email']) ?>">
        </div>
        <div class="linha-campos">
            <div class="campo">
                <label for="papel">Papel</label>
                <select id="papel" name="papel">
                    <?php foreach (PAPEIS as $k => $nome): ?>
                        <option value="<?= e($k) ?>"<?= $u['papel'] === $k ? ' selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="departamento">Departamento</label>
                <input id="departamento" name="departamento" maxlength="80" value="<?= e($u['departamento'] ?? '') ?>" placeholder="Armazém, Logística, Faturação…">
            </div>
        </div>
        <?php if ($id): ?>
            <label class="interruptor">
                <input type="checkbox" name="ativo" value="1"<?= (int) $u['ativo'] ? ' checked' : '' ?>>
                <span>Conta ativa (desativar corta o acesso na hora, mas mantém o histórico)</span>
            </label>
        <?php else: ?>
            <input type="hidden" name="ativo" value="1">
        <?php endif; ?>
        <div class="form-acoes">
            <a class="botao botao-sec" href="<?= e(url('admin', 'utilizadores')) ?>">Voltar</a>
            <button class="botao" type="submit"><?= $id ? 'Guardar' : 'Criar conta' ?></button>
        </div>
    </form>

    <?php if ($id): ?>
        <section class="cartao">
            <h2>Acesso</h2>
            <form method="post" action="<?= e(url('admin', 'reporPassword')) ?>" data-confirmar="Gerar uma palavra-passe temporária nova? A atual deixa de funcionar.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="botao botao-sec botao-largo" type="submit"><?= icone('cadeado') ?> Repor palavra-passe</button>
            </form>
            <?php if ((int) $u['totp_ativo']): ?>
                <form method="post" action="<?= e(url('admin', 'repor2fa')) ?>" data-confirmar="Remover o 2FA desta conta (ex.: perdeu o telemóvel)?">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="botao botao-sec botao-largo" type="submit">Remover 2FA</button>
                </form>
            <?php endif; ?>
            <p class="ajuda">Tudo fica registado na auditoria.</p>
        </section>
    <?php endif; ?>
</div>
