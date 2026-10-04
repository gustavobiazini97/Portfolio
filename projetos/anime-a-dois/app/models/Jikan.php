<?php
// Cliente do Jikan (https://jikan.moe), uma API gratuita que lê o MyAnimeList.
// Dá a pesquisa de séries, a capa, o número de episódios, os títulos e quais são filler ou recap.
// Limites do Jikan: 3 pedidos por segundo e 60 por minuto — por isso há uma pausa entre pedidos.

class Jikan
{
    // Intervalo mínimo entre dois pedidos, em segundos (3 por segundo, com folga)
    const INTERVALO = 0.4;

    // Episódios por página na lista de episódios do Jikan
    const POR_PAGINA = 100;

    // Hora do último pedido (para respeitar o INTERVALO)
    private static float $ultimo = 0;

    // ---------- Pedidos ----------

    // GET à API; devolve o JSON já descodificado. Lança RuntimeException com uma mensagem para o utilizador.
    private static function get(string $caminho, array $query = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('O servidor não tem a extensão curl do PHP.');
        }

        $base = rtrim(Database::config()['jikan_url'] ?? 'https://api.jikan.moe/v4', '/');
        $url  = $base . $caminho . ($query ? '?' . http_build_query($query) : '');

        // Até 3 tentativas: o Jikan às vezes responde 429 (muitos pedidos) ou 5xx (MyAnimeList lento)
        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            $espera = self::$ultimo + self::INTERVALO - microtime(true);
            if ($espera > 0) {
                usleep((int) ($espera * 1e6));
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_USERAGENT      => 'AnimeADois/1.0 (+https://github.com/gustavobiazini97/Portfolio)',
            ]);
            $corpo  = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            self::$ultimo = microtime(true);

            if ($codigo === 200 && is_string($corpo)) {
                $json = json_decode($corpo, true);
                if (is_array($json)) {
                    return $json;
                }
            }
            if ($codigo === 404) {
                throw new RuntimeException('Não encontrei esse anime no MyAnimeList.');
            }
            // 429, 5xx ou sem resposta: espera um pouco mais a cada tentativa
            sleep($tentativa);
        }

        throw new RuntimeException('O MyAnimeList não está a responder. Tenta daqui a um bocadinho.');
    }

    // ---------- Pesquisa ----------

    // Resultados para a folha "Adicionar série" (no máximo 12, sem conteúdo adulto)
    public static function pesquisar(string $texto): array
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < 2) {
            return [];
        }

        $json = self::get('/anime', ['q' => mb_substr($texto, 0, 80), 'limit' => 12, 'sfw' => 'true']);

        $lista  = [];
        $vistos = [];   // o Jikan às vezes repete o mesmo anime na mesma página
        foreach ($json['data'] ?? [] as $a) {
            $r = self::resumo($a);
            if ($r['mal_id'] > 0 && !isset($vistos[$r['mal_id']])) {
                $vistos[$r['mal_id']] = true;
                $lista[] = $r;
            }
        }
        return $lista;
    }

    // ---------- Uma série ----------

    // Dados de um anime (os mesmos campos da pesquisa + duração dos episódios)
    public static function anime(int $malId): array
    {
        $json = self::get('/anime/' . $malId);
        if (empty($json['data'])) {
            throw new RuntimeException('Não encontrei esse anime no MyAnimeList.');
        }
        return self::resumo($json['data']);
    }

    // Todos os episódios com título, filler e recap, indexados pelo número: [n => [...]].
    // Vem às páginas de 100; uma série muito longa (One Piece) leva uns segundos.
    public static function episodios(int $malId): array
    {
        $episodios = [];
        $pagina = 1;
        do {
            $json = self::get('/anime/' . $malId . '/episodes', ['page' => $pagina]);
            foreach ($json['data'] ?? [] as $ep) {
                $n = (int) ($ep['mal_id'] ?? 0);   // no Jikan, o mal_id de um episódio é o número dele
                if ($n < 1 || $n > 65000) {
                    continue;
                }
                $titulo = trim((string) ($ep['title'] ?? ''));
                $episodios[$n] = [
                    'titulo' => $titulo === '' ? null : mb_substr($titulo, 0, 200),
                    'filler' => !empty($ep['filler']),
                    'recap'  => !empty($ep['recap']),
                ];
            }
            $continua = !empty($json['pagination']['has_next_page']);
            $pagina++;
        } while ($continua && $pagina <= 30);   // 30 páginas = 3000 episódios: chega e evita um ciclo infinito

        ksort($episodios);
        return $episodios;
    }

    // ---------- Normalização ----------

    // Converte um anime do Jikan nos campos que a app usa
    private static function resumo(array $a): array
    {
        // Títulos: o inglês aparece na app (é o mais conhecido cá); o original fica como subtítulo
        $ingles = null;
        $original = null;
        foreach ($a['titles'] ?? [] as $t) {
            if (($t['type'] ?? '') === 'English' && $ingles === null) {
                $ingles = $t['title'];
            }
            if (($t['type'] ?? '') === 'Default' && $original === null) {
                $original = $t['title'];
            }
        }
        $original ??= $a['title'] ?? '';
        $ingles   ??= $a['title_english'] ?? null;
        $nome = $ingles ?: $original;

        $img = $a['images'] ?? [];
        $capa = $img['webp']['large_image_url'] ?? $img['jpg']['large_image_url'] ?? $img['jpg']['image_url'] ?? null;

        return [
            'mal_id'     => (int) ($a['mal_id'] ?? 0),
            'nome'       => mb_substr((string) $nome, 0, 80),
            'original'   => $original !== $nome ? mb_substr((string) $original, 0, 120) : null,
            'capa'       => $capa ? mb_substr($capa, 0, 255) : null,
            'tipo'       => $a['type'] ?? null,
            'episodios'  => isset($a['episodes']) ? (int) $a['episodes'] : null,
            'ano'        => $a['year'] ?? ($a['aired']['prop']['from']['year'] ?? null),
            'em_emissao' => !empty($a['airing']) || ($a['status'] ?? '') === 'Not yet aired',
            'minutos'    => self::minutos($a['duration'] ?? null),
        ];
    }

    // "24 min per ep" → 24; "1 hr 50 min" → 110; desconhecido → null
    private static function minutos(?string $duracao): ?int
    {
        if (!$duracao) {
            return null;
        }
        $h = preg_match('/(\d+)\s*hr/', $duracao, $m) ? (int) $m[1] : 0;
        $min = preg_match('/(\d+)\s*min/', $duracao, $m) ? (int) $m[1] : 0;
        $total = $h * 60 + $min;
        return $total > 0 ? min($total, 255) : null;   // a coluna é TINYINT UNSIGNED
    }
}
