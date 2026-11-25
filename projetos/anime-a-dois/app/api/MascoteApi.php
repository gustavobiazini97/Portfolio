<?php
// API: o mascote do casal (o bicho que cresce com os episódios vistos pelos dois). A lógica vive no Model Mascote.
// A pessoa vem sempre do token: nenhuma rota recebe o id de um mascote nem de outro utilizador.

class MascoteApi extends ApiController
{
    // GET /mascote — o estado do bicho do teu par (fase, humor, espécie, nome, cor); mascote: null se ainda não tens par
    public function ver(): void
    {
        $user = $this->exigirToken();
        $this->json(['ok' => true, 'mascote' => Mascote::estado($user)]);
    }

    // PUT /mascote  { nome?, cor?, especie? } — a espécie só se escolhe uma vez e só depois de o ovo chocar
    public function guardar(): void
    {
        $user = $this->exigirToken();

        // Só os três campos conhecidos passam (nada de mass-assignment); os Models validam e lançam InvalidArgumentException (→ 422)
        $campos = array_intersect_key($this->corpo(), array_flip(['nome', 'cor', 'especie']));
        Mascote::guardar($user, $campos);

        $this->ok('Guardado.', ['mascote' => Mascote::estado($user)]);
    }
}
