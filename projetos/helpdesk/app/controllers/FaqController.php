<?php
// FAQ: todos leem e pesquisam; o TI escreve e edita (inclusive a partir de um pedido resolvido).
declare(strict_types=1);

final class FaqController extends Controller
{
    // GET: lista e pesquisa
    public function index(): void
    {
        $this->exigirLogin();
        $q = get('q');
        $categoria = inteiro(get('categoria'));
        $this->view('faq/index', [
            'q' => $q,
            'categoria' => $categoria,
            'categorias' => Categoria::ativas(),
            'artigos' => Faq::pesquisar($q, $categoria, self::eTecnico()),
        ], 'FAQ');
    }

    // GET: um artigo
    public function ver(): void
    {
        $this->exigirLogin();
        $a = Faq::porId(inteiro(get('id')));
        if (!$a || ((int) $a['publicado'] !== 1 && !self::eTecnico())) {
            $this->erro(404);
        }
        Faq::registarVista((int) $a['id']);
        $this->view('faq/ver', [
            'a' => $a,
            'relacionados' => Faq::relacionados($a),
            'votou' => !empty($_SESSION['faq_votos'][$a['id']]),
            'deNovoPedido' => get('de') === 'novo',
        ], $a['titulo']);
    }

    // GET (JSON): sugestões enquanto o funcionário escreve o título do pedido
    public function sugerir(): void
    {
        $this->exigirLogin();
        $q = get('q');
        if (mb_strlen($q) < 3) {
            $this->json(['artigos' => []]);
        }
        $artigos = array_map(fn ($a) => [
            'id' => (int) $a['id'],
            'titulo' => $a['titulo'],
            'resumo' => $a['resumo'],
            'url' => url('faq', 'ver', ['id' => $a['id'], 'de' => 'novo']),
        ], Faq::pesquisar($q, 0, false, 4));
        $this->json(['artigos' => $artigos]);
    }

    // POST: "Isto foi útil?"
    public function votar(): void
    {
        $this->exigirLogin();
        $a = Faq::porId(inteiro(post('id')));
        if (!$a) {
            $this->erro(404);
        }
        Faq::votar((int) $a['id'], post('util') === '1');
        flash('ok', 'Obrigado pela resposta.');
        redirecionar('faq', 'ver', ['id' => $a['id']]);
    }

    // POST: "Resolveu o meu problema" (a partir das sugestões do novo pedido) — pedido evitado
    public function resolveu(): void
    {
        $u = $this->exigirLogin();
        $a = Faq::porId(inteiro(post('id')));
        if (!$a || (int) $a['publicado'] !== 1) {
            $this->erro(404);
        }
        Faq::registarDesvio((int) $a['id'], (int) $u['id']);
        Faq::votar((int) $a['id'], true);
        flash('ok', 'Ainda bem que ficou resolvido! Não foi preciso abrir pedido.');
        redirecionar('painel');
    }

    // GET (TI): novo artigo; com ?ticket=ID vem preenchido a partir do pedido resolvido
    public function novo(): void
    {
        $this->exigirPapel('tecnico', 'admin');
        $artigo = ['id' => 0, 'titulo' => '', 'resumo' => '', 'conteudo' => '', 'palavras_chave' => '', 'categoria_id' => null, 'publicado' => 1];
        $tid = inteiro(get('ticket'));
        if ($tid && ($t = Ticket::porId($tid))) {
            $resposta = Comentario::ultimaRespostaTi($tid);
            $artigo['titulo'] = $t['titulo'];
            $artigo['resumo'] = excerto($t['descricao'], 200);
            $artigo['conteudo'] = "Problema:\n" . $t['descricao'] . "\n\nSolução:\n" . ($resposta['corpo'] ?? '');
            $artigo['categoria_id'] = $t['categoria_id'];
            $artigo['publicado'] = 0;   // rascunho: rever antes de publicar (pode ter dados pessoais)
        }
        $this->formulario($artigo);
    }

    // GET (TI): editar
    public function editar(): void
    {
        $this->exigirPapel('tecnico', 'admin');
        $a = Faq::porId(inteiro(get('id')));
        if (!$a) {
            $this->erro(404);
        }
        $this->formulario($a);
    }

    // POST (TI): cria ou atualiza
    public function guardar(): void
    {
        $u = $this->exigirPapel('tecnico', 'admin');
        $id = inteiro(post('id'));
        if ($id && !Faq::porId($id)) {
            $this->erro(404);
        }
        $d = [
            'titulo' => post('titulo'),
            'resumo' => post('resumo'),
            'conteudo' => post('conteudo'),
            'palavras_chave' => mb_substr(post('palavras_chave'), 0, 255),
            'categoria_id' => inteiro(post('categoria_id')) ?: null,
            'publicado' => post('publicado') === '1' ? 1 : 0,
        ];
        $erros = [];
        if (mb_strlen($d['titulo']) < 5 || mb_strlen($d['titulo']) > 150) {
            $erros[] = 'O título precisa de 5 a 150 caracteres.';
        }
        if (mb_strlen($d['resumo']) < 10 || mb_strlen($d['resumo']) > 255) {
            $erros[] = 'O resumo precisa de 10 a 255 caracteres (é o que aparece nas sugestões).';
        }
        if (mb_strlen($d['conteudo']) < 20 || mb_strlen($d['conteudo']) > 20000) {
            $erros[] = 'O conteúdo precisa de 20 a 20000 caracteres.';
        }
        if ($d['categoria_id'] && !Categoria::porId($d['categoria_id'])) {
            $erros[] = 'Categoria inválida.';
        }
        if ($erros) {
            foreach ($erros as $e) {
                flash('erro', $e);
            }
            $this->formulario(['id' => $id] + $d);
        }
        $id = Faq::guardar($id, $d, (int) $u['id']);
        flash('ok', $d['publicado'] ? 'Artigo publicado.' : 'Rascunho guardado (só o TI o vê).');
        redirecionar('faq', 'ver', ['id' => $id]);
    }

    // POST (TI): apagar
    public function apagar(): void
    {
        $this->exigirPapel('tecnico', 'admin');
        $a = Faq::porId(inteiro(post('id')));
        if (!$a) {
            $this->erro(404);
        }
        Faq::apagar((int) $a['id']);
        Auditoria::registar('faq_apagado', $a['titulo']);
        flash('ok', 'Artigo apagado.');
        redirecionar('faq');
    }

    private function formulario(array $artigo): never
    {
        $this->view('faq/form', ['a' => $artigo, 'categorias' => Categoria::ativas()], $artigo['id'] ? 'Editar artigo' : 'Novo artigo');
    }
}
