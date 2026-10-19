// Testes do texto com o dia de um registo ("Ep. 14 · ontem, 22:10") no perfil do par/amigo.
// Correr com: flutter test

import 'package:anime_a_dois/widgets/comum.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  // "Agora" fixo: segunda, 19 out 2026, 20:30 (hora local)
  final agora = DateTime(2026, 10, 19, 20, 30);

  test('hoje e ontem mostram a hora', () {
    expect(diaDoRegisto(DateTime(2026, 10, 19, 8, 5), agora: agora), 'hoje, 08:05');
    expect(diaDoRegisto(DateTime(2026, 10, 18, 22, 10), agora: agora), 'ontem, 22:10');
  });

  test('esta semana mostra o dia da semana', () {
    expect(diaDoRegisto(DateTime(2026, 10, 17, 21, 0), agora: agora), 'sábado, 21:00');
  });

  test('mais antigo mostra a data (com ano só se for de outro ano)', () {
    expect(diaDoRegisto(DateTime(2026, 10, 2, 21, 0), agora: agora), '2 out');
    expect(diaDoRegisto(DateTime(2025, 12, 24, 21, 0), agora: agora), '24 dez 2025');
  });

  test('JSON da API sem registo ou incompleto dá null', () {
    expect(textoUltimoRegisto(null), isNull);
    expect(textoUltimoRegisto({'numero': 3}), isNull);
    expect(textoUltimoRegisto({'numero': 14, 'vistoEm': '2020-01-01T21:00:00+00:00'}), startsWith('Ep. 14 · '));
  });
}
