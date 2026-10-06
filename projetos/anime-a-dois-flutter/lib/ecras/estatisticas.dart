// Estatísticas de uma série: os números dos dois, o ritmo das últimas semanas,
// a previsão de quando acabas, os arcos e as curiosidades.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

class EcraEstatisticas extends StatefulWidget {
  final String slug; // série aberta ao entrar

  const EcraEstatisticas({super.key, required this.slug});

  @override
  State<EcraEstatisticas> createState() => _EcraEstatisticasState();
}

class _EcraEstatisticasState extends State<EcraEstatisticas> {
  late String _slug = widget.slug;
  List<Serie> _series = []; // para os separadores
  Serie? _serie;
  Estatisticas? _est;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregarSeries();
    _carregar();
  }

  // Lista de séries para os separadores (só uma vez)
  Future<void> _carregarSeries() async {
    try {
      final d = await Api.instancia.get('/series');
      if (!mounted) return;
      setState(() => _series = (d['series'] as List).map((s) => Serie.deJson(s as Map<String, dynamic>)).toList());
    } on ApiErro {
      // sem separadores, o ecrã continua a mostrar a série atual
    }
  }

  Future<void> _carregar() async {
    setState(() {
      _est = null;
      _erro = null;
    });
    try {
      final d = await Api.instancia.get('/series/$_slug/estatisticas');
      if (!mounted) return;
      setState(() {
        _serie = Serie.deJson(d['serie'] as Map<String, dynamic>);
        _est = Estatisticas.deJson(d['estatisticas'] as Map<String, dynamic>);
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return Scaffold(
      body: Fundo(
        acento: _serie?.cor,
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 6, 8, 0),
                child: Row(
                  children: [
                    const BackButton(),
                    Text('Estatísticas',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
              ),

              // Separadores por série
              if (_series.isNotEmpty)
                SizedBox(
                  height: 48,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    children: [
                      for (final s in _series)
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text(s.nomeCurto),
                            selected: s.slug == _slug,
                            selectedColor: s.cor.withValues(alpha: 0.6),
                            shape: const StadiumBorder(),
                            onSelected: (_) {
                              if (s.slug == _slug) return;
                              setState(() => _slug = s.slug);
                              _carregar();
                            },
                          ),
                        ),
                    ],
                  ),
                ),

              Expanded(
                child: _erro != null
                    ? ErroComRetry(mensagem: _erro!, aoTentar: _carregar)
                    : _est == null
                        ? const Carregando()
                        : _corpo(p, _serie!, _est!),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _corpo(Paleta p, Serie serie, Estatisticas est) {
    final textos = Theme.of(context).textTheme;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
      children: [
        // ---------- Tu vs par ----------
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final pessoa in est.pessoas) ...[
              Expanded(child: _cartaoPessoa(p, pessoa)),
              if (pessoa != est.pessoas.last) const SizedBox(width: 12),
            ],
          ],
        ),
        const SizedBox(height: 14),

        // ---------- Ritmo ----------
        Vidro(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const TituloSecao('Ritmo · episódios por semana'),
              SizedBox(height: 130, child: _grafico(p, est.semanas)),
              if (est.previsao != null) ...[
                const SizedBox(height: 12),
                Text(est.previsao!, style: textos.bodySmall?.copyWith(color: p.suave)),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),

        // ---------- Arcos (só Naruto e Shippuden têm dados) ----------
        if (est.arcos.isNotEmpty) ...[
          Vidro(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const TituloSecao('Arcos'),
                for (final a in est.arcos) _linhaArco(p, a),
              ],
            ),
          ),
          const SizedBox(height: 14),
        ],

        // ---------- Curiosidades ----------
        Vidro(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const TituloSecao('Curiosidades'),
              Text('💬  ${est.comentarios} ${est.comentarios == 1 ? 'comentário trocado' : 'comentários trocados'}'),
              if (est.recordeN != null) ...[
                const SizedBox(height: 6),
                Text('🔥  Recorde: ${est.recordeN} episódios num dia'
                    '${est.recordeQuem == 'tu' ? ' (tu)' : ' (${est.recordeQuem})'}'),
              ],
            ],
          ),
        ),
      ],
    );
  }

  // Cartão de uma pessoa: avatar, episódios, horas e fillers
  Widget _cartaoPessoa(Paleta p, PessoaEst pessoa) {
    final textos = Theme.of(context).textTheme;
    final corTxt = pessoa.ehTu ? p.tuTxt : p.parTxt;
    return Vidro(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Avatar(user: pessoa.user, cor: pessoa.ehTu ? p.tu : p.par, tamanho: 28),
              const SizedBox(width: 8),
              Expanded(
                child: Text(pessoa.user.nome,
                    overflow: TextOverflow.ellipsis, style: textos.labelLarge?.copyWith(fontWeight: FontWeight.w800)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text('${pessoa.vistos}',
              style: textos.headlineMedium?.copyWith(fontWeight: FontWeight.w800, color: corTxt, height: 1)),
          Text('episódios', style: textos.labelSmall?.copyWith(color: p.suave)),
          const SizedBox(height: 8),
          Text(pessoa.horas, style: textos.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
          Text(pessoa.fillers, style: textos.bodySmall?.copyWith(color: p.suave)),
        ],
      ),
    );
  }

  // Gráfico de barras: duas barras por semana (tu e o par), altura pelo "nível" 0–10 da API
  Widget _grafico(Paleta p, List<Semana> semanas) {
    final textos = Theme.of(context).textTheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        for (final s in semanas)
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _barraVertical(s.nivelTu, s.tu, p.tu),
                    const SizedBox(width: 3),
                    _barraVertical(s.nivelPar, s.par, p.par),
                  ],
                ),
                const SizedBox(height: 6),
                Text(s.rotulo, style: textos.labelSmall?.copyWith(color: p.suave)),
              ],
            ),
          ),
      ],
    );
  }

  Widget _barraVertical(int nivel, int valor, Color cor) {
    return Tooltip(
      message: '$valor',
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeOutCubic,
        width: 12,
        height: 4 + nivel * 8.0, // mínimo de 4px para se ver a barra vazia
        decoration: BoxDecoration(color: cor, borderRadius: BorderRadius.circular(6)),
      ),
    );
  }

  // Um arco: nome, intervalo, selo e as duas barras finas
  Widget _linhaArco(Paleta p, Arco a) {
    final textos = Theme.of(context).textTheme;

    // Selo: ✓ os dois acabaram; ½ só um (na cor de quem acabou)
    Widget? selo;
    if (a.selo == 'os-dois') {
      selo = Icon(Icons.check_circle_rounded, size: 18, color: p.tuTxt);
    } else if (a.selo == 'tu' || a.selo == 'par') {
      selo = Text('½',
          style: textos.labelLarge?.copyWith(fontWeight: FontWeight.w800, color: a.selo == 'tu' ? p.tuTxt : p.parTxt));
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  a.nome,
                  overflow: TextOverflow.ellipsis,
                  style: textos.bodySmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: a.filler ? p.suave : null, // arcos de filler mais apagados
                  ),
                ),
              ),
              Text('${a.de}–${a.ate}', style: textos.labelSmall?.copyWith(color: p.suave)),
              if (selo != null) ...[const SizedBox(width: 8), selo],
            ],
          ),
          const SizedBox(height: 5),
          BarraProgresso(pct: a.pctTu, cor: p.tu, altura: 6),
          const SizedBox(height: 3),
          BarraProgresso(pct: a.pctPar, cor: p.par, altura: 6),
        ],
      ),
    );
  }
}
