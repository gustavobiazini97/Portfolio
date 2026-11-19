<?php
// O que cada pessoa quer receber no telemóvel: episódios, comentários, séries e pedidos de amizade.
// (Chega à app Android pelo Firebase e ao site instalado pelo Web Push. Já não há avisos por email.)

use Illuminate\Database\Eloquent\Model;

class Preferencia extends Model
{
    protected $table = 'preferencias';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    public $timestamps = false;

    // Os quatro interruptores (o formulário do site e a API usam estes nomes)
    const TIPOS = ['ep_push', 'com_push', 'amigo_push', 'serie_push'];

    protected $fillable = ['user_id', 'ep_push', 'com_push', 'serie_push', 'amigo_push'];

    protected $casts = ['ep_push' => 'boolean', 'com_push' => 'boolean', 'serie_push' => 'boolean', 'amigo_push' => 'boolean'];

    // Preferências de uma pessoa; sem linha na tabela fica tudo ligado
    public static function de(User $user): Preferencia
    {
        return static::find($user->id)
            ?? new static(['user_id' => $user->id, 'ep_push' => true, 'com_push' => true, 'serie_push' => true, 'amigo_push' => true]);
    }

    // Guarda os interruptores (o que não vier marcado fica desligado)
    public static function guardar(User $user, array $dados): Preferencia
    {
        $valores = [];
        foreach (self::TIPOS as $tipo) {
            $valores[$tipo] = !empty($dados[$tipo]);
        }
        return static::updateOrCreate(['user_id' => $user->id], $valores);
    }

    // Para a API: os quatro valores
    public function paraJson(): array
    {
        $r = [];
        foreach (self::TIPOS as $tipo) {
            $r[$tipo] = (bool) $this->$tipo;
        }
        return $r;
    }
}
