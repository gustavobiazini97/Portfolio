// Testes dos modelos: o JSON da API v2 (igual ao que o api.php devolve) converte-se bem em classes Dart.
// Correr com: flutter test

import 'package:anime_a_dois/api/modelos.dart';
import 'package:flutter/painting.dart';
import 'package:flutter_test/flutter_test.dart';

// Uma série como a API a envia
Map<String, dynamic> _serie({String slug = 'naruto', String cor = '#F7C59F'}) => {
      'slug': slug,
      'nome': 'Naruto',
      'nomeCurto': 'Naruto',
      'anos': '2002–2007',
      'totalEpisodios': 220,
      'capa': null,
      'tipo': null,
      'emEmissao': false,
      'minutosEp': null,
      'malId': 20,
      'cor': cor,
    };

Map<String, dynamic> _user(int id, String nome) =>
    {'id': id, 'nome': nome, 'username': nome.toLowerCase(), 'inicial': nome[0], 'foto': null};

void main() {
  test('Item da biblioteca com companheiro e cor em hexadecimal', () {
    final i = ItemBiblioteca.deJson({
      'serie': _serie(),
      'estado': 'a_ver',
      'estadoTexto': 'A ver',
      'conjunta': true,
      'pct': 3,
      'companheiros': [
        {'user': _user(1, 'Gustavo'), 'pct': 5},
      ],
    });
    expect(i.serie.cor, const Color(0xFFF7C59F));
    expect(i.companheiros.single.user.nome, 'Gustavo');
    expect(i.companheiros.single.pct, 5);
    expect(i.naTua, isFalse); // só vem no perfil de outra pessoa
  });

  test('Convite recebido: a outra pessoa é quem convidou', () {
    final c = ConviteSerie.deJson({
      'serie': _serie(slug: 'frieren', cor: '#B8C0F5'),
      'recebido': true,
      'de': _user(1, 'Gustavo'),
      'para': _user(3, 'Rui'),
    });
    expect(c.outro.nome, 'Gustavo');
  });

  test('Episódio com um booleano por companheiro', () {
    final e = Episodio.deJson({'id': 5, 'numero': 5, 'filler': 1, 'recap': false, 'tu': true, 'com': [false, true], 'coment': 2});
    expect(e.filler, isTrue);
    expect(e.com, [false, true]);
    expect(e.algumCom, isTrue);
    expect(Episodio.deJson({'id': 6, 'numero': 6}).algumCom, isFalse);
  });

  test('Estatísticas com vários companheiros', () {
    final e = Estatisticas.deJson({
      'pessoas': [
        {'user': _user(2, 'Andreia'), 'cor': 'tu', 'vistos': 7, 'horas': '2,7 h', 'fillers': 'nenhum filler'},
        {'user': _user(1, 'Gustavo'), 'cor': 'par', 'vistos': 12, 'horas': '4,6 h', 'fillers': 'nenhum filler'},
      ],
      'semanas': [
        {'rotulo': 'esta', 'tu': 7, 'com': [12], 'nivelTu': 6, 'nivelCom': [10]},
      ],
      'previsao': null,
      'arcos': [
        {'nome': 'Land of Waves', 'de': 1, 'ate': 19, 'filler': false, 'total': 19, 'pctTu': 37, 'pctCom': [63], 'selo': ''},
      ],
      'comentarios': 0,
      'recorde': {'n': 12, 'quem': 'Gustavo'},
    });
    expect(e.pessoas.first.ehTu, isTrue);
    expect(e.semanas.single.nivelCom, [10]);
    expect(e.arcos.single.pctCom, [63]);
    expect(e.recordeQuem, 'Gustavo');
  });
}
