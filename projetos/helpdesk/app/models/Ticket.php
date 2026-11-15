<?php
// Pedidos (tickets): criação, filas, estados, atribuição, prioridade e relógio do SLA.
declare(strict_types=1);

final class Ticket
{
    // SELECT base com os nomes de quem abriu, do técnico e da categoria
    private const SELECT = 'SELECT t.*, r.nome AS requerente_nome, r.email AS requerente_email, r.departamento AS requerente_departamento,
                                   te.nome AS tecnico_nome, c.nome AS categoria_nome
                              FROM tickets t
                              JOIN utilizadores r ON r.id = t.requerente_id
                         LEFT JOIN utilizadores te ON te.id = t.tecnico_id
                         LEFT JOIN categorias c ON c.id = t.categoria_id';

    public static function porId(int $id): ?array
    {
        return Database::linha(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    // Controlo de acesso (contra IDOR): o TI vê tudo; o funcionário só os pedidos que abriu
    public static function podeVer(array $t, array $u): bool
    {
        return in_array($u['papel'], ['tecnico', 'admin'], true) || (int) $t['requerente_id'] === (int) $u['id'];
    }

    // ─── Criação ───────────────────────────────────────────────────────────

    // Abre um pedido: calcula a prioridade (matriz), os prazos do SLA e atribui ao técnico da categoria
    public static function criar(int $requerenteId, string $titulo, string $descricao, ?int $categoriaId, int $impacto, int $urgencia): int
    {
        $prioridade = Sla::prioridade($impacto, $urgencia);
        $pol = SlaPolitica::porPrioridade($prioridade) ?? ['resposta_min' => 240, 'resolucao_min' => 1620];
        $agora = agora();

        // Técnico por defeito da categoria (só se continuar ativo e no TI)
        $tecnicoId = null;
        if ($categoriaId) {
            $cat = Categoria::porId($categoriaId);
            if ($cat && $cat['tecnico_padrao_id']) {
                $tec = Utilizador::porId((int) $cat['tecnico_padrao_id']);
                if ($tec && (int) $tec['ativo'] === 1 && in_array($tec['papel'], ['tecnico', 'admin'], true)) {
                    $tecnicoId = (int) $tec['id'];
                }
            }
        }

        return Database::transacao(function () use ($requerenteId, $titulo, $descricao, $categoriaId, $impacto, $urgencia, $prioridade, $pol, $agora, $tecnicoId) {
            Database::q(
                'INSERT INTO tickets (titulo, descricao, categoria_id, requerente_id, tecnico_id, impacto, urgencia, prioridade,
                                      estado, prazo_resposta, prazo_resolucao, criado_em, atualizado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'novo\', ?, ?, ?, ?)',
                [
                    $titulo, $descricao, $categoriaId, $requerenteId, $tecnicoId, $impacto, $urgencia, $prioridade,
                    Sla::somar($agora, (int) $pol['resposta_min']),
                    Sla::somar($agora, (int) $pol['resolucao_min']),
                    $agora, $agora,
                ]
            );
            $id = Database::ultimoId();
            Historico::registar($id, $requerenteId, 'criado', null, 'P' . $prioridade . ' ' . PRIORIDADES[$prioridade]);
            if ($tecnicoId) {
                Historico::registar($id, null, 'atribuido', null, (string) Utilizador::porId($tecnicoId)['nome']);
            }
            return $id;
        });
    }

    // ─── Listas e contadores ──────────────────────────────────────────────

    // Lista filtrada e paginada. O funcionário só vê os seus; o TI vê a fila toda.
    public static function listar(array $f, array $u, int $pagina = 1, int $porPagina = 20): array
    {
        $onde = [];
        $p = [];
        $eTi = in_array($u['papel'], ['tecnico', 'admin'], true);

        if (!$eTi) {
            $onde[] = 't.requerente_id = ?';
            $p[] = $u['id'];
        }

        // Estado: "abertos" (por defeito), "todos" ou um estado concreto
        $estado = $f['estado'] ?? 'abertos';
        if ($estado === 'abertos') {
            $onde[] = "t.estado IN ('novo','em_curso','aguarda_utilizador','aguarda_terceiros')";
        } elseif (isset(ESTADOS[$estado])) {
            $onde[] = 't.estado = ?';
            $p[] = $estado;
        }

        if (!empty($f['prioridade']) && isset(PRIORIDADES[(int) $f['prioridade']])) {
            $onde[] = 't.prioridade = ?';
            $p[] = (int) $f['prioridade'];
        }
        if (!empty($f['categoria'])) {
            $onde[] = 't.categoria_id = ?';
            $p[] = (int) $f['categoria'];
        }

        // Filas do TI: os meus, sem técnico, de um técnico, em risco, ultrapassados
        if ($eTi) {
            $tecnico = (string) ($f['tecnico'] ?? '');
            if ($tecnico === 'meus') {
                $onde[] = 't.tecnico_id = ?';
                $p[] = $u['id'];
            } elseif ($tecnico === 'nenhum') {
                $onde[] = 't.tecnico_id IS NULL';
            } elseif (ctype_digit($tecnico)) {
                $onde[] = 't.tecnico_id = ?';
                $p[] = (int) $tecnico;
            }
            $sla = (string) ($f['sla'] ?? '');
            if ($sla === 'risco') {
                $onde[] = '(t.alerta_resposta = 1 OR t.alerta_resolucao = 1) AND t.alerta_resposta < 2 AND t.alerta_resolucao < 2';
            } elseif ($sla === 'violado') {
                $onde[] = '(t.alerta_resposta = 2 OR t.alerta_resolucao = 2)';
            }
        }

        // Pesquisa: "TI-00042", "42" ou texto no título/descrição
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            if (preg_match('/^(?:TI-)?0*(\d+)$/i', $q, $m)) {
                $onde[] = 't.id = ?';
                $p[] = (int) $m[1];
            } else {
                $onde[] = '(t.titulo LIKE ? OR t.descricao LIKE ?)';
                $p[] = '%' . $q . '%';
                $p[] = '%' . $q . '%';
            }
        }

        $where = $onde ? ' WHERE ' . implode(' AND ', $onde) : '';
        $total = (int) Database::valor('SELECT COUNT(*) FROM tickets t' . $where, $p);

        // TI: fechados no fim, depois por prioridade e prazo. Funcionário: os mais recentes primeiro.
        $ordem = $eTi
            ? " ORDER BY (t.estado IN ('resolvido','fechado')), (t.estado IN ('aguarda_utilizador','aguarda_terceiros')), t.prioridade, t.prazo_resolucao"
            : ' ORDER BY (t.estado = \'aguarda_utilizador\') DESC, t.atualizado_em DESC';
        $pagina = max(1, $pagina);
        $limite = ' LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina);

        return [
            'linhas' => Database::linhas(self::SELECT . $where . $ordem . $limite, $p),
            'total' => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina' => $pagina,
        ];
    }

    // Números do topo do painel
    public static function contadores(array $u): array
    {
        $abertos = "estado IN ('novo','em_curso','aguarda_utilizador','aguarda_terceiros')";
        if (in_array($u['papel'], ['tecnico', 'admin'], true)) {
            return Database::linha(
                "SELECT SUM(tecnico_id IS NULL AND $abertos) AS sem_tecnico,
                        SUM(tecnico_id = ? AND $abertos) AS meus,
                        SUM($abertos AND (alerta_resposta = 1 OR alerta_resolucao = 1) AND alerta_resposta < 2 AND alerta_resolucao < 2) AS risco,
                        SUM($abertos AND (alerta_resposta = 2 OR alerta_resolucao = 2)) AS violados,
                        SUM($abertos) AS abertos
                   FROM tickets",
                [$u['id']]
            ) ?? [];
        }
        return Database::linha(
            "SELECT SUM($abertos) AS abertos,
                    SUM(estado = 'aguarda_utilizador') AS a_espera,
                    SUM(estado = 'resolvido') AS por_confirmar
               FROM tickets WHERE requerente_id = ?",
            [$u['id']]
        ) ?? [];
    }

    // ─── Mudanças ──────────────────────────────────────────────────────────

    // Muda o estado tratando do relógio do SLA (pausa, retoma, resolvido, fechado, reaberto)
    public static function mudarEstado(array $t, string $novo, ?int $por): void
    {
        $antigo = $t['estado'];
        if ($novo === $antigo || !isset(ESTADOS[$novo])) {
            return;
        }
        $agora = agora();
        $set = ['estado' => $novo, 'atualizado_em' => $agora];

        // Sai de uma pausa: os minutos úteis parados empurram o prazo de resolução
        if ($t['sla_pausado_em'] && !in_array($novo, ESTADOS_PAUSA, true)) {
            $pausa = Sla::minutosEntre($t['sla_pausado_em'], $agora);
            $totalPausa = (int) $t['sla_pausa_min'] + $pausa;
            $set['sla_pausado_em'] = null;
            $set['sla_pausa_min'] = $totalPausa;
            $set['prazo_resolucao'] = self::prazoResolucao($t['criado_em'], (int) $t['prioridade'], $totalPausa);
            $set['alerta_resolucao'] = 0;
        }
        // Entra numa pausa (só conta a primeira; mudar entre os dois "à espera" mantém a hora original)
        if (in_array($novo, ESTADOS_PAUSA, true) && !$t['sla_pausado_em']) {
            $set['sla_pausado_em'] = $agora;
        }

        if ($novo === 'resolvido') {
            $set['resolvido_em'] = $agora;
        }
        if ($novo === 'fechado') {
            $set['fechado_em'] = $agora;
            if (!$t['resolvido_em']) {
                $set['resolvido_em'] = $agora;
            }
        }
        // Reaberto: volta a contar o SLA de resolução
        if (in_array($antigo, ['resolvido', 'fechado'], true) && in_array($novo, ESTADOS_ABERTOS, true)) {
            $set['resolvido_em'] = null;
            $set['fechado_em'] = null;
            $set['avaliacao'] = null;
            $set['avaliacao_comentario'] = null;
        }

        self::atualizar((int) $t['id'], $set);
        Historico::registar((int) $t['id'], $por, 'estado', ESTADOS[$antigo], ESTADOS[$novo]);
    }

    // Atribui a um técnico (null = devolve à fila)
    public static function atribuir(array $t, ?int $tecnicoId, int $por): void
    {
        if ((int) $t['tecnico_id'] === (int) $tecnicoId) {
            return;
        }
        $novoNome = $tecnicoId ? (string) (Utilizador::porId($tecnicoId)['nome'] ?? '?') : 'ninguém';
        self::atualizar((int) $t['id'], ['tecnico_id' => $tecnicoId, 'atualizado_em' => agora()]);
        Historico::registar((int) $t['id'], $por, 'atribuido', $t['tecnico_nome'] ?: 'ninguém', $novoNome);
    }

    // O técnico corrige a prioridade (ex.: "só a mim" mas é o PC da faturação): prazos recalculados
    public static function mudarPrioridade(array $t, int $prioridade, int $por, string $motivo): void
    {
        if ($prioridade === (int) $t['prioridade'] || !isset(PRIORIDADES[$prioridade])) {
            return;
        }
        $pol = SlaPolitica::porPrioridade($prioridade);
        self::atualizar((int) $t['id'], [
            'prioridade' => $prioridade,
            'prazo_resposta' => Sla::somar($t['criado_em'], (int) $pol['resposta_min']),
            'prazo_resolucao' => self::prazoResolucao($t['criado_em'], $prioridade, (int) $t['sla_pausa_min']),
            'alerta_resposta' => 0,
            'alerta_resolucao' => 0,
            'atualizado_em' => agora(),
        ]);
        Historico::registar(
            (int) $t['id'], $por, 'prioridade',
            'P' . $t['prioridade'] . ' ' . PRIORIDADES[(int) $t['prioridade']],
            mb_substr('P' . $prioridade . ' ' . PRIORIDADES[$prioridade] . ($motivo !== '' ? ' — ' . $motivo : ''), 0, 150)
        );
    }

    public static function mudarCategoria(array $t, ?int $categoriaId, int $por): void
    {
        if ((int) $t['categoria_id'] === (int) $categoriaId) {
            return;
        }
        $nova = $categoriaId ? (string) (Categoria::porId($categoriaId)['nome'] ?? '?') : 'sem categoria';
        self::atualizar((int) $t['id'], ['categoria_id' => $categoriaId, 'atualizado_em' => agora()]);
        Historico::registar((int) $t['id'], $por, 'categoria', $t['categoria_nome'] ?: 'sem categoria', $nova);
    }

    // 1.ª resposta pública do TI: fecha o relógio da resposta
    public static function registarPrimeiraResposta(array $t): void
    {
        if (!$t['primeira_resposta_em']) {
            self::atualizar((int) $t['id'], ['primeira_resposta_em' => agora()]);
        }
    }

    // Marca o pedido como mexido (ordena a lista do funcionário)
    public static function tocar(int $id): void
    {
        self::atualizar($id, ['atualizado_em' => agora()]);
    }

    // Avaliação do funcionário ao confirmar a resolução (1 a 5)
    public static function avaliar(int $id, int $nota, string $comentario): void
    {
        self::atualizar($id, ['avaliacao' => max(1, min(5, $nota)), 'avaliacao_comentario' => $comentario !== '' ? mb_substr($comentario, 0, 500) : null]);
    }

    // ─── Para as tarefas em segundo plano ─────────────────────────────────

    // Pedidos resolvidos há mais de X dias sem confirmação
    public static function paraFecharAuto(int $dias): array
    {
        return Database::linhas(
            self::SELECT . " WHERE t.estado = 'resolvido' AND t.resolvido_em <= ?",
            [date('Y-m-d H:i:s', time() - $dias * 86400)]
        );
    }

    // Pedidos abertos e com o relógio a correr (candidatos a alertas de SLA)
    public static function comRelogioAtivo(): array
    {
        return Database::linhas(
            self::SELECT . " WHERE t.estado IN ('novo','em_curso','aguarda_utilizador','aguarda_terceiros')
                              AND (t.alerta_resposta < 2 OR t.alerta_resolucao < 2)"
        );
    }

    public static function marcarAlerta(int $id, string $campo, int $nivel): void
    {
        if (in_array($campo, ['alerta_resposta', 'alerta_resolucao'], true)) {
            self::atualizar($id, [$campo => $nivel]);
        }
    }

    // ─── Internos ──────────────────────────────────────────────────────────

    // Prazo de resolução = criação + minutos da prioridade + minutos em pausa
    private static function prazoResolucao(string $criadoEm, int $prioridade, int $pausaMin): string
    {
        $pol = SlaPolitica::porPrioridade($prioridade);
        return Sla::somar($criadoEm, (int) ($pol['resolucao_min'] ?? 1620) + $pausaMin);
    }

    // UPDATE com colunas fixas do código (nunca vindas do pedido HTTP)
    private static function atualizar(int $id, array $set): void
    {
        $permitidas = ['estado', 'atualizado_em', 'sla_pausado_em', 'sla_pausa_min', 'prazo_resposta', 'prazo_resolucao',
            'alerta_resposta', 'alerta_resolucao', 'resolvido_em', 'fechado_em', 'avaliacao', 'avaliacao_comentario',
            'tecnico_id', 'prioridade', 'categoria_id', 'primeira_resposta_em'];
        $set = array_intersect_key($set, array_flip($permitidas));
        if (!$set) {
            return;
        }
        $sql = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($set)));
        Database::q("UPDATE tickets SET $sql WHERE id = ?", [...array_values($set), $id]);
    }
}
