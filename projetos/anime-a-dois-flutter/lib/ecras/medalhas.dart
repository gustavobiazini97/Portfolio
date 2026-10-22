// Medalhas: vitrine agrupada (Episódios, Tempo, Ritmo, Séries, Juntos, Especiais), com o progresso das que faltam.
// Cada medalha é um emblema bronze, prata ou ouro (as de ouro têm um brilho a passar); as que faltam
// aparecem escuras com cadeado e a barra de progresso.
// As novas desde a última visita aparecem com uma animação de desbloqueio (no máximo 3 seguidas, as melhores);
// tocar numa já obtida repete-a.

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
  final String nivel; // bronze | prata | ouro
  final int meta, atual;
  final bool obtida;
  final DateTime? obtidaEm; // dia em que foi conseguida (null: API antiga ou por obter)
  final bool obtidaAprox; // true = não deu para saber o dia exato ("antes de …")

  Medalha.deJson(Map<String, dynamic> j)
      : id = j['id'] as String,
        emoji = j['emoji'] as String,
        nome = j['nome'] as String,
        descricao = j['descricao'] as String,
        grupo = j['grupo'] as String,
        nivel = j['nivel'] as String? ?? 'bronze', // API antiga: sem nível
        meta = (j['meta'] as num).toInt(),
        atual = (j['atual'] as num).toInt(),
        obtida = j['obtida'] == true,
        obtidaEm = DateTime.tryParse(j['obtidaEm'] as String? ?? ''),
        obtidaAprox = j['obtidaAprox'] == true;

  // "Conseguida a 14 out 2026" / "Conseguida hoje" / "Conseguida antes de 22 out 2026" (null sem data)
  String? quandoTexto({DateTime? agora}) {
    final d = obtidaEm;
    if (!obtida || d == null) return null;
    if (obtidaAprox) return 'Conseguida antes de ${dataComAno(d)}';
    final local = d.toLocal();
    final hoje = agora ?? DateTime.now();
    final dias = DateTime(hoje.year, hoje.month, hoje.day).difference(DateTime(local.year, local.month, local.day)).inDays;
    if (dias <= 0) return 'Conseguida hoje';
    if (dias == 1) return 'Conseguida ontem';
    return 'Conseguida a ${dataComAno(d)}';
  }

  // Progresso 0–100 (para a barra das que faltam)
  int get pct => meta == 0 ? 0 : (atual * 100 / meta).floor().clamp(0, 100);

  // "99 de 100" (o tempo em horas)
  String get progressoTexto => id.startsWith('horas') ? '${atual ~/ 60} de ${meta ~/ 60} h' : '$atual de $meta';

  // Ordem dos níveis (para escolher as melhores): ouro > prata > bronze
  int get peso => const {'ouro': 3, 'prata': 2}[nivel] ?? 1;

  // "Bronze", "Prata", "Ouro"
  String get nivelTexto => const {'ouro': 'Ouro', 'prata': 'Prata'}[nivel] ?? 'Bronze';
}

// Cores de cada nível: [clara, escura, aro]
const _coresNivel = {
  'bronze': [Color(0xFFF2C29B), Color(0xFFA9612F), Color(0xFF6E3A1C)],
  'prata': [Color(0xFFF4F6F9), Color(0xFFA3AAB4), Color(0xFF66707C)],
  'ouro': [Color(0xFFFFE58F), Color(0xFFD9A21B), Color(0xFF946400)],
};

// Cor de destaque de um nível (texto "Ouro", raios do desbloqueio…)
Color corDoNivel(String nivel) => (_coresNivel[nivel] ?? _coresNivel['bronze']!)[1];

// Emblema de uma medalha: obtida → disco metálico do nível com aro e o emoji (ouro com brilho a passar);
// por obter → disco escuro com cadeado.
class EmblemaMedalha extends StatefulWidget {
  final Medalha medalha;
  final double tamanho;

  const EmblemaMedalha({super.key, required this.medalha, this.tamanho = 60});

  @override
  State<EmblemaMedalha> createState() => _EmblemaMedalhaState();
}

class _EmblemaMedalhaState extends State<EmblemaMedalha> with SingleTickerProviderStateMixin {
  // Brilho das de ouro: uma faixa de luz a atravessar a cada 3 s
  AnimationController? _brilho;

  bool get _comBrilho => widget.medalha.obtida && widget.medalha.nivel == 'ouro';

  @override
  void initState() {
    super.initState();
    if (_comBrilho) _brilho = AnimationController(vsync: this, duration: const Duration(seconds: 3))..repeat();
  }

  @override
  void dispose() {
    _brilho?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final m = widget.medalha;
    final t = widget.tamanho;

    // Por obter: disco escuro, aro tracejado ao de leve e cadeado
    if (!m.obtida) {
      return Container(
        width: t,
        height: t,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: p.trilho,
          border: Border.all(color: p.suave.withValues(alpha: .35), width: t * .05),
        ),
        child: Icon(Icons.lock_rounded, size: t * .38, color: p.suave.withValues(alpha: .7)),
      );
    }

    final c = _coresNivel[m.nivel] ?? _coresNivel['bronze']!;
    final disco = Container(
      width: t,
      height: t,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        // Metal: claro em cima à esquerda, escuro em baixo à direita
        gradient: LinearGradient(colors: [c[0], c[1]], begin: Alignment.topLeft, end: Alignment.bottomRight),
        border: Border.all(color: c[2], width: t * .07),
        boxShadow: [BoxShadow(color: c[1].withValues(alpha: m.nivel == 'ouro' ? .6 : .35), blurRadius: t * .25)],
      ),
      // Anel interior fino (dá o ar de moeda cunhada)
      child: Container(
        width: t * .72,
        height: t * .72,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white.withValues(alpha: .45), width: 1.2),
        ),
        child: Text(m.emoji, style: TextStyle(fontSize: t * .4)),
      ),
    );

    if (_brilho == null) return disco;

    // Ouro: faixa de luz diagonal por cima do disco
    return AnimatedBuilder(
      animation: _brilho!,
      child: disco,
      builder: (_, filho) {
        final x = _brilho!.value * 3 - 1; // -1 → 2: entra pela esquerda, sai pela direita e descansa fora
        return ClipOval(
          child: Stack(
            children: [
              filho!,
              Positioned.fill(
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment(x - .4, -1),
                        end: Alignment(x + .4, 1),
                        colors: [Colors.white.withValues(alpha: 0), Colors.white.withValues(alpha: .55), Colors.white.withValues(alpha: 0)],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
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
        // Muitas de uma vez (ex.: medalhas novas numa atualização): só as 3 melhores têm animação
        final novas = obtidas.where((m) => !vistas.contains(m.id)).toList()..sort((a, b) => b.peso.compareTo(a.peso));
        for (final m in novas.take(3)) {
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
          EmblemaMedalha(medalha: m, tamanho: 60),
          const SizedBox(height: 8),
          Text(m.nome,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: m.obtida ? p.tinta : p.suave)),
          const Spacer(),
          if (m.obtida)
            Text(m.nivelTexto, style: TextStyle(fontSize: 11, color: corDoNivel(m.nivel), fontWeight: FontWeight.w800))
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
              EmblemaMedalha(medalha: m, tamanho: 84),
              const SizedBox(height: 10),
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
                      child: CustomPaint(size: const Size(260, 260), painter: _Raios(corDoNivel(m.nivel).withValues(alpha: .4))),
                    ),
                    // A medalha: entra a crescer com mola e dá uma volta
                    AnimatedBuilder(
                      animation: _entrada,
                      builder: (_, __) => Transform.rotate(
                        angle: (1 - volta.value) * math.pi,
                        child: Transform.scale(
                          scale: escala.value,
                          child: EmblemaMedalha(medalha: m, tamanho: 140),
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
                    Text('MEDALHA DE ${m.nivelTexto.toUpperCase()}',
                        style: TextStyle(color: corDoNivel(m.nivel), fontSize: 12, letterSpacing: 2.4, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text(m.nome, style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(m.descricao, style: const TextStyle(color: Colors.white70, fontSize: 14)),
                    // Dia em que foi conseguida (ao tocar numa medalha já ganha)
                    if (m.quandoTexto() != null) ...[
                      const SizedBox(height: 10),
                      Text(m.quandoTexto()!, style: TextStyle(color: corDoNivel(m.nivel), fontSize: 13, fontWeight: FontWeight.w700)),
                    ],
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
