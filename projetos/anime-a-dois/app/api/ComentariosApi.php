<?php
// API: comentários de um episódio. Como no site, só para séries da tua biblioteca, e cada pessoa
// só vê os seus comentários e os de quem vê essa série com ela (comentários isolados).

class ComentariosApi extends ApiController
{
    // GET /episodios/{id}/comentarios
    public function lista(string $id): void
    {
        $user = $this->exigirToken();
        $episodio = $this->episodioDaBiblioteca((int) $id, $user);
        $this->responderLista($episodio, $user);
    }

    // POST /episodios/{id}/comentarios  { texto } — responde com a lista atualizada
    public function criar(string $id): void
    {
        $user = $this->exigirToken();
        $episodio = $this->episodioDaBiblioteca((int) $id, $user);

        // Comentario::escrever valida (vazio, tamanho) e lança InvalidArgumentException → 422
        $comentario = Comentario::escrever($user, $episodio, (string) $this->campo('texto', ''));
        Notificador::comentario($user, $comentario);   // avisa os companheiros da série, conforme as preferências deles

        $this->responderLista($episodio, $user, 201);
    }

    // DELETE /comentarios/{id} — só o próprio autor pode apagar
    public function apagar(string $id): void
    {
        $user = $this->exigirToken();
        $comentario = Comentario::find((int) $id);

        // Null guard + dono: comentário inexistente ou de outra pessoa
        if ($comentario === null) {
            $this->erro('Esse comentário já não existe.', 404);
        }
        if ((int) $comentario->user_id !== (int) $user->id) {
            $this->erro('Só podes apagar os teus comentários.', 403);
        }

        $episodio = $comentario->episodio;
        $comentario->delete();
        $this->responderLista($episodio, $user);
    }

    // Lista do episódio: só os teus e os dos teus companheiros nesta série, do mais antigo para o mais recente
    private function responderLista(Episodio $episodio, User $user, int $estado = 200): never
    {
        $autores = array_merge([$user->id], $episodio->serie->companheirosDe($user)->pluck('id')->all());
        $lista = Comentario::with('autor')
            ->where('episodio_id', $episodio->id)
            ->whereIn('user_id', $autores)
            ->orderBy('criado_em')->orderBy('id')
            ->get();

        $this->json([
            'ok'          => true,
            'episodio'    => ['id' => (int) $episodio->id, 'numero' => (int) $episodio->numero, 'titulo' => $episodio->titulo],
            'comentarios' => $lista->map(fn (Comentario $c) => $this->comentarioJson($c, $user))->all(),
            'max'         => Comentario::MAX,   // a app usa isto como limite do campo de texto
        ], $estado);
    }

    // Episódio pelo id, de uma série da tua biblioteca (404 / 403 em JSON)
    private function episodioDaBiblioteca(int $id, User $user): Episodio
    {
        $episodio = Episodio::find($id);
        if ($episodio === null || $episodio->serie === null) {
            $this->erro('Esse episódio não existe.', 404);
        }
        if (!$episodio->serie->naBibliotecaDe($user)) {
            $this->erro('Essa série não está na tua biblioteca.', 403);
        }
        return $episodio;
    }
}
