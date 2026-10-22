<?php
// API: o sininho da app (🔔). A lógica vive no Model Notificacoes (partilhada com o site);
// aqui só se converte para JSON. O "já vi" e os filtros ficam guardados na própria app.

class NotificacoesApi extends ApiController
{
    // GET /notificacoes — { ok, notificacoes: [ {id, tipo, em, quem, texto, detalhe, serie, episodio, juntos, relacao, quantos} ] }
    public function lista(): void
    {
        $user = $this->exigirToken();

        $saida = array_map(fn ($n) => [
            'id'       => $n['id'],
            'tipo'     => $n['tipo'],                               // ep | com | convite | amigo
            'em'       => date(DATE_ATOM, strtotime($n['ts'])),     // ISO 8601, com fuso
            'quem'     => $this->userJson($n['quem']),
            'texto'    => $n['texto'],                              // "Andreia viu os episódios 12 a 14"
            'detalhe'  => $n['detalhe'],                            // nome da série, comentário…
            'serie'    => $n['serie'],                              // slug, ou null
            'episodio' => $n['episodio'],                           // número, ou null
            'juntos'   => $n['juntos'],                             // ep: série vista contigo?
            'relacao'  => $n['relacao'],                            // ep: par | amigo
            'quantos'  => $n['quantos'],                            // ep: episódios nesta sessão (resumo do Início)
        ], Notificacoes::lista($user));

        $this->json(['ok' => true, 'notificacoes' => $saida]);
    }
}
