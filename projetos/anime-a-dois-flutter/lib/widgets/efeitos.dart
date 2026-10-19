// Efeitos visuais da 1.8.0 (a app com "vida"):
//   ViraCartao      — o cartão do episódio vira ao ser marcado
//   explosao()      — partículas a saltar de um ponto do ecrã (cor da série e a tua)
//   coracao()       — ❤️ a crescer e a subir (quando os dois já viram o episódio)
//   NumeroAContar   — número que sobe de 0 até ao valor
//   PistaCorrida    — barra de progresso com a foto da pessoa a correr (⚡ em quem vai à frente)
//   Cascata         — entrada suave, um a um, dos cartões de uma lista
//   Confettis       — chuva de papelinhos (celebração ao acabar uma série)

import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../api/modelos.dart';
import '../tema/paleta.dart';
import 'comum.dart';

// ---------- Cartão que vira ----------

// Quando [virado] passa de false para true, o cartão dá meia-volta (sem ficar ao espelho) e cresce um pouco
class ViraCartao extends StatefulWidget {
  final bool virado;
  final Widget child;

  const ViraCartao({super.key, required this.virado, required this.child});

  @override
  State<ViraCartao> createState() => _ViraCartaoState();
}

class _ViraCartaoState extends State<ViraCartao> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 520));

  @override
  void didUpdateWidget(ViraCartao antigo) {
    super.didUpdateWidget(antigo);
    if (widget.virado && !antigo.virado) _c.forward(from: 0); // só ao marcar (desmarcar não vira)
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      child: widget.child,
      builder: (_, filho) {
        final t = Curves.easeInOutCubic.transform(_c.value);
        // 0 → 90° (fica de lado) e depois -90° → 0: a face nunca aparece ao contrário
        final angulo = t < .5 ? math.pi * t : math.pi * t - math.pi;
        final escala = 1 + .07 * math.sin(math.pi * t); // pequeno "salto" a meio
        return Transform.scale(
          scale: escala,
          child: Transform(
            alignment: Alignment.center,
            transform: Matrix4.identity()
              ..setEntry(3, 2, .0015) // perspetiva
              ..rotateY(angulo),
            child: filho,
          ),
        );
      },
    );
  }
}

// ---------- Partículas ----------

// Partículas a saltar de [centro] (coordenadas do ecrã) nas [cores] dadas; somem sozinhas
void explosao(BuildContext context, Offset centro, List<Color> cores, {int quantas = 26}) {
  final overlay = Overlay.maybeOf(context);
  if (overlay == null) return;
  late final OverlayEntry entrada;
  entrada = OverlayEntry(
    builder: (_) => _Explosao(centro: centro, cores: cores, quantas: quantas, acabou: () => entrada.remove()),
  );
  overlay.insert(entrada);
}

class _Particula {
  final double angulo, velocidade, tamanho, rodar;
  final Color cor;
  final bool redonda;

  _Particula(math.Random r, List<Color> cores)
      : angulo = r.nextDouble() * 2 * math.pi,
        velocidade = 140 + r.nextDouble() * 220,
        tamanho = 4 + r.nextDouble() * 6,
        rodar = (r.nextDouble() - .5) * 10,
        cor = cores[r.nextInt(cores.length)],
        redonda = r.nextBool();
}

class _Explosao extends StatefulWidget {
  final Offset centro;
  final List<Color> cores;
  final int quantas;
  final VoidCallback acabou;

  const _Explosao({required this.centro, required this.cores, required this.quantas, required this.acabou});

  @override
  State<_Explosao> createState() => _ExplosaoState();
}

class _ExplosaoState extends State<_Explosao> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 850))
    ..addStatusListener((s) {
      if (s == AnimationStatus.completed) widget.acabou();
    })
    ..forward();
  late final _particulas = List.generate(widget.quantas, (_) => _Particula(math.Random(), widget.cores));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, __) => CustomPaint(
          size: MediaQuery.sizeOf(context),
          painter: _PintaExplosao(widget.centro, _particulas, _c.value),
        ),
      ),
    );
  }
}

class _PintaExplosao extends CustomPainter {
  final Offset centro;
  final List<_Particula> particulas;
  final double t; // 0–1

  _PintaExplosao(this.centro, this.particulas, this.t);

  @override
  void paint(Canvas canvas, Size size) {
    final s = Curves.easeOutCubic.transform(t);
    for (final p in particulas) {
      // Sai em linha reta, abranda e cai um pouco (gravidade)
      final pos = centro +
          Offset(math.cos(p.angulo), math.sin(p.angulo)) * (p.velocidade * s) +
          Offset(0, 120 * t * t);
      final tinta = Paint()..color = p.cor.withValues(alpha: (1 - t).clamp(0, 1));
      canvas.save();
      canvas.translate(pos.dx, pos.dy);
      canvas.rotate(p.rodar * t);
      if (p.redonda) {
        canvas.drawCircle(Offset.zero, p.tamanho / 2, tinta);
      } else {
        canvas.drawRRect(
          RRect.fromRectAndRadius(Rect.fromCenter(center: Offset.zero, width: p.tamanho, height: p.tamanho * .55), const Radius.circular(1.5)),
          tinta,
        );
      }
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_PintaExplosao antigo) => antigo.t != t;
}

// ❤️ que cresce em [centro], sobe e desaparece (os dois já viram este episódio)
void coracao(BuildContext context, Offset centro) {
  final overlay = Overlay.maybeOf(context);
  if (overlay == null) return;
  late final OverlayEntry entrada;
  entrada = OverlayEntry(builder: (_) => _Coracao(centro: centro, acabou: () => entrada.remove()));
  overlay.insert(entrada);
}

class _Coracao extends StatefulWidget {
  final Offset centro;
  final VoidCallback acabou;

  const _Coracao({required this.centro, required this.acabou});

  @override
  State<_Coracao> createState() => _CoracaoState();
}

class _CoracaoState extends State<_Coracao> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))
    ..addStatusListener((s) {
      if (s == AnimationStatus.completed) widget.acabou();
    })
    ..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, __) {
          final t = _c.value;
          final escala = Curves.elasticOut.transform((t / .45).clamp(0, 1)) * 1.6;
          final opacidade = t < .6 ? 1.0 : (1 - (t - .6) / .4);
          return Stack(
            children: [
              Positioned(
                left: widget.centro.dx - 40,
                top: widget.centro.dy - 40 - 70 * Curves.easeIn.transform(t),
                child: Opacity(
                  opacity: opacidade.clamp(0, 1),
                  child: Transform.scale(
                    scale: escala,
                    child: const SizedBox(
                      width: 80,
                      height: 80,
                      child: Center(child: Text('❤️', style: TextStyle(fontSize: 44))),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

// ---------- Números ----------

// Número que sobe de 0 até [valor] (e anima de novo quando o valor muda)
class NumeroAContar extends StatelessWidget {
  final int valor;
  final TextStyle? estilo;
  final Duration duracao;

  const NumeroAContar({super.key, required this.valor, this.estilo, this.duracao = const Duration(milliseconds: 900)});

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: valor.toDouble()),
      duration: duracao,
      curve: Curves.easeOutCubic,
      builder: (_, v, __) => Text('${v.round()}', style: estilo),
    );
  }
}

// ---------- Corrida ----------

// Pista de uma pessoa: a parte já vista enche-se e a foto dela corre até lá; ⚡ em quem vai à frente
class PistaCorrida extends StatelessWidget {
  final Utilizador? user;
  final int pct; // 0–100
  final Color cor;
  final bool lider;

  const PistaCorrida({super.key, required this.user, required this.pct, required this.cor, this.lider = false});

  static const _foto = 28.0;

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return LayoutBuilder(
      builder: (_, c) => TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: pct.clamp(0, 100) / 100),
        duration: const Duration(milliseconds: 1100),
        curve: Curves.easeOutCubic,
        builder: (_, v, __) {
          final x = (c.maxWidth - _foto) * v; // posição da foto
          return SizedBox(
            height: _foto + 4,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.centerLeft,
              children: [
                // Pista (fundo) com tracejado de meta no fim
                Container(
                  height: 6,
                  decoration: BoxDecoration(color: p.trilho, borderRadius: BorderRadius.circular(6)),
                ),
                // Parte já percorrida
                Container(
                  width: x + _foto / 2,
                  height: 6,
                  decoration: BoxDecoration(color: cor, borderRadius: BorderRadius.circular(6)),
                ),
                // A pessoa, com aro na sua cor
                Positioned(
                  left: x,
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(2),
                        decoration: BoxDecoration(color: cor, shape: BoxShape.circle),
                        child: Avatar(user: user, cor: cor, tamanho: _foto - 4),
                      ),
                      if (lider)
                        const Positioned(
                          right: -8,
                          top: -9,
                          child: Text('⚡', style: TextStyle(fontSize: 15)),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

// ---------- Entrada em cascata ----------

// O filho entra a deslizar de baixo e a aparecer; o [indice] atrasa um pouco cada um (máx. ~0,4 s)
class Cascata extends StatefulWidget {
  final int indice;
  final Widget child;

  const Cascata({super.key, required this.indice, required this.child});

  @override
  State<Cascata> createState() => _CascataState();
}

class _CascataState extends State<Cascata> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));

  @override
  void initState() {
    super.initState();
    final atraso = Duration(milliseconds: math.min(widget.indice, 7) * 55);
    Future.delayed(atraso, () {
      if (mounted) _c.forward();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final curva = CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);
    return FadeTransition(
      opacity: curva,
      child: SlideTransition(
        position: Tween(begin: const Offset(0, .12), end: Offset.zero).animate(curva),
        child: widget.child,
      ),
    );
  }
}

// Embrulha cada widget de uma lista numa Cascata (pela ordem)
List<Widget> emCascata(List<Widget> filhos) => [
      for (var i = 0; i < filhos.length; i++) Cascata(indice: i, child: filhos[i]),
    ];

// ---------- Confettis ----------

// Chuva de papelinhos por cima do ecrã (cai uma vez, ~3,5 s)
class Confettis extends StatefulWidget {
  final List<Color> cores;

  const Confettis({super.key, required this.cores});

  @override
  State<Confettis> createState() => _ConfettisState();
}

class _Papel {
  final double x, atraso, velocidade, balanco, rodar, largura;
  final Color cor;

  _Papel(math.Random r, List<Color> cores)
      : x = r.nextDouble(),
        atraso = r.nextDouble() * .35,
        velocidade = .55 + r.nextDouble() * .6,
        balanco = 10 + r.nextDouble() * 26,
        rodar = (r.nextDouble() - .5) * 14,
        largura = 6 + r.nextDouble() * 6,
        cor = cores[r.nextInt(cores.length)];
}

class _ConfettisState extends State<Confettis> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 3600))..forward();
  late final _papeis = List.generate(140, (_) => _Papel(math.Random(), widget.cores));

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, __) => CustomPaint(size: Size.infinite, painter: _PintaConfettis(_papeis, _c.value)),
      ),
    );
  }
}

class _PintaConfettis extends CustomPainter {
  final List<_Papel> papeis;
  final double t;

  _PintaConfettis(this.papeis, this.t);

  @override
  void paint(Canvas canvas, Size size) {
    for (final p in papeis) {
      final local = ((t - p.atraso) / (1 - p.atraso)).clamp(0.0, 1.0);
      if (local <= 0) continue;
      final y = -20 + (size.height + 40) * local * p.velocidade * 1.4;
      if (y > size.height + 20) continue;
      final x = size.width * p.x + math.sin(local * 10 + p.x * 6) * p.balanco;
      final opacidade = local > .85 ? (1 - local) / .15 : 1.0;
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(p.rodar * local);
      canvas.drawRect(
        Rect.fromCenter(center: Offset.zero, width: p.largura, height: p.largura * .5),
        Paint()..color = p.cor.withValues(alpha: opacidade.clamp(0, 1)),
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_PintaConfettis antigo) => antigo.t != t;
}
