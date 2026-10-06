<?php
// Base dos controllers da API: lê o corpo do pedido, responde em JSON e trata da autenticação por token.
// Ao contrário dos controllers do site, aqui não há sessões, views, flash nem CSRF:
// cada pedido traz o token no cabeçalho "Authorization: Bearer <token>".

abstract class ApiController
{
    // Token e utilizador do pedido atual (preenchidos por exigirToken)
    protected ?Token $token = null;
    protected ?string $tokenTexto = null;

    // Corpo do pedido já descodificado (lido uma única vez)
    private ?array $corpo = null;

    // ---------- Respostas ----------

    // Responde com JSON e termina o pedido
    protected function json(array $dados, int $estado = 200): never
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Erro com mensagem para mostrar ao utilizador (mesmo formato em toda a API)
    protected function erro(string $mensagem, int $estado = 422): never
    {
        $this->json(['ok' => false, 'mensagem' => $mensagem], $estado);
    }

    // ---------- Pedido ----------

    // Campos do corpo: aceita JSON (a app Flutter) ou formulário (multipart / x-www-form-urlencoded)
    protected function corpo(): array
    {
        if ($this->corpo !== null) {
            return $this->corpo;
        }

        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipo, 'application/json')) {
            $dados = json_decode((string) file_get_contents('php://input'), true);
            // JSON mal formado ou que não é um objeto → corpo vazio (as validações tratam do resto)
            $this->corpo = is_array($dados) ? $dados : [];
        } else {
            $this->corpo = $_POST;
        }
        return $this->corpo;
    }

    // Um campo do corpo, com valor por defeito
    protected function campo(string $nome, mixed $defeito = null): mixed
    {
        return $this->corpo()[$nome] ?? $defeito;
    }

    // ---------- Autenticação ----------

    // Lê o token do cabeçalho. Alguns servidores (PHP-FPM) escondem o Authorization,
    // por isso também se aceita X-Auth-Token, que a app envia sempre em paralelo.
    private function lerToken(): ?string
    {
        $cabecalho = $_SERVER['HTTP_AUTHORIZATION']
                  ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']    // depois de um RewriteRule no Apache
                  ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? null) : null);

        if ($cabecalho !== null && preg_match('/^Bearer\s+(\S+)$/i', $cabecalho, $m)) {
            return $m[1];
        }
        return $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
    }

    // Rotas privadas: sem token válido responde 401 e a app volta ao ecrã de login
    protected function exigirToken(): User
    {
        $texto = $this->lerToken();
        $token = $texto === null ? null : Token::validar($texto);

        // Null guard: sem token, token errado ou expirado
        if ($token === null) {
            $this->erro('Entra outra vez na tua conta.', 401);
        }

        $this->token = $token;
        $this->tokenTexto = $texto;
        return $token->user;
    }

    // Token em texto do pedido atual (para o logout e para revogar os outros)
    protected function tokenAtual(): string
    {
        return (string) $this->tokenTexto;
    }

    // ---------- Formatos comuns (o que a app recebe) ----------

    // Pessoa: nunca inclui o hash da palavra-passe
    protected function userJson(?User $u): ?array
    {
        if ($u === null) {
            return null;
        }
        $foto = $u->foto;   // só os metadados (sem os bytes)
        return [
            'id'       => (int) $u->id,
            'nome'     => $u->nome,
            'username' => $u->username,
            'inicial'  => $u->inicial(),
            // URL absoluto da foto; ?v muda a cada troca, para a app não mostrar a antiga da cache
            'foto'     => $foto ? self::urlApi('/utilizadores/' . $u->id . '/foto') . '?v=' . strtotime($foto->atualizada_em) : null,
        ];
    }

    // Série com o progresso dos dois e a frase do Vs
    protected function serieJson(Serie $s, User $tu, ?User $par): array
    {
        $meu  = $s->progressoDe($tu);
        $dele = $s->progressoDe($par);
        return [
            'slug'           => $s->slug,
            'nome'           => $s->nome,
            'nomeCurto'      => $s->nomeCurto(),
            'anos'           => $s->anos,
            'totalEpisodios' => (int) $s->total_episodios,
            'cor'            => $s->cor(),
            'tu'             => $meu,
            'par'            => $par ? $dele : null,
            'resumo'         => Serie::resumoVs($meu, $dele, $par),
        ];
    }

    // Último episódio visto por alguém (para o cartão "o teu par viu…")
    protected function ultimoJson(?Episodio $ep): ?array
    {
        if ($ep === null) {
            return null;
        }
        $quando = $ep->pivot->visto_em ?? null;
        return [
            'serie'   => $ep->serie->slug,
            'serieNome' => $ep->serie->nome,
            'numero'  => (int) $ep->numero,
            'titulo'  => $ep->titulo,
            'filler'  => (bool) $ep->filler,
            'vistoEm' => $quando ? date(DATE_ATOM, strtotime($quando)) : null,   // ISO 8601, com fuso
            'quando'  => tempo_relativo($quando),                               // "há 2 horas"
        ];
    }

    // Comentário como a app o mostra
    protected function comentarioJson(Comentario $c, User $quemVe): array
    {
        return [
            'id'      => (int) $c->id,
            'texto'   => $c->texto,
            'autor'   => $this->userJson($c->autor),
            'meu'     => (int) $c->user_id === (int) $quemVe->id,   // só os teus têm o botão de apagar
            'criadoEm'=> date(DATE_ATOM, strtotime($c->criado_em)),
            'quando'  => tempo_relativo($c->criado_em),
        ];
    }

    // Endereço absoluto de uma rota da API, ex.: https://animeadois.alwaysdata.net/api.php/series
    protected static function urlApi(string $rota): string
    {
        return url_absoluto() . 'api.php' . $rota;
    }
}
