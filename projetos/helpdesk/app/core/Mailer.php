<?php
// Emails: ficam numa fila (tabela fila_emails) e são enviados em segundo plano,
// para o funcionário não esperar pelo servidor de email ao abrir um pedido.
// Envio por SMTP (STARTTLS ou SSL, AUTH LOGIN) escrito à mão, sem bibliotecas; ou modo 'log' para testes.
declare(strict_types=1);

final class Mailer
{
    // Põe um email na fila
    public static function enfileirar(string $para, string $assunto, string $corpo): void
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        Database::q(
            'INSERT INTO fila_emails (para, assunto, corpo, criado_em) VALUES (?, ?, ?, ?)',
            [$para, self::semQuebras($assunto), $corpo, agora()]
        );
    }

    // Envia até $limite emails pendentes (máximo 5 tentativas por email)
    public static function processarFila(int $limite = 20): int
    {
        $pendentes = Database::linhas(
            'SELECT * FROM fila_emails WHERE enviado_em IS NULL AND tentativas < 5 ORDER BY id LIMIT ' . max(1, $limite)
        );
        $enviados = 0;
        foreach ($pendentes as $m) {
            try {
                self::enviar($m['para'], $m['assunto'], $m['corpo']);
                Database::q('UPDATE fila_emails SET enviado_em = ?, erro = NULL WHERE id = ?', [agora(), $m['id']]);
                $enviados++;
            } catch (Throwable $e) {
                Database::q(
                    'UPDATE fila_emails SET tentativas = tentativas + 1, erro = ? WHERE id = ?',
                    [mb_substr($e->getMessage(), 0, 250), $m['id']]
                );
            }
        }
        return $enviados;
    }

    // Envio imediato (usado pela fila)
    private static function enviar(string $para, string $assunto, string $corpo): void
    {
        $de = (string) config('email.de', 'helpdesk@localhost');
        $deNome = (string) config('email.de_nome', config('app_nome', 'Helpdesk'));

        // Mensagem MIME: texto simples UTF-8 em base64 (acentos sempre certos)
        $cabecalhos = [
            'Date: ' . date('r'),
            'From: ' . self::codificar($deNome) . ' <' . self::semQuebras($de) . '>',
            'To: <' . self::semQuebras($para) . '>',
            'Subject: ' . self::codificar($assunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $de)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',   // respostas automáticas ("estou de férias") não devolvem
        ];
        $mensagem = implode("\r\n", $cabecalhos) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($corpo), 76, "\r\n"));

        if (config('email.modo', 'log') !== 'smtp') {
            // Modo de teste: escreve o email legível no log em vez de o enviar
            $linha = str_repeat('─', 60) . "\n" . date('c') . "\nPara: $para\nAssunto: $assunto\n\n$corpo\n";
            file_put_contents(RAIZ . '/storage/logs/emails.log', $linha, FILE_APPEND | LOCK_EX);
            return;
        }
        self::smtp($de, $para, $mensagem);
    }

    // Conversa SMTP: EHLO → STARTTLS → AUTH LOGIN → MAIL FROM → RCPT TO → DATA → QUIT
    private static function smtp(string $de, string $para, string $mensagem): void
    {
        $host = (string) config('email.smtp.host');
        $porta = (int) config('email.smtp.porta', 587);
        $seg = (string) config('email.smtp.seguranca', 'tls');

        $alvo = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $porta;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $s = @stream_socket_client($alvo, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) {
            throw new RuntimeException("SMTP: sem ligação a $host:$porta ($errstr)");
        }
        stream_set_timeout($s, 20);

        try {
            self::esperar($s, 220);
            $eu = gethostname() ?: 'localhost';
            self::cmd($s, "EHLO $eu", 250);
            if ($seg === 'tls') {
                self::cmd($s, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP: falhou o STARTTLS');
                }
                self::cmd($s, "EHLO $eu", 250);
            }
            $user = (string) config('email.smtp.utilizador', '');
            if ($user !== '') {
                self::cmd($s, 'AUTH LOGIN', 334);
                self::cmd($s, base64_encode($user), 334);
                self::cmd($s, base64_encode((string) config('email.smtp.password', '')), 235);
            }
            self::cmd($s, 'MAIL FROM:<' . self::semQuebras($de) . '>', 250);
            self::cmd($s, 'RCPT TO:<' . self::semQuebras($para) . '>', [250, 251]);
            self::cmd($s, 'DATA', 354);
            // Linhas que começam por "." levam outro ponto (dot-stuffing)
            $corpo = preg_replace('/^\./m', '..', $mensagem);
            self::cmd($s, $corpo . "\r\n.", 250);
            self::cmd($s, 'QUIT', 221);
        } finally {
            fclose($s);
        }
    }

    // Envia um comando e confirma o código da resposta
    private static function cmd($s, string $linha, int|array $esperado): void
    {
        fwrite($s, $linha . "\r\n");
        self::esperar($s, $esperado);
    }

    // Lê a resposta (pode ter várias linhas "250-...") e valida o código
    private static function esperar($s, int|array $esperado): void
    {
        $resposta = '';
        while (($l = fgets($s, 515)) !== false) {
            $resposta .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        $codigo = (int) substr($resposta, 0, 3);
        if (!in_array($codigo, (array) $esperado, true)) {
            throw new RuntimeException('SMTP: resposta inesperada ' . trim($resposta));
        }
    }

    // Cabeçalho com acentos: =?UTF-8?B?...?=
    private static function codificar(string $texto): string
    {
        return '=?UTF-8?B?' . base64_encode(self::semQuebras($texto)) . '?=';
    }

    // Tira \r e \n (contra injeção de cabeçalhos)
    private static function semQuebras(string $texto): string
    {
        return str_replace(["\r", "\n"], ' ', $texto);
    }
}
