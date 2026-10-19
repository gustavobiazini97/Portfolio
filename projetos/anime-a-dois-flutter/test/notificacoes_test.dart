// Testes do modelo do sininho: o JSON do GET /notificacoes converte-se bem.
// Correr com: flutter test

import 'package:anime_a_dois/ecras/notificacoes.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('notificação de episódios', () {
    final n = Notificacao.deJson({
      'id': 'ep-2-1-1760900000',
      'tipo': 'ep',
      'em': '2026-10-18T22:10:00+01:00',
      'quem': {'id': 2, 'nome': 'Andreia', 'username': 'andreia', 'inicial': 'A', 'foto': null},
      'texto': 'Andreia viu os episódios 12 a 14',
      'detalhe': 'Naruto',
      'serie': 'naruto',
      'episodio': 14,
    });
    expect(n.tipo, 'ep');
    expect(n.quem.nome, 'Andreia');
    expect(n.serie, 'naruto');
    expect(n.episodio, 14);
    expect(n.emoji, '▶️');
  });

  test('pedido de amizade não tem série', () {
    final n = Notificacao.deJson({
      'id': 'amigo-5',
      'tipo': 'amigo',
      'em': '2026-10-18T10:00:00+01:00',
      'quem': {'id': 5, 'nome': 'Rui', 'username': 'rui', 'inicial': 'R', 'foto': null},
      'texto': 'Rui quer ser teu amigo',
      'detalhe': 'Responde em Amigos',
      'serie': null,
      'episodio': null,
    });
    expect(n.serie, isNull);
    expect(n.episodio, isNull);
    expect(n.emoji, '👋');
  });

  test('filtros: outras séries do par sim, dos amigos não (por omissão)', () {
    Notificacao ep({required bool juntos, required String relacao}) => Notificacao.deJson({
          'id': 'x',
          'tipo': 'ep',
          'em': '2026-10-18T10:00:00+01:00',
          'quem': {'id': 2, 'nome': 'A', 'username': 'a', 'inicial': 'A', 'foto': null},
          'texto': '',
          'detalhe': '',
          'serie': 's',
          'episodio': 1,
          'juntos': juntos,
          'relacao': relacao,
        });
    final f = FiltrosSino();
    expect(f.deixa(ep(juntos: true, relacao: 'amigo')), isTrue);
    expect(f.deixa(ep(juntos: false, relacao: 'par')), isTrue);
    expect(f.deixa(ep(juntos: false, relacao: 'amigo')), isFalse);
    f.outrasPar = false;
    expect(f.deixa(ep(juntos: false, relacao: 'par')), isFalse);
  });
}
