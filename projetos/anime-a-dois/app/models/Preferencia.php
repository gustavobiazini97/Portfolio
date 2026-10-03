<?php
// O que cada pessoa quer receber (episódios / comentários) e por onde (telemóvel / email).

use Illuminate\Database\Eloquent\Model;

class Preferencia extends Model
{
    protected $table = 'preferencias';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['user_id', 'email', 'ep_push', 'ep_email', 'com_push', 'com_email'];

    protected $casts = ['ep_push' => 'boolean', 'ep_email' => 'boolean', 'com_push' => 'boolean', 'com_email' => 'boolean'];

    // Preferências de uma pessoa; sem linha na tabela devolve os valores por defeito (só telemóvel)
    public static function de(User $user): Preferencia
    {
        return static::find($user->id)
            ?? new static(['user_id' => $user->id, 'email' => null, 'ep_push' => true, 'ep_email' => false, 'com_push' => true, 'com_email' => false]);
    }

    // Guarda o formulário do perfil; lança InvalidArgumentException com a mensagem para o utilizador
    public static function guardar(User $user, array $dados): Preferencia
    {
        $email = trim($dados['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Esse email não parece válido.');
        }

        $querEmail = !empty($dados['ep_email']) || !empty($dados['com_email']);
        if ($querEmail && $email === '') {
            throw new InvalidArgumentException('Para receber por email, escreve o teu email.');
        }

        return static::updateOrCreate(['user_id' => $user->id], [
            'email'     => $email === '' ? null : $email,
            'ep_push'   => !empty($dados['ep_push']),
            'ep_email'  => !empty($dados['ep_email']),
            'com_push'  => !empty($dados['com_push']),
            'com_email' => !empty($dados['com_email']),
        ]);
    }
}
