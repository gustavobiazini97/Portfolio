<?php
// Conta de utilizador. Toda a lógica de registo e login vive aqui (não nos controllers).

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

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

    // Apaga a conta de vez, confirmando a palavra-passe. As tabelas ligadas (vistos, comentários, biblioteca, foto,
    // amizades, convites, avisos...) apagam-se sozinhas (ON DELETE CASCADE); só o par_id dos outros não tem chave, por isso limpa-se à mão.
    public function apagarConta(string $password): void
    {
        if (!password_verify($password, $this->password_hash)) {
            throw new InvalidArgumentException('A palavra-passe não está certa.');
        }
        $this->eliminar();
    }

    // Apaga a conta sem pedir palavra-passe (o backoffice usa isto depois de confirmar quem é admin)
    public function eliminar(): void
    {
        \Illuminate\Database\Capsule\Manager::connection()->transaction(function () {
            static::where('par_id', $this->id)->update(['par_id' => null]);   // quem tinha esta conta como par fica sem par
            $this->delete();
        });
    }

    // Regista que a pessoa abriu a app (no máximo de 5 em 5 minutos, para não escrever na base de dados a cada pedido)
    public function tocar(): void
    {
        if ($this->ultimo_acesso === null || strtotime($this->ultimo_acesso) < time() - 300) {
            static::where('id', $this->id)->update(['ultimo_acesso' => date('Y-m-d H:i:s')]);
        }
    }

    public function ehAdmin(): bool
    {
        return (int) $this->admin === 1;
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

    // O par: a pessoa com quem partilhas séries (users.par_id); null se ainda não tens par
    public function parceiro(): ?User
    {
        return $this->par_id ? static::find($this->par_id) : null;
    }

    // Pessoas com quem podes partilhar séries: o par primeiro, depois os amigos (por ordem alfabética)
    public function ligados()
    {
        $lista = collect();
        if ($par = $this->parceiro()) {
            $lista->push($par);
        }
        return $lista->concat(Amizade::amigosDe($this)->reject(fn ($a) => $a->id === $this->par_id))->values();
    }

    // O par ou um amigo?
    public function ligadoA(User $outro): bool
    {
        return $outro->id !== $this->id && ($this->par_id === $outro->id || Amizade::sao($this, $outro));
    }

    // Ainda falta o par se registar? (só a primeira conta, enquanto não chega o máximo de contas do casal)
    public function esperaPar(): bool
    {
        return $this->par_id === null && static::count() < (Database::config()['max_contas'] ?? 2);
    }

    // O registo só está aberto enquanto houver menos contas do que o máximo
    public static function registoAberto(): bool
    {
        return static::count() < (Database::config()['max_contas'] ?? 2);
    }

    // Valida nome, utilizador e palavra-passe de uma conta nova (registo ou backoffice); devolve os valores limpos
    public static function validarNovos(array $dados): array
    {
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

        return ['nome' => $nome, 'username' => $username, 'password' => $password];
    }

    // Cria uma conta nova; lança InvalidArgumentException com a mensagem para o utilizador
    // $convite = link de convite válido (Amizade::convite): abre o registo e liga a conta ao amigo que convidou
    public static function registar(array $dados, ?object $convite = null): User
    {
        if ($convite === null && !static::registoAberto()) {
            throw new InvalidArgumentException('O registo está fechado: já existem as duas contas.');
        }

        ['nome' => $nome, 'username' => $username, 'password' => $password] = static::validarNovos($dados);

        $user = static::create([
            'nome'          => $nome,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'novidades_vistas' => Novidade::ultima(),   // conta nova: começa sem novidades por ver
        ]);

        if ($convite !== null) {
            // Amigo convidado: biblioteca vazia e amigo de quem convidou (sem par)
            Amizade::usarConvite($convite, $user);
            return $user;
        }

        // Segunda conta do casal: ficam par uma da outra
        $primeiro = static::where('id', '!=', $user->id)->orderBy('id')->first();
        if ($primeiro !== null) {
            static::where('id', $primeiro->id)->update(['par_id' => $user->id]);
            $user->par_id = $primeiro->id;
            $user->save();
        }

        // As séries do seed (Naruto, Shippuden, Boruto) começam na biblioteca do casal (conjuntas)
        $agora = date('Y-m-d H:i:s');
        $linhas = Serie::whereNull('adicionada_por')->pluck('id')
            ->map(fn ($id) => ['user_id' => $user->id, 'serie_id' => $id, 'estado' => 'a_ver', 'desde' => $agora])->all();
        Capsule::table('bibliotecas')->insertOrIgnore($linhas);

        // As do seed passam a ser vistas pelo casal juntos
        if ($primeiro !== null) {
            foreach (array_column($linhas, 'serie_id') as $serieId) {
                Capsule::table('series_juntos')->insertOrIgnore([
                    ['serie_id' => $serieId, 'user_id' => $user->id,     'com_id' => $primeiro->id],
                    ['serie_id' => $serieId, 'user_id' => $primeiro->id, 'com_id' => $user->id],
                ]);
            }
        }

        return $user;
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
