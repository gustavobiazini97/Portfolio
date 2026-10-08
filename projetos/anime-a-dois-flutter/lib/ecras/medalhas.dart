// Medalhas: vitrine agrupada (Episódios, Tempo, Ritmo, Séries, Juntos), com o progresso das que faltam.
// As novas desde a última visita aparecem com uma animação de desbloqueio; tocar numa já obtida repete-a.

import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

// Uma medalha como a API a envia (GET /medalhas)
class Medalha {
  final String id, emoji, nome, descricao, grupo;
  final int meta, atual;
  final bool obtida;

  Medalha.deJson(Map<String, dynamic> j)
      : id = j['id'] as String,
        emoji = j['emoji'] as String,
        nome = j['nome'] as String,
        descricao = j['descricao'] as String,
        grupo = j['grupo'] as String,
        meta = (j['meta'] as num).toInt(),
        atual = (j['atual'] as num).toInt(),
        obtida = j['obtida'] == true;

  // Progresso 0–100 (para a barra das que faltam)
  int get pct => meta == 0 ? 0 : (atual * 100 / meta).floor().clamp(0, 100);

  // "99 de 100" (o tempo em horas)
  String get progressoTexto => id.startsWith('horas') ? '${atual ~/ 60} de ${meta ~/ 60} h' : '$atual de $meta';
}

// Chave no telemóvel com os ids das medalhas já mostradas
const _chaveVistas = 'medalhas_vistas';

// Busca as medalhas e devolve os nomes das que foram obtidas desde a última vez (e marca-as como vistas).
// Na primeira vez guarda o estado sem anunciar nada (para não aparecerem todas de uma vez).
Future<List<String>> medalhasNovas() async {
  try {
    final d = await Api.instancia.get('/medalhas');
    final obtidas = ((d['medalhas'] as List).map((m) => Medalha.deJson(m as Map<String, dynamic>))).where((m) => m.obtida).toList();
    final prefs = await SharedPreferences.getInstance();
    final vistas = prefs.getStringList(_chaveVistas);
    await prefs.setStringList(_chaveVistas, obtidas.map((m) => m.id).toList());
    if (vistas == null) return [];
    return obtidas.where((m) => !vistas.contains(m.id)).map((m) => '${m.emoji} ${m.nome}').toList();
  } catch (_) {
    return []; // sem rede: fica para a próxima
  }
}

class EcraMedalhas extends StatefulWidget {
  const EcraMedalhas({super.key});

  @override
  State<EcraMedalhas> createState() => _EcraMedalhasState();
}

class _EcraMedalhasState extends State<EcraMedalhas> {
  List<Medalha> _medalhas = [];
  bool _aCarregar = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/medalhas');
      final lista = (d['medalhas'] as List).map((m) => Medalha.deJson(m as Map<String, dynamic>)).toList();
      // Novas desde a última vez: celebra uma a uma
      final prefs = await SharedPreferences.getInstance();
      final vistas = prefs.getStringList(_chaveVistas);
      final obtidas = lista.where((m) => m.obtida).toList();
      await prefs.setStringList(_chaveVistas, obtidas.map((m) => m.id).toList());
      if (!mounted) return;
      setState(() {
        _medalhas = lista;
        _aCarregar = false;
        _erro = null;
      });
      if (vistas != null) {
        for (final m in obtidas.where((m) => !vistas.contains(m.id))) {
          if (!mounted) return;
          await celebrar(m);
        }
      }
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  // Animação de desbloqueio: a medalha cresce a rodar, com raios à volta
  Future<void> celebrar(Medalha m) async {
    HapticFeedback.heavyImpact();
    await showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Fechar',
      barrierColor: Colors.black.withValues(alpha: .6),
      transitionDuration: const Duration(milliseconds: 900),
      pageBuilder: (ctx, _, __) => _Desbloqueio(medalha: m),
      transitionBuilder: (ctx, anim, _, filho) => FadeTransition(opacity: CurvedAnimation(parent: anim, curve: const Interval(0, .4)), child: filho),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final obtidas = _medalhas.where((m) => m.obtida).length;
    final grupos = <String>[];
    for (final m in _medalhas) {
      if (!grupos.contains(m.grupo)) grupos.add(m.grupo);
    }

    return Scaffold(
      body: Fundo(
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 6, 8, 0),
                child: Row(
                  children: [
                    const BackButton(),
                    Text('Medalhas', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              Expanded(
                child: _aCarregar
                    ? const Carregando()
                    : _erro != null
                        ? ErroComRetry(mensagem: _erro!, aoTentar: _carregar)
                        : ListView(
                            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                            children: [
                              // Resumo: quantas tens e a barra total
                              Vidro(
                                child: Row(
                                  children: [
                                    const Text('🏅', style: TextStyle(fontSize: 34)),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text('$obtidas de ${_medalhas.length} medalhas',
                                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                                          const SizedBox(height: 8),
                                          BarraProgresso(
                                              pct: _medalhas.isEmpty ? 0 : (obtidas * 100 ~/ _medalhas.length), cor: p.tu, altura: 8),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              for (final g in grupos) ...[
                                const SizedBox(height: 18),
                                Padding(
                                  padding: const EdgeInsets.only(left: 4, bottom: 10),
                                  child: Text(g, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
                                ),
                                GridView.count(
                                  crossAxisCount: 3,
                                  shrinkWrap: true,
                                  physics: const NeverScrollableScrollPhysics(),
                                  mainAxisSpacing: 12,
                                  crossAxisSpacing: 12,
                                  childAspectRatio: .78,
                                  children: [for (final m in _medalhas.where((m) => m.grupo == g)) _cartao(p, m)],
                                ),
                              ],
                            ],
                          ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Uma medalha: círculo com o emoji (a preto e branco se faltar), nome e progresso
  Widget _cartao(Paleta p, Medalha m) {
    return Vidro(
      raio: 22,
      padding: const EdgeInsets.fromLTRB(8, 12, 8, 10),
      onTap: () => m.obtida ? celebrar(m) : _detalhe(p, m),
      child: Column(
        children: [
          Container(
            width: 56,
            height: 56,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: m.obtida ? LinearGradient(colors: [p.tu, p.par], begin: Alignment.topLeft, end: Alignment.bottomRight) : null,
              color: m.obtida ? null : p.trilho,
              boxShadow: m.obtida ? [BoxShadow(color: p.par.withValues(alpha: .45), blurRadius: 14)] : null,
            ),
            child: Opacity(
              opacity: m.obtida ? 1 : .35,
              child: ColorFiltered(
                colorFilter: m.obtida
                    ? const ColorFilter.mode(Colors.transparent, BlendMode.dst)
                    : const ColorFilter.matrix(<double>[
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0, 0, 0, 1, 0,
                      ]),
                child: Text(m.emoji, style: const TextStyle(fontSize: 26)),
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(m.nome,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: m.obtida ? p.tinta : p.suave)),
          const Spacer(),
          if (m.obtida)
            Text('obtida', style: TextStyle(fontSize: 11, color: p.tuTxt, fontWeight: FontWeight.w700))
          else ...[
            BarraProgresso(pct: m.pct, cor: p.par, altura: 4),
            const SizedBox(height: 3),
            Text(m.progressoTexto, style: TextStyle(fontSize: 10.5, color: p.suave)),
          ],
        ],
      ),
    );
  }

  // Detalhe de uma medalha por obter: o que falta
  void _detalhe(Paleta p, Medalha m) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(m.emoji, style: const TextStyle(fontSize: 48)),
              const SizedBox(height: 8),
              Text(m.nome, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text(m.descricao, textAlign: TextAlign.center, style: TextStyle(color: p.suave)),
              const SizedBox(height: 16),
              BarraProgresso(pct: m.pct, cor: p.par, altura: 10),
              const SizedBox(height: 6),
              Text('${m.progressoTexto} · ${m.pct}%', style: TextStyle(fontSize: 13, color: p.suave)),
            ],
          ),
        ),
      ),
    );
  }
}

// Ecrã do desbloqueio: raios a girar, a medalha a entrar com mola e o nome por baixo
class _Desbloqueio extends StatefulWidget {
  final Medalha medalha;

  const _Desbloqueio({required this.medalha});

  @override
  State<_Desbloqueio> createState() => _DesbloqueioState();
}

class _DesbloqueioState extends State<_Desbloqueio> with TickerProviderStateMixin {
  late final AnimationController _entrada = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..forward();
  late final AnimationController _raios = AnimationController(vsync: this, duration: const Duration(seconds: 8))..repeat();

  @override
  void dispose() {
    _entrada.dispose();
    _raios.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final m = widget.medalha;
    final escala = CurvedAnimation(parent: _entrada, curve: Curves.elasticOut);
    final volta = CurvedAnimation(parent: _entrada, curve: const Interval(0, .6, curve: Curves.easeOutBack));

    return GestureDetector(
      onTap: () => Navigator.of(context).pop(),
      child: Material(
        color: Colors.transparent,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 260,
                height: 260,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    // Raios de luz a girar devagar
                    RotationTransition(
                      turns: _raios,
                      child: CustomPaint(size: const Size(260, 260), painter: _Raios(p.par.withValues(alpha: .35))),
                    ),
                    // A medalha: entra a crescer com mola e dá uma volta
                    AnimatedBuilder(
                      animation: _entrada,
                      builder: (_, __) => Transform.rotate(
                        angle: (1 - volta.value) * math.pi,
                        child: Transform.scale(
                          scale: escala.value,
                          child: Container(
                            width: 140,
                            height: 140,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: LinearGradient(colors: [p.tu, p.par], begin: Alignment.topLeft, end: Alignment.bottomRight),
                              border: Border.all(color: Colors.white.withValues(alpha: .8), width: 4),
                              boxShadow: [BoxShadow(color: p.par.withValues(alpha: .7), blurRadius: 40)],
                            ),
                            child: Text(m.emoji, style: const TextStyle(fontSize: 64)),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              FadeTransition(
                opacity: CurvedAnimation(parent: _entrada, curve: const Interval(.5, 1)),
                child: Column(
                  children: [
                    const Text('MEDALHA DESBLOQUEADA',
                        style: TextStyle(color: Colors.white70, fontSize: 12, letterSpacing: 2.4, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    Text(m.nome, style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(m.descricao, style: const TextStyle(color: Colors.white70, fontSize: 14)),
                    const SizedBox(height: 24),
                    const Text('toca para continuar', style: TextStyle(color: Colors.white38, fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// Raios à volta da medalha (12 fatias alternadas)
class _Raios extends CustomPainter {
  final Color cor;

  _Raios(this.cor);

  @override
  void paint(Canvas canvas, Size size) {
    final centro = size.center(Offset.zero);
    final r = size.width / 2;
    final tinta = Paint()..color = cor;
    for (var i = 0; i < 12; i++) {
      final a = i * math.pi / 6;
      final caminho = Path()
        ..moveTo(centro.dx, centro.dy)
        ..lineTo(centro.dx + r * math.cos(a - .12), centro.dy + r * math.sin(a - .12))
        ..lineTo(centro.dx + r * math.cos(a + .12), centro.dy + r * math.sin(a + .12))
        ..close();
      canvas.drawPath(caminho, tinta);
    }
  }

  @override
  bool shouldRepaint(covariant _Raios antigo) => antigo.cor != cor;
}
