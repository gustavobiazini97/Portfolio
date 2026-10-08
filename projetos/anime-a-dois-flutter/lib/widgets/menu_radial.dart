// Menu radial: com toque longo, os botões abrem-se em arco à volta do dedo; arrasta-se até um
// e larga-se para o escolher (largar fora fecha sem fazer nada). Vibra ao passar por cada opção.
// Uso: abrir() no onLongPressStart, mover() no onLongPressMoveUpdate e fechar() no onLongPressEnd.

import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../tema/paleta.dart';

// Uma opção do menu
class OpcaoRadial {
  final IconData icone;
  final String texto;
  final VoidCallback acao;
  final bool destaque; // a ação principal (cor cheia)

  const OpcaoRadial({required this.icone, required this.texto, required this.acao, this.destaque = false});
}

class MenuRadial {
  OverlayEntry? _entrada;
  final ValueNotifier<int?> _foco = ValueNotifier(null); // opção debaixo do dedo
  List<OpcaoRadial> _opcoes = [];
  List<Offset> _posicoes = []; // centro de cada botão (coordenadas do ecrã)
  Offset _centro = Offset.zero;

  // Distância dos botões ao dedo e tamanho de cada botão
  static const _raio = 96.0;
  static const _botao = 56.0;

  bool get aberto => _entrada != null;

  // Abre o menu no sítio do dedo
  void abrir(BuildContext context, Offset dedo, List<OpcaoRadial> opcoes) {
    fechar(executar: false);
    final ecra = MediaQuery.sizeOf(context);
    _opcoes = opcoes;
    _centro = dedo;
    _posicoes = _calcularPosicoes(dedo, ecra, opcoes.length);
    _foco.value = null;
    HapticFeedback.mediumImpact();

    _entrada = OverlayEntry(builder: (_) => _Desenho(menu: this));
    Overlay.of(context).insert(_entrada!);
  }

  // O dedo mexeu-se: foca a opção mais próxima (se estiver perto o suficiente)
  void mover(Offset dedo) {
    if (!aberto) return;
    int? melhor;
    var distancia = double.infinity;
    for (var i = 0; i < _posicoes.length; i++) {
      final d = (_posicoes[i] - dedo).distance;
      if (d < distancia) {
        distancia = d;
        melhor = i;
      }
    }
    // Só conta se o dedo saiu do centro e está perto do botão
    final longeDoCentro = (dedo - _centro).distance > 34;
    final novo = (longeDoCentro && distancia < _botao) ? melhor : null;
    if (novo != _foco.value) {
      if (novo != null) HapticFeedback.selectionClick();
      _foco.value = novo;
    }
  }

  // Larga: executa a opção focada (se houver) e fecha
  void fechar({bool executar = true}) {
    final escolhida = _foco.value;
    _entrada?.remove();
    _entrada = null;
    _foco.value = null;
    if (executar && escolhida != null && escolhida < _opcoes.length) {
      HapticFeedback.lightImpact();
      _opcoes[escolhida].acao();
    }
  }

  // Botões em arco por cima do dedo (ou por baixo, se o dedo estiver perto do topo),
  // empurrados para dentro do ecrã quando o dedo está perto de uma borda
  static List<Offset> _calcularPosicoes(Offset dedo, Size ecra, int n) {
    final paraBaixo = dedo.dy < 200;
    // Arco de 150°, centrado em cima (270°) ou em baixo (90°)
    final meio = paraBaixo ? math.pi / 2 : 3 * math.pi / 2;
    const abertura = 150 * math.pi / 180;
    final lista = <Offset>[];
    for (var i = 0; i < n; i++) {
      final t = n == 1 ? 0.5 : i / (n - 1);
      final a = meio - abertura / 2 + abertura * t;
      lista.add(dedo + Offset(math.cos(a), math.sin(a)) * _raio);
    }
    // Ajuste horizontal: nenhum botão sai do ecrã
    const margem = _botao / 2 + 8;
    final minX = lista.map((o) => o.dx).reduce(math.min);
    final maxX = lista.map((o) => o.dx).reduce(math.max);
    var dx = 0.0;
    if (minX < margem) dx = margem - minX;
    if (maxX + dx > ecra.width - margem) dx = ecra.width - margem - maxX;
    return lista.map((o) => o + Offset(dx, 0)).toList();
  }
}

// O desenho do menu (por cima de tudo; não apanha toques: o gesto continua no cartão)
class _Desenho extends StatelessWidget {
  final MenuRadial menu;

  const _Desenho({required this.menu});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return IgnorePointer(
      child: Material(
        type: MaterialType.transparency, // estilo de texto normal (sem o sublinhado amarelo do Overlay)
        child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: 1),
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutBack,
        builder: (_, v, __) => ValueListenableBuilder<int?>(
          valueListenable: menu._foco,
          builder: (_, foco, __) => Stack(
            children: [
              // Fundo escurecido
              Positioned.fill(child: ColoredBox(color: Colors.black.withValues(alpha: (.35 * v.clamp(0, 1)).toDouble()))),
              // Ponto onde está o dedo
              Positioned(
                left: menu._centro.dx - 22,
                top: menu._centro.dy - 22,
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withValues(alpha: .25), border: Border.all(color: Colors.white70, width: 2)),
                ),
              ),
              // Os botões: saem do dedo para a posição final
              for (var i = 0; i < menu._opcoes.length; i++) _botao(p, i, foco == i, v),
              // Nome da opção focada (ou a dica), por cima do arco
              Positioned(
                left: 0,
                right: 0,
                top: _topoTexto(),
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                    decoration: BoxDecoration(color: p.btn, borderRadius: BorderRadius.circular(16)),
                    child: Text(
                      foco == null ? 'Arrasta até uma opção' : menu._opcoes[foco].texto,
                      style: TextStyle(color: p.btnTxt, fontSize: 13, fontWeight: FontWeight.w600, decoration: TextDecoration.none),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        ),
      ),
    );
  }

  // Altura do texto: por cima do arco (ou por baixo, se o arco abriu para baixo)
  double _topoTexto() {
    final ys = menu._posicoes.map((o) => o.dy);
    final paraBaixo = menu._posicoes.isNotEmpty && menu._posicoes.first.dy > menu._centro.dy;
    return paraBaixo ? ys.reduce(math.max) + MenuRadial._botao / 2 + 14 : ys.reduce(math.min) - MenuRadial._botao / 2 - 44;
  }

  Widget _botao(Paleta p, int i, bool focado, double v) {
    final o = menu._opcoes[i];
    final destino = menu._posicoes[i];
    final pos = Offset.lerp(menu._centro, destino, v)!;
    final tamanho = MenuRadial._botao * (focado ? 1.22 : 1);
    return Positioned(
      left: pos.dx - tamanho / 2,
      top: pos.dy - tamanho / 2,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        width: tamanho,
        height: tamanho,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: focado ? p.tuTxt : (o.destaque ? p.tu : p.bg),
          border: Border.all(color: focado ? Colors.white : p.vidroBorda, width: 2),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .25), blurRadius: 14, offset: const Offset(0, 6))],
        ),
        child: Icon(o.icone, size: focado ? 28 : 24, color: focado ? p.bg : (o.destaque ? p.noPastel : p.tinta)),
      ),
    );
  }
}
