<?php
// API: biblioteca — adicionar séries (a pesquisa no AniList/Kitsu/Jikan é feita no telemóvel, como no site),
// mudar o estado, convidar o par/um amigo, aceitar ou recusar convites, juntar-se a séries de quem está
// ligado a ti, deixar de ver juntos e tirar séries. A lógica vive no Model Serie.

class BibliotecaApi extends ApiController
{
    // POST /biblioteca  { mal_id, info: {...}, episodios: [{n, titulo, filler, recap}], fonte, modo: ver|propor, com? }
    //   modo "propor" = fica na tua biblioteca e a pessoa "com" (par ou amigo) recebe um convite "Quero ver contigo"
    public function adicionar(): void
    {
        $user = $this->exigirToken();

        $malId     = (int) $this->campo('mal_id', 0);
        $info      = DadosAnime::info((array) $this->campo('info', []), $malId);      // valida tudo (→ 422 se falhar)
        $episodios = DadosAnime::episodios((array) $this->campo('episodios', []));

        $convidarA = $this->campo('modo', 'ver') === 'propor' ? $this->ligado((int) $this->campo('com', 0), $user) : null;
        $serie = Serie::adicionarDoMal($user, $info, $episodios, $convidarA, $this->campo('fonte') === 'jikan');

        // Séries só tuas não incomodam ninguém; o convite sim
        if ($convidarA !== null) {
            Notificador::serie($user, $serie, 'convite', $convidarA);
        }

        $this->ok(
            $convidarA ? $serie->nomeCurto() . ' entrou na tua biblioteca e o convite foi enviado a ' . $convidarA->nome . '.'
                       : $serie->nomeCurto() . ' entrou na tua biblioteca.',
            ['serie' => $this->serieBase($serie)],
            201
        );
    }

    // PUT /series/{slug}/estado  { estado: a_ver | pausa | acabado } — na TUA biblioteca
    public function estado(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $estado = (string) $this->campo('estado', '');
        $serie->definirEstado($user, $estado);
        $this->ok($serie->nomeCurto() . ': ' . mb_strtolower(Serie::estadoTexto($estado)) . '.', ['estado' => $estado]);
    }

    // POST /series/{slug}/convites  { com: id } — convida o par ou um amigo a ver contigo uma série que já tens
    public function convidar(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $para  = $this->ligado((int) $this->campo('com', 0), $user);
        $serie->convidar($user, $para);
        Notificador::serie($user, $serie, 'convite', $para);
        $this->ok('Convite enviado a ' . $para->nome . ': ' . $serie->nomeCurto() . '.');
    }

    // POST /series/{slug}/convites/aceitar  { de: id } — a série entra na tua biblioteca e passam a vê-la juntos
    public function aceitar(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $de    = $this->ligado((int) $this->campo('de', 0), $user);
        $serie->aceitar($user, $de);
        Notificador::serie($user, $serie, 'aceite', $de);
        $this->ok($serie->nomeCurto() . ': agora vês com ' . $de->nome . '.');
    }

    // DELETE /series/{slug}/convites/{id} — recusar um convite recebido de {id}, ou cancelar o que lhe enviaste
    public function recusar(string $slug, string $id): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $serie->retirarConvite($user, $this->ligado((int) $id, $user));
        $this->ok('Convite de ' . $serie->nomeCurto() . ' retirado.');
    }

    // POST /series/{slug}/juntar  { com?: id } — entras numa série de alguém ligado a ti.
    //   sem "com" = fica só na tua biblioteca; com "com" = passam a vê-la juntos (ele é avisado)
    public function juntar(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $com   = (int) $this->campo('com', 0) > 0 ? $this->ligado((int) $this->campo('com'), $user) : null;
        $serie->juntarSe($user, $com);
        if ($com !== null) {
            Notificador::serie($user, $serie, 'juntou', $com);
        }
        $this->ok($com ? $serie->nomeCurto() . ' entrou na tua biblioteca e vês com ' . $com->nome . '.'
                       : $serie->nomeCurto() . ' entrou na tua biblioteca.');
    }

    // DELETE /series/{slug}/juntos/{id} — tu e {id} deixam de ver a série juntos (cada um fica com a sua)
    public function separar(string $slug, string $id): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $com   = $this->ligado((int) $id, $user);
        $serie->deixarDeVerJuntos($user, $com);
        $this->ok($serie->nomeCurto() . ': tu e ' . $com->nome . ' passam a ver cada um a sua.');
    }

    // DELETE /series/{slug} — tira da TUA biblioteca (só se ainda não marcaste episódios dela)
    public function remover(string $slug): void
    {
        $user  = $this->exigirToken();
        $serie = $this->serieOu404($slug);
        $nome  = $serie->nomeCurto();
        $serie->removerDe($user);
        $this->ok($nome . ' saiu da tua biblioteca.');
    }
}
