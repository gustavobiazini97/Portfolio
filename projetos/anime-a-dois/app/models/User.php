<?php
// Conta de utilizador. Toda a lógica de registo e login vive aqui (não nos controllers).

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = ['nome', 'username', 'password_hash'];

    // Nunca expor o hash se o Model for convertido em array/JSON
    protected $hidden = ['password_hash'];

    // Episódios que este utilizador marcou como vistos (tabela vistos)
    public function vistos()
    {
        return $this->belongsToMany(Episodio::class, 'vistos', 'user_id', 'episodio_id')
                    ->withPivot('visto_em');
    }

    // A outra conta (a app só tem duas); null enquanto o parceiro não se registar
    public function parceiro(): ?User
    {
        return static::where('id', '!=', $this->id)->orderBy('id')->first();
    }

    // O registo só está aberto enquanto houver menos contas do que o máximo
    public static function registoAberto(): bool
    {
        return static::count() < (Database::config()['max_contas'] ?? 2);
    }

    // Cria uma conta nova; lança InvalidArgumentException com a mensagem para o utilizador
    public static function registar(array $dados): User
    {
        if (!static::registoAberto()) {
            throw new InvalidArgumentException('O registo está fechado: já existem as duas contas.');
        }

        // Limpeza: espaços a mais fora; username sempre em minúsculas
        $nome      = trim($dados['nome'] ?? '');
        $username  = strtolower(trim($dados['username'] ?? ''));
        $password  = $dados['password'] ?? '';
        $confirmar = $dados['password_confirmar'] ?? '';

        // Validações, pela ordem em que aparecem no formulário
        $tamNome = mb_strlen($nome);
        if ($tamNome < 2 || $tamNome > 40) {
            throw new InvalidArgumentException('O nome tem de ter entre 2 e 40 caracteres.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            throw new InvalidArgumentException('O utilizador só pode ter letras, números, ponto, hífen ou _ (3 a 30).');
        }
        if (static::where('username', $username)->exists()) {
            throw new InvalidArgumentException('Esse nome de utilizador já está em uso.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A palavra-passe tem de ter pelo menos 8 caracteres.');
        }
        if ($password !== $confirmar) {
            throw new InvalidArgumentException('As palavras-passe não coincidem.');
        }

        return static::create([
            'nome'          => $nome,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    // Devolve o utilizador se as credenciais estiverem certas; null caso contrário
    public static function autenticar(string $username, string $password): ?User
    {
        $user = static::where('username', strtolower(trim($username)))->first();

        // Null guard: utilizador inexistente ou palavra-passe errada dão a mesma resposta
        if ($user === null || !password_verify($password, $user->password_hash)) {
            return null;
        }

        // Se o PHP passar a usar um algoritmo melhor, o hash é atualizado no próximo login
        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }

        return $user;
    }
}
