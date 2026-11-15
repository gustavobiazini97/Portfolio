<?php
// Administração: contas, categorias, prazos do SLA, estatísticas e auditoria. Só admins.
declare(strict_types=1);

final class AdminController extends Controller
{
    // ─── Contas ────────────────────────────────────────────────────────────

    public function utilizadores(): void
    {
        $this->exigirPapel('admin');
        $q = get('q');
        $this->view('admin/utilizadores', ['lista' => Utilizador::todos($q), 'q' => $q], 'Contas');
    }

    // GET: formulário (id 0 = nova conta)
    public function utilizador(): void
    {
        $this->exigirPapel('admin');
        $id = inteiro(get('id'));
        $u = $id ? Utilizador::porId($id) : ['id' => 0, 'nome' => '', 'email' => '', 'papel' => 'utilizador', 'departamento' => '', 'ativo' => 1, 'totp_ativo' => 0];
        if (!$u) {
            $this->erro(404);
        }
        $this->view('admin/utilizador', ['u' => $u], $id ? 'Editar conta' : 'Nova conta');
    }

    // POST: cria ou atualiza uma conta
    public function guardarUtilizador(): void
    {
        $eu = $this->exigirPapel('admin');
        $id = inteiro(post('id'));
        $antes = $id ? Utilizador::porId($id) : null;
        if ($id && !$antes) {
            $this->erro(404);
        }
        $d = [
            'nome' => post('nome'),
            'email' => mb_strtolower(post('email')),
            'papel' => post('papel'),
            'departamento' => mb_substr(post('departamento'), 0, 80),
            'ativo' => post('ativo') === '1' ? 1 : 0,
        ];

        $erros = [];
        if (mb_strlen($d['nome']) < 2 || mb_strlen($d['nome']) > 100) {
            $erros[] = 'O nome precisa de 2 a 100 caracteres.';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 190) {
            $erros[] = 'Email inválido.';
        } else {
            $outro = Utilizador::porEmail($d['email']);
            if ($outro && (int) $outro['id'] !== $id) {
                $erros[] = 'Já existe uma conta com esse email.';
            }
        }
        if (!isset(PAPEIS[$d['papel']])) {
            $erros[] = 'Papel inválido.';
        }
        // Um admin não se pode tirar a si próprio de admin nem desativar-se (evita ficar sem acesso)
        if ($id === (int) $eu['id'] && ($d['papel'] !== 'admin' || !$d['ativo'])) {
            $erros[] = 'Não podes tirar-te de administrador nem desativar a tua própria conta.';
        }
        if ($erros) {
            foreach ($erros as $e) {
                flash('erro', $e);
            }
            $this->view('admin/utilizador', ['u' => ['id' => $id, 'totp_ativo' => $antes['totp_ativo'] ?? 0] + $d], $id ? 'Editar conta' : 'Nova conta');
        }

        if ($id) {
            Utilizador::atualizar($id, $d);
            // Mudanças de permissões ficam na auditoria
            if ($antes['papel'] !== $d['papel']) {
                Auditoria::registar('papel_alterado', "{$antes['email']}: {$antes['papel']} → {$d['papel']}");
            }
            if ((int) $antes['ativo'] !== $d['ativo']) {
                Auditoria::registar($d['ativo'] ? 'conta_ativada' : 'conta_desativada', $antes['email']);
            }
            flash('ok', 'Conta guardada.');
            redirecionar('admin', 'utilizadores');
        }

        $temporaria = $this->passwordTemporaria();
        $novoId = Utilizador::criar($d, $temporaria);
        Auditoria::registar('conta_criada', "{$d['email']} ({$d['papel']})");
        flash('ok', "Conta criada. Palavra-passe temporária: $temporaria — entrega-a à pessoa; vai ter de a trocar no primeiro login.");
        redirecionar('admin', 'utilizador', ['id' => $novoId]);
    }

    // POST: tirar o 2FA a quem perdeu o telemóvel (volta a configurar no próximo login, se for admin)
    public function repor2fa(): void
    {
        $this->exigirPapel('admin');
        $u = Utilizador::porId(inteiro(post('id')));
        if (!$u) {
            $this->erro(404);
        }
        Utilizador::desativar2fa((int) $u['id']);
        Auditoria::registar('2fa_reposto', $u['email']);
        flash('ok', "2FA removido de {$u['nome']}.");
        redirecionar('admin', 'utilizador', ['id' => $u['id']]);
    }

    // POST: palavra-passe temporária nova (obriga a trocar no próximo login)
    public function reporPassword(): void
    {
        $this->exigirPapel('admin');
        $u = Utilizador::porId(inteiro(post('id')));
        if (!$u) {
            $this->erro(404);
        }
        $temporaria = $this->passwordTemporaria();
        Utilizador::definirPassword((int) $u['id'], $temporaria, true);
        Limite::limpar('login:' . $u['email']);
        Auditoria::registar('password_reposta', $u['email']);
        flash('ok', "Palavra-passe temporária de {$u['nome']}: $temporaria");
        redirecionar('admin', 'utilizador', ['id' => $u['id']]);
    }

    // ─── Categorias ────────────────────────────────────────────────────────

    public function categorias(): void
    {
        $this->exigirPapel('admin');
        $this->view('admin/categorias', [
            'lista' => Categoria::todasComDados(),
            'equipa' => Utilizador::equipaTi(),
        ], 'Categorias');
    }

    public function guardarCategoria(): void
    {
        $this->exigirPapel('admin');
        $id = inteiro(post('id'));
        $nome = post('nome');
        $tecnicoId = inteiro(post('tecnico_id')) ?: null;
        if ($id && !Categoria::porId($id)) {
            $this->erro(404);
        }
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            flash('erro', 'O nome da categoria precisa de 2 a 80 caracteres.');
            redirecionar('admin', 'categorias');
        }
        if ($tecnicoId && !in_array($tecnicoId, array_map('intval', array_column(Utilizador::equipaTi(), 'id')), true)) {
            flash('erro', 'O técnico por defeito tem de ser um técnico ativo.');
            redirecionar('admin', 'categorias');
        }
        Categoria::guardar($id, $nome, mb_substr(post('descricao'), 0, 255), $tecnicoId, post('ativa') === '1' || !$id);
        flash('ok', 'Categoria guardada.');
        redirecionar('admin', 'categorias');
    }

    // ─── SLA ───────────────────────────────────────────────────────────────

    public function sla(): void
    {
        $this->exigirPapel('admin');
        $this->view('admin/sla', ['politicas' => SlaPolitica::todas()], 'Prazos (SLA)');
    }

    public function guardarSla(): void
    {
        $this->exigirPapel('admin');
        foreach (array_keys(PRIORIDADES) as $p) {
            $resp = inteiro(post("resposta_$p"));
            $res = inteiro(post("resolucao_$p"));
            // Entre 5 minutos e 60 dias úteis; a resolução nunca antes da resposta
            if ($resp < 5 || $res < $resp || $res > 60 * 540) {
                flash('erro', "Prazos inválidos em P$p: a resolução tem de ser maior ou igual à resposta (mínimo 5 minutos).");
                redirecionar('admin', 'sla');
            }
            SlaPolitica::guardar($p, $resp, $res);
        }
        Auditoria::registar('sla_alterado');
        flash('ok', 'Prazos guardados. Aplicam-se aos pedidos novos e às mudanças de prioridade.');
        redirecionar('admin', 'sla');
    }

    // ─── Estatísticas e auditoria ──────────────────────────────────────────

    public function estatisticas(): void
    {
        $this->exigirPapel('admin');
        $dias = in_array(inteiro(get('dias')), [7, 30, 90, 365], true) ? inteiro(get('dias')) : 30;
        $this->view('admin/estatisticas', ['dias' => $dias, 'e' => Estatisticas::periodo($dias)], 'Estatísticas');
    }

    public function auditoria(): void
    {
        $this->exigirPapel('admin');
        $acao = get('acao');
        $this->view('admin/auditoria', [
            'lista' => Auditoria::ultimos(300, $acao),
            'acoes' => Auditoria::acoes(),
            'acao' => $acao,
        ], 'Auditoria');
    }

    // "k7mp-x3qa-9tdw": fácil de ditar (sem 0/O, 1/l/i)
    private function passwordTemporaria(): string
    {
        $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';
        $grupos = [];
        for ($g = 0; $g < 3; $g++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $grupos[] = $s;
        }
        return implode('-', $grupos);
    }
}
