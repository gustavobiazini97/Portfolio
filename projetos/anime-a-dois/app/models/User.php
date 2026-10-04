<?php
// Conta de utilizador. Toda a lógica de registo e login vive aqui (não nos controllers).

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = ['nome', 'username', 'password_hash', 'novidades_vistas'];

    // Nunca expor o hash se o Model for convertido em array/JSON
    protected $hidden = ['password_hash'];

    // Episódios que este utilizador marcou como vistos (tabela vistos)
    public function vistos()
    {
        return $this->belongsToMany(Episodio::class, 'vistos', 'user_id', 'episodio_id')
                    ->withPivot('visto_em');
    }

    // Último episódio marcado (em qualquer série), com a série carregada; null se ainda nenhum.
    // A data em que foi marcado fica em $episodio->pivot->visto_em
    public function ultimoVisto(): ?Episodio
    {
        return $this->vistos()
                    ->with('serie')
                    ->orderByPivot('visto_em', 'desc')
                    ->orderBy('episodios.numero', 'desc')   // desempate quando têm a mesma hora
                    ->first();
    }

    // ---------- Perfil ----------

    // Dados da foto SEM os bytes (só para saber se existe e a data, que entra no URL)
    public function foto()
    {
        return $this->hasOne(Foto::class, 'user_id')->select(['user_id', 'tipo', 'atualizada_em']);
    }

    // URL da foto de perfil, ou null se não tiver; ?v muda a cada troca de foto
    public function fotoUrl(): ?string
    {
        $foto = $this->foto;
        return $foto ? url('perfil', 'foto', ['id' => $this->id, 'v' => strtotime($foto->atualizada_em)]) : null;
    }

    // Inicial para o avatar sem foto
    public function inicial(): string
    {
        return mb_strtoupper(mb_substr($this->nome, 0, 1));
    }

    // Guarda (ou troca) a foto a partir de um ficheiro enviado
    public function definirFoto(string $caminho): void
    {
        [$bytes, $tipo] = Foto::processar($caminho);
        Foto::updateOrCreate(
            ['user_id' => $this->id],
            ['imagem' => $bytes, 'tipo' => $tipo, 'atualizada_em' => date('Y-m-d H:i:s')]
        );
        $this->unsetRelation('foto');
    }

    // Tira a foto (volta a aparecer a inicial)
    public function removerFoto(): void
    {
        Foto::where('user_id', $this->id)->delete();
        $this->unsetRelation('foto');
    }

    // Muda o nome que aparece na app
    public function alterarNome(string $nome): void
    {
        $nome = trim($nome);
        $tam = mb_strlen($nome);
        if ($tam < 2 || $tam > 40) {
            throw new InvalidArgumentException('O nome tem de ter entre 2 e 40 caracteres.');
        }
        $this->nome = $nome;
        $this->save();
    }

    // Muda o nome de utilizador (o que se usa para entrar); mesmas regras do registo
    public function alterarUsername(string $username): void
    {
        $username = strtolower(trim($username));
        if ($username === $this->username) {
            return;
        }
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            throw new InvalidArgumentException('O utilizador só pode ter letras, números, ponto, hífen ou _ (3 a 30).');
        }
        if (static::where('username', $username)->where('id', '!=', $this->id)->exists()) {
            throw new InvalidArgumentException('Esse nome de utilizador já está em uso.');
        }
        $this->username = $username;
        $this->save();
    }

    // Muda a palavra-passe, confirmando a atual
    public function alterarPassword(string $atual, string $nova, string $confirmar): void
    {
        if (!password_verify($atual, $this->password_hash)) {
            throw new InvalidArgumentException('A palavra-passe atual não está certa.');
        }
        if (strlen($nova) < 8) {
            throw new InvalidArgumentException('A nova palavra-passe tem de ter pelo menos 8 caracteres.');
        }
        if ($nova !== $confirmar) {
            throw new InvalidArgumentException('As palavras-passe novas não coincidem.');
        }
        $this->password_hash = password_hash($nova, PASSWORD_DEFAULT);
        $this->save();
    }

    // Fecha o popup das novidades: fica tudo visto até à próxima atualização
    public function marcarNovidadesVistas(): void
    {
        $this->novidades_vistas = Novidade::ultima();
        $this->save();
    }

    // Indica se este episódio já está marcado como visto
    public function viu(Episodio $episodio): bool
    {
        return $this->vistos()->where('episodios.id', $episodio->id)->exists();
    }

    // Marca (ou desmarca) vários episódios de uma vez; devolve os IDs que mudaram de estado.
    // Ao marcar, só insere os que ainda não estavam vistos (os antigos mantêm a data original).
    public function definirVistos(array $ids, bool $visto): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        if (!$visto) {
            $tinha = $this->vistos()->whereIn('episodios.id', $ids)->pluck('episodios.id')->all();
            $this->vistos()->detach($ids);
            return array_map('intval', $tinha);
        }

        // IDs que já estavam vistos: ficam como estão
        $jaVistos = $this->vistos()->whereIn('episodios.id', $ids)->pluck('episodios.id')->all();
        $novos = array_diff($ids, $jaVistos);

        $agora = date('Y-m-d H:i:s');
        $linhas = [];
        foreach ($novos as $id) {
            $linhas[$id] = ['visto_em' => $agora];
        }
        $this->vistos()->attach($linhas);

        return array_values($novos);
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
            'novidades_vistas' => Novidade::ultima(),   // conta nova: começa sem novidades por ver
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
