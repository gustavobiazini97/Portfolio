// Biblioteca (separador da barra de baixo): todas as tuas séries em grelha, agrupadas por estado
// (A ver · Em pausa · Acabado), com filtro por nome. Tocar numa capa abre a série.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'serie.dart';

class EcraBiblioteca extends StatefulWidget {
  const EcraBiblioteca({super.key});

  @override
  State<EcraBiblioteca> createState() => _EcraBibliotecaState();
}

class _EcraBibliotecaState extends State<EcraBiblioteca> {
  List<ItemBiblioteca> _series = [];
  String _filtro = '';
  bool _aCarregar = true;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/series');
      if (!mounted) return;
      setState(() {
        _series = ItemBiblioteca.lista(d['series']);
        _erro = null;
        _aCarregar = false;
      });
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  Future<void> _abrir(Serie s) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraSerie(slug: s.slug)));
    if (mounted) _carregar();
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return Scaffold(
      body: Fundo(
        child: SafeArea(
          bottom: false,
          child: _aCarregar
              ? const Carregando()
              : (_erro != null && _series.isEmpty)
                  ? ErroComRetry(mensagem: _erro!, aoTentar: _carregar)
                  : RefreshIndicator(onRefresh: _carregar, child: _corpo(p)),
        ),
      ),
    );
  }

  Widget _corpo(Paleta p) {
    final q = _filtro.trim().toLowerCase();
    final visiveis = q.isEmpty ? _series : _series.where((i) => i.serie.nome.toLowerCase().contains(q)).toList();

    // Grupos pela ordem da fila do site
    const grupos = [('a_ver', 'A ver'), ('pausa', 'Em pausa'), ('acabado', 'Acabado')];

    return CustomScrollView(
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 6),
          sliver: SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Biblioteca', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text('${_series.length} ${_series.length == 1 ? 'série' : 'séries'}', style: TextStyle(color: p.suave)),
                const SizedBox(height: 14),
                // Filtro por nome
                TextField(
                  onChanged: (v) => setState(() => _filtro = v),
                  decoration: const InputDecoration(hintText: 'Procurar na tua biblioteca', prefixIcon: Icon(Icons.search_rounded)),
                ),
              ],
            ),
          ),
        ),
        if (_series.isEmpty)
          SliverFillRemaining(
            hasScrollBody: false,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Text('Ainda não tens séries. Toca no + em baixo para adicionar a primeira.',
                    textAlign: TextAlign.center, style: TextStyle(color: p.suave)),
              ),
            ),
          ),
        for (final (estado, titulo) in grupos) ...() {
          final doGrupo = visiveis.where((i) => i.estado == estado).toList();
          if (doGrupo.isEmpty) return <Widget>[];
          return <Widget>[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(24, 18, 20, 10),
              sliver: SliverToBoxAdapter(
                child: Text('$titulo · ${doGrupo.length}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 130,
                  mainAxisSpacing: 16,
                  crossAxisSpacing: 12,
                  childAspectRatio: 0.52,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, n) => _capa(p, doGrupo[n]),
                  childCount: doGrupo.length,
                ),
              ),
            ),
          ];
        }(),
        const SliverToBoxAdapter(child: SizedBox(height: 24)),
      ],
    );
  }

  // Uma capa: imagem, avatares de quem vê contigo, nome, % e as barrinhas
  Widget _capa(Paleta p, ItemBiblioteca i) {
    return GestureDetector(
      onTap: () => _abrir(i.serie),
      child: LayoutBuilder(
        builder: (context, c) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                Opacity(opacity: i.estado == 'acabado' ? .8 : 1, child: Capa(serie: i.serie, largura: c.maxWidth, raio: 16)),
                if (i.companheiros.isNotEmpty)
                  Positioned(
                    top: 6,
                    right: 6,
                    child: AvataresSobrepostos(
                      tamanho: 18,
                      pessoas: [for (final comp in i.companheiros.take(3)) (comp.user, p.par, true)],
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Text(i.serie.nomeCurto, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w500)),
            Text('${i.pct}%${i.serie.emEmissao ? ' · em emissão' : ''}', style: TextStyle(fontSize: 11, color: p.suave)),
            const SizedBox(height: 4),
            BarraProgresso(pct: i.pct, cor: p.tu, altura: 4),
            if (i.companheiros.isNotEmpty) ...[
              const SizedBox(height: 3),
              BarraProgresso(pct: i.companheiros.first.pct, cor: p.par, altura: 4),
            ],
          ],
        ),
      ),
    );
  }
}
