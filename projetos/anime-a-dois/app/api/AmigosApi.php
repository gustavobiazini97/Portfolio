<?php
// API: amigos — a lista, o link de convite (para quem ainda não tem conta), pedidos pelo utilizador exato,
// aceitar/recusar/desfazer e o perfil do par ou de um amigo (com a biblioteca dele, respeitando a privacidade).

class AmigosApi extends ApiController
{
    // GET /amigos — amigos, pedidos recebidos e enviados, e o link de convite em circulação
    public function lista(): void
    {
        $user    = $this->exigirToken();
        $convite = Amizade::conviteAtivo($user);

        $this->json([
            'ok'        => true,
            'parceiro'  => $this->userJson($user->parceiro()),
            'amigos'    => $this->pessoas(Amizade::amigosDe($user)),
            'recebidos' => $this->pessoas(Amizade::pedidosRecebidos($user)),
            'enviados'  => $this->pessoas(Amizade::pedidosEnviados($user)),
            'convite'   => $this->conviteJson($convite),
            'validadeDias' => Amizade::VALIDADE_DIAS,
        ]);
    }

    // POST /amigos/convite — gera um link novo (o anterior, se ainda não foi usado, deixa de servir)
    public function criarConvite(): void
    {
        $user = $this->exigirToken();
        Amizade::criarConvite($user);
        $this->ok('Link criado. Já podes enviá-lo.', ['convite' => $this->conviteJson(Amizade::conviteAtivo($user))]);
    }

    // POST /amigos/pedidos  { username } — pedido de amizade pelo utilizador exato
    public function pedir(): void
    {
        $user = $this->exigirToken();
        $resultado = Amizade::pedir($user, (string) $this->campo('username', ''), $para);   // erros → 422
        Notificador::amigos($user, $para, $resultado === 'amigos' ? 'aceite' : 'pedido');   // avisa a outra pessoa
        $this->ok($resultado === 'amigos' ? 'Ele já te tinha pedido: agora são amigos.' : 'Pedido enviado.', ['resultado' => $resultado]);
    }

    // POST /amigos/pedidos/{id}/aceitar — aceita o pedido de {id}
    public function aceitar(string $id): void
    {
        $user  = $this->exigirToken();
        $outro = User::find((int) $id);
        if ($outro === null) {
            $this->erro('Esse pedido já não existe.', 404);
        }
        Amizade::aceitar($user, $outro);
        Notificador::amigos($user, $outro, 'aceite');   // avisa quem tinha pedido
        $this->ok('Agora tu e ' . $outro->nome . ' são amigos.');
    }

    // DELETE /amigos/pedidos/{id} — recusa o pedido de {id}, ou cancela o que lhe enviaste
    public function recusar(string $id): void
    {
        $user  = $this->exigirToken();
        $outro = User::find((int) $id);
        if ($outro !== null) {
            Amizade::recusar($user, $outro);
        }
        $this->ok('Pedido retirado.');
    }

    // DELETE /amigos/{id} — deixa de ser amigo de {id} (nos dois sentidos)
    public function remover(string $id): void
    {
        $user  = $this->exigirToken();
        $outro = User::find((int) $id);
        if ($outro === null) {
            $this->erro('Essa pessoa já não existe.', 404);
        }
        Amizade::remover($user, $outro);
        $this->ok($outro->nome . ' já não é teu amigo.');
    }

    // GET /pessoas/{id} — perfil do teu par ou de um amigo: último episódio e biblioteca dele.
    // Se ele escolheu "só o que vemos juntos", aparecem só as séries que vê contigo (como no site).
    public function pessoa(string $id): void
    {
        $user   = $this->exigirToken();
        $par    = $user->parceiro();
        $pessoa = User::find((int) $id);

        // Null guard: só se vê o perfil do par e dos amigos (de mais ninguém)
        if ($pessoa === null || $pessoa->id === $user->id || ($par?->id !== $pessoa->id && !Amizade::sao($user, $pessoa))) {
            $this->erro('Não encontrei essa pessoa.', 404);
        }

        $biblioteca = Serie::biblioteca($pessoa);
        $soJuntos = !$pessoa->veTudo($user);
        if ($soJuntos) {
            $biblioteca = array_values(array_filter($biblioteca, fn ($i) =>
                in_array($user->id, array_map(fn ($c) => $c['user']->id, $i['companheiros']), true)));
        }

        // Para cada série dele: já a tens? vês com ele? (decide os botões "Juntar-me")
        $itens = array_map(function ($i) use ($user, $pessoa) {
            $j = $this->itemBibliotecaJson($i);
            $j['naTua']   = $i['serie']->naBibliotecaDe($user);
            $j['vesCom']  = $i['serie']->verCom($user, $pessoa);
            return $j;
        }, $biblioteca);

        $this->json([
            'ok'         => true,
            'pessoa'     => $this->userJson($pessoa),
            'ehPar'      => $par?->id === $pessoa->id,
            'ultimo'     => $this->ultimoJson($pessoa->ultimoVistoPara($user)),
            'soJuntos'   => $soJuntos,
            'biblioteca' => $itens,
        ]);
    }

    // Lista de Users → lista de pessoas da API
    private function pessoas($users): array
    {
        return collect($users)->map(fn ($u) => $this->userJson($u))->values()->all();
    }

    // Link de convite para partilhar (o link do site: abre o registo com o convite), ou null
    private function conviteJson(?object $convite): ?array
    {
        if ($convite === null) {
            return null;
        }
        return [
            'codigo'    => $convite->codigo,
            'link'      => url_absoluto() . url('auth', 'registo', ['convite' => $convite->codigo]),
            'validoAte' => date(DATE_ATOM, strtotime($convite->expira_em)),
        ];
    }
}
