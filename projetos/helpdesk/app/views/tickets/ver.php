<?php
// Página do pedido: abertura, conversa + histórico, resposta e (para o TI) o painel lateral de ações.
/** @var array $t */
/** @var bool $eTi */
/** @var bool $eRequerente */
/** @var array $linha */
/** @var array $anexos */
/** @var array $sla */
/** @var array $equipa */
/** @var array $categorias */
$id = (int) $t['id'];
$fechado = $t['estado'] === 'fechado';
$aberto = in_array($t['estado'], ESTADOS_ABERTOS, true);

// Lista de anexos de um comentário (0 = da abertura)
$mostrarAnexos = function (int $grupo) use ($anexos): void {
    if (empty($anexos[$grupo])) {
        return;
    }
    echo '<ul class="anexos">';
    foreach ($anexos[$grupo] as $a) {
        echo '<li><a href="' . e(url('ticket', 'anexo', ['id' => $a['id']])) . '" target="_blank" rel="noopener">'
            . icone('clip') . '<span>' . e($a['nome_original']) . '</span><small>' . e(Anexo::tamanhoLegivel((int) $a['tamanho'])) . '</small></a></li>';
    }
    echo '</ul>';
};
?>
<div class="cab cab-ticket">
    <div>
        <p class="codigo-grande"><?= e(codigo_ticket($id)) ?></p>
        <h1><?= e($t['titulo']) ?></h1>
        <div class="chips">
            <?= chip_estado($t['estado'], $eTi) ?>
            <?= chip_prioridade((int) $t['prioridade']) ?>
            <span class="chip chip-neutro"><?= e($t['categoria_nome'] ?? 'Sem categoria') ?></span>
        </div>
    </div>
    <div class="cab-acoes">
        <a class="botao botao-sec" href="<?= e(url('ticket', 'lista')) ?>">Voltar</a>
    </div>
</div>

<div class="ticket-grid">
    <div class="ticket-principal">

        <!-- Abertura do pedido -->
        <article class="msg msg-abertura">
            <header class="msg-cab">
                <span class="avatar-peq"><?= e(mb_strtoupper(mb_substr($t['requerente_nome'], 0, 1))) ?></span>
                <b><?= e($t['requerente_nome']) ?></b>
                <span class="meta">abriu o pedido · <time datetime="<?= e($t['criado_em']) ?>" title="<?= e(data_pt($t['criado_em'])) ?>"><?= e(tempo_relativo($t['criado_em'])) ?></time></span>
            </header>
            <div class="msg-corpo"><?= texto_multilinha($t['descricao']) ?></div>
            <?php $mostrarAnexos(0); ?>
        </article>

        <!-- Conversa e histórico, por ordem de data -->
        <ol class="linha-tempo">
            <?php foreach ($linha as $item): $d = $item['dados']; ?>
                <?php if ($item['tipo'] === 'evento'): ?>
                    <li class="evento">
                        <span><?= e(Historico::frase($d)) ?></span>
                        <time datetime="<?= e($d['criado_em']) ?>" title="<?= e(data_pt($d['criado_em'])) ?>"><?= e(tempo_relativo($d['criado_em'])) ?></time>
                    </li>
                <?php else:
                    $doTi = in_array($d['papel'], ['tecnico', 'admin'], true) && (int) $d['autor_id'] !== (int) $t['requerente_id'];
                    $classe = $d['interno'] ? 'msg-interna' : ($doTi ? 'msg-ti' : 'msg-requerente');
                ?>
                    <li class="msg <?= $classe ?>">
                        <header class="msg-cab">
                            <span class="avatar-peq"><?= e(mb_strtoupper(mb_substr($d['nome'], 0, 1))) ?></span>
                            <b><?= e($d['nome']) ?></b>
                            <?php if ($doTi): ?><span class="etiqueta">TI</span><?php endif; ?>
                            <?php if ($d['interno']): ?><span class="etiqueta etiqueta-interna"><?= icone('cadeado') ?> Nota interna</span><?php endif; ?>
                            <time class="meta" datetime="<?= e($d['criado_em']) ?>" title="<?= e(data_pt($d['criado_em'])) ?>"><?= e(tempo_relativo($d['criado_em'])) ?></time>
                        </header>
                        <div class="msg-corpo"><?= texto_multilinha($d['corpo']) ?></div>
                        <?php $mostrarAnexos((int) $d['id']); ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>

        <!-- Resolvido: o funcionário confirma e avalia (ou responde e o pedido reabre) -->
        <?php if ($t['estado'] === 'resolvido' && $eRequerente): ?>
            <section class="cartao confirmar">
                <h2>Ficou resolvido?</h2>
                <form method="post" action="<?= e(url('ticket', 'confirmar')) ?>" class="form">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <fieldset class="campo">
                        <legend>Como avalias o atendimento?</legend>
                        <div class="estrelas">
                            <?php foreach ([1 => 'Mau', 2 => 'Fraco', 3 => 'Razoável', 4 => 'Bom', 5 => 'Excelente'] as $n => $txt): ?>
                                <label class="estrela"><input type="radio" name="nota" value="<?= $n ?>" required<?= $n === 5 ? ' checked' : '' ?>><span><b><?= $n ?></b> <?= $txt ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="campo">
                        <label for="comentario-aval">Comentário <span class="opcional">(opcional)</span></label>
                        <input id="comentario-aval" name="comentario" maxlength="500">
                    </div>
                    <button class="botao" type="submit"><?= icone('visto') ?> Sim, fechar o pedido</button>
                </form>
                <p class="ajuda">Não ficou? Responde em baixo a explicar e o pedido volta a ser tratado.</p>
            </section>
        <?php endif; ?>

        <?php if ($fechado && $t['avaliacao']): ?>
            <p class="nota-final">Avaliação: <b><?= (int) $t['avaliacao'] ?>/5</b><?= $t['avaliacao_comentario'] ? ' — “' . e($t['avaliacao_comentario']) . '”' : '' ?></p>
        <?php endif; ?>

        <!-- Responder -->
        <?php if ($fechado && !$eTi): ?>
            <p class="aviso-caixa">Este pedido está fechado. Se o problema voltou, <a href="<?= e(url('ticket', 'novo')) ?>">abre um pedido novo</a>.</p>
        <?php else: ?>
            <form id="responder" class="cartao form form-resposta" method="post" action="<?= e(url('ticket', 'responder')) ?>" enctype="multipart/form-data">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="campo">
                    <label for="corpo"><?= $eTi ? 'Resposta' : 'Responder' ?></label>
                    <textarea id="corpo" name="corpo" rows="4" maxlength="5000" placeholder="<?= $eTi ? 'Escreve ao utilizador… (ou marca como nota interna)' : 'Escreve aqui…' ?>"><?= e(antigo('corpo')) ?></textarea>
                </div>
                <?php if ($eTi): ?>
                    <div class="linha-campos">
                        <label class="interruptor">
                            <input type="checkbox" name="interno" value="1" data-nota-interna>
                            <span><?= icone('cadeado') ?> Nota interna (o utilizador não vê)</span>
                        </label>
                        <label class="campo campo-inline">
                            <span>Mudar estado para</span>
                            <select name="estado">
                                <option value="">— manter (<?= e(rotulo_estado($t['estado'], true)) ?>) —</option>
                                <?php foreach (['em_curso', 'aguarda_utilizador', 'aguarda_terceiros', 'resolvido', 'fechado'] as $es): ?>
                                    <?php if ($es !== $t['estado']): ?><option value="<?= $es ?>"><?= e(rotulo_estado($es, true)) ?></option><?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                <?php endif; ?>
                <div class="linha-campos">
                    <label class="anexar">
                        <?= icone('clip') ?> <span data-anexos-rotulo>Anexar ficheiros</span>
                        <input type="file" name="anexos[]" multiple data-max="<?= (int) config('anexos.max_por_envio', 3) ?>"
                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.csv,.docx,.xlsx,.zip">
                    </label>
                    <button class="botao" type="submit">Enviar</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Lateral: prazos, detalhes e ações do TI -->
    <aside class="lateral">
        <section class="cartao">
            <h2>Prazos</h2>
            <dl class="detalhes">
                <dt>1.ª resposta</dt>
                <dd><?= chip_sla($sla['resposta']) ?><small>até <?= e(data_pt($t['prazo_resposta'])) ?></small></dd>
                <dt>Resolução</dt>
                <dd><?= chip_sla($sla['resolucao']) ?><small><?= $t['sla_pausado_em'] ? 'parado desde ' . e(data_pt($t['sla_pausado_em'])) : 'até ' . e(data_pt($t['prazo_resolucao'])) ?></small></dd>
            </dl>
            <p class="ajuda">Contam só no horário do TI (<?= e((string) config('horario.inicio')) ?>–<?= e((string) config('horario.fim')) ?>, dias úteis). Param enquanto o pedido está à espera de resposta.</p>
        </section>

        <section class="cartao">
            <h2>Detalhes</h2>
            <dl class="detalhes">
                <dt>Pedido por</dt>
                <dd><?= e($t['requerente_nome']) ?><?php if ($eTi): ?><small><?= e($t['requerente_departamento'] ?? '') ?> <?= e($t['requerente_email']) ?></small><?php endif; ?></dd>
                <dt>Técnico</dt>
                <dd><?= $t['tecnico_nome'] ? e($t['tecnico_nome']) : '<span class="meta">Ainda ninguém</span>' ?></dd>
                <dt>Afeta</dt>
                <dd><?= e(IMPACTOS[(int) $t['impacto']] ?? '') ?></dd>
                <dt>Urgência</dt>
                <dd><?= e(URGENCIAS[(int) $t['urgencia']] ?? '') ?></dd>
                <dt>Aberto</dt>
                <dd><?= e(data_pt($t['criado_em'])) ?></dd>
                <?php if ($t['resolvido_em']): ?><dt>Resolvido</dt><dd><?= e(data_pt($t['resolvido_em'])) ?></dd><?php endif; ?>
            </dl>
        </section>

        <?php if ($eTi): ?>
            <section class="cartao acoes-ti">
                <h2>Ações</h2>

                <?php if ((int) $t['tecnico_id'] !== (int) $eu['id'] && $aberto): ?>
                    <form method="post" action="<?= e(url('ticket', 'ficar')) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="botao botao-largo" type="submit">Fico eu com este</button>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= e(url('ticket', 'atribuir')) ?>" class="form-mini">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label for="tecnico_id">Atribuir a</label>
                    <div class="junto">
                        <select id="tecnico_id" name="tecnico_id">
                            <option value="0">Ninguém (fila)</option>
                            <?php foreach ($equipa as $tec): ?>
                                <option value="<?= (int) $tec['id'] ?>"<?= (int) $t['tecnico_id'] === (int) $tec['id'] ? ' selected' : '' ?>><?= e($tec['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="botao botao-sec" type="submit">OK</button>
                    </div>
                </form>

                <form method="post" action="<?= e(url('ticket', 'prioridade')) ?>" class="form-mini">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label for="prioridade">Prioridade</label>
                    <select id="prioridade" name="prioridade">
                        <?php foreach (PRIORIDADES as $k => $nome): ?>
                            <option value="<?= $k ?>"<?= (int) $t['prioridade'] === $k ? ' selected' : '' ?>>P<?= $k ?> <?= e($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="junto">
                        <input name="motivo" maxlength="120" placeholder="Motivo (fica no histórico)" aria-label="Motivo da mudança de prioridade">
                        <button class="botao botao-sec" type="submit">OK</button>
                    </div>
                </form>

                <form method="post" action="<?= e(url('ticket', 'categoria')) ?>" class="form-mini">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label for="categoria_id">Categoria</label>
                    <div class="junto">
                        <select id="categoria_id" name="categoria_id">
                            <option value="0">Sem categoria</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= (int) $t['categoria_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="botao botao-sec" type="submit">OK</button>
                    </div>
                </form>

                <?php if (in_array($t['estado'], ['resolvido', 'fechado'], true)): ?>
                    <a class="botao botao-sec botao-largo" href="<?= e(url('faq', 'novo', ['ticket' => $id])) ?>"><?= icone('faq') ?> Transformar em artigo da FAQ</a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </aside>
</div>
