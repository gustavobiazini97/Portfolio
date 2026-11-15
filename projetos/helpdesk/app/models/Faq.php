<?php
// FAQ / base de conhecimento: artigos, pesquisa, sugestões ao abrir pedido e "isto foi útil?".
declare(strict_types=1);

final class Faq
{
    private const EDITAVEIS = ['titulo', 'resumo', 'conteudo', 'palavras_chave', 'categoria_id', 'publicado'];

    // Palavras que aparecem em quase todos os textos e só baralham a pesquisa
    private const VAZIAS = ['de', 'do', 'da', 'dos', 'das', 'no', 'na', 'nos', 'nas', 'em', 'um', 'uma', 'os', 'as', 'ao', 'aos',
        'que', 'não', 'nao', 'com', 'sem', 'por', 'para', 'pra', 'se', 'me', 'meu', 'minha', 'eu', 'ele', 'ela', 'mais', 'muito',
        'já', 'ja', 'está', 'esta', 'estou', 'tem', 'ter', 'foi', 'ser', 'como', 'quando', 'isto', 'isso', 'o', 'a', 'e'];

    public static function porId(int $id): ?array
    {
        return Database::linha(
            'SELECT f.*, c.nome AS categoria_nome, u.nome AS autor_nome FROM faq_artigos f
          LEFT JOIN categorias c ON c.id = f.categoria_id LEFT JOIN utilizadores u ON u.id = f.autor_id WHERE f.id = ?',
            [$id]
        );
    }

    // Pesquisa: texto completo (FULLTEXT) + LIKE para palavras curtas ("vpn", "pc").
    // Sem texto: todos, os mais vistos primeiro.
    public static function pesquisar(string $q, int $categoriaId = 0, bool $incluirRascunhos = false, int $limite = 50): array
    {
        $onde = [];
        $p = [];
        if (!$incluirRascunhos) {
            $onde[] = 'f.publicado = 1';
        }
        if ($categoriaId > 0) {
            $onde[] = 'f.categoria_id = ?';
            $p[] = $categoriaId;
        }

        $q = trim(mb_substr($q, 0, 150));
        $relevancia = 'f.visualizacoes';
        $pr = [];
        if ($q !== '') {
            // Palavras com 2+ letras e que não sejam "vazias" ("não", "de", "o"…), para o LIKE
            // (o FULLTEXT ignora palavras curtas como "pc" ou "vpn")
            $palavras = array_values(array_slice(array_filter(
                preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q)) ?: [],
                fn ($w) => mb_strlen($w) >= 2 && !in_array($w, self::VAZIAS, true)
            ), 0, 6));
            // Só palavras vazias ("não sei") → nada a procurar
            if (!$palavras) {
                return [];
            }
            // O MATCH também recebe só as palavras úteis (senão "não" bate em metade dos artigos)
            $q = implode(' ', $palavras);
            // Primeiro o ? do MATCH, depois os ? dos LIKE (pela ordem em que aparecem no SQL)
            $p[] = $q;
            $likes = [];
            foreach ($palavras as $w) {
                $likes[] = '(f.titulo LIKE ? OR f.palavras_chave LIKE ? OR f.resumo LIKE ?)';
                array_push($p, "%$w%", "%$w%", "%$w%");
            }
            $onde[] = '(MATCH(f.titulo, f.resumo, f.conteudo, f.palavras_chave) AGAINST (? IN NATURAL LANGUAGE MODE)'
                . ($likes ? ' OR ' . implode(' OR ', $likes) : '') . ')';
            // Ordem: relevância do texto + bónus por cada palavra no título (×2) e nas palavras-chave
            $relevancia = 'MATCH(f.titulo, f.resumo, f.conteudo, f.palavras_chave) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $pr = [$q];
            foreach ($palavras as $w) {
                $relevancia .= ' + (f.titulo LIKE ?) * 2 + (COALESCE(f.palavras_chave, \'\') LIKE ?)';
                array_push($pr, "%$w%", "%$w%");
            }
        }

        $where = $onde ? ' WHERE ' . implode(' AND ', $onde) : '';
        return Database::linhas(
            "SELECT f.id, f.titulo, f.resumo, f.publicado, f.visualizacoes, f.util_sim, f.util_nao, f.atualizado_em,
                    c.nome AS categoria_nome, ($relevancia) AS relevancia
               FROM faq_artigos f LEFT JOIN categorias c ON c.id = f.categoria_id
               $where ORDER BY relevancia DESC, f.titulo LIMIT " . max(1, min(100, $limite)),
            [...$pr, ...$p]
        );
    }

    // Cria (id 0) ou atualiza um artigo; só os campos da whitelist
    public static function guardar(int $id, array $d, int $autorId): int
    {
        $d = array_intersect_key($d, array_flip(self::EDITAVEIS));
        $agora = agora();
        if ($id > 0) {
            Database::q(
                'UPDATE faq_artigos SET titulo = ?, resumo = ?, conteudo = ?, palavras_chave = ?, categoria_id = ?, publicado = ?, atualizado_em = ? WHERE id = ?',
                [$d['titulo'], $d['resumo'], $d['conteudo'], $d['palavras_chave'] ?: null, $d['categoria_id'] ?: null, $d['publicado'], $agora, $id]
            );
            return $id;
        }
        Database::q(
            'INSERT INTO faq_artigos (titulo, resumo, conteudo, palavras_chave, categoria_id, publicado, autor_id, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['titulo'], $d['resumo'], $d['conteudo'], $d['palavras_chave'] ?: null, $d['categoria_id'] ?: null, $d['publicado'], $autorId, $agora, $agora]
        );
        return Database::ultimoId();
    }

    public static function apagar(int $id): void
    {
        Database::q('DELETE FROM faq_artigos WHERE id = ?', [$id]);
    }

    // Mais uma visualização (uma por sessão e artigo, para não inflacionar)
    public static function registarVista(int $id): void
    {
        if (empty($_SESSION['faq_vistos'][$id])) {
            $_SESSION['faq_vistos'][$id] = true;
            Database::q('UPDATE faq_artigos SET visualizacoes = visualizacoes + 1 WHERE id = ?', [$id]);
        }
    }

    // "Isto foi útil?" — um voto por sessão e artigo
    public static function votar(int $id, bool $util): bool
    {
        if (!empty($_SESSION['faq_votos'][$id])) {
            return false;
        }
        $_SESSION['faq_votos'][$id] = true;
        Database::q('UPDATE faq_artigos SET ' . ($util ? 'util_sim = util_sim + 1' : 'util_nao = util_nao + 1') . ' WHERE id = ?', [$id]);
        return true;
    }

    // "Resolveu-me o problema": um pedido que não chegou a ser aberto
    public static function registarDesvio(int $artigoId, int $uid): void
    {
        Database::q('INSERT INTO faq_desvios (artigo_id, utilizador_id, criado_em) VALUES (?, ?, ?)', [$artigoId, $uid, agora()]);
    }

    // Artigos relacionados (mesma categoria)
    public static function relacionados(array $artigo, int $limite = 4): array
    {
        if (!$artigo['categoria_id']) {
            return [];
        }
        return Database::linhas(
            'SELECT id, titulo, resumo FROM faq_artigos WHERE publicado = 1 AND categoria_id = ? AND id <> ?
              ORDER BY visualizacoes DESC LIMIT ' . max(1, $limite),
            [$artigo['categoria_id'], $artigo['id']]
        );
    }
}
