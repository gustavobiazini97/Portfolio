<?php
// Token de acesso da API (a app Flutter não usa a sessão do browser).
// O telemóvel guarda o token; a base de dados só guarda o sha256 dele,
// por isso uma fuga da tabela não dá acesso a nenhuma conta.

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'tokens';
    public $timestamps = false;

    protected $fillable = ['user_id', 'token_hash', 'dispositivo', 'criado_em', 'usado_em', 'expira_em'];

    // Nunca expor o hash em arrays/JSON
    protected $hidden = ['token_hash'];

    // Dias sem uso até o token expirar (cada uso empurra a data para a frente)
    const DIAS = 90;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Hash guardado na base de dados para um token em texto
    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    // Cria um token novo para a pessoa e devolve-o em texto (é a única vez que existe em claro)
    public static function criar(User $user, ?string $dispositivo = null): string
    {
        $token = bin2hex(random_bytes(32));    // 64 caracteres hexadecimais, impossível de adivinhar
        $agora = date('Y-m-d H:i:s');

        // Nome do telemóvel: limpo e cortado ao tamanho da coluna
        $dispositivo = $dispositivo === null ? null : mb_substr(trim($dispositivo), 0, 80);

        static::create([
            'user_id'     => $user->id,
            'token_hash'  => self::hash($token),
            'dispositivo' => $dispositivo === '' ? null : $dispositivo,
            'criado_em'   => $agora,
            'usado_em'    => $agora,
            'expira_em'   => date('Y-m-d H:i:s', strtotime('+' . self::DIAS . ' days')),
        ]);

        // De vez em quando, limpa os tokens expirados de toda a gente
        if (random_int(1, 20) === 1) {
            static::where('expira_em', '<', $agora)->delete();
        }

        return $token;
    }

    // Procura o token; devolve o registo (com o user) se for válido, null caso contrário
    public static function validar(string $token): ?Token
    {
        // Formato errado nem chega à base de dados
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $registo = static::with('user')->where('token_hash', self::hash($token))->first();

        // Null guard: token desconhecido, expirado ou de uma conta apagada
        if ($registo === null || $registo->expira_em < date('Y-m-d H:i:s') || $registo->user === null) {
            return null;
        }

        // Renova a validade, no máximo uma vez por hora (evita uma escrita em cada pedido)
        if (strtotime($registo->usado_em) < time() - 3600) {
            $registo->usado_em  = date('Y-m-d H:i:s');
            $registo->expira_em = date('Y-m-d H:i:s', strtotime('+' . self::DIAS . ' days'));
            $registo->save();
        }

        return $registo;
    }

    // Termina a sessão deste telemóvel
    public static function revogar(string $token): void
    {
        static::where('token_hash', self::hash($token))->delete();
    }

    // Termina as sessões dos outros telemóveis (ex.: depois de mudar a palavra-passe)
    public static function revogarOutros(User $user, string $tokenAtual): void
    {
        static::where('user_id', $user->id)->where('token_hash', '!=', self::hash($tokenAtual))->delete();
    }
}
