<?php
// Pedidos: lista com filtros, abrir pedido, conversa, ações do TI e anexos.
declare(strict_types=1);

final class TicketController extends Controller
{
    // GET: lista (o funcionário vê os seus; o TI vê tudo, com filtros de fila)
    public function lista(): void
    {
        $u = $this->exigirLogin();
        $filtros = [
            'estado' => get('estado', 'abertos'),
            'prioridade' => get('prioridade'),
            'categoria' => get('categoria'),
            'tecnico' => get('tecnico'),
            'sla' => get('sla'),
            'q' => get('q'),
        ];
        $resultado = Ticket::listar($filtros, $u, max(1, inteiro(get('p', '1'))));
        $this->view('tickets/lista', [
            'filtros' => $filtros,
            'resultado' => $resultado,
            'categorias' => Categoria::ativas(),
            'equipa' => self::eTecnico() ? Utilizador::equipaTi() : [],
        ], 'Pedidos');
    }

    // GET: formulário de novo pedido (com sugestões da FAQ enquanto se escreve o título)
    public function novo(): void
    {
        $this->exigirLogin();
        $this->view('tickets/novo', ['categorias' => Categoria::ativas()], 'Novo pedido');
    }

    // POST: cria o pedido
    public function criar(): void
    {
        $u = $this->exigirLogin();
        $campos = ['titulo', 'descricao', 'categoria_id', 'impacto', 'urgencia'];

        // Máximo 10 pedidos por hora por pessoa (contra abusos ou scripts)
        if (Limite::excedido('ticket:' . $u['id'], 10, 60)) {
            $this->guardarAntigo($campos);
            flash('erro', 'Abriste muitos pedidos na última hora. Espera um pouco ou fala diretamente com o TI.');
            redirecionar('ticket', 'novo');
        }

        $titulo = post('titulo');
        $descricao = post('descricao');
        $categoriaId = inteiro(post('categoria_id'));
        $impacto = inteiro(post('impacto'));
        $urgencia = inteiro(post('urgencia'));
        $anexos = Upload::lista('anexos');

        $erros = [];
        if (mb_strlen($titulo) < 5 || mb_strlen($titulo) > 150) {
            $erros[] = 'O título precisa de 5 a 150 caracteres.';
        }
        if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 5000) {
            $erros[] = 'Descreve o problema com pelo menos 10 caracteres (máximo 5000).';
        }
        if ($categoriaId) {
            $cat = Categoria::porId($categoriaId);
            if (!$cat || (int) $cat['ativa'] !== 1) {
                $erros[] = 'Categoria inválida.';
            }
        }
        if (!isset(IMPACTOS[$impacto])) {
            $erros[] = 'Diz-nos a quem afeta o problema.';
        }
        if (!isset(URGENCIAS[$urgencia])) {
            $erros[] = 'Diz-nos quanto o problema te atrapalha.';
        }
        $erros = array_merge($erros, Upload::validar($anexos));

        if ($erros) {
            $this->guardarAntigo($campos);
            foreach ($erros as $erro) {
                flash('erro', $erro);
            }
            redirecionar('ticket', 'novo');
        }

        $id = Ticket::criar((int) $u['id'], $titulo, $descricao, $categoriaId ?: null, $impacto, $urgencia);
        Anexo::guardarTodos($anexos, $id, null, (int) $u['id']);
        Limite::registar('ticket:' . $u['id']);

        Notificador::ticketCriado(Ticket::porId($id));
        flash('ok', 'Pedido ' . codigo_ticket($id) . ' criado. Vais ser avisado sempre que houver novidades.');
        redirecionar('ticket', 'ver', ['id' => $id]);
    }

    // GET: página do pedido (conversa + histórico numa só linha do tempo)
    public function ver(): void
    {
        $u = $this->exigirLogin();
        $t = $this->ticketVisivel($u, inteiro(get('id')));
        $eTi = self::eTecnico();

        // Linha do tempo: comentários e eventos do histórico, por ordem de data
        $linha = [];
        foreach (Comentario::doTicket((int) $t['id'], $eTi) as $c) {
            $linha[] = ['tipo' => 'comentario', 'quando' => $c['criado_em'], 'ordem' => 1, 'dados' => $c];
        }
        foreach (Historico::doTicket((int) $t['id']) as $h) {
            if ($h['acao'] === 'criado') {
                continue;   // a abertura já é o cartão do topo
            }
            $linha[] = ['tipo' => 'evento', 'quando' => $h['criado_em'], 'ordem' => 0, 'dados' => $h];
        }
        usort($linha, fn ($a, $b) => [$a['quando'], $a['ordem']] <=> [$b['quando'], $b['ordem']]);

        Notificacao::marcarDoTicket((int) $u['id'], (int) $t['id']);

        $this->view('tickets/ver', [
            't' => $t,
            'eTi' => $eTi,
            'eRequerente' => (int) $t['requerente_id'] === (int) $u['id'],
            'linha' => $linha,
            'anexos' => Anexo::doTicketAgrupados((int) $t['id']),
            'sla' => Sla::estado($t),
            'equipa' => $eTi ? Utilizador::equipaTi() : [],
            'categorias' => $eTi ? Categoria::ativas() : [],
        ], codigo_ticket((int) $t['id']) . ' · ' . $t['titulo']);
    }

    // POST: resposta / nota interna / mudança de estado (técnico) e anexos
    public function responder(): void
    {
        $u = $this->exigirLogin();
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        $eTi = self::eTecnico();
        $eRequerente = (int) $t['requerente_id'] === (int) $u['id'];

        $corpo = post('corpo');
        $interno = $eTi && post('interno') === '1';
        $novoEstado = $eTi ? post('estado') : '';
        $anexos = Upload::lista('anexos');

        // Pedido fechado: só o TI pode deixar notas; o funcionário abre um pedido novo
        if ($t['estado'] === 'fechado' && !$eTi) {
            flash('erro', 'Este pedido já está fechado. Se o problema voltou, abre um pedido novo.');
            redirecionar('ticket', 'ver', ['id' => $t['id']]);
        }

        $erros = Upload::validar($anexos);
        if (mb_strlen($corpo) > 5000) {
            $erros[] = 'A mensagem é demasiado longa (máximo 5000 caracteres).';
        }
        if ($novoEstado !== '' && !in_array($novoEstado, ['em_curso', 'aguarda_utilizador', 'aguarda_terceiros', 'resolvido', 'fechado'], true)) {
            $erros[] = 'Estado inválido.';
        }
        if ($corpo === '' && $anexos) {
            $corpo = 'Enviou ' . plural(count($anexos), 'anexo', 'anexos') . '.';
        }
        if ($corpo === '' && $novoEstado === '') {
            $erros[] = 'Escreve uma mensagem.';
        }
        // Pôr "à espera do utilizador" ou "resolvido" sem explicar nada não ajuda ninguém
        if ($corpo === '' && in_array($novoEstado, ['aguarda_utilizador', 'resolvido'], true)) {
            $erros[] = 'Ao mudar para "' . rotulo_estado($novoEstado, true) . '", escreve uma mensagem para o utilizador.';
        }
        if ($interno && in_array($novoEstado, ['aguarda_utilizador', 'resolvido'], true)) {
            $erros[] = 'Uma nota interna não é vista pelo utilizador: para lhe pedir algo ou dar como resolvido, usa uma resposta pública.';
        }
        if ($erros) {
            $_SESSION['_antigo'] = ['corpo' => $corpo];
            foreach ($erros as $erro) {
                flash('erro', $erro);
            }
            redirecionar('ticket', 'ver', ['id' => $t['id']]);
        }

        $estadoInicial = $t['estado'];
        $estadoMudou = false;

        if ($corpo !== '') {
            $cid = Comentario::criar((int) $t['id'], (int) $u['id'], $corpo, $interno);
            Anexo::guardarTodos($anexos, (int) $t['id'], $cid, (int) $u['id']);
            Ticket::tocar((int) $t['id']);

            if ($eTi && !$interno && !$eRequerente) {
                // 1.ª resposta pública do TI: para o relógio da resposta e, sem técnico, fica com quem respondeu
                Ticket::registarPrimeiraResposta($t);
                if (!$t['tecnico_id']) {
                    Ticket::atribuir($t, (int) $u['id'], (int) $u['id']);
                }
                if ($t['estado'] === 'novo' && $novoEstado === '') {
                    $novoEstado = 'em_curso';
                }
            }
            if ($eRequerente && !$eTi) {
                // O funcionário respondeu: se o TI estava à espera dele, ou se estava "resolvido", volta a "em curso"
                if (in_array($t['estado'], ['aguarda_utilizador', 'resolvido'], true)) {
                    $novoEstado = 'em_curso';
                }
            }
        }

        if ($novoEstado !== '' && $novoEstado !== $estadoInicial) {
            Ticket::mudarEstado(Ticket::porId((int) $t['id']), $novoEstado, (int) $u['id']);
            $estadoMudou = true;
        }

        // Avisos: estado + resposta num só aviso para o funcionário; resto como comentário
        $atual = Ticket::porId((int) $t['id']);
        if ($estadoMudou && $eTi && !$eRequerente && !$interno) {
            Notificador::estado($atual, $novoEstado, (int) $u['id'], $corpo);
        } else {
            if ($corpo !== '') {
                Notificador::comentario($atual, $u, $corpo, $interno);
            }
            if ($estadoMudou && $eTi && !$eRequerente) {
                Notificador::estado($atual, $novoEstado, (int) $u['id']);
            }
        }

        flash('ok', $interno ? 'Nota interna guardada.' : ($estadoMudou && !$eTi && $estadoInicial === 'resolvido' ? 'Resposta enviada. O pedido foi reaberto.' : 'Resposta enviada.'));
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // POST (TI): atribuir a um técnico (0 = devolver à fila)
    public function atribuir(): void
    {
        $u = $this->exigirPapel('tecnico', 'admin');
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        $tecnicoId = inteiro(post('tecnico_id'));
        if ($tecnicoId && !in_array($tecnicoId, array_map('intval', array_column(Utilizador::equipaTi(), 'id')), true)) {
            flash('erro', 'Só podes atribuir a técnicos ativos.');
            redirecionar('ticket', 'ver', ['id' => $t['id']]);
        }
        Ticket::atribuir($t, $tecnicoId ?: null, (int) $u['id']);
        if ($tecnicoId && $tecnicoId !== (int) $t['tecnico_id']) {
            Notificador::atribuido(Ticket::porId((int) $t['id']), $tecnicoId, (int) $u['id']);
        }
        flash('ok', $tecnicoId ? 'Pedido atribuído.' : 'Pedido devolvido à fila.');
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // POST (TI): "Fico eu com este" — atribui a si e passa a "em curso"
    public function ficar(): void
    {
        $u = $this->exigirPapel('tecnico', 'admin');
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        Ticket::atribuir($t, (int) $u['id'], (int) $u['id']);
        if ($t['estado'] === 'novo') {
            Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'em_curso', (int) $u['id']);
            Notificador::estado(Ticket::porId((int) $t['id']), 'em_curso', (int) $u['id']);
        }
        flash('ok', 'O pedido é teu.');
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // POST (TI): corrigir a prioridade, com motivo (fica no histórico; os prazos são recalculados)
    public function prioridade(): void
    {
        $u = $this->exigirPapel('tecnico', 'admin');
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        $p = inteiro(post('prioridade'));
        $motivo = mb_substr(post('motivo'), 0, 120);
        if (!isset(PRIORIDADES[$p]) || mb_strlen($motivo) < 3) {
            flash('erro', 'Escolhe a prioridade e explica o motivo (fica no histórico).');
            redirecionar('ticket', 'ver', ['id' => $t['id']]);
        }
        Ticket::mudarPrioridade($t, $p, (int) $u['id'], $motivo);
        flash('ok', 'Prioridade alterada; os prazos foram recalculados.');
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // POST (TI): mudar a categoria
    public function categoria(): void
    {
        $u = $this->exigirPapel('tecnico', 'admin');
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        $cid = inteiro(post('categoria_id'));
        if ($cid && !Categoria::porId($cid)) {
            $this->erro(404);
        }
        Ticket::mudarCategoria($t, $cid ?: null, (int) $u['id']);
        flash('ok', 'Categoria alterada.');
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // POST (funcionário): "Sim, ficou resolvido" + avaliação → fecha
    public function confirmar(): void
    {
        $u = $this->exigirLogin();
        $t = $this->ticketVisivel($u, inteiro(post('id')));
        if ((int) $t['requerente_id'] !== (int) $u['id'] || $t['estado'] !== 'resolvido') {
            $this->proibido('confirmar pedido ' . $t['id']);
        }
        $nota = inteiro(post('nota'));
        if ($nota < 1 || $nota > 5) {
            flash('erro', 'Escolhe uma avaliação de 1 a 5.');
            redirecionar('ticket', 'ver', ['id' => $t['id']]);
        }
        Ticket::avaliar((int) $t['id'], $nota, post('comentario'));
        Ticket::mudarEstado($t, 'fechado', (int) $u['id']);
        Historico::registar((int) $t['id'], (int) $u['id'], 'avaliado', null, $nota . '/5');
        Notificador::avaliado($t, $nota, (int) $u['id']);
        flash('ok', 'Obrigado! Pedido fechado.');
        redirecionar('ticket', 'ver', ['id' => $t['id']]);
    }

    // GET: descarregar/ver um anexo (só quem pode ver o pedido; anexos de notas internas só o TI)
    public function anexo(): void
    {
        $u = $this->exigirLogin();
        $a = Anexo::porId(inteiro(get('id')));
        if (!$a) {
            $this->erro(404);
        }
        $this->ticketVisivel($u, (int) $a['ticket_id']);
        if ($a['comentario_id'] && !self::eTecnico()) {
            $interno = (int) Database::valor('SELECT interno FROM comentarios WHERE id = ?', [$a['comentario_id']]);
            if ($interno === 1) {
                $this->erro(404);
            }
        }
        $caminho = Upload::pasta() . '/' . basename($a['nome_disco']);
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,4}$/', $a['nome_disco']) || !is_file($caminho)) {
            $this->erro(404);
        }

        // Imagens e PDF abrem no browser; o resto descarrega. Nada é executado (sandbox + nosniff).
        $inline = in_array($a['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
        header('Content-Type: ' . ($inline ? $a['mime'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($caminho));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="anexo"; filename*=UTF-8\'\'' . rawurlencode($a['nome_original']));
        // O leitor de PDF do browser não abre dentro de "sandbox"; para as imagens e o resto, sandbox total
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'"
            . ($a['mime'] === 'application/pdf' ? '' : '; sandbox'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($caminho);
        exit;
    }

    // ─── Internos ──────────────────────────────────────────────────────────

    // Pedido existente E visível para este utilizador; senão 404 (não revela que o pedido existe)
    private function ticketVisivel(array $u, int $id): array
    {
        $t = $id ? Ticket::porId($id) : null;
        if (!$t) {
            $this->erro(404);
        }
        if (!Ticket::podeVer($t, $u)) {
            Auditoria::registar('acesso_negado', 'pedido ' . $id);
            $this->erro(404);
        }
        return $t;
    }
}
