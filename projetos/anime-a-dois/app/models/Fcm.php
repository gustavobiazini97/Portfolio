<?php
// Notificações para a app Android pelo Firebase Cloud Messaging (API HTTP v1), sem bibliotecas externas.
//
// Precisa da chave da conta de serviço do Firebase (ficheiro JSON, secreto) no servidor, fora do site:
//   por defeito ~/firebase-conta.json (ou o caminho em config.php → 'fcm_conta').
// Sem esse ficheiro, o envio fica desligado e o resto da app funciona na mesma.
//
// Como funciona: assina um JWT com a chave privada → a Google troca-o por um token de acesso (1 h, guardado
// em config_app) → cada notificação é um POST para .../messages:send com o token do telemóvel (tabela dispositivos).

use Illuminate\Database\Capsule\Manager as Capsule;

class Fcm
{
    const ESCOPO = 'https://www.googleapis.com/auth/firebase.messaging';

    // Só os testes mudam isto (servidor falso em vez da Google)
    public static ?string $contaFicheiro = null;
    public static string $base = 'https://fcm.googleapis.com';

    // ---------- Configuração ----------

    // Conta de serviço lida do ficheiro, ou null se não existir / estiver incompleta
    public static function conta(): ?array
    {
        $ficheiro = self::$contaFicheiro
            ?? (Database::config()['fcm_conta'] ?? dirname(__DIR__, 3) . '/firebase-conta.json');
        if (!is_file($ficheiro) || !is_readable($ficheiro)) {
            return null;
        }
        $c = json_decode((string) file_get_contents($ficheiro), true);
        foreach (['project_id', 'private_key', 'client_email', 'token_uri'] as $campo) {
            if (empty($c[$campo]) || !is_string($c[$campo])) {
                return null;
            }
        }
        return $c;
    }

    public static function ativo(): bool
    {
        return self::conta() !== null;
    }

    // ---------- Envio ----------

    // Envia a notificação a todos os telemóveis (app Android) desta pessoa; devolve quantos a receberam.
    // Telemóveis que já não existem (app desinstalada, token trocado) são apagados.
    public static function enviar(User $destino, array $msg): int
    {
        $tokens = Dispositivo::where('user_id', $destino->id)->pluck('token')->all();
        $conta = self::conta();
        if ($tokens === [] || $conta === null) {
            return 0;
        }
        // O ícone da notificação fica com a cor da paleta que esta pessoa escolheu na app
        $msg['cor'] ??= User::CORES_PALETA[(int) $destino->paleta] ?? User::CORES_PALETA[0];

        $enviados = 0;
        foreach ($tokens as $token) {
            $estado = self::enviarUm($conta, $token, $msg);
            if ($estado === 401) {
                // Token de acesso recusado (expirou antes do previsto): pede outro e tenta mais uma vez
                self::esquecerAcesso();
                $estado = self::enviarUm($conta, $token, $msg);
            }
            if ($estado === 200) {
                $enviados++;
            } elseif ($estado === 404 || $estado === 400) {
                Dispositivo::where('token', $token)->delete();   // UNREGISTERED / token inválido
            } else {
                error_log('[fcm] envio falhou: estado ' . $estado);
            }
        }
        return $enviados;
    }

    // POST de uma notificação; devolve o estado HTTP (0 = sem resposta)
    private static function enviarUm(array $conta, string $token, array $msg): int
    {
        $acesso = self::acesso($conta);
        if ($acesso === null) {
            return 0;
        }

        $notificacao = ['title' => (string) $msg['titulo'], 'body' => (string) $msg['corpo']];
        $android = [
            'priority' => 'HIGH',
            'ttl'      => '86400s',                               // guarda até 1 dia se o telemóvel estiver desligado
            'notification' => array_filter([
                'tag'   => $msg['tag'] ?? null,                   // a mesma etiqueta substitui em vez de empilhar
                'color' => $msg['cor'] ?? null,                   // cor do ícone (#RRGGBB, a paleta da pessoa)
                'image' => $msg['imagem'] ?? null,                // capa da série em grande
            ]),
        ];
        $corpo = ['message' => [
            'token'        => $token,
            'notification' => $notificacao,
            'data'         => ['url' => (string) ($msg['url'] ?? ''), 'tag' => (string) ($msg['tag'] ?? '')],
            'android'      => $android,
        ]];

        [$estado] = self::http(
            self::$base . '/v1/projects/' . rawurlencode($conta['project_id']) . '/messages:send',
            json_encode($corpo, JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Authorization: Bearer ' . $acesso]
        );
        return $estado;
    }

    // ---------- Token de acesso (OAuth 2, conta de serviço) ----------

    // Token válido (guardado em config_app até 5 min antes de expirar), ou null se a Google o recusar
    private static function acesso(array $conta): ?string
    {
        $guardado = json_decode((string) Capsule::table('config_app')->where('chave', 'fcm_acesso')->value('valor'), true);
        if (is_array($guardado) && ($guardado['ate'] ?? 0) > time() + 300 && !empty($guardado['token'])) {
            return $guardado['token'];
        }

        [$estado, $resposta] = self::http($conta['token_uri'], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => self::jwt($conta),
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $r = json_decode($resposta, true);
        if ($estado !== 200 || empty($r['access_token'])) {
            error_log('[fcm] token de acesso recusado: estado ' . $estado);
            return null;
        }
        Capsule::table('config_app')->updateOrInsert(['chave' => 'fcm_acesso'], ['valor' => json_encode([
            'token' => $r['access_token'],
            'ate'   => time() + (int) ($r['expires_in'] ?? 3600),
        ])]);
        return $r['access_token'];
    }

    private static function esquecerAcesso(): void
    {
        Capsule::table('config_app')->where('chave', 'fcm_acesso')->delete();
    }

    // JWT assinado com RS256 (a chave privada da conta de serviço)
    public static function jwt(array $conta, ?int $agora = null): string
    {
        $agora ??= time();
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $entrada = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $b64(json_encode([
                'iss'   => $conta['client_email'],
                'scope' => self::ESCOPO,
                'aud'   => $conta['token_uri'],
                'iat'   => $agora,
                'exp'   => $agora + 3600,
            ]));
        if (!openssl_sign($entrada, $assinatura, $conta['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Chave privada do Firebase inválida.');
        }
        return $entrada . '.' . $b64($assinatura);
    }

    // POST simples; devolve [estado, corpo]
    private static function http(string $url, string $corpo, array $cabecalhos): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $corpo,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resposta = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$estado, is_string($resposta) ? $resposta : ''];
    }
}
