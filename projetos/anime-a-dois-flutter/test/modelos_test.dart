// Testes dos modelos: o JSON da API (igual ao que o api.php devolve) converte-se bem em classes Dart.
// Correr com: flutter test

import 'package:anime_a_dois/api/modelos.dart';
import 'package:flutter/painting.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Série com progresso dos dois e cor em hexadecimal', () {
    final s = Serie.deJson({
      'slug': 'naruto',
      'nome': 'Naruto',
      'nomeCurto': 'Naruto',
      'anos': '2002–2007',
      'totalEpisodios': 220,
      'cor': '#F7C59F',
      'tu': {'vistos': 12, 'pct': 5, 'posicao': 12},
      'par': {'vistos': 5, 'pct': 2, 'posicao': 5},
      'resumo': 'Vais 7 episódios à frente.',
    });
    expect(s.tu.posicao, 12);
    expect(s.par?.vistos, 5);
    expect(s.cor, const Color(0xFFF7C59F));
  });

  test('Série sem par (ainda sem conta)', () {
    final s = Serie.deJson({
      'slug': 'boruto',
      'nome': 'Boruto',
      'totalEpisodios': 293,
      'cor': '#A9C4EE',
      'tu': {'vistos': 0, 'pct': 0, 'posicao': 0},
      'par': null,
      'resumo': '',
    });
    expect(s.par, isNull);
    expect(s.nomeCurto, 'Boruto'); // sem nomeCurto usa o nome
  });

  test('Episódio aceita filler como true ou 1', () {
    expect(Episodio.deJson({'id': 26, 'numero': 26, 'filler': true}).filler, isTrue);
    expect(Episodio.deJson({'id': 26, 'numero': 26, 'filler': 1}).filler, isTrue);
    expect(Episodio.deJson({'id': 1, 'numero': 1, 'filler': false}).filler, isFalse);
  });

  test('Estatísticas com recorde e sem previsão', () {
    final e = Estatisticas.deJson({
      'pessoas': [
        {
          'user': {'id': 1, 'nome': 'Gus', 'username': 'gus', 'inicial': 'G', 'foto': null},
          'cor': 'tu',
          'vistos': 12,
          'horas': '4,6 h',
          'fillers': 'nenhum filler',
        },
      ],
      'semanas': [
        {'rotulo': 'esta', 'tu': 12, 'par': 5, 'nivelTu': 10, 'nivelPar': 4},
      ],
      'previsao': null,
      'arcos': [],
      'comentarios': 0,
      'recorde': {'n': 12, 'quem': 'tu'},
    });
    expect(e.pessoas.single.ehTu, isTrue);
    expect(e.semanas.single.nivelPar, 4);
    expect(e.recordeN, 12);
    expect(e.previsao, isNull);
  });
}
