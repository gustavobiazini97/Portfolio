<?php
// Um telemóvel/browser com notificações ativas (dados que o browser dá ao subscrever o push).

use Illuminate\Database\Eloquent\Model;

class Subscricao extends Model
{
    protected $table = 'subscricoes';
    public $timestamps = false;

    protected $fillable = ['user_id', 'endpoint', 'chave_hash', 'p256dh', 'auth', 'criado_em'];

    // Regista (ou atualiza) a subscrição enviada pelo JavaScript; valida o mínimo
    public static function registar(User $user, array $dados): Subscricao
    {
        $endpoint = $dados['endpoint'] ?? '';
        $p256dh   = $dados['keys']['p256dh'] ?? '';
        $auth     = $dados['keys']['auth'] ?? '';

        if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 1000 || $p256dh === '' || $auth === '' || !self::servicoConhecido($endpoint)) {
            throw new InvalidArgumentException('Subscrição inválida.');
        }

        return static::updateOrCreate(['chave_hash' => hash('sha256', $endpoint)], [
            'user_id'   => $user->id,          // se o telemóvel mudar de conta, passa para a nova
            'endpoint'  => $endpoint,
            'p256dh'    => $p256dh,
            'auth'      => $auth,
            'criado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    // Serviços de push dos browsers (Chrome/Android, Firefox, Edge, Safari). O servidor envia pedidos
    // para o endpoint: aceitar um endereço qualquer deixava alguém pô-lo a fazer pedidos onde quisesse (SSRF).
    const SERVICOS = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];

    private static function servicoConhecido(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        foreach (self::SERVICOS as $s) {
            if ($host === $s || str_ends_with($host, '.' . $s)) {
                return true;
            }
        }
        return false;
    }

    // Remove a subscrição deste telemóvel (desativar)
    public static function remover(User $user, string $endpoint): void
    {
        static::where('chave_hash', hash('sha256', $endpoint))->where('user_id', $user->id)->delete();
    }
}
