<?php
// Dados de séries vindos de APIs públicas de anime, pedidos pelo TELEMÓVEL (js/app.js):
//   AniList  → pesquisa, capa, número de episódios, em emissão (rápido e estável)
//   Kitsu    → títulos dos episódios
//   Jikan    → fillers e recaps (lê o MyAnimeList; está em baixo desde ago/2026, por isso é opcional
//              e a app volta a tentar sozinha em segundo plano até conseguir)
// O browser envia para cá o que recebeu e esta classe limpa e valida tudo antes de ir para a base de dados.
// A chave de cada série continua a ser o id do MyAnimeList (mal_id), que o AniList também dá.

class DadosAnime
{
    // Limites das colunas (series/episodios)
    const MAX_EPISODIOS = 3000;     // One Piece anda pelos 1100: chega e sobra
    const MAX_TITULO    = 200;

    // Dados de uma série, vindos do browser (JSON) → campos seguros para a base de dados.
    // Lança InvalidArgumentException se faltar o essencial.
    public static function info(array $bruto, int $malId): array
    {
        if ((int) ($bruto['mal_id'] ?? 0) !== $malId || $malId < 1) {
            throw new InvalidArgumentException('Os dados da série não batem certo. Tenta outra vez.');
        }

        $nome = self::texto($bruto['nome'] ?? '', 80);
        if ($nome === '') {
            throw new InvalidArgumentException('A série veio sem nome. Tenta outra vez.');
        }

        $ano = (int) ($bruto['ano'] ?? 0);
        $minutos = (int) ($bruto['minutos'] ?? 0);

        return [
            'mal_id'     => $malId,
            'nome'       => $nome,
            'capa'       => self::capa($bruto['capa'] ?? null),
            'tipo'       => self::texto($bruto['tipo'] ?? '', 20) ?: null,
            'episodios'  => max(0, min(self::MAX_EPISODIOS, (int) ($bruto['episodios'] ?? 0))),
            'ano'        => ($ano >= 1900 && $ano <= 2100) ? $ano : null,
            'em_emissao' => !empty($bruto['em_emissao']),
            'minutos'    => ($minutos > 0) ? min($minutos, 255) : null,   // a coluna é TINYINT UNSIGNED
        ];
    }

    // Lista de episódios vinda do browser ([{n, titulo, filler, recap}, ...]) → [n => [...]], por ordem
    public static function episodios(array $bruto): array
    {
        $episodios = [];
        foreach ($bruto as $ep) {
            $n = (int) ($ep['n'] ?? 0);
            if ($n < 1 || $n > self::MAX_EPISODIOS) {
                continue;
            }
            $titulo = self::texto($ep['titulo'] ?? '', self::MAX_TITULO);
            $episodios[$n] = [
                'titulo' => $titulo === '' ? null : $titulo,
                'filler' => !empty($ep['filler']),
                'recap'  => !empty($ep['recap']),
            ];
        }
        ksort($episodios);
        return $episodios;
    }

    // ---------- Confirmação no servidor ----------
    // Os dados vêm do browser, por isso alguém podia mandar um nome, uma capa ou um total falsos
    // para um mal_id verdadeiro (e toda a gente que adicionasse essa série via os dados falsos).
    // Antes de criar ou atualizar uma série, o servidor pergunta ao AniList e os dados de lá ganham.
    // Se o AniList não responder, ficam os do browser (já validados) e fica registo no log.

    const ANILIST = 'https://graphql.anilist.co';

    // Mesmos tipos que o js/app.js usa
    const TIPOS = ['TV' => 'TV', 'TV_SHORT' => 'TV', 'MOVIE' => 'Movie', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => 'Special', 'MUSIC' => 'Music'];

    // $info (de info()) → os mesmos campos com os valores do AniList por cima; 'confirmado' diz se correu bem
    public static function confirmar(array $info): array
    {
        $m = self::anilist((int) $info['mal_id']);
        if ($m === null) {
            error_log('[anime] AniList sem resposta para mal_id ' . (int) $info['mal_id'] . ': ficam os dados do browser');
            return $info + ['confirmado' => false];
        }

        // A mesma conta do js/app.js (resumo): em emissão sem total, os que já saíram
        $eps = (int) ($m['episodes'] ?? 0);
        if ($eps === 0 && isset($m['nextAiringEpisode']['episode'])) {
            $eps = (int) $m['nextAiringEpisode']['episode'] - 1;
        }
        $ano = (int) ($m['seasonYear'] ?? $m['startDate']['year'] ?? 0);
        $minutos = (int) ($m['duration'] ?? 0);
        $nome = self::texto($m['title']['english'] ?? '', 80) ?: self::texto($m['title']['romaji'] ?? '', 80);

        return [
            'mal_id'     => (int) $info['mal_id'],
            'nome'       => $nome !== '' ? $nome : $info['nome'],
            'capa'       => self::capa($m['coverImage']['extraLarge'] ?? $m['coverImage']['large'] ?? null) ?? $info['capa'],
            'tipo'       => self::TIPOS[$m['format'] ?? ''] ?? (self::texto($m['format'] ?? '', 20) ?: null),
            'episodios'  => max(0, min(self::MAX_EPISODIOS, $eps)),
            'ano'        => ($ano >= 1900 && $ano <= 2100) ? $ano : null,
            'em_emissao' => in_array($m['status'] ?? '', ['RELEASING', 'NOT_YET_RELEASED'], true),
            'minutos'    => $minutos > 0 ? min($minutos, 255) : null,
            'confirmado' => true,
        ];
    }

    // Com os dados confirmados e o total conhecido, episódios além do total são inventados: ficam de fora
    public static function cortar(array $episodios, array $info): array
    {
        $total = (int) ($info['episodios'] ?? 0);
        if (empty($info['confirmado']) || $total < 1) {
            return $episodios;
        }
        return array_filter($episodios, fn ($n) => $n <= $total, ARRAY_FILTER_USE_KEY);
    }

    // Pergunta ao AniList pela série com este id do MyAnimeList; null se falhar (rede, limite, não existe)
    private static function anilist(int $malId): ?array
    {
        $corpo = json_encode([
            'query' => 'query ($id: Int) { Media(idMal: $id, type: ANIME) { idMal title { english romaji } '
                     . 'coverImage { extraLarge large } episodes status format seasonYear startDate { year } duration nextAiringEpisode { episode } } }',
            'variables' => ['id' => $malId],
        ]);
        $contexto = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: AnimeADois/1.0\r\n",
            'content'       => $corpo,
            'timeout'       => 6,
            'ignore_errors' => true,     // ler o corpo mesmo com 4xx/5xx (e decidir aqui)
        ]]);

        $resposta = @file_get_contents(self::ANILIST, false, $contexto);
        if ($resposta === false) {
            return null;
        }
        $json = json_decode($resposta, true);
        $m = $json['data']['Media'] ?? null;

        // Só serve se for mesmo a série pedida
        return is_array($m) && (int) ($m['idMal'] ?? 0) === $malId ? $m : null;
    }

    // Domínios de onde se aceitam capas (nunca um endereço qualquer vindo do browser)
    const DOMINIOS_CAPA = ['anilist.co', 'myanimelist.net', 'kitsu.app', 'kitsu.io'];

    // Capa só por HTTPS e só de um desses domínios (ou subdomínios: s4.anilist.co, cdn.myanimelist.net...)
    public static function capa(mixed $url): ?string
    {
        if (!is_string($url) || strlen($url) > 255) {
            return null;
        }
        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');
        foreach (self::DOMINIOS_CAPA as $dominio) {
            if (($partes['scheme'] ?? '') === 'https' && ($host === $dominio || str_ends_with($host, '.' . $dominio))) {
                return $url;
            }
        }
        return null;
    }

    // Texto limpo: sem espaços a mais nem caracteres de controlo, cortado ao tamanho da coluna
    private static function texto(mixed $valor, int $max): string
    {
        $texto = is_scalar($valor) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valor) ?? '') : '';
        return mb_substr($texto, 0, $max);
    }
}
