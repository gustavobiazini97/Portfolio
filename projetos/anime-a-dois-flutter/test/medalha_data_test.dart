// Testes do texto com o dia em que uma medalha foi conseguida ("Conseguida a 14 out 2026").
// Correr com: flutter test

import 'package:anime_a_dois/ecras/medalhas.dart';
import 'package:flutter_test/flutter_test.dart';

Medalha _m({bool obtida = true, String? em, bool aprox = false}) => Medalha.deJson({
      'id': 'ep-100',
      'emoji': '💯',
      'nome': 'Centenário',
      'descricao': 'Vê 100 episódios.',
      'grupo': 'Episódios',
      'nivel': 'prata',
      'meta': 100,
      'atual': obtida ? 100 : 40,
      'obtida': obtida,
      'obtidaEm': em,
      'obtidaAprox': aprox,
    });

void main() {
  // "Agora" fixo: quinta, 22 out 2026, 16:00 (hora local)
  final agora = DateTime(2026, 10, 22, 16, 0);

  test('data normal', () {
    expect(_m(em: DateTime(2026, 10, 14, 21).toIso8601String()).quandoTexto(agora: agora), 'Conseguida a 14 out 2026');
  });

  test('hoje e ontem', () {
    expect(_m(em: DateTime(2026, 10, 22, 9).toIso8601String()).quandoTexto(agora: agora), 'Conseguida hoje');
    expect(_m(em: DateTime(2026, 10, 21, 23).toIso8601String()).quandoTexto(agora: agora), 'Conseguida ontem');
  });

  test('sem dia exato: "antes de"', () {
    expect(_m(em: DateTime(2026, 10, 22, 16).toIso8601String(), aprox: true).quandoTexto(agora: agora),
        'Conseguida antes de 22 out 2026');
  });

  test('sem data (API antiga) ou por obter: nada', () {
    expect(_m().quandoTexto(agora: agora), isNull);
    expect(_m(obtida: false, em: '2026-10-14T21:00:00+01:00').quandoTexto(agora: agora), isNull);
  });
}
