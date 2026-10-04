<?php
// Dados do Jikan (https://jikan.moe), a API gratuita que lê o MyAnimeList.
//
// Quem fala com o Jikan é o TELEMÓVEL (js/app.js), não o servidor: o alojamento (alwaysdata)
// não consegue ligar ao api.jikan.moe (a ligação fica pendurada), mas o browser consegue.
// O browser envia para cá o que recebeu e esta classe limpa e valida tudo antes de ir para a base de dados.

class Jikan
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

    // Só aceita capas do MyAnimeList por HTTPS (nunca um endereço qualquer vindo do browser)
    public static function capa(mixed $url): ?string
    {
        if (!is_string($url) || strlen($url) > 255) {
            return null;
        }
        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');
        $doMal = $host === 'myanimelist.net' || str_ends_with($host, '.myanimelist.net');
        return (($partes['scheme'] ?? '') === 'https' && $doMal) ? $url : null;
    }

    // Texto limpo: sem espaços a mais nem caracteres de controlo, cortado ao tamanho da coluna
    private static function texto(mixed $valor, int $max): string
    {
        $texto = is_scalar($valor) ? trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valor) ?? '') : '';
        return mb_substr($texto, 0, $max);
    }
}
