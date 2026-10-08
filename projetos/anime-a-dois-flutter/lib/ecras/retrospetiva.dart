// Retrospetiva mensal e anual ("Wrapped"): ecrãs tipo stories que avançam sozinhos
// (toque à direita = seguinte, à esquerda = anterior, manter = pausa) e um cartão final
// que se partilha como imagem (WhatsApp, Instagram…).
// Os números vêm do GET /resumos/{ano}[/{mes}] (Model Resumo no PHP).

import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

const _meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// "outubro"
String nomeMes(int mes) => _meses[(mes - 1).clamp(0, 11)];

// ---------- Lista: escolher que retrospetiva ver ----------

class EcraRetrospetivas extends StatefulWidget {
  const EcraRetrospetivas({super.key});

  @override
  State<EcraRetrospetivas> createState() => _EcraRetrospetivasState();
}

class _EcraRetrospetivasState extends State<EcraRetrospetivas> {
  List<Map<String, dynamic>> _meses = [];
  List<Map<String, dynamic>> _anos = [];
  bool _aCarregar = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/resumos');
      if (!mounted) return;
      setState(() {
        _meses = ((d['meses'] as List?) ?? []).map((m) => m as Map<String, dynamic>).toList();
        _anos = ((d['anos'] as List?) ?? []).map((a) => a as Map<String, dynamic>).toList();
        _aCarregar = false;
        _erro = null;
      });
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  void _abrir(int ano, [int? mes]) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraRetrospetiva(ano: ano, mes: mes)));

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
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
                    Text('Retrospetivas', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              Expanded(
                child: _aCarregar
                    ? const Carregando()
                    : _erro != null
                        ? ErroComRetry(mensagem: _erro!, aoTentar: _carregar)
                        : (_meses.isEmpty
                            ? Center(child: Text('Marca episódios e a primeira retrospetiva aparece aqui.', style: TextStyle(color: p.suave)))
                            : ListView(
                                padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                                children: [
                                  // Anos: cartões grandes
                                  for (final a in _anos) ...[
                                    Vidro(
                                      onTap: () => _abrir((a['ano'] as num).toInt()),
                                      tinta: LinearGradient(colors: [p.tu.withValues(alpha: .55), p.par.withValues(alpha: .55)]),
                                      child: Row(
                                        children: [
                                          const Text('🎞️', style: TextStyle(fontSize: 30)),
                                          const SizedBox(width: 14),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text('O teu ${a['ano']}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                                                Text('${a['episodios']} episódios', style: TextStyle(color: p.tinta.withValues(alpha: .7))),
                                              ],
                                            ),
                                          ),
                                          Icon(Icons.play_circle_fill_rounded, size: 32, color: p.tinta),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 12),
                                  ],
                                  const SizedBox(height: 6),
                                  const Padding(
                                    padding: EdgeInsets.only(left: 4, bottom: 8),
                                    child: Text('Meses', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
                                  ),
                                  // Meses: lista
                                  for (final m in _meses)
                                    Padding(
                                      padding: const EdgeInsets.only(bottom: 8),
                                      child: Vidro(
                                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                                        onTap: () => _abrir((m['ano'] as num).toInt(), (m['mes'] as num).toInt()),
                                        child: Row(
                                          children: [
                                            const Text('📼', style: TextStyle(fontSize: 22)),
                                            const SizedBox(width: 12),
                                            Expanded(child: Text(m['nome'] as String, style: const TextStyle(fontWeight: FontWeight.w500))),
                                            Text('${m['episodios']} ep.', style: TextStyle(color: p.suave)),
                                            Icon(Icons.chevron_right_rounded, color: p.suave),
                                          ],
                                        ),
                                      ),
                                    ),
                                ],
                              )),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------- Stories ----------

class EcraRetrospetiva extends StatefulWidget {
  final int ano;
  final int? mes; // null = o ano inteiro

  const EcraRetrospetiva({super.key, required this.ano, this.mes});

  @override
  State<EcraRetrospetiva> createState() => _EcraRetrospetivaState();
}

class _EcraRetrospetivaState extends State<EcraRetrospetiva> with SingleTickerProviderStateMixin {
  Map<String, dynamic>? _r; // o resumo
  String? _erro;

  int _pagina = 0;
  late final AnimationController _tempo = AnimationController(vsync: this, duration: const Duration(seconds: 6))
    ..addStatusListener((s) {
      if (s == AnimationStatus.completed) _seguinte();
    });

  final _cartaoFinal = GlobalKey(); // para transformar o cartão final em imagem
  bool _aPartilhar = false;

  bool get _anual => widget.mes == null;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  @override
  void dispose() {
    _tempo.dispose();
    super.dispose();
  }

  Future<void> _carregar() async {
    try {
      final rota = _anual ? '/resumos/${widget.ano}' : '/resumos/${widget.ano}/${widget.mes}';
      final d = await Api.instancia.get(rota);
      if (!mounted) return;
      setState(() => _r = d['resumo'] as Map<String, dynamic>);
      _tempo.forward(from: 0);
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  // ---------- Navegação entre slides ----------

  List<Widget> _slides = [];

  void _seguinte() {
    if (_pagina < _slides.length - 1) {
      HapticFeedback.selectionClick();
      setState(() => _pagina++);
      // O último (cartão final) não avança sozinho
      if (_pagina < _slides.length - 1) _tempo.forward(from: 0);
    }
  }

  void _anterior() {
    if (_pagina > 0) {
      HapticFeedback.selectionClick();
      setState(() => _pagina--);
      _tempo.forward(from: 0);
    }
  }

  // Cartão final → PNG → partilhar
  Future<void> _partilhar() async {
    setState(() => _aPartilhar = true);
    try {
      final limite = _cartaoFinal.currentContext!.findRenderObject() as RenderRepaintBoundary;
      final imagem = await limite.toImage(pixelRatio: 3);
      final dados = await imagem.toByteData(format: ui.ImageByteFormat.png);
      if (dados == null) return;
      await Share.shareXFiles(
        [XFile.fromData(dados.buffer.asUint8List(), mimeType: 'image/png', name: 'anime-a-dois-retrospetiva.png')],
        text: 'A minha retrospetiva ${_r!['periodo']['nome']} no Anime a Dois 📼',
      );
    } catch (_) {
      if (mounted) aviso(context, 'Não foi possível criar a imagem.', erro: true);
    } finally {
      if (mounted) setState(() => _aPartilhar = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final r = _r;

    if (r == null) {
      return Scaffold(
        backgroundColor: const Color(0xFF0E1116),
        body: Center(
          child: _erro == null
              ? const CircularProgressIndicator()
              : ErroComRetry(mensagem: _erro!, aoTentar: () {
                  setState(() => _erro = null);
                  _carregar();
                }),
        ),
      );
    }

    _slides = _construirSlides(p, r);
    final pagina = _pagina.clamp(0, _slides.length - 1).toInt();

    return Scaffold(
      backgroundColor: const Color(0xFF0E1116),
      body: GestureDetector(
        // Toque: esquerda = anterior, direita = seguinte; manter carregado = pausa
        onTapUp: (d) => d.globalPosition.dx < MediaQuery.sizeOf(context).width * .3 ? _anterior() : _seguinte(),
        onLongPressStart: (_) => _tempo.stop(),
        onLongPressEnd: (_) {
          if (pagina < _slides.length - 1) _tempo.forward();
        },
        child: Stack(
          children: [
            // Fundo: degradê das tuas cores, que muda de slide para slide
            AnimatedContainer(
              duration: const Duration(milliseconds: 600),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: pagina.isEven
                      ? [const Color(0xFF1B1F2A), p.tu.withValues(alpha: .55)]
                      : [p.par.withValues(alpha: .55), const Color(0xFF1B1F2A)],
                ),
              ),
            ),
            SafeArea(
              child: Column(
                children: [
                  // Barras de progresso dos slides (como nas stories)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                    child: Row(
                      children: [
                        for (var i = 0; i < _slides.length; i++)
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 2),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(2),
                                child: AnimatedBuilder(
                                  animation: _tempo,
                                  builder: (_, __) => LinearProgressIndicator(
                                    minHeight: 3,
                                    value: i < pagina ? 1 : (i == pagina ? (i == _slides.length - 1 ? 1 : _tempo.value) : 0),
                                    backgroundColor: Colors.white24,
                                    valueColor: const AlwaysStoppedAnimation(Colors.white),
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                  // Título pequeno e fechar
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 4, 0),
                    child: Row(
                      children: [
                        Image.asset('assets/icon/icon.png', width: 22, height: 22),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text('Retrospetiva · ${r['periodo']['nome']}',
                              style: const TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500)),
                        ),
                        IconButton(
                          onPressed: () => Navigator.of(context).pop(),
                          icon: const Icon(Icons.close_rounded, color: Colors.white),
                        ),
                      ],
                    ),
                  ),
                  // O slide, com uma entrada suave
                  Expanded(
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 450),
                      transitionBuilder: (filho, anim) => FadeTransition(
                        opacity: anim,
                        child: SlideTransition(
                          position: Tween(begin: const Offset(0, .06), end: Offset.zero).animate(anim),
                          child: filho,
                        ),
                      ),
                      child: KeyedSubtree(key: ValueKey(pagina), child: _slides[pagina]),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---------- Os slides ----------

  List<Widget> _construirSlides(Paleta p, Map<String, dynamic> r) {
    final periodo = r['periodo'] as Map<String, dynamic>;
    final total = (r['episodios'] as num).toInt();
    final quando = _anual ? 'este ano' : 'este mês';
    final doPeriodo = _anual ? 'do ano' : 'do mês';

    if (total == 0) {
      return [
        _Slide(
          emoji: '🫥',
          pequeno: periodo['nome'] as String,
          grande: 'Nada por aqui',
          texto: 'Ainda não marcaste episódios $quando. Bora a isso!',
        ),
      ];
    }

    final top = ((r['top'] as List?) ?? []).map((t) => t as Map<String, dynamic>).toList();
    final anterior = r['anterior'] as Map<String, dynamic>;
    final variacao = anterior['variacao'] as num?;
    final melhorDia = r['melhorDia'] as Map<String, dynamic>?;
    final par = r['par'] as Map<String, dynamic>?;
    final acabadas = ((r['acabadas'] as List?) ?? []).map((s) => Serie.deJson(s as Map<String, dynamic>)).toList();
    final sequencia = (r['sequencia'] as num).toInt();
    final momento = r['momento'] as String?;

    final slides = <Widget>[
      // 1. Abertura
      _Slide(
        emoji: '📼',
        pequeno: 'A tua retrospetiva',
        grande: _anual ? '${periodo['ano']}' : nomeMes((periodo['mes'] as num).toInt()),
        texto: _anual ? 'Um ano inteiro de anime. Vamos a isto?' : 'O que viste em ${periodo['nome']}.',
      ),

      // 2. Episódios e horas (e comparação com o período anterior)
      _Slide(
        emoji: '🎬',
        pequeno: 'Viste',
        grande: '$total',
        subGrande: total == 1 ? 'episódio' : 'episódios',
        texto: '${r['horas']} de anime'
            '${variacao == null ? '' : ' · ${variacao >= 0 ? '+' : ''}$variacao% do que ${_anual ? 'no ano anterior' : 'no mês anterior'}'}',
      ),

      // 3. Série do período (e as seguintes)
      if (top.isNotEmpty)
        _SlideSerie(
          titulo: 'A tua série $doPeriodo',
          serie: Serie.deJson(top.first['serie'] as Map<String, dynamic>),
          episodios: (top.first['episodios'] as num).toInt(),
          outras: [
            for (final t in top.skip(1)) '${Serie.deJson(t['serie'] as Map<String, dynamic>).nomeCurto} · ${t['episodios']} ep.',
          ],
        ),

      // 4. (Ano) episódios por mês
      if (_anual && r['porMes'] != null) _SlideMeses(meses: (r['porMes'] as List).map((m) => m as Map<String, dynamic>).toList(), melhor: r['melhorMes'] as Map<String, dynamic>?),

      // 5. Melhor dia
      if (melhorDia != null)
        _Slide(
          emoji: (melhorDia['episodios'] as num) >= 10 ? '🍿' : '📆',
          pequeno: 'O teu dia de maratona',
          grande: '${melhorDia['episodios']}',
          subGrande: 'episódios num dia',
          texto: _maiuscula(melhorDia['texto'] as String),
        ),

      // 6. Hora preferida
      if (momento != null)
        _Slide(
          emoji: switch (momento) { 'madrugada' => '🦉', 'manhã' => '☀️', 'tarde' => '🌤️', _ => '🌙' },
          pequeno: 'Gostas de ver anime',
          grande: momento == 'madrugada' ? 'de madrugada' : 'à $momento',
          texto: 'A tua hora: ${r['hora']}h${r['diaSemana'] != null ? ' · o teu dia: ${r['diaSemana']}' : ''}',
        ),

      // 7. Constância
      _Slide(
        emoji: sequencia >= 7 ? '🔥' : '📅',
        pequeno: 'A tua maior sequência',
        grande: '$sequencia',
        subGrande: sequencia == 1 ? 'dia seguido' : 'dias seguidos',
        texto: 'Viste anime em ${r['diasAtivos']} ${(r['diasAtivos'] as num) == 1 ? 'dia' : 'dias'} $quando.',
      ),

      // 8. Tu vs o par
      if (par != null) _SlideVs(tu: total, par: (par['episodios'] as num).toInt(), nomePar: (par['user'] as Map<String, dynamic>)['nome'] as String),

      // 9. Extras: séries acabadas, fillers, comentários
      _Slide(
        emoji: acabadas.isNotEmpty ? '🏁' : '✨',
        pequeno: 'E ainda…',
        grande: acabadas.isNotEmpty ? '${acabadas.length} ${acabadas.length == 1 ? 'série acabada' : 'séries acabadas'}' : 'mais umas coisas',
        texto: [
          if (acabadas.isNotEmpty) acabadas.map((s) => s.nomeCurto).join(', '),
          '${r['fillers']} fillers vistos',
          '${r['comentarios']} comentários escritos',
        ].join(' · '),
      ),
    ];

    // 10. Cartão final (partilhável)
    slides.add(_cartaoFinalSlide(p, r, top, par));
    return slides;
  }

  String _maiuscula(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);

  // O cartão que vira imagem, com o botão de partilhar por baixo
  Widget _cartaoFinalSlide(Paleta p, Map<String, dynamic> r, List<Map<String, dynamic>> top, Map<String, dynamic>? par) {
    final periodo = r['periodo'] as Map<String, dynamic>;
    final linha = (String a, String b) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(
            children: [
              Expanded(child: Text(a, style: TextStyle(color: p.noPastel.withValues(alpha: .7), fontSize: 14))),
              Text(b, style: TextStyle(color: p.noPastel, fontSize: 16, fontWeight: FontWeight.w800)),
            ],
          ),
        );

    return Padding(
      padding: const EdgeInsets.fromLTRB(28, 12, 28, 24),
      child: Column(
        children: [
          Expanded(
            child: Center(
              child: RepaintBoundary(
                key: _cartaoFinal,
                child: Container(
                  width: 320,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(32),
                    gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [p.tu, p.par]),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Image.asset('assets/icon/icon.png', width: 28, height: 28),
                          const SizedBox(width: 8),
                          Text('anime a dois', style: TextStyle(color: p.noPastel, fontWeight: FontWeight.w800)),
                        ],
                      ),
                      const SizedBox(height: 18),
                      Text(_anual ? 'O meu ${periodo['ano']}' : 'O meu ${periodo['nome']}',
                          style: TextStyle(color: p.noPastel, fontSize: 24, fontWeight: FontWeight.w800, height: 1.1)),
                      const SizedBox(height: 14),
                      linha('Episódios', '${r['episodios']}'),
                      linha('Tempo', r['horas'] as String),
                      if (top.isNotEmpty) linha('Série favorita', Serie.deJson(top.first['serie'] as Map<String, dynamic>).nomeCurto),
                      linha('Maior sequência', '${r['sequencia']} dias'),
                      if (r['melhorDia'] != null) linha('Recorde num dia', '${r['melhorDia']['episodios']} ep.'),
                      if (par != null) linha('Vs ${(par['user'] as Map<String, dynamic>)['nome']}', '${r['episodios']} × ${par['episodios']}'),
                    ],
                  ),
                ),
              ),
            ),
          ),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: const Color(0xFF1A1F2B)),
              onPressed: _aPartilhar ? null : _partilhar,
              icon: _aPartilhar
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.ios_share_rounded),
              label: const Text('Partilhar imagem'),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------- Tipos de slide ----------

// Slide de texto: emoji, frase pequena, número/palavra gigante e uma linha por baixo
class _Slide extends StatelessWidget {
  final String emoji, pequeno, grande, texto;
  final String? subGrande;

  const _Slide({required this.emoji, required this.pequeno, required this.grande, required this.texto, this.subGrande});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TweenAnimationBuilder<double>(
            tween: Tween(begin: .3, end: 1),
            duration: const Duration(milliseconds: 700),
            curve: Curves.elasticOut,
            builder: (_, v, filho) => Transform.scale(scale: v, alignment: Alignment.centerLeft, child: filho),
            child: Text(emoji, style: const TextStyle(fontSize: 64)),
          ),
          const SizedBox(height: 18),
          Text(pequeno, style: const TextStyle(color: Colors.white70, fontSize: 20, fontWeight: FontWeight.w500)),
          const SizedBox(height: 4),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(grande, style: const TextStyle(color: Colors.white, fontSize: 88, fontWeight: FontWeight.w800, height: 1, letterSpacing: -2)),
          ),
          if (subGrande != null) Text(subGrande!, style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700)),
          const SizedBox(height: 18),
          Text(texto, style: const TextStyle(color: Colors.white70, fontSize: 16, height: 1.4)),
        ],
      ),
    );
  }
}

// A série mais vista: capa grande, nome, episódios e as seguintes
class _SlideSerie extends StatelessWidget {
  final String titulo;
  final Serie serie;
  final int episodios;
  final List<String> outras;

  const _SlideSerie({required this.titulo, required this.serie, required this.episodios, required this.outras});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(titulo, style: const TextStyle(color: Colors.white70, fontSize: 20, fontWeight: FontWeight.w500)),
          const SizedBox(height: 18),
          TweenAnimationBuilder<double>(
            tween: Tween(begin: .85, end: 1),
            duration: const Duration(milliseconds: 600),
            curve: Curves.easeOutBack,
            builder: (_, v, filho) => Transform.scale(scale: v, child: filho),
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(24),
                boxShadow: [BoxShadow(color: serie.cor.withValues(alpha: .6), blurRadius: 40)],
              ),
              child: Capa(serie: serie, largura: 180, raio: 24),
            ),
          ),
          const SizedBox(height: 18),
          Text(serie.nome, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
          Text('$episodios episódios', style: const TextStyle(color: Colors.white70, fontSize: 16)),
          if (outras.isNotEmpty) ...[
            const SizedBox(height: 16),
            for (final o in outras) Text(o, style: const TextStyle(color: Colors.white54, fontSize: 14)),
          ],
        ],
      ),
    );
  }
}

// Ano: barras por mês, a do mês mais forte em destaque
class _SlideMeses extends StatelessWidget {
  final List<Map<String, dynamic>> meses;
  final Map<String, dynamic>? melhor;

  const _SlideMeses({required this.meses, this.melhor});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final maximo = meses.fold<int>(1, (m, x) => (x['episodios'] as num).toInt() > m ? (x['episodios'] as num).toInt() : m);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Mês a mês', style: TextStyle(color: Colors.white70, fontSize: 20, fontWeight: FontWeight.w500)),
          if (melhor != null)
            Text('O teu mês: ${melhor!['nome']}', style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w800)),
          const SizedBox(height: 28),
          SizedBox(
            height: 200,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (final m in meses)
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        if ((m['episodios'] as num) > 0)
                          Text('${m['episodios']}', style: const TextStyle(color: Colors.white70, fontSize: 9)),
                        TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: (m['episodios'] as num) / maximo),
                          duration: const Duration(milliseconds: 900),
                          curve: Curves.easeOutCubic,
                          builder: (_, v, __) => Container(
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            height: 4 + v * 160,
                            decoration: BoxDecoration(
                              color: melhor != null && m['mes'] == melhor!['mes'] ? p.par : Colors.white38,
                              borderRadius: BorderRadius.circular(6),
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text((m['nome'] as String).substring(0, 1).toUpperCase(),
                            style: const TextStyle(color: Colors.white60, fontSize: 11)),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// Tu vs o par: duas barras que crescem e a frase de quem ganhou
class _SlideVs extends StatelessWidget {
  final int tu, par;
  final String nomePar;

  const _SlideVs({required this.tu, required this.par, required this.nomePar});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final maximo = tu > par ? tu : (par > 0 ? par : 1);
    final dif = (tu - par).abs();
    final frase = tu == par
        ? 'Empate técnico. Sincronizados! 🎯'
        : tu > par
            ? 'Ganhaste por $dif ${dif == 1 ? 'episódio' : 'episódios'} 🏆'
            : '$nomePar ganhou-te por $dif ${dif == 1 ? 'episódio' : 'episódios'} 👀';

    Widget barra(String nome, int n, Color cor) => Padding(
          padding: const EdgeInsets.only(bottom: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(nome, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600))),
                  Text('$n', style: TextStyle(color: cor, fontSize: 28, fontWeight: FontWeight.w800)),
                ],
              ),
              const SizedBox(height: 8),
              TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: n / maximo),
                duration: const Duration(milliseconds: 1100),
                curve: Curves.easeOutCubic,
                builder: (_, v, __) => ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(value: v, minHeight: 16, backgroundColor: Colors.white12, valueColor: AlwaysStoppedAnimation(cor)),
                ),
              ),
            ],
          ),
        );

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('⚔️', style: TextStyle(fontSize: 56)),
          const SizedBox(height: 12),
          const Text('Tu vs', style: TextStyle(color: Colors.white70, fontSize: 20)),
          Text(nomePar, style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w800)),
          const SizedBox(height: 28),
          barra('Tu', tu, p.tu),
          barra(nomePar, par, p.par),
          const SizedBox(height: 10),
          Text(frase, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
