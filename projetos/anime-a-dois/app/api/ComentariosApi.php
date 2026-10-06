<?php
// API: comentários de um episódio (ler, escrever, apagar os teus).

class ComentariosApi extends ApiController
{
    // GET /episodios/{id}/comentarios
    public function lista(string $id): void
    {
        $user = $this->exigirToken();
        $episodio = $this->episodioOu404((int) $id);
        $this->responderLista($episodio, $user);
    }

    // POST /episodios/{id}/comentarios  { texto } — responde com a lista atualizada
    public function criar(string $id): void
    {
        $user = $this->exigirToken();
        $episodio = $this->episodioOu404((int) $id);

        // Comentario::escrever valida (vazio, tamanho) e lança InvalidArgumentException → 422
        $comentario = Comentario::escrever($user, $episodio, (string) $this->campo('texto', ''));
        Notificador::comentario($user, $comentario);   // avisa o par, conforme as preferências dele

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

    // Lista de comentários do episódio, do mais antigo para o mais recente
    private function responderLista(Episodio $episodio, User $user, int $estado = 200): never
    {
        $lista = Comentario::with('autor')
            ->where('episodio_id', $episodio->id)
            ->orderBy('criado_em')->orderBy('id')
            ->get();

        $this->json([
            'ok'          => true,
            'episodio'    => ['id' => (int) $episodio->id, 'numero' => (int) $episodio->numero, 'titulo' => $episodio->titulo],
            'comentarios' => $lista->map(fn (Comentario $c) => $this->comentarioJson($c, $user))->all(),
            'max'         => Comentario::MAX,   // a app usa isto como limite do campo de texto
        ], $estado);
    }

    // Episódio pelo id, ou 404 em JSON
    private function episodioOu404(int $id): Episodio
    {
        $episodio = Episodio::find($id);
        if ($episodio === null) {
            $this->erro('Esse episódio não existe.', 404);
        }
        return $episodio;
    }
}
