<?php
// API: entrar, criar conta, sair e "quem sou eu". A lógica continua no Model User;
// aqui só se troca o redirect/flash do site por JSON e a sessão por um token.

class AuthApi extends ApiController
{
    // GET /auth/estado — público: diz à app se ainda dá para criar conta
    public function estado(): void
    {
        $this->json([
            'ok'            => true,
            'registoAberto' => User::registoAberto(),
        ]);
    }

    // POST /auth/login  { username, password, dispositivo? }
    public function login(): void
    {
        $user = User::autenticar((string) $this->campo('username', ''), (string) $this->campo('password', ''));

        // Null guard: mesma mensagem para utilizador inexistente e palavra-passe errada
        if ($user === null) {
            $this->erro('Utilizador ou palavra-passe errados.', 401);
        }

        $this->json([
            'ok'    => true,
            'token' => Token::criar($user, $this->campo('dispositivo')),
            'user'  => $this->userJson($user),
        ]);
    }

    // POST /auth/registo  { nome, username, password, password_confirmar, dispositivo? }
    public function registo(): void
    {
        // User::registar lança InvalidArgumentException com a mensagem certa (apanhada no api.php → 422)
        $user = User::registar($this->corpo());

        $this->json([
            'ok'    => true,
            'token' => Token::criar($user, $this->campo('dispositivo')),
            'user'  => $this->userJson($user),
        ], 201);
    }

    // POST /auth/logout — termina a sessão só neste telemóvel
    public function logout(): void
    {
        $this->exigirToken();
        Token::revogar($this->tokenAtual());
        $this->json(['ok' => true, 'mensagem' => 'Sessão terminada.']);
    }

    // GET /eu — tu e o teu par (a app chama isto ao abrir, para saber se o token ainda vale)
    public function eu(): void
    {
        $user = $this->exigirToken();
        $this->json([
            'ok'       => true,
            'user'     => $this->userJson($user),
            'parceiro' => $this->userJson($user->parceiro()),
        ]);
    }
}
