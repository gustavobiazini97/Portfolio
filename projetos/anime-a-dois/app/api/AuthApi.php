<?php
// API: entrar, criar conta (do casal, ou de um amigo por link de convite), sair e "quem sou eu".
// A lógica continua no Model User; aqui só se troca o redirect/flash do site por JSON e a sessão por um token.

class AuthApi extends ApiController
{
    // GET /auth/estado[?convite=<código ou link>] — público: diz à app se dá para criar conta
    public function estado(): void
    {
        $convite = $this->convite($_GET['convite'] ?? null);
        $this->json([
            'ok'            => true,
            'registoAberto' => User::registoAberto(),
            // Com um convite válido o registo abre e a conta fica amiga de quem convidou
            'convite'       => $convite ? ['de' => $this->userJson($convite->de), 'validoAte' => date(DATE_ATOM, strtotime($convite->expira_em))] : null,
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

    // POST /auth/registo  { nome, username, password, password_confirmar, convite?, dispositivo? }
    public function registo(): void
    {
        // Convite: aceita o código sozinho ou o link inteiro colado (como no site)
        $texto = trim((string) $this->campo('convite', ''));
        $convite = $texto === '' ? null : $this->convite($texto);
        if ($texto !== '' && $convite === null) {
            $this->erro('Esse link de convite já não é válido. Pede um novo.');
        }

        // User::registar valida tudo e lança InvalidArgumentException (apanhada no api.php → 422)
        $user = User::registar($this->corpo(), $convite);

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
        $this->ok('Sessão terminada.');
    }

    // GET /eu — tu, o teu par, as definições e a quem podes convidar (a app chama isto ao abrir)
    public function eu(): void
    {
        $user = $this->exigirToken();
        $this->json([
            'ok'            => true,
            'user'          => $this->userJson($user),
            'parceiro'      => $this->userJson($user->parceiro()),
            'paleta'        => (int) $user->paleta,
            'paletas'       => User::PALETAS,
            'soJuntos'      => (bool) $user->so_juntos,
            'admin'         => $user->ehAdmin(),
            // Par + amigos: a quem podes convidar para ver uma série contigo
            'ligados'       => $user->ligados()->map(fn ($u) => $this->userJson($u))->values()->all(),
            'pedidosAmigos' => Amizade::pedidosRecebidos($user)->count(),
        ]);
    }

    // Convite válido a partir do código ou do link inteiro (apanha os 32 caracteres hexadecimais)
    private function convite(?string $texto): ?object
    {
        if ($texto === null || !preg_match('/[a-f0-9]{32}/i', $texto, $m)) {
            return null;
        }
        return Amizade::convite(strtolower($m[0]));
    }
}
