<?php
// API: perfil (nome, utilizador, palavra-passe, foto, cores, privacidade, apagar conta) e notificações.

class PerfilApi extends ApiController
{
    // PUT /perfil  { nome, username }
    public function guardar(): void
    {
        $user = $this->exigirToken();

        // As validações vivem no Model; um erro lança InvalidArgumentException → 422
        $user->alterarNome((string) $this->campo('nome', $user->nome));
        $user->alterarUsername((string) $this->campo('username', $user->username));

        $this->json(['ok' => true, 'mensagem' => 'Dados atualizados.', 'user' => $this->userJson($user)]);
    }

    // PUT /perfil/password  { atual, nova, confirmar }
    public function password(): void
    {
        $user = $this->exigirToken();
        $user->alterarPassword(
            (string) $this->campo('atual', ''),
            (string) $this->campo('nova', ''),
            (string) $this->campo('confirmar', '')
        );

        // Credenciais novas: os outros telemóveis e browsers têm de entrar outra vez (este continua)
        Token::revogarOutros($user, $this->tokenAtual());
        SessaoLonga::terminarTodas($user);   // sessões mantidas no site

        $this->json(['ok' => true, 'mensagem' => 'Palavra-passe alterada.']);
    }

    // PUT /perfil/paleta  { paleta: 0–5 } — as cores da app (só para ti; as mesmas do site)
    public function paleta(): void
    {
        $user = $this->exigirToken();
        $user->alterarPaleta((int) $this->campo('paleta', 0));   // paleta inexistente → 422
        $this->ok('Cores guardadas.', ['paleta' => (int) $user->paleta]);
    }

    // PUT /perfil/privacidade  { so_juntos: bool } — os amigos só veem as séries que vês com eles (o par vê sempre tudo)
    public function privacidade(): void
    {
        $user = $this->exigirToken();
        $user->alterarSoJuntos((bool) $this->campo('so_juntos', false));
        $this->ok('Privacidade guardada.', ['soJuntos' => (bool) $user->so_juntos]);
    }

    // POST /perfil/apagar  { password } — apaga a conta e tudo o que lhe pertence (os tokens vão com ela)
    public function apagarConta(): void
    {
        $user = $this->exigirToken();
        $user->apagarConta((string) $this->campo('password', ''));   // palavra-passe errada → 422
        $this->ok('A tua conta foi apagada.');
    }

    // POST /perfil/foto  (multipart, campo "foto")
    public function enviarFoto(): void
    {
        $user = $this->exigirToken();
        $ficheiro = $_FILES['foto'] ?? null;

        // Erros de envio do PHP (nada escolhido, grande demais, envio cortado)
        if ($ficheiro === null || $ficheiro['error'] === UPLOAD_ERR_NO_FILE) {
            $this->erro('Escolhe uma foto primeiro.');
        }
        if (in_array($ficheiro['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $this->erro('Essa foto é grande demais.');
        }
        if ($ficheiro['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($ficheiro['tmp_name'])) {
            $this->erro('A foto não chegou bem. Tenta outra vez.');
        }

        $user->definirFoto($ficheiro['tmp_name']);   // corta, reduz e guarda na base de dados
        $this->json(['ok' => true, 'mensagem' => 'Foto atualizada.', 'user' => $this->userJson($user)]);
    }

    // DELETE /perfil/foto
    public function removerFoto(): void
    {
        $user = $this->exigirToken();
        $user->removerFoto();
        $this->json(['ok' => true, 'mensagem' => 'Foto removida.', 'user' => $this->userJson($user)]);
    }

    // GET /utilizadores/{id}/foto — a imagem em si (só com token, como no site)
    public function foto(string $id): void
    {
        $this->exigirToken();
        $foto = Foto::find((int) $id);

        // Null guard: sem foto → 404 sem corpo
        if ($foto === null) {
            http_response_code(404);
            exit;
        }

        // O URL tem ?v=<data>, por isso a app pode guardar em cache sem medo
        header('Content-Type: ' . $foto->tipo);
        header('Content-Length: ' . strlen($foto->imagem));
        header('Cache-Control: private, max-age=31536000, immutable');
        echo $foto->imagem;
        exit;
    }

    // GET /perfil/notificacoes — o que queres receber e por onde
    public function notificacoes(): void
    {
        $user = $this->exigirToken();
        $this->json(['ok' => true, 'preferencias' => $this->prefJson(Preferencia::de($user))]);
    }

    // PUT /perfil/notificacoes  { email, ep_push, ep_email, com_push, com_email }
    public function guardarNotificacoes(): void
    {
        $user = $this->exigirToken();
        $pref = Preferencia::guardar($user, $this->corpo());   // valida o email → 422 se falhar
        $this->json(['ok' => true, 'mensagem' => 'Notificações guardadas.', 'preferencias' => $this->prefJson($pref)]);
    }

    // Preferências com tipos certos (booleanos em vez de 0/1)
    private function prefJson(Preferencia $p): array
    {
        return [
            'email'     => $p->email,
            'ep_push'   => (bool) $p->ep_push,
            'ep_email'  => (bool) $p->ep_email,
            'com_push'  => (bool) $p->com_push,
            'com_email' => (bool) $p->com_email,
        ];
    }
}
