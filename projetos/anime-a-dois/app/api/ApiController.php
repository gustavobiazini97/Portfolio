<?php
// Base dos controllers da API: lê o corpo do pedido, responde em JSON e trata da autenticação por token.
// Ao contrário dos controllers do site, aqui não há sessões, views, flash nem CSRF:
// cada pedido traz o token no cabeçalho "Authorization: Bearer <token>".
// As regras (bibliotecas, companheiros, amigos, privacidade) são as mesmas do site: vivem nos Models.

abstract class ApiController
{
    // Token e utilizador do pedido atual (preenchidos por exigirToken)
    protected ?Token $token = null;
    protected ?string $tokenTexto = null;

    // Corpo do pedido já descodificado (lido uma única vez)
    private ?array $corpo = null;

    // Cores das três séries do seed (as mesmas do app.css, [data-serie])
    const CORES_SEED = ['naruto' => '#F7C59F', 'shippuden' => '#F2A7A0', 'boruto' => '#A9C4EE'];

    // Cores de destaque das séries novas, por series.acento (1–8; as mesmas do app.css, [data-acento])
    const CORES_ACENTO = [1 => '#C9B6F2', 2 => '#9FD8D2', 3 => '#F4B6CF', 4 => '#F2D98C',
                          5 => '#A7D3F2', 6 => '#C7DFA0', 7 => '#F3B58F', 8 => '#B8C0F5'];

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

    // Sucesso simples: só a mensagem (e dados extra, se houver)
    protected function ok(string $mensagem, array $extra = [], int $estado = 200): never
    {
        $this->json(['ok' => true, 'mensagem' => $mensagem] + $extra, $estado);
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
        $token->user->tocar();   // "último acesso" para o backoffice (no máximo de 5 em 5 min)
        return $token->user;
    }

    // Token em texto do pedido atual (para o logout e para revogar os outros)
    protected function tokenAtual(): string
    {
        return (string) $this->tokenTexto;
    }

    // ---------- Procuras comuns ----------

    // Série pelo slug, ou 404 em JSON
    protected function serieOu404(string $slug): Serie
    {
        $serie = Serie::porSlug($slug);
        if ($serie === null) {
            $this->erro('Essa série não existe.', 404);
        }
        return $serie;
    }

    // Série pelo slug que tem de estar na tua biblioteca (como no site: senão não se abre)
    protected function serieDaBiblioteca(string $slug, User $user): Serie
    {
        $serie = $this->serieOu404($slug);
        if (!$serie->naBibliotecaDe($user)) {
            $this->erro($serie->nomeCurto() . ' não está na tua biblioteca.', 403);
        }
        return $serie;
    }

    // Utilizador pelo id, só se for o teu par ou um amigo teu (senão, erro para o utilizador)
    protected function ligado(int $id, User $tu): User
    {
        $outro = User::find($id);
        if ($outro === null || !$tu->ligadoA($outro)) {
            throw new InvalidArgumentException('Só podes partilhar séries com o teu par e com os teus amigos.');
        }
        return $outro;
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

    // Cor de destaque de uma série: a fixa das do seed, ou a do acento (séries novas)
    protected static function corDe(Serie $s): string
    {
        return self::CORES_SEED[$s->slug] ?? self::CORES_ACENTO[(int) $s->acento] ?? '#C9C2E0';
    }

    // Dados fixos de uma série (sem nada de nenhuma pessoa)
    protected function serieBase(Serie $s): array
    {
        return [
            'slug'           => $s->slug,
            'nome'           => $s->nome,
            'nomeCurto'      => $s->nomeCurto(),
            'anos'           => $s->anos,
            'totalEpisodios' => (int) $s->total_episodios,
            'capa'           => $s->capa,
            'tipo'           => $s->tipo,
            'emEmissao'      => (bool) $s->em_emissao,
            'minutosEp'      => $s->minutos_ep ? (int) $s->minutos_ep : null,
            'malId'          => $s->mal_id ? (int) $s->mal_id : null,
            'cor'            => self::corDe($s),
        ];
    }

    // Um item da fila de capas (Serie::biblioteca): série, estado, a tua % e a dos companheiros
    protected function itemBibliotecaJson(array $item): array
    {
        return [
            'serie'        => $this->serieBase($item['serie']),
            'estado'       => $item['estado'],
            'estadoTexto'  => Serie::estadoTexto($item['estado']),
            'conjunta'     => $item['conjunta'],
            'pct'          => (int) $item['tu'],
            'companheiros' => array_map(fn ($c) => ['user' => $this->userJson($c['user']), 'pct' => (int) $c['pct']], $item['companheiros']),
        ];
    }

    // Último episódio visto por alguém (para o cartão "o teu par viu…")
    protected function ultimoJson(?Episodio $ep): ?array
    {
        if ($ep === null || $ep->serie === null) {
            return null;
        }
        $quando = $ep->pivot->visto_em ?? null;
        return [
            'serie'     => $ep->serie->slug,
            'serieNome' => $ep->serie->nome,
            'numero'    => (int) $ep->numero,
            'titulo'    => $ep->titulo,
            'filler'    => (bool) $ep->filler,
            'vistoEm'   => $quando ? date(DATE_ATOM, strtotime($quando)) : null,   // ISO 8601, com fuso
            'quando'    => tempo_relativo($quando),                               // "há 2 horas"
        ];
    }

    // Comentário como a app o mostra
    protected function comentarioJson(Comentario $c, User $quemVe): array
    {
        return [
            'id'       => (int) $c->id,
            'texto'    => $c->texto,
            'autor'    => $this->userJson($c->autor),
            'meu'      => (int) $c->user_id === (int) $quemVe->id,   // só os teus têm o botão de apagar
            'criadoEm' => date(DATE_ATOM, strtotime($c->criado_em)),
            'quando'   => tempo_relativo($c->criado_em),
        ];
    }

    // Frase do Vs (igual à do Início do site): quem vai à frente e por quantos episódios.
    // Com vários companheiros compara com o mais avançado.
    protected static function resumoVs(array $meu, $companheiros, array $deles, bool $temLigados): string
    {
        $companheiros = collect($companheiros)->values();
        if ($companheiros->isEmpty()) {
            return $temLigados ? 'Vês esta série sozinho. Convida o teu par ou um amigo para verem juntos.' : 'Série só tua por agora.';
        }

        // O companheiro mais avançado
        $lider = 0;
        foreach ($deles as $i => $d) {
            if ($d['posicao'] > $deles[$lider]['posicao']) {
                $lider = $i;
            }
        }
        $dif = $meu['posicao'] - $deles[$lider]['posicao'];
        if ($dif > 0) {
            return 'Vais ' . plural($dif, 'episódio', 'episódios') . ' à frente.';
        }
        if ($dif < 0) {
            return $companheiros[$lider]->nome . ' vai ' . plural(-$dif, 'episódio', 'episódios') . ' à frente.';
        }
        return $companheiros->count() === 1 ? 'Estão os dois no mesmo episódio.' : 'Estão todos no mesmo episódio.';
    }

    // Endereço absoluto de uma rota da API, ex.: https://animeadois.alwaysdata.net/api.php/series
    protected static function urlApi(string $rota): string
    {
        return url_absoluto() . 'api.php' . $rota;
    }
}
