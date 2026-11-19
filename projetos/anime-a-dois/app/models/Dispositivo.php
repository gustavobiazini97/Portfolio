<?php
// Telemóveis com a app Android instalada que recebem notificações (token do Firebase Cloud Messaging).
// Um telemóvel = um token; se outra pessoa entrar no mesmo telemóvel, o token passa a ser dela.

use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    protected $table = 'dispositivos';
    public $timestamps = false;
    protected $fillable = ['user_id', 'token', 'visto_em'];

    // Liga o token deste telemóvel à pessoa (chamado pela app ao entrar e quando o Firebase troca o token)
    public static function registar(User $user, string $token): void
    {
        $token = trim($token);
        // Tokens do FCM: letras, números e : _ - (uns 150–200 caracteres; até 255)
        if (!preg_match('/^[A-Za-z0-9:_\-]{20,255}$/', $token)) {
            throw new InvalidArgumentException('Token de notificações inválido.');
        }
        static::updateOrCreate(['token' => $token], ['user_id' => $user->id, 'visto_em' => date('Y-m-d H:i:s')]);
    }

    // Terminar sessão no telemóvel: deixa de receber (só apaga se o token for desta pessoa)
    public static function remover(User $user, string $token): void
    {
        static::where('user_id', $user->id)->where('token', trim($token))->delete();
    }
}
