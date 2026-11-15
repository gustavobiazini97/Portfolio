<?php
// Quem é avisado de quê. Cada aviso vai para o sino (sempre) e para o email (se a pessoa quiser).
// Quem fez a ação nunca é notificado da própria ação.
declare(strict_types=1);

final class Notificador
{
    // Pedido novo → técnico atribuído; sem técnico → toda a equipa de TI
    public static function ticketCriado(array $t): void
    {
        $destino = $t['tecnico_id'] ? [Utilizador::porId((int) $t['tecnico_id'])] : Utilizador::equipaTi();
        $p = 'P' . $t['prioridade'] . ' ' . PRIORIDADES[(int) $t['prioridade']];
        self::enviar(
            $destino, $t, (int) $t['requerente_id'],
            "Novo pedido ($p) de {$t['requerente_nome']}: {$t['titulo']}",
            "{$t['requerente_nome']} abriu um pedido novo.\n\nPrioridade: $p\nCategoria: " . ($t['categoria_nome'] ?? '—')
            . "\n\n" . excerto($t['descricao'], 600)
        );
    }

    // Resposta pública ou nota interna
    public static function comentario(array $t, array $autor, string $corpo, bool $interno): void
    {
        $doTi = in_array($autor['papel'], ['tecnico', 'admin'], true);
        if ($interno) {
            // Nota interna: só o técnico atribuído (se não foi ele a escrever)
            $destino = $t['tecnico_id'] ? [Utilizador::porId((int) $t['tecnico_id'])] : [];
            $msg = "{$autor['nome']} deixou uma nota interna";
        } elseif ($doTi) {
            // Resposta do TI → funcionário que abriu
            $destino = [Utilizador::porId((int) $t['requerente_id'])];
            $msg = "{$autor['nome']} respondeu ao teu pedido";
        } else {
            // Resposta do funcionário → técnico atribuído ou toda a equipa
            $destino = $t['tecnico_id'] ? [Utilizador::porId((int) $t['tecnico_id'])] : Utilizador::equipaTi();
            $msg = "{$autor['nome']} respondeu";
        }
        self::enviar($destino, $t, (int) $autor['id'], "$msg: {$t['titulo']}", "$msg:\n\n" . excerto($corpo, 800));
    }

    // Mudança de estado → funcionário que abriu
    // (com $comentario: a resposta do técnico vai no mesmo aviso, em vez de dois avisos seguidos)
    public static function estado(array $t, string $novo, int $por, string $comentario = ''): void
    {
        $extra = match ($novo) {
            'aguarda_utilizador' => "\n\nO TI precisa de uma resposta tua para continuar.",
            'resolvido' => "\n\nSe ficou resolvido, confirma no pedido (e avalia o atendimento). Se não, responde e o pedido reabre.",
            default => '',
        };
        $resposta = $comentario !== '' ? "\n\nResposta do TI:\n" . excerto($comentario, 800) : '';
        self::enviar(
            [Utilizador::porId((int) $t['requerente_id'])], $t, $por,
            'O teu pedido está agora "' . ESTADOS[$novo] . '": ' . $t['titulo'],
            'Estado do pedido: ' . ESTADOS[$novo] . $resposta . $extra
        );
    }

    // Funcionário confirmou a resolução e avaliou → técnico
    public static function avaliado(array $t, int $nota, int $por): void
    {
        if (!$t['tecnico_id']) {
            return;
        }
        self::enviar(
            [Utilizador::porId((int) $t['tecnico_id'])], $t, $por,
            "{$t['requerente_nome']} confirmou a resolução e avaliou com $nota/5: {$t['titulo']}",
            "O pedido foi fechado pelo funcionário. Avaliação: $nota/5."
        );
    }

    // Atribuição → técnico que recebeu
    public static function atribuido(array $t, int $tecnicoId, int $por): void
    {
        self::enviar(
            [Utilizador::porId($tecnicoId)], $t, $por,
            "Pedido atribuído a ti: {$t['titulo']}",
            'Foi-te atribuído este pedido (P' . $t['prioridade'] . ' ' . PRIORIDADES[(int) $t['prioridade']] . ').'
        );
    }

    // SLA: aviso aos 80% → técnico (ou equipa); ultrapassado → técnico + admins
    public static function sla(array $t, string $qual, bool $ultrapassado): void
    {
        $nomeRelogio = $qual === 'resposta' ? 'primeira resposta' : 'resolução';
        $base = $t['tecnico_id'] ? [Utilizador::porId((int) $t['tecnico_id'])] : Utilizador::equipaTi();
        $destino = $ultrapassado ? array_merge($base, Utilizador::admins()) : $base;
        $msg = $ultrapassado
            ? "Prazo de $nomeRelogio ULTRAPASSADO: {$t['titulo']}"
            : "Prazo de $nomeRelogio quase a acabar: {$t['titulo']}";
        self::enviar($destino, $t, 0, $msg, $msg . "\n\nPrioridade: P{$t['prioridade']} " . PRIORIDADES[(int) $t['prioridade']]);
    }

    // Fecho automático → funcionário
    public static function fechoAuto(array $t): void
    {
        self::enviar(
            [Utilizador::porId((int) $t['requerente_id'])], $t, 0,
            "Pedido fechado automaticamente: {$t['titulo']}",
            'O pedido estava resolvido e não houve resposta, por isso foi fechado. Se o problema voltar, abre um pedido novo.'
        );
    }

    // Envia a cada destinatário (sem repetidos, sem o autor, só contas ativas)
    private static function enviar(array $destino, array $t, int $autorId, string $mensagem, string $corpoEmail): void
    {
        $vistos = [];
        $codigo = codigo_ticket((int) $t['id']);
        foreach ($destino as $u) {
            if (!$u || (int) $u['ativo'] !== 1 || (int) $u['id'] === $autorId || isset($vistos[$u['id']])) {
                continue;
            }
            $vistos[$u['id']] = true;
            Notificacao::criar((int) $u['id'], (int) $t['id'], $mensagem);
            if ((int) $u['notif_email'] === 1) {
                Mailer::enfileirar(
                    $u['email'],
                    "[$codigo] " . excerto($mensagem, 150),
                    "Olá {$u['nome']},\n\n$corpoEmail\n\nVer o pedido $codigo:\n" . url_absoluto('ticket', 'ver', ['id' => $t['id']])
                    . "\n\n— " . config('app_nome', 'Helpdesk TI') . "\n(Para deixares de receber estes emails, muda no teu perfil.)"
                );
            }
        }
    }
}
