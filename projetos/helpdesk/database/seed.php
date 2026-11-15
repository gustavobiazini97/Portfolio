<?php
// Dados iniciais.
//   php database/seed.php admin@empresa.pt "Nome do Admin"   → cria o 1.º admin + categorias + FAQ base
//   php database/seed.php --demo                             → tudo isso + contas e pedidos de exemplo (portfólio/testes)
// Os emails, as palavras-passe temporárias e os pedidos de exemplo são inventados.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';

$demo = in_array('--demo', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '--demo'));
$emailAdmin = $args[0] ?? ($demo ? 'admin@demo.pt' : '');
$nomeAdmin = $args[1] ?? 'Administrador';

if ($emailAdmin === '' || !filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php database/seed.php admin@empresa.pt \"Nome\"   (ou --demo)\n");
    exit(1);
}

// ─── Categorias ────────────────────────────────────────────────────────────
if ((int) Database::valor('SELECT COUNT(*) FROM categorias') === 0) {
    $categorias = [
        ['Computador ou portátil', 'Não liga, lento, ecrã, teclado, rato'],
        ['Impressoras', 'Não imprime, encravada, digitalizar'],
        ['Email e Outlook', 'Não envia/recebe, caixa cheia, assinatura'],
        ['Rede, internet e VPN', 'Sem internet, Wi-Fi, ligar de casa'],
        ['Programas da empresa', 'ERP, faturação, CRM, erros e acessos'],
        ['Contas e acessos', 'Palavras-passe, pastas partilhadas, permissões'],
        ['Telefones e telemóveis', 'Telefone fixo, telemóvel da empresa'],
        ['Pedido de equipamento', 'Material novo ou substituição'],
    ];
    foreach ($categorias as [$nome, $desc]) {
        Categoria::guardar(0, $nome, $desc, null, true);
    }
    echo "  ✓ categorias\n";
}
$cat = [];
foreach (Categoria::ativas() as $c) {
    $cat[$c['nome']] = (int) $c['id'];
}

// ─── Primeiro admin ───────────────────────────────────────────────────────
$senhas = [];
$criarConta = function (string $nome, string $email, string $papel, string $departamento) use (&$senhas): int {
    if ($u = Utilizador::porEmail($email)) {
        return (int) $u['id'];
    }
    $temp = bin2hex(random_bytes(5));   // 10 caracteres
    $id = Utilizador::criar(['nome' => $nome, 'email' => $email, 'papel' => $papel, 'departamento' => $departamento], $temp);
    $senhas[] = [$email, $temp, PAPEIS[$papel]];
    return $id;
};
$adminId = $criarConta($nomeAdmin, $emailAdmin, 'admin', 'Informática');

// ─── FAQ base (útil em qualquer empresa) ──────────────────────────────────
if ((int) Database::valor('SELECT COUNT(*) FROM faq_artigos') === 0) {
    $artigos = [
        ['A impressora não imprime ou aparece "offline"', 'Verificar a ligação, limpar a fila de impressão e escolher a impressora certa.', 'Impressoras', 'impressora, imprimir, offline, fila, encravada, papel',
         "Antes de abrir pedido, experimenta por esta ordem:\n\n1. Confirma que a impressora está ligada e sem luzes de erro (papel, tinteiro, papel encravado).\n2. No PC, abre **Definições → Bluetooth e dispositivos → Impressoras** e confirma que estás a enviar para a impressora certa.\n3. Clica na impressora → **Abrir fila de impressão** → **Impressora → Cancelar todos os documentos**.\n4. Desliga a impressora 30 segundos e volta a ligar.\n5. Tenta imprimir outra vez.\n\nSe continuar \"offline\", abre um pedido e diz **qual é a impressora** (local ou nome na etiqueta)."],
        ['Esqueci-me da palavra-passe ou a conta ficou bloqueada', 'Como pedir a reposição e como escolher uma palavra-passe nova que não esqueças.', 'Contas e acessos', 'password, palavra-passe, senha, bloqueada, esqueci, login, windows',
         "Depois de várias tentativas erradas a conta bloqueia por segurança.\n\n- **Esta app (Helpdesk):** usa \"Esqueci-me da palavra-passe\" no ecrã de entrada.\n- **Windows / email da empresa:** abre um pedido na categoria **Contas e acessos** a partir de outro PC ou pede a um colega para abrir por ti.\n\nDicas para a nova: usa uma frase curta com 3–4 palavras (ex.: `cafe-azul-armazem-27`). É mais fácil de lembrar e mais difícil de adivinhar.\n\nO TI **nunca** te vai pedir a palavra-passe por email ou telefone."],
        ['O Outlook não envia nem recebe emails', 'Confirmar a ligação, a caixa de saída e o espaço da caixa de correio.', 'Email e Outlook', 'outlook, email, correio, enviar, receber, caixa de saída, cheia',
         "1. Confirma que tens internet (abre um site qualquer).\n2. Em baixo, no Outlook, vê se diz **Desligado** ou **A trabalhar offline**. Se sim: separador **Enviar/Receber → Trabalhar Offline** (clica para desligar).\n3. Vê a **Caixa de Saída**: um email com anexo muito grande pode estar a bloquear os outros. Apaga-o ou reduz o anexo (máximo ~20 MB).\n4. Caixa cheia? Esvazia **Itens Eliminados** e **Lixo**.\n5. Fecha e volta a abrir o Outlook.\n\nSe nada resultar, abre pedido com a **mensagem de erro** (uma fotografia do ecrã ajuda)."],
        ['Estou sem internet no computador', 'Cabo, Wi-Fi e reinício: os passos que resolvem a maioria dos casos.', 'Rede, internet e VPN', 'internet, net, rede, wifi, wi-fi, cabo, sem ligação',
         "1. Os colegas ao lado têm internet? Se **ninguém** tem, é geral: abre pedido com prioridade alta (afeta a equipa).\n2. Se és só tu e usas **cabo**: confirma que o cabo está bem encaixado no PC e na parede (a luz da porta deve piscar).\n3. Se usas **Wi-Fi**: desliga e volta a ligar o Wi-Fi no canto inferior direito.\n4. Reinicia o computador (**Reiniciar**, não \"Encerrar\").\n\nAo abrir pedido, diz se é cabo ou Wi-Fi e em que posto estás."],
        ['Como ligar à VPN para trabalhar de casa', 'Ligar à VPN da empresa para aceder aos programas e pastas a partir de fora.', 'Rede, internet e VPN', 'vpn, casa, teletrabalho, remoto, fora, ligar',
         "1. Liga-te primeiro à internet de casa.\n2. Abre o programa da VPN (ícone na barra de tarefas, junto ao relógio).\n3. Entra com o teu utilizador e palavra-passe da empresa.\n4. Aprova o pedido no telemóvel, se te for pedido.\n5. Quando aparecer **Ligado**, já podes abrir os programas e as pastas partilhadas.\n\nNão tens o programa da VPN? Abre um pedido na categoria **Rede, internet e VPN** (precisa de aprovação da chefia)."],
        ['O computador está muito lento', 'O que fazer antes de pedir ajuda: reiniciar, fechar programas e ver o espaço em disco.', 'Computador ou portátil', 'lento, devagar, pc, computador, bloqueia, congela, encravado',
         "1. **Reinicia** o computador. Muitos PCs ficam semanas sem reiniciar e isso deixa-os lentos.\n2. Fecha os separadores do browser que não estás a usar.\n3. Abre o **Gestor de Tarefas** (Ctrl+Shift+Esc) e vê que programa está a usar muita CPU ou memória.\n4. Vê o espaço livre no disco C: (Explorador de Ficheiros → Este PC). Com menos de 10% livre, tudo fica lento.\n\nSe continuar lento, abre pedido e diz **desde quando** e **em que programas** nota mais."],
        ['Pedir acesso a uma pasta partilhada ou a um programa', 'Que informação pôr no pedido para o TI dar o acesso à primeira.', 'Contas e acessos', 'acesso, permissão, pasta, partilhada, programa, licença',
         "Abre um pedido na categoria **Contas e acessos** com:\n\n- O **nome exato** da pasta ou do programa.\n- O tipo de acesso: só ler, ou ler e alterar.\n- **Quem aprova** (normalmente a tua chefia). Se puderes, põe-na em cópia ou anexa o email de aprovação.\n\nSem aprovação, o TI não pode dar acessos a dados de outros departamentos."],
        ['O segundo monitor não dá imagem', 'Verificar cabos e as definições de ecrã do Windows.', 'Computador ou portátil', 'monitor, ecrã, segundo ecrã, imagem, preto, hdmi, projetar',
         "1. Confirma que o monitor está ligado (luz acesa) e que o cabo está bem encaixado nas duas pontas.\n2. Carrega em **Windows + P** e escolhe **Expandir**.\n3. Em **Definições → Sistema → Ecrã**, clica em **Detetar**.\n4. Se usas uma dock/hub no portátil, desliga-a e volta a ligar.\n\nSe nada aparecer, experimenta outro cabo ou outra entrada do monitor antes de abrir o pedido."],
    ];
    foreach ($artigos as [$titulo, $resumo, $categoria, $chaves, $conteudo]) {
        Faq::guardar(0, [
            'titulo' => $titulo, 'resumo' => $resumo, 'conteudo' => $conteudo, 'palavras_chave' => $chaves,
            'categoria_id' => $cat[$categoria] ?? null, 'publicado' => 1,
        ], $adminId);
    }
    echo "  ✓ FAQ (" . count($artigos) . " artigos)\n";
}

// ─── Demo: contas e pedidos de exemplo ────────────────────────────────────
if ($demo && (int) Database::valor('SELECT COUNT(*) FROM tickets') === 0) {
    $tec1 = $criarConta('Rui Matos', 'rui.ti@demo.pt', 'tecnico', 'Informática');
    $tec2 = $criarConta('Carla Neves', 'carla.ti@demo.pt', 'tecnico', 'Informática');
    $joana = $criarConta('Joana Silva', 'joana@demo.pt', 'utilizador', 'Faturação');
    $pedro = $criarConta('Pedro Costa', 'pedro@demo.pt', 'utilizador', 'Armazém');
    $marta = $criarConta('Marta Lopes', 'marta@demo.pt', 'utilizador', 'Logística');

    // Técnicos por defeito em algumas categorias
    Categoria::guardar($cat['Impressoras'], 'Impressoras', 'Não imprime, encravada, digitalizar', $tec1, true);
    Categoria::guardar($cat['Programas da empresa'], 'Programas da empresa', 'ERP, faturação, CRM, erros e acessos', $tec2, true);

    // Cria um pedido "há X horas" (recalcula os prazos a partir dessa data)
    $pedido = function (int $quem, string $titulo, string $desc, string $categoria, int $imp, int $urg, int $horasAtras) use ($cat): array {
        $id = Ticket::criar($quem, $titulo, $desc, $cat[$categoria] ?? null, $imp, $urg);
        $quando = date('Y-m-d H:i:s', time() - $horasAtras * 3600);
        $pol = SlaPolitica::porPrioridade(Sla::prioridade($imp, $urg));
        Database::q(
            'UPDATE tickets SET criado_em = ?, atualizado_em = ?, prazo_resposta = ?, prazo_resolucao = ? WHERE id = ?',
            [$quando, $quando, Sla::somar($quando, (int) $pol['resposta_min']), Sla::somar($quando, (int) $pol['resolucao_min']), $id]
        );
        Database::q('UPDATE historico SET criado_em = ? WHERE ticket_id = ?', [$quando, $id]);
        return Ticket::porId($id);
    };
    // Comentário "há X horas"
    $comentar = function (array $t, int $autor, string $texto, int $horasAtras, bool $interno = false): void {
        $cid = Comentario::criar((int) $t['id'], $autor, $texto, $interno);
        $quando = date('Y-m-d H:i:s', time() - $horasAtras * 3600);
        Database::q('UPDATE comentarios SET criado_em = ? WHERE id = ?', [$quando, $cid]);
        Database::q('UPDATE tickets SET atualizado_em = ? WHERE id = ?', [$quando, $t['id']]);
    };

    // Recua a última linha do histórico do pedido (as mudanças de estado ficam na hora certa)
    $recuar = function (array $t, int $horasAtras): void {
        Database::q('UPDATE historico SET criado_em = ? WHERE ticket_id = ? ORDER BY id DESC LIMIT 1',
            [date('Y-m-d H:i:s', time() - $horasAtras * 3600), $t['id']]);
    };

    // 1. Crítico, sem técnico, acabado de abrir
    $pedido($marta, 'Ninguém na logística consegue entrar no programa de expedição',
        "Desde as 9h que aparece \"Erro de ligação ao servidor\" em todos os PCs da logística.\nNão conseguimos emitir guias.", 'Programas da empresa', 3, 3, 0);

    // 2. Em curso, com resposta do técnico e nota interna
    $t = $pedido($joana, 'Impressora da faturação imprime as faturas cortadas',
        "As faturas saem com a parte de baixo cortada. Já experimentei outro papel.", 'Impressoras', 2, 2, 5);
    $comentar($t, $tec1, "Olá Joana, consegues dizer-me se acontece só com faturas ou com tudo? Vou passar aí depois do almoço.", 4);
    Ticket::registarPrimeiraResposta(Ticket::porId((int) $t['id']));
    Database::q('UPDATE tickets SET primeira_resposta_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 4 * 3600), $t['id']]);
    Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'em_curso', $tec1);
    $recuar($t, 4);
    $comentar($t, $joana, "Só com faturas. Os outros documentos saem bem.", 3);
    $comentar($t, $tec1, "Provável margem errada no modelo de fatura do ERP. Ver com a Carla.", 2, true);

    // 3. À espera do utilizador (SLA pausado)
    $t = $pedido($pedro, 'Pedido de acesso à pasta "Inventários 2026"', "Preciso de acesso para atualizar as contagens do armazém.", 'Contas e acessos', 1, 2, 26);
    Ticket::atribuir(Ticket::porId((int) $t['id']), $tec2, $tec2);
    $comentar($t, $tec2, "Olá Pedro, preciso da aprovação da tua chefia. Podes pedir-lhe que responda aqui ou reencaminhar o email dela?", 24);
    Database::q('UPDATE tickets SET primeira_resposta_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 24 * 3600), $t['id']]);
    Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'aguarda_utilizador', $tec2);
    $recuar($t, 24);
    Database::q('UPDATE tickets SET sla_pausado_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 24 * 3600), $t['id']]);

    // 4. Resolvido, à espera da confirmação do funcionário
    $t = $pedido($joana, 'O Outlook não sincroniza no telemóvel da empresa', "Os emails novos não aparecem no telemóvel desde ontem.", 'Email e Outlook', 1, 2, 30);
    Ticket::atribuir(Ticket::porId((int) $t['id']), $tec1, $tec1);
    $comentar($t, $tec1, "Removi e voltei a adicionar a conta no telemóvel. Já deve sincronizar. Confirmas?", 6);
    Database::q('UPDATE tickets SET primeira_resposta_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 28 * 3600), $t['id']]);
    Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'resolvido', $tec1);
    $recuar($t, 6);
    Database::q('UPDATE tickets SET resolvido_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 6 * 3600), $t['id']]);

    // 5. Fechado e avaliado
    $t = $pedido($marta, 'Rato sem fios deixou de funcionar', "O rato não responde mesmo depois de trocar as pilhas.", 'Computador ou portátil', 1, 1, 72);
    Ticket::atribuir(Ticket::porId((int) $t['id']), $tec2, $tec2);
    $comentar($t, $tec2, "Deixei um rato novo na tua secretária.", 50);
    Database::q('UPDATE tickets SET primeira_resposta_em = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 50 * 3600), $t['id']]);
    Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'resolvido', $tec2);
    Ticket::avaliar((int) $t['id'], 5, 'Rápido, obrigada!');
    Ticket::mudarEstado(Ticket::porId((int) $t['id']), 'fechado', $marta);

    // 6. Baixa prioridade, sem técnico
    $pedido($pedro, 'Pedido de um segundo monitor para o posto da receção de mercadorias',
        "Dava jeito ter a lista de encomendas e o ERP abertos ao mesmo tempo.", 'Pedido de equipamento', 1, 1, 3);

    // Alertas de SLA calculados já (para o painel mostrar os contadores certos)
    Tarefas::alertasSla();
    echo "  ✓ contas e pedidos de exemplo\n";
}

if ($senhas) {
    echo "\nContas criadas (palavra-passe temporária; troca obrigatória no 1.º login):\n";
    foreach ($senhas as [$email, $temp, $papel]) {
        printf("  %-22s %-14s %s\n", $email, $temp, $papel);
    }
    echo "\nO admin vai ter de configurar a verificação em dois passos (2FA) no primeiro login.\n";
}
echo "Seed concluído.\n";
