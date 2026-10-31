// Texto do progresso da transferência da atualização
import 'package:anime_a_dois/estado/instalador.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('com tamanho conhecido', () {
    expect(Instalador.textoProgresso(5 * 1024 * 1024, 30 * 1024 * 1024), '5,0 / 30,0 MB');
  });
  test('sem tamanho conhecido', () {
    expect(Instalador.textoProgresso(1572864, null), '1,5 MB');
  });
}
