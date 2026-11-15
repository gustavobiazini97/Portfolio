<?php
// Limites contra abuso: conta acontecimentos por chave numa janela de tempo e bloqueia quem passa do máximo.
// Usado no login (força bruta: por IP e por utilizador) e no botão "testar notificação" (emails a mais).
// Falha aberto se a tabela ainda não existir (antes do migrate): o pior caso é ficar sem limite, nunca sem login.

use Illuminate\Database\Capsule\Manager as Capsule;

class Limite
{
    // Login: 5 falhas por utilizador ou 20 por IP em 15 min → bloqueio de 15 min
    const LOGIN_USER = 5;
    const LOGIN_IP   = 20;
    const LOGIN_JANELA = 900;

    // ---------- Genérico ----------

    // Está bloqueado agora?
    public static function bloqueado(string $chave): bool
    {
        try {
            $linha = Capsule::table('limites')->where('chave_hash', self::hash($chave))->first();
            return $linha !== null && $linha->bloqueado_ate !== null && strtotime($linha->bloqueado_ate) > time();
        } catch (Throwable $e) {
            return false;
        }
    }

    // Conta mais um acontecimento; ao passar o máximo na janela, bloqueia durante $janela segundos.
    // Devolve true se esta vez ainda estava dentro do limite.
    public static function registar(string $chave, int $maximo, int $janela): bool
    {
        try {
            $hash  = self::hash($chave);
            $agora = date('Y-m-d H:i:s');
            $linha = Capsule::table('limites')->where('chave_hash', $hash)->first();

            // Janela nova: sem linha, ou a anterior já passou (e não está bloqueado)
            if ($linha === null || (strtotime($linha->inicio) < time() - $janela && !self::ativo($linha))) {
                Capsule::table('limites')->updateOrInsert(['chave_hash' => $hash], ['contagem' => 1, 'inicio' => $agora, 'bloqueado_ate' => null]);
                return true;
            }

            $contagem = (int) $linha->contagem + 1;
            $dados = ['contagem' => min($contagem, 65535)];
            if ($contagem > $maximo) {
                $dados['bloqueado_ate'] = date('Y-m-d H:i:s', time() + $janela);
            }
            Capsule::table('limites')->where('chave_hash', $hash)->update($dados);

            // De vez em quando, limpa as linhas velhas de toda a gente
            if (random_int(1, 50) === 1) {
                Capsule::table('limites')->where('inicio', '<', date('Y-m-d H:i:s', time() - 86400))
                    ->where(fn ($q) => $q->whereNull('bloqueado_ate')->orWhere('bloqueado_ate', '<', $agora))->delete();
            }
            return $contagem <= $maximo;
        } catch (Throwable $e) {
            return true;
        }
    }

    // Esquece a chave (ex.: login certo apaga as falhas desse utilizador)
    public static function limpar(string $chave): void
    {
        try {
            Capsule::table('limites')->where('chave_hash', self::hash($chave))->delete();
        } catch (Throwable $e) {
            // sem tabela: nada a limpar
        }
    }

    // ---------- Login ----------

    // Antes de verificar a palavra-passe: este IP ou este utilizador estão bloqueados?
    public static function loginBloqueado(string $username): bool
    {
        return self::bloqueado(self::chaveIp()) || self::bloqueado(self::chaveUser($username));
    }

    // Palavra-passe errada: conta para o IP e para o utilizador
    public static function loginFalhou(string $username): void
    {
        self::registar(self::chaveIp(), self::LOGIN_IP, self::LOGIN_JANELA);
        self::registar(self::chaveUser($username), self::LOGIN_USER, self::LOGIN_JANELA);
        error_log('[login] falhou para "' . self::limparLog($username) . '" de ' . self::ip());
    }

    // Login certo: as falhas deste utilizador deixam de contar (as do IP continuam)
    public static function loginCerto(string $username): void
    {
        self::limpar(self::chaveUser($username));
    }

    // Mensagem igual para o site e para a app
    const MENSAGEM_LOGIN = 'Demasiadas tentativas. Espera 15 minutos e tenta outra vez.';

    // ---------- Internos ----------

    // IP de quem fez o pedido (o alojamento passa o IP real em REMOTE_ADDR)
    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private static function chaveIp(): string
    {
        return 'login-ip:' . self::ip();
    }

    private static function chaveUser(string $username): string
    {
        return 'login-user:' . strtolower(trim($username));
    }

    private static function ativo(object $linha): bool
    {
        return $linha->bloqueado_ate !== null && strtotime($linha->bloqueado_ate) > time();
    }

    private static function hash(string $chave): string
    {
        return hash('sha256', $chave);
    }

    // Nada de quebras de linha nem texto gigante no log
    private static function limparLog(string $texto): string
    {
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $texto) ?? '', 0, 40);
    }
}
