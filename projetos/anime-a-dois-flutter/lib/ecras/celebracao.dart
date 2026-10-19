// Celebração ao acabar uma série: confettis, um cartão vertical (formato stories) com a capa,
// as vossas fotos e os números a contar, e o botão para o partilhar como imagem
// (Instagram, WhatsApp…). Abre-se do ecrã da série quando marcas o último episódio que faltava
// (uma vez por série).

import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import '../widgets/efeitos.dart';

class EcraCelebracao extends StatefulWidget {
  final Serie serie;
  final Utilizador? eu;
  final List<(Utilizador, Progresso)> companheiros; // quem vê a série contigo e onde vai
  final int fillers;

  const EcraCelebracao({super.key, required this.serie, required this.eu, required this.companheiros, this.fillers = 0});

  @override
  State<EcraCelebracao> createState() => _EcraCelebracaoState();
}

class _EcraCelebracaoState extends State<EcraCelebracao> {
  final _cartao = GlobalKey(); // o que vira imagem
  bool _aPartilhar = false;

  // Companheiros que também já acabaram (o cartão diz "Acabámos" quando há pelo menos um)
  List<Utilizador> get _acabaramComigo => [
        for (final (u, pr) in widget.companheiros)
          if (widget.serie.totalEpisodios > 0 && pr.vistos >= widget.serie.totalEpisodios) u,
      ];

  @override
  void initState() {
    super.initState();
    HapticFeedback.heavyImpact();
  }

  // Cartão → PNG → folha de partilha do Android
  Future<void> _partilhar() async {
    setState(() => _aPartilhar = true);
    try {
      final limite = _cartao.currentContext!.findRenderObject() as RenderRepaintBoundary;
      final imagem = await limite.toImage(pixelRatio: 3);
      final dados = await imagem.toByteData(format: ui.ImageByteFormat.png);
      if (dados == null) return;
      final juntos = _acabaramComigo.isNotEmpty;
      await Share.shareXFiles(
        [XFile.fromData(dados.buffer.asUint8List(), mimeType: 'image/png', name: 'anime-a-dois-${widget.serie.slug}.png')],
        text: '${juntos ? 'Acabámos' : 'Acabei'} ${widget.serie.nome} no Anime a Dois 🎉',
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
    final s = widget.serie;
    return Scaffold(
      backgroundColor: Colors.black.withValues(alpha: .88),
      body: Stack(
        children: [
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
              child: Column(
                children: [
                  // Cartão o maior possível, sempre em 9:16
                  Expanded(
                    child: Center(
                      child: AspectRatio(
                        aspectRatio: 9 / 16,
                        child: TweenAnimationBuilder<double>(
                          tween: Tween(begin: .82, end: 1),
                          duration: const Duration(milliseconds: 700),
                          curve: Curves.elasticOut,
                          builder: (_, escala, filho) => Transform.scale(scale: escala, child: filho),
                          child: RepaintBoundary(key: _cartao, child: _Cartao(estado: this)),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(color: Colors.white38),
                            minimumSize: const Size.fromHeight(50),
                          ),
                          onPressed: () => Navigator.of(context).pop(),
                          child: const Text('Fechar'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton.icon(
                          style: FilledButton.styleFrom(
                            backgroundColor: s.cor,
                            foregroundColor: p.noPastel,
                            minimumSize: const Size.fromHeight(50),
                          ),
                          onPressed: _aPartilhar ? null : _partilhar,
                          icon: const Icon(Icons.ios_share_rounded),
                          label: const Text('Partilhar'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          // Confettis por cima de tudo
          Positioned.fill(child: Confettis(cores: [s.cor, p.tu, p.par, const Color(0xFFFFD66B), Colors.white])),
        ],
      ),
    );
  }
}

// O cartão (o que vai na imagem): capa desfocada no fundo, capa nítida ao centro, título, fotos e números
class _Cartao extends StatelessWidget {
  final _EcraCelebracaoState estado;

  const _Cartao({required this.estado});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final w = estado.widget;
    final s = w.serie;
    final juntos = estado._acabaramComigo;
    final minutos = (s.minutosEp ?? 24) * s.totalEpisodios;
    final horas = (minutos / 60).round();
    final hoje = DateTime.now();
    const meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    return LayoutBuilder(
      builder: (_, c) {
        final u = c.maxWidth / 360; // unidade: o cartão é desenhado para 360 de largura e escala
        return ClipRRect(
          borderRadius: BorderRadius.circular(28 * u),
          child: Stack(
            fit: StackFit.expand,
            children: [
              // Fundo: cor da série → escuro
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [s.cor, const Color(0xFF14161E), const Color(0xFF0B0C10)],
                    stops: const [0, .62, 1],
                  ),
                ),
              ),
              // Capa desfocada por cima do fundo
              if (s.capa != null)
                Opacity(
                  opacity: .45,
                  child: ImageFiltered(
                    imageFilter: ui.ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                    child: Image.network(s.capa!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox()),
                  ),
                ),
              // Escurecer em baixo para os números se lerem bem
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Color(0xCC0B0C10)],
                    stops: [.35, 1],
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(24 * u, 28 * u, 24 * u, 22 * u),
                child: Column(
                  children: [
                    Text('🎉  ${juntos.isNotEmpty ? 'ACABÁMOS' : 'ACABEI'}',
                        style: TextStyle(color: Colors.white, fontSize: 14 * u, fontWeight: FontWeight.w800, letterSpacing: 3 * u)),
                    SizedBox(height: 18 * u),
                    // Capa nítida, com sombra
                    DecoratedBox(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(18 * u),
                        boxShadow: [BoxShadow(color: Colors.black54, blurRadius: 30 * u, offset: Offset(0, 14 * u))],
                      ),
                      child: Capa(serie: s, largura: 150 * u, raio: 18 * u),
                    ),
                    SizedBox(height: 18 * u),
                    Text(s.nome,
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: Colors.white, fontSize: 24 * u, fontWeight: FontWeight.w800, height: 1.15)),
                    SizedBox(height: 14 * u),
                    // Fotos de quem acabou
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (final (i, pessoa) in [w.eu, ...juntos].indexed)
                          Transform.translate(
                            offset: Offset(-10.0 * u * i, 0),
                            child: Container(
                              padding: EdgeInsets.all(2.5 * u),
                              decoration: BoxDecoration(color: i == 0 ? p.tu : p.par, shape: BoxShape.circle),
                              child: Avatar(user: pessoa, cor: i == 0 ? p.tu : p.par, tamanho: 40 * u),
                            ),
                          ),
                      ],
                    ),
                    if (juntos.isNotEmpty) ...[
                      SizedBox(height: 6 * u),
                      Text('${w.eu?.nome ?? 'Eu'} e ${juntos.map((j) => j.nome).join(' e ')}',
                          style: TextStyle(color: Colors.white70, fontSize: 12.5 * u)),
                    ],
                    const Spacer(),
                    // Números a contar
                    Row(
                      children: [
                        _numero(s.totalEpisodios, 'episódios', u),
                        _numero(horas, 'horas', u),
                        if (w.fillers > 0) _numero(w.fillers, 'fillers', u),
                      ],
                    ),
                    SizedBox(height: 18 * u),
                    Text('anime a dois · ${hoje.day} ${meses[hoje.month - 1]} ${hoje.year}',
                        style: TextStyle(color: Colors.white54, fontSize: 11.5 * u, letterSpacing: .5 * u)),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _numero(int valor, String legenda, double u) {
    return Expanded(
      child: Column(
        children: [
          NumeroAContar(
            valor: valor,
            duracao: const Duration(milliseconds: 1500),
            estilo: TextStyle(color: Colors.white, fontSize: 30 * u, fontWeight: FontWeight.w800),
          ),
          Text(legenda, style: TextStyle(color: Colors.white60, fontSize: 12 * u)),
        ],
      ),
    );
  }
}
