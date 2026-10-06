// APIs públicas de anime, chamadas diretamente do telemóvel (como o js/app.js do site faz no browser):
//   AniList → pesquisa, capa, número de episódios, em emissão (GraphQL)
//   Jikan   → títulos + fillers e recaps (MyAnimeList); anda em baixo, por isso tenta-se depressa
//   Kitsu   → títulos dos episódios, quando o Jikan não responde
// O servidor do alwaysdata não chega a estas APIs: a app vai buscar os dados e entrega-os à API do site,
// que os valida (DadosAnime) antes de guardar.

import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api.dart';

const _anilist = 'https://graphql.anilist.co';
const _jikan = 'https://api.jikan.moe/v4';
const _kitsu = 'https://kitsu.app/api/edge';

// Campos pedidos ao AniList (os mesmos do site)
const _campos = 'idMal title { english romaji } coverImage { extraLarge large } episodes status format '
    'seasonYear startDate { year } duration nextAiringEpisode { episode }';

const _tipos = {'TV': 'TV', 'TV_SHORT': 'TV', 'MOVIE': 'Movie', 'OVA': 'OVA', 'ONA': 'ONA', 'SPECIAL': 'Special', 'MUSIC': 'Music'};

// Uma série encontrada na pesquisa (os campos que a API do site espera em "info")
class ResultadoAnime {
  final int malId;
  final String nome;
  final String? original; // nome em japonês romanizado, se for diferente
  final String? capa;
  final String? tipo;
  final int? episodios;
  final int? ano;
  final bool emEmissao;
  final int? minutos;

  const ResultadoAnime({
    required this.malId,
    required this.nome,
    this.original,
    this.capa,
    this.tipo,
    this.episodios,
    this.ano,
    this.emEmissao = false,
    this.minutos,
  });

  // Formato do campo "info" do POST /biblioteca
  Map<String, dynamic> paraInfo() => {
        'mal_id': malId,
        'nome': nome,
        'capa': capa,
        'tipo': tipo,
        'episodios': episodios,
        'ano': ano,
        'em_emissao': emEmissao,
        'minutos': minutos,
      };

  // "Sousou no Frieren · TV · 2023 · 28 ep."
  String get resumo => [
        if (original != null) original!,
        if (tipo != null) tipo!,
        if (ano != null) '$ano',
        if (episodios != null) '$episodios ep.',
        if (emEmissao) 'em emissão',
      ].join(' · ');
}

// Episódios encontrados: de onde vieram ('jikan' traz fillers) e a lista no formato da API
class EpisodiosAnime {
  final String fonte; // jikan | kitsu | nenhuma
  final List<Map<String, dynamic>> lista; // [{n, titulo, filler, recap}]

  const EpisodiosAnime(this.fonte, this.lista);
}

class Anime {
  Anime._();

  // Jikan em baixo: durante 30 min nem se tenta (poupa segundos a cada adicionar)
  static DateTime? _jikanEmBaixoAte;

  // ---------- Pedidos ----------

  // GET/POST com tempo máximo e novas tentativas (429, 5xx, sem resposta)
  static Future<dynamic> _pedir(Future<http.Response> Function() fazer, {int tentativas = 3, Duration limite = const Duration(seconds: 10)}) async {
    for (var t = 1;; t++) {
      try {
        final r = await fazer().timeout(limite);
        if (r.statusCode == 429 || r.statusCode >= 500) {
          throw _Repetir();
        }
        if (r.statusCode >= 400) {
          throw ApiErro('O serviço de anime respondeu com erro ${r.statusCode}.');
        }
        return jsonDecode(utf8.decode(r.bodyBytes));
      } on ApiErro {
        rethrow;
      } catch (_) {
        if (t >= tentativas) {
          throw ApiErro('O serviço de anime não está a responder. Tenta daqui a um bocadinho.');
        }
        await Future<void>.delayed(Duration(milliseconds: 800 * t));
      }
    }
  }

  static Future<Map<String, dynamic>> _anilistPedido(String query, Map<String, dynamic> variaveis, {int tentativas = 3}) async {
    final json = await _pedir(
      () => http.post(
        Uri.parse(_anilist),
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({'query': query, 'variables': variaveis}),
      ),
      tentativas: tentativas,
    ) as Map<String, dynamic>;
    if (json['data'] == null) {
      throw ApiErro('A pesquisa falhou.');
    }
    return json['data'] as Map<String, dynamic>;
  }

  // Uma série do AniList → ResultadoAnime (null se não tiver id do MyAnimeList)
  static ResultadoAnime? _resumo(Map<String, dynamic> m) {
    final malId = m['idMal'] as int?;
    if (malId == null) return null;
    final titulo = (m['title'] as Map<String, dynamic>?) ?? {};
    final nome = (titulo['english'] ?? titulo['romaji'] ?? '') as String;
    final romaji = titulo['romaji'] as String?;
    final estado = m['status'] as String?;
    final capa = m['coverImage'] as Map<String, dynamic>?;
    final proximo = m['nextAiringEpisode'] as Map<String, dynamic>?;
    // Em emissão sem total anunciado: os que já saíram (o próximo a sair menos um)
    final eps = (m['episodes'] as int?) ?? (proximo != null ? (proximo['episode'] as int) - 1 : null);
    final inicio = m['startDate'] as Map<String, dynamic>?;
    return ResultadoAnime(
      malId: malId,
      nome: nome,
      original: romaji != null && romaji != nome ? romaji : null,
      capa: capa == null ? null : (capa['extraLarge'] ?? capa['large']) as String?,
      tipo: _tipos[m['format']] ?? m['format'] as String?,
      episodios: (eps != null && eps > 0) ? eps : null,
      ano: (m['seasonYear'] as int?) ?? inicio?['year'] as int?,
      emEmissao: estado == 'RELEASING' || estado == 'NOT_YET_RELEASED',
      minutos: m['duration'] as int?,
    );
  }

  // ---------- Pesquisa ----------

  // Até 12 séries com id do MyAnimeList, sem conteúdo adulto
  static Future<List<ResultadoAnime>> pesquisar(String q) async {
    final data = await _anilistPedido(
      'query (\$q: String) { Page(perPage: 15) { media(search: \$q, type: ANIME, isAdult: false, sort: SEARCH_MATCH) { $_campos } } }',
      {'q': q},
      tentativas: 2,
    );
    final media = ((data['Page'] as Map<String, dynamic>?)?['media'] as List?) ?? [];
    final vistos = <int>{};
    final lista = <ResultadoAnime>[];
    for (final m in media) {
      final r = _resumo(m as Map<String, dynamic>);
      if (r != null && vistos.add(r.malId)) lista.add(r);
    }
    return lista.take(12).toList();
  }

  // ---------- Episódios: Jikan (com fillers) → Kitsu (só títulos) → nenhum ----------

  static Future<EpisodiosAnime> episodios(int malId, {void Function(int)? aoAvancar}) async {
    try {
      return EpisodiosAnime('jikan', await _episodiosJikan(malId, aoAvancar));
    } catch (_) {
      try {
        return EpisodiosAnime('kitsu', await _episodiosKitsu(malId, aoAvancar));
      } catch (_) {
        return const EpisodiosAnime('nenhuma', []); // fica com o número de episódios, sem títulos
      }
    }
  }

  static Future<List<Map<String, dynamic>>> _episodiosJikan(int malId, void Function(int)? aoAvancar) async {
    if (_jikanEmBaixoAte != null && DateTime.now().isBefore(_jikanEmBaixoAte!)) {
      throw ApiErro('Jikan em baixo');
    }
    final lista = <Map<String, dynamic>>[];
    try {
      for (var p = 1; p <= 30; p++) {
        final json = await _pedir(
          () => http.get(Uri.parse('$_jikan/anime/$malId/episodes?page=$p'), headers: {'Accept': 'application/json'}),
          tentativas: p == 1 ? 1 : 3,
          limite: Duration(seconds: p == 1 ? 6 : 12),
        ) as Map<String, dynamic>;
        for (final ep in (json['data'] as List? ?? [])) {
          final e = ep as Map<String, dynamic>;
          lista.add({'n': e['mal_id'], 'titulo': e['title'], 'filler': e['filler'] == true, 'recap': e['recap'] == true});
        }
        aoAvancar?.call(lista.length);
        final pag = json['pagination'] as Map<String, dynamic>?;
        if (pag?['has_next_page'] != true) break;
        await Future<void>.delayed(const Duration(milliseconds: 400)); // limite do Jikan
      }
      return lista;
    } catch (e) {
      _jikanEmBaixoAte = DateTime.now().add(const Duration(minutes: 30));
      rethrow;
    }
  }

  static Future<List<Map<String, dynamic>>> _episodiosKitsu(int malId, void Function(int)? aoAvancar) async {
    const cab = {'Accept': 'application/vnd.api+json'};
    final mapa = await _pedir(() => http.get(
          Uri.parse('$_kitsu/mappings?filter[externalSite]=myanimelist/anime&filter[externalId]=$malId&include=item'),
          headers: cab,
        )) as Map<String, dynamic>;
    final item = ((mapa['included'] as List?) ?? [])
        .map((x) => x as Map<String, dynamic>)
        .where((x) => x['type'] == 'anime')
        .firstOrNull;
    if (item == null) throw ApiErro('Sem episódios no Kitsu.');

    final lista = <Map<String, dynamic>>[];
    String? url = '$_kitsu/anime/${item['id']}/episodes?page[limit]=20&page[offset]=0&sort=number&fields[episodes]=number,canonicalTitle';
    for (var n = 0; url != null && n < 200; n++) {
      final json = await _pedir(() => http.get(Uri.parse(url!), headers: cab)) as Map<String, dynamic>;
      for (final ep in (json['data'] as List? ?? [])) {
        final a = ((ep as Map<String, dynamic>)['attributes'] as Map<String, dynamic>?) ?? {};
        if (a['number'] != null) {
          lista.add({'n': a['number'], 'titulo': a['canonicalTitle'], 'filler': false, 'recap': false});
        }
      }
      aoAvancar?.call(lista.length);
      url = (json['links'] as Map<String, dynamic>?)?['next'] as String?;
    }
    return lista;
  }
}

// Marca interna: o pedido falhou de uma forma que vale a pena repetir
class _Repetir implements Exception {}
