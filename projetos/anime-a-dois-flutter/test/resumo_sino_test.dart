// Testes do resumo do topo do Início ("Andreia viu 3 episódios desde ontem") e do "desde quando".
// Correr com: flutter test

import 'package:anime_a_dois/ecras/notificacoes.dart';
import 'package:anime_a_dois/widgets/comum.dart';
import 'package:flutter_test/flutter_test.dart';

// Notificação de teste, como a API a envia
Notificacao _n(String tipo, int quem, String nome, String texto, DateTime em, {int? quantos}) => Notificacao.deJson({
      'id': '$tipo-$quem-${em.millisecondsSinceEpoch}',
      'tipo': tipo,
      'em': em.toIso8601String(),
      'quem': {'id': quem, 'nome': nome, 'username': nome.toLowerCase(), 'inicial': nome[0]},
      'texto': '$nome $texto',
      'detalhe': '',
      if (quantos != null) 'quantos': quantos,
    });

void main() {
  // "Agora" fixo: quinta, 22 out 2026, 16:00 (hora local)
  final agora = DateTime(2026, 10, 22, 16, 0);

  test('desde quando', () {
    expect(desdeQuando(DateTime(2026, 10, 22, 9, 0), agora: agora), 'hoje');
    expect(desdeQuando(DateTime(2026, 10, 21, 23, 0), agora: agora), 'desde ontem');
    expect(desdeQuando(DateTime(2026, 10, 17, 21, 0), agora: agora), 'desde sábado');
    expect(desdeQuando(DateTime(2026, 10, 2, 21, 0), agora: agora), 'desde 2 out');
  });

  test('sem novidades não há resumo', () {
    expect(resumoDoSino([], agora: agora), isNull);
  });

  test('só episódios de uma pessoa: soma os episódios e diz desde quando', () {
    final lista = [
      _n('ep', 2, 'Andreia', 'viu os episódios 12 a 14', DateTime(2026, 10, 22, 10, 0)), // 3 (do texto)
      _n('ep', 2, 'Andreia', 'viu o episódio 5', DateTime(2026, 10, 21, 22, 0)), // 1
    ];
    expect(resumoDoSino(lista, agora: agora), 'Andreia viu 4 episódios desde ontem');
  });

  test('um só episódio fica no singular', () {
    final lista = [_n('ep', 2, 'Andreia', 'viu o episódio 5', DateTime(2026, 10, 22, 10, 0))];
    expect(resumoDoSino(lista, agora: agora), 'Andreia viu 1 episódio hoje');
  });

  test('o campo quantos da API tem prioridade sobre o texto', () {
    final lista = [_n('ep', 2, 'Andreia', 'viu 7 episódios', DateTime(2026, 10, 22, 10, 0), quantos: 7)];
    expect(resumoDoSino(lista, agora: agora), 'Andreia viu 7 episódios hoje');
    final semCampo = [_n('ep', 2, 'Andreia', 'viu 7 episódios', DateTime(2026, 10, 22, 10, 0))];
    expect(resumoDoSino(semCampo, agora: agora), 'Andreia viu 7 episódios hoje');
  });

  test('uma novidade de outro tipo mostra o texto dela', () {
    final lista = [_n('amigo', 5, 'nês', 'quer ser teu amigo', DateTime(2026, 10, 22, 10, 0))];
    expect(resumoDoSino(lista, agora: agora), 'nês quer ser teu amigo');
  });

  test('várias pessoas ou tipos: conta as novidades', () {
    final lista = [
      _n('ep', 2, 'Andreia', 'viu o episódio 5', DateTime(2026, 10, 22, 10, 0)),
      _n('com', 5, 'nês', 'comentou o episódio 3', DateTime(2026, 10, 22, 9, 0)),
    ];
    expect(resumoDoSino(lista, agora: agora), '2 novidades');
  });
}
