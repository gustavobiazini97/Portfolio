// Peças visuais usadas em vários ecrãs: fundo com manchas, painel de vidro, avatar,
// barra de progresso, linha "pessoa + barra", carregamento/erro e avisos.

import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';

// ---------- Fundo ----------

// Fundo da app: cor lisa com duas manchas difusas (sálvia e alperce) e, opcional, o acento da série
class Fundo extends StatelessWidget {
  final Widget child;
  final Color? acento;

  const Fundo({super.key, required this.child, this.acento});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final escuro = Theme.of(context).brightness == Brightness.dark;
    return ColoredBox(
      color: p.bg,
      child: Stack(
        children: [
          _Mancha(cor: p.orbeTu, tamanho: 340, topo: -120, esquerda: -110),
          _Mancha(cor: p.orbePar, tamanho: 300, baixo: -90, direita: -100),
          if (acento != null)
            _Mancha(cor: acento!.withValues(alpha: escuro ? 0.22 : 0.45), tamanho: 260, topo: -80, direita: -60),
          Positioned.fill(child: child),
        ],
      ),
    );
  }
}

// Uma mancha: círculo com degradê radial que se desvanece até transparente
class _Mancha extends StatelessWidget {
  final Color cor;
  final double tamanho;
  final double? topo, baixo, esquerda, direita;

  const _Mancha({required this.cor, required this.tamanho, this.topo, this.baixo, this.esquerda, this.direita});

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: topo,
      bottom: baixo,
      left: esquerda,
      right: direita,
      child: IgnorePointer(
        child: Container(
          width: tamanho,
          height: tamanho,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(colors: [cor, cor.withValues(alpha: 0)]),
          ),
        ),
      ),
    );
  }
}

// ---------- Vidro ----------

// Painel de vidro fosco (glassmorphism): desfoca o que está por trás e tem uma borda clara
class Vidro extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final double raio;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  final Gradient? tinta; // cor extra por cima do vidro (ex.: episódio visto)

  const Vidro({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.raio = 28,
    this.onTap,
    this.onLongPress,
    this.tinta,
  });

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final cantos = BorderRadius.circular(raio);
    return ClipRRect(
      borderRadius: cantos,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
        // Material > Ink (cor/borda) > InkWell: o efeito do toque fica por cima da cor
        child: Material(
          color: Colors.transparent,
          child: Ink(
            decoration: BoxDecoration(
              color: tinta == null ? p.vidro : null,
              gradient: tinta,
              borderRadius: cantos,
              border: Border.all(color: p.vidroBorda),
            ),
            child: InkWell(
              onTap: onTap,
              onLongPress: onLongPress,
              borderRadius: cantos,
              child: Padding(padding: padding, child: child),
            ),
          ),
        ),
      ),
    );
  }
}

// ---------- Avatar ----------

// Foto de perfil (pedida com o token) ou a inicial sobre a cor da pessoa
class Avatar extends StatelessWidget {
  final Utilizador? user;
  final double tamanho;
  final Color cor; // sálvia para ti, alperce para o par

  const Avatar({super.key, required this.user, required this.cor, this.tamanho = 36});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);

    // Sem foto (ou se a foto falhar): círculo com a inicial
    final inicial = Container(
      width: tamanho,
      height: tamanho,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: cor, shape: BoxShape.circle),
      child: Text(
        user?.inicial ?? '?',
        style: TextStyle(color: p.noPastel, fontWeight: FontWeight.w800, fontSize: tamanho * 0.42),
      ),
    );

    final foto = user?.foto;
    if (foto == null) return inicial;

    return ClipOval(
      child: Image.network(
        foto,
        width: tamanho,
        height: tamanho,
        fit: BoxFit.cover,
        headers: Api.instancia.cabecalhos, // a foto só é servida a quem tem sessão
        errorBuilder: (_, __, ___) => inicial,
      ),
    );
  }
}

// ---------- Barras ----------

// Barra de progresso arredondada, com animação quando o valor muda
class BarraProgresso extends StatelessWidget {
  final int pct; // 0–100
  final Color cor;
  final double altura;

  const BarraProgresso({super.key, required this.pct, required this.cor, this.altura = 10});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return ClipRRect(
      borderRadius: BorderRadius.circular(altura),
      child: Container(
        height: altura,
        color: p.trilho,
        alignment: Alignment.centerLeft,
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: (pct.clamp(0, 100)) / 100),
          duration: const Duration(milliseconds: 600),
          curve: Curves.easeOutCubic,
          builder: (_, valor, __) => FractionallySizedBox(
            widthFactor: valor,
            child: Container(
              decoration: BoxDecoration(color: cor, borderRadius: BorderRadius.circular(altura)),
            ),
          ),
        ),
      ),
    );
  }
}

// Linha de uma pessoa: nome e números em cima, barra por baixo.
// A barra ocupa sempre a largura toda, seja qual for o tamanho do nome (como no site).
class LinhaPessoa extends StatelessWidget {
  final String nome;
  final Progresso progresso;
  final int total;
  final bool ehTu;

  const LinhaPessoa({super.key, required this.nome, required this.progresso, required this.total, required this.ehTu});

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final corTxt = ehTu ? p.tuTxt : p.parTxt;
    final estilo = Theme.of(context).textTheme.bodySmall;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(nome, overflow: TextOverflow.ellipsis, style: estilo?.copyWith(fontWeight: FontWeight.w700)),
            ),
            Text(
              progresso.posicao > 0 ? 'ep ${progresso.posicao} · ${progresso.pct}%' : '${progresso.pct}%',
              style: estilo?.copyWith(color: corTxt, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 6),
        BarraProgresso(pct: progresso.pct, cor: ehTu ? p.tu : p.par),
      ],
    );
  }
}

// ---------- Estados de carregamento ----------

class Carregando extends StatelessWidget {
  const Carregando({super.key});

  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator(strokeWidth: 2.5));
}

// Mensagem de erro com botão para tentar outra vez
class ErroComRetry extends StatelessWidget {
  final String mensagem;
  final VoidCallback aoTentar;

  const ErroComRetry({super.key, required this.mensagem, required this.aoTentar});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off_rounded, size: 40, color: Paleta.de(context).suave),
            const SizedBox(height: 12),
            Text(mensagem, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: aoTentar, child: const Text('Tentar outra vez')),
          ],
        ),
      ),
    );
  }
}

// ---------- Avisos ----------

// Mensagem curta em baixo (sucesso ou erro)
void aviso(BuildContext context, String mensagem, {bool erro = false}) {
  final p = Paleta.de(context);
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(mensagem),
      backgroundColor: erro ? p.erro : null,
      duration: const Duration(seconds: 3),
    ));
}

// Título pequeno de secção (ex.: "Ritmo")
class TituloSecao extends StatelessWidget {
  final String texto;

  const TituloSecao(this.texto, {super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        texto,
        style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
      ),
    );
  }
}
