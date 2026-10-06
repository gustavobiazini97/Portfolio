// Página de uma série: o mapa dos dois, a pista de cartões que desliza na horizontal
// e o marcar/desmarcar (um episódio com um toque, vários com toque longo → seleção).

import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'comentarios.dart';
import 'estatisticas.dart';

class EcraSerie extends StatefulWidget {
  final String slug;
  final int? episodio; // número a abrir ao centro (ex.: o último que o par viu)

  const EcraSerie({super.key, required this.slug, this.episodio});

  @override
  State<EcraSerie> createState() => _EcraSerieState();
}

class _EcraSerieState extends State<EcraSerie> {
  // Dados do GET /series/{slug}
  Serie? _serie;
  Utilizador? _user;
  Utilizador? _par;
  List<Episodio> _eps = [];
  Progresso _meu = const Progresso(); // o teu progresso (atualizado a cada marcação)

  PageController? _pista;
  int _atual = 0; // índice do cartão ao centro
  final Set<int> _selecionados = {}; // IDs escolhidos no modo de seleção
  bool _aGuardar = false;
  String? _erro;

  bool get _aSelecionar => _selecionados.isNotEmpty;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  @override
  void dispose() {
    _pista?.dispose();
    super.dispose();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/series/${widget.slug}');
      if (!mounted) return;
      final serie = Serie.deJson(d['serie'] as Map<String, dynamic>);
      final eps = (d['episodios'] as List).map((e) => Episodio.deJson(e as Map<String, dynamic>)).toList();

      // Cartão inicial: o pedido; senão o seguinte ao último que viste (como no site)
      final numero = widget.episodio ?? serie.tu.posicao + 1;
      final indice = (numero - 1).clamp(0, math.max(0, eps.length - 1)).toInt();

      setState(() {
        _serie = serie;
        _meu = serie.tu;
        _user = Utilizador.deJson(d['user'] as Map<String, dynamic>);
        _par = Utilizador.talvez(d['parceiro']);
        _eps = eps;
        _atual = indice;
        _pista?.dispose();
        // viewportFraction < 1: vê-se um pedaço dos cartões vizinhos
        _pista = PageController(viewportFraction: 0.62, initialPage: indice);
        _erro = null;
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  // ---------- Marcar ----------

  // Marca/desmarca uma lista de episódios. Muda logo no ecrã e desfaz se a API falhar.
  Future<void> _marcar(List<Episodio> eps, {required bool visto, bool avancar = false}) async {
    if (eps.isEmpty || _aGuardar) return;
    final antes = {for (final e in eps) e.id: e.tu}; // para desfazer
    setState(() {
      _aGuardar = true;
      for (final e in eps) {
        e.tu = visto;
      }
      _selecionados.clear();
    });

    try {
      final d = await Api.instancia.post('/series/${widget.slug}/vistos', {
        'episodios': eps.map((e) => e.id).toList(),
        'acao': visto ? 'marcar' : 'desmarcar',
      });
      if (!mounted) return;
      setState(() => _meu = Progresso.deJson(d['progresso'] as Map<String, dynamic>));
      if (eps.length > 1) aviso(context, d['mensagem'] as String);

      // Depois de marcar o episódio do centro, desliza para o seguinte
      if (avancar && _atual < _eps.length - 1) {
        _pista?.animateToPage(_atual + 1, duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic);
      }
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        for (final ep in eps) {
          ep.tu = antes[ep.id] ?? ep.tu;
        }
      });
      aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardar = false);
    }
  }

  // Toque num cartão: seleção, centrar, ou alternar o do centro
  void _tocar(int i) {
    final ep = _eps[i];
    if (_aSelecionar) {
      setState(() => _selecionados.contains(ep.id) ? _selecionados.remove(ep.id) : _selecionados.add(ep.id));
    } else if (i != _atual) {
      _pista?.animateToPage(i, duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
    } else {
      _marcar([ep], visto: !ep.tu);
    }
  }

  // Abre a folha de comentários e atualiza o balão quando muda
  Future<void> _comentarios(Episodio ep) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => FolhaComentarios(
        episodio: ep,
        aoMudar: (n) {
          if (mounted) setState(() => ep.coment = n);
        },
      ),
    );
  }

  // ---------- Ecrã ----------

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final serie = _serie;

    return Scaffold(
      body: Fundo(
        acento: serie?.cor,
        child: SafeArea(
          child: Column(
            children: [
              // Topo: voltar, nome, estatísticas
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 6, 8, 0),
                child: Row(
                  children: [
                    const BackButton(),
                    Expanded(
                      child: Text(
                        serie?.nome ?? '',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Estatísticas',
                      icon: const Icon(Icons.insights_rounded),
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => EcraEstatisticas(slug: widget.slug)),
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: serie == null
                    ? (_erro == null
                        ? const Carregando()
                        : ErroComRetry(
                            mensagem: _erro!,
                            aoTentar: () {
                              setState(() => _erro = null);
                              _carregar();
                            },
                          ))
                    : _corpo(p, serie),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _corpo(Paleta p, Serie serie) {
    final textos = Theme.of(context).textTheme;
    final atual = _eps.isEmpty ? null : _eps[_atual];

    return Column(
      children: [
        // ---------- Mapa dos dois ----------
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
          child: Vidro(
            padding: const EdgeInsets.fromLTRB(18, 14, 18, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                LinhaPessoa(nome: _user?.nome ?? 'Tu', progresso: _meu, total: serie.totalEpisodios, ehTu: true),
                if (_par != null && serie.par != null) ...[
                  const SizedBox(height: 10),
                  LinhaPessoa(nome: _par!.nome, progresso: serie.par!, total: serie.totalEpisodios, ehTu: false),
                ],
                const SizedBox(height: 8),
                Text(
                  '${serie.totalEpisodios} episódios · ${serie.totalFillers} fillers',
                  style: textos.labelSmall?.copyWith(color: p.suave),
                ),
              ],
            ),
          ),
        ),

        // ---------- Pista de episódios ----------
        Expanded(
          child: PageView.builder(
            controller: _pista,
            itemCount: _eps.length,
            onPageChanged: (i) => setState(() => _atual = i),
            itemBuilder: (context, i) => AnimatedBuilder(
              animation: _pista!,
              // Os cartões afastados do centro ficam mais pequenos e mais transparentes
              builder: (context, cartao) {
                final pagina = (_pista!.hasClients && _pista!.position.haveDimensions)
                    ? (_pista!.page ?? _atual.toDouble())
                    : _atual.toDouble();
                final distancia = math.min((pagina - i).abs(), 1.0);
                return Transform.scale(
                  scale: 1 - distancia * 0.12,
                  child: Opacity(opacity: 1 - distancia * 0.4, child: cartao),
                );
              },
              child: _cartao(p, _eps[i], i),
            ),
          ),
        ),

        // ---------- Régua para saltar episódios (útil nas séries com 500) ----------
        if (_eps.length > 1)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              children: [
                SizedBox(
                  width: 64,
                  child: Text('ep ${_atual + 1}', textAlign: TextAlign.center, style: textos.labelMedium),
                ),
                Expanded(
                  child: Slider(
                    value: (_atual + 1).toDouble(),
                    min: 1,
                    max: _eps.length.toDouble(),
                    onChanged: (v) => _pista?.jumpToPage(v.round() - 1),
                  ),
                ),
                SizedBox(
                  width: 44,
                  child: Text('${_eps.length}', style: textos.labelMedium?.copyWith(color: p.suave)),
                ),
              ],
            ),
          ),

        // ---------- Botões de baixo ----------
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
          child: _aSelecionar ? _barraSelecao(p) : _barraNormal(p, atual),
        ),
      ],
    );
  }

  // Botão principal (marcar o episódio do centro) + comentários
  Widget _barraNormal(Paleta p, Episodio? atual) {
    if (atual == null) return const SizedBox.shrink();
    return Row(
      children: [
        Expanded(
          child: FilledButton.icon(
            onPressed: _aGuardar ? null : () => _marcar([atual], visto: !atual.tu, avancar: !atual.tu),
            icon: Icon(atual.tu ? Icons.undo_rounded : Icons.check_rounded),
            label: Text(atual.tu ? 'Desmarcar o ${atual.numero}' : 'Vi o episódio ${atual.numero}'),
          ),
        ),
        const SizedBox(width: 10),
        IconButton.filledTonal(
          tooltip: 'Comentários',
          onPressed: () => _comentarios(atual),
          icon: Badge(
            isLabelVisible: atual.coment > 0,
            label: Text('${atual.coment}'),
            backgroundColor: p.parTxt,
            child: const Icon(Icons.chat_bubble_outline_rounded),
          ),
        ),
      ],
    );
  }

  // Modo de seleção: marcar ou desmarcar todos os escolhidos de uma vez
  Widget _barraSelecao(Paleta p) {
    final escolhidos = _eps.where((e) => _selecionados.contains(e.id)).toList();
    return Row(
      children: [
        TextButton(
          onPressed: () => setState(_selecionados.clear),
          child: const Text('Cancelar'),
        ),
        const Spacer(),
        OutlinedButton(
          onPressed: _aGuardar ? null : () => _marcar(escolhidos, visto: false),
          child: const Text('Desmarcar'),
        ),
        const SizedBox(width: 8),
        FilledButton(
          onPressed: _aGuardar ? null : () => _marcar(escolhidos, visto: true),
          child: Text('Marcar ${escolhidos.length}'),
        ),
      ],
    );
  }

  // Um cartão da pista
  Widget _cartao(Paleta p, Episodio e, int i) {
    final textos = Theme.of(context).textTheme;
    final selecionado = _selecionados.contains(e.id);

    // Cor do cartão conta quem viu: sálvia = só tu, alperce = só o par, degradê = os dois
    Gradient? tinta;
    if (e.tu && e.par) {
      tinta = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [p.tu.withValues(alpha: 0.75), p.par.withValues(alpha: 0.75)],
      );
    } else if (e.tu) {
      tinta = LinearGradient(colors: [p.tu.withValues(alpha: 0.75), p.tu.withValues(alpha: 0.45)]);
    } else if (e.par) {
      tinta = LinearGradient(colors: [p.par.withValues(alpha: 0.75), p.par.withValues(alpha: 0.45)]);
    }
    // Texto escuro por cima do pastel; senão a cor normal
    final corTexto = tinta != null ? p.noPastel : p.tinta;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 14),
      child: Vidro(
        raio: 30,
        padding: const EdgeInsets.all(20),
        tinta: tinta,
        onTap: () => _tocar(i),
        // Toque longo: entra no modo de seleção com este episódio
        onLongPress: () => setState(() => _selecionados.add(e.id)),
        child: DefaultTextStyle.merge(
          style: TextStyle(color: corTexto),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text('EPISÓDIO',
                      style: textos.labelSmall
                          ?.copyWith(letterSpacing: 1.6, fontWeight: FontWeight.w700, color: corTexto.withValues(alpha: 0.7))),
                  const Spacer(),
                  if (_aSelecionar)
                    Icon(
                      selecionado ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                      color: corTexto,
                    ),
                ],
              ),
              Text(
                '${e.numero}',
                style: textos.displayMedium?.copyWith(fontWeight: FontWeight.w800, color: corTexto, height: 1.1),
              ),
              if (e.filler) ...[
                const SizedBox(height: 6),
                // Etiqueta "filler" bem visível
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(
                    color: corTexto.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: corTexto.withValues(alpha: 0.35)),
                  ),
                  child: Text('filler', style: textos.labelSmall?.copyWith(fontWeight: FontWeight.w800, color: corTexto)),
                ),
              ],
              const SizedBox(height: 10),
              Expanded(
                child: Text(
                  e.titulo ?? 'Sem título',
                  maxLines: 5,
                  overflow: TextOverflow.ellipsis,
                  style: textos.bodyMedium?.copyWith(fontWeight: FontWeight.w600, color: corTexto),
                ),
              ),
              // Quem viu (avatares) e o balão dos comentários
              Row(
                children: [
                  if (e.tu) Avatar(user: _user, cor: p.tu, tamanho: 26),
                  if (e.tu && e.par) const SizedBox(width: 4),
                  if (e.par) Avatar(user: _par, cor: p.par, tamanho: 26),
                  const Spacer(),
                  if (e.coment > 0) ...[
                    Icon(Icons.chat_bubble_rounded, size: 16, color: corTexto.withValues(alpha: 0.8)),
                    const SizedBox(width: 4),
                    Text('${e.coment}', style: textos.labelMedium?.copyWith(color: corTexto)),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
