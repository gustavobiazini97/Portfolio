<?php
// Amigos: quem pode ver o perfil e a biblioteca uns dos outros (não partilham séries; isso é só do par).
// Entram de três maneiras: link de convite (a pessoa cria conta já amiga de quem convidou),
// pedido pelo nome de utilizador exato (a outra pessoa aceita) e o par (que não conta como "amigo").

use Illuminate\Database\Capsule\Manager as Capsule;

class Amizade
{
    const VALIDADE_DIAS = 7;   // um link de convite deixa de servir passado este tempo

    // ---------- Consultas ----------

    // Os amigos de uma pessoa (Users), por ordem alfabética
    public static function amigosDe(User $u)
    {
        $ids = Capsule::table('amizades')->where('user_id', $u->id)->pluck('amigo_id')->all();
        return $ids === [] ? collect() : User::whereIn('id', $ids)->orderBy('nome')->get();
    }

    // Já são amigos?
    public static function sao(User $a, User $b): bool
    {
        return Capsule::table('amizades')->where('user_id', $a->id)->where('amigo_id', $b->id)->exists();
    }

    // Pedidos que esta pessoa recebeu e ainda não respondeu (Users, do mais antigo para o mais recente)
    public static function pedidosRecebidos(User $u)
    {
        $ids = Capsule::table('pedidos_amizade')->where('para_id', $u->id)->orderBy('criado_em')->pluck('de_id')->all();
        return $ids === [] ? collect() : User::whereIn('id', $ids)->get();
    }

    // Pedidos que esta pessoa enviou e ainda estão à espera
    public static function pedidosEnviados(User $u)
    {
        $ids = Capsule::table('pedidos_amizade')->where('de_id', $u->id)->orderBy('criado_em')->pluck('para_id')->all();
        return $ids === [] ? collect() : User::whereIn('id', $ids)->get();
    }

    // ---------- Pedidos pelo nome de utilizador ----------

    // Envia um pedido ao utilizador com este nome EXATO (não há pesquisa parcial: ninguém navega pelas contas)
    public static function pedir(User $de, string $username): void
    {
        $username = strtolower(trim($username));
        $para = $username === '' ? null : User::where('username', $username)->first();

        // Mesma mensagem para "não existe" e para os casos sem sentido, para não revelar quem tem conta
        if ($para === null || $para->id === $de->id || $para->id === $de->par_id) {
            throw new InvalidArgumentException('Não encontrei ninguém com esse utilizador.');
        }
        if (self::sao($de, $para)) {
            throw new InvalidArgumentException($para->nome . ' já é teu amigo.');
        }

        // Se a outra pessoa já tinha pedido a amizade, é como aceitar
        if (Capsule::table('pedidos_amizade')->where('de_id', $para->id)->where('para_id', $de->id)->exists()) {
            self::aceitar($de, $para);
            return;
        }
        if (Capsule::table('pedidos_amizade')->where('de_id', $de->id)->where('para_id', $para->id)->exists()) {
            throw new InvalidArgumentException('Já enviaste um pedido a ' . $para->nome . '.');
        }
        Capsule::table('pedidos_amizade')->insert(['de_id' => $de->id, 'para_id' => $para->id]);
    }

    // $quem aceita o pedido que $de lhe enviou
    public static function aceitar(User $quem, User $de): void
    {
        Capsule::connection()->transaction(function () use ($quem, $de) {
            $apagados = Capsule::table('pedidos_amizade')->where('de_id', $de->id)->where('para_id', $quem->id)->delete();
            if ($apagados === 0 && !self::sao($quem, $de)) {
                throw new InvalidArgumentException('Esse pedido já não existe.');
            }
            self::ligar($quem, $de);
        });
    }

    // $quem recusa o pedido de $de (ou, se for ele que enviou, cancela-o)
    public static function recusar(User $quem, User $outro): void
    {
        Capsule::table('pedidos_amizade')
            ->where(fn ($q) => $q->where('de_id', $outro->id)->where('para_id', $quem->id))
            ->orWhere(fn ($q) => $q->where('de_id', $quem->id)->where('para_id', $outro->id))
            ->delete();
    }

    // Desfaz a amizade nos dois sentidos
    public static function remover(User $a, User $b): void
    {
        Capsule::table('amizades')
            ->where(fn ($q) => $q->where('user_id', $a->id)->where('amigo_id', $b->id))
            ->orWhere(fn ($q) => $q->where('user_id', $b->id)->where('amigo_id', $a->id))
            ->delete();
    }

    // Cria a amizade nos dois sentidos (sem erro se já existir)
    private static function ligar(User $a, User $b): void
    {
        Capsule::table('amizades')->insertOrIgnore([
            ['user_id' => $a->id, 'amigo_id' => $b->id],
            ['user_id' => $b->id, 'amigo_id' => $a->id],
        ]);
    }

    // ---------- Links de convite ----------

    // Cria um link novo de uso único; devolve o código
    public static function criarConvite(User $de): string
    {
        // Convites antigos e não usados deste utilizador ficam inválidos: só há um link em circulação
        Capsule::table('convites_amigo')->where('de_id', $de->id)->whereNull('usado_por')->delete();

        $codigo = bin2hex(random_bytes(16));
        Capsule::table('convites_amigo')->insert([
            'codigo'    => $codigo,
            'de_id'     => $de->id,
            'expira_em' => date('Y-m-d H:i:s', strtotime('+' . self::VALIDADE_DIAS . ' days')),
        ]);
        return $codigo;
    }

    // O link que esta pessoa tem em circulação (por usar e dentro da validade), ou null
    public static function conviteAtivo(User $de): ?object
    {
        return Capsule::table('convites_amigo')
            ->where('de_id', $de->id)->whereNull('usado_por')->where('expira_em', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    // Convite válido (existe, por usar, dentro da validade) com o User que convidou em ->de; senão null
    public static function convite(?string $codigo): ?object
    {
        if ($codigo === null || !preg_match('/^[a-f0-9]{32}$/', $codigo)) {
            return null;
        }
        $c = Capsule::table('convites_amigo')->where('codigo', $codigo)->whereNull('usado_por')->where('expira_em', '>', date('Y-m-d H:i:s'))->first();
        if ($c === null) {
            return null;
        }
        $c->de = User::find($c->de_id);
        return $c->de === null ? null : $c;
    }

    // Marca o convite como usado e liga o novo utilizador a quem convidou
    public static function usarConvite(object $convite, User $novo): void
    {
        Capsule::connection()->transaction(function () use ($convite, $novo) {
            // O "whereNull" garante que dois pedidos ao mesmo tempo não usam o mesmo link
            $ok = Capsule::table('convites_amigo')->where('codigo', $convite->codigo)->whereNull('usado_por')->update(['usado_por' => $novo->id]);
            if ($ok === 0) {
                throw new InvalidArgumentException('Esse convite já foi usado.');
            }
            self::ligar($novo, $convite->de);
        });
    }
}
