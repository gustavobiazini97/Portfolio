// Perfil do teu par ou de um amigo: foto, último episódio, números (dele e vossos), medalhas e a biblioteca
// arrumada por estado (A ver / Em pausa / Acabado), com a tua % ao lado da dele nas séries que também tens.
// Se ele escolheu "só o que vemos juntos": aparecem só as séries que vê contigo, os números contam só essas
// e as medalhas ficam privadas.
// Nas séries que ainda não tens dá para te juntares: "Ver com ele" (botão) ou "Só para mim" (menu ⋯).

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'medalhas.dart' show Medalha;
import 'serie.dart';

// Números do perfil, como a API os envia (GET /pessoas/{id} → numeros)
class _Numeros {
  final int episodios, horas, series, acabadas, seriesJuntos, episodiosJuntos;

  _Numeros.deJson(Map<String, dynamic> j)
      : episodios = (j['episodios'] as num? ?? 0).toInt(),
        horas = (j['horas'] as num? ?? 0).toInt(),
        series = (j['series'] as num? ?? 0).toInt(),
        acabadas = (j['acabadas'] as num? ?? 0).toInt(),
        seriesJuntos = (j['seriesJuntos'] as num? ?? 0).toInt(),
        episodiosJuntos = (j['episodiosJuntos'] as num? ?? 0).toInt();
}

// Ordem e nomes das secções da biblioteca
const _estados = {'a_ver': 'A ver', 'pausa': 'Em pausa', 'acabado': 'Acabado'};

class EcraPessoa extends StatefulWidget {
  final int id;

  const EcraPessoa({super.key, required this.id});

  @override
  State<EcraPessoa> createState() => _EcraPessoaState();
}

class _EcraPessoaState extends State<EcraPessoa> {
  Utilizador? _pessoa;
  bool _ehPar = false;
  bool _soJuntos = false;
  Ultimo? _ultimo;
  _Numeros? _numeros;
  List<Medalha>? _medalhas; // null = privadas ("só juntos") ou API antiga
  List<ItemBiblioteca> _biblioteca = [];
  Map<String, int?> _teuPct = {}; // slug → a tua % (só nas séries que também tens)
  String? _erro;
  bool _ocupado = false;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/pessoas/${widget.id}');
      if (!mounted) return;
      final itens = (d['biblioteca'] as List? ?? []).cast<Map<String, dynamic>>();
      final med = d['medalhas'] as Map<String, dynamic>?;
      setState(() {
        _pessoa = Utilizador.deJson(d['pessoa'] as Map<String, dynamic>);
        _ehPar = d['ehPar'] == true;
        _soJuntos = d['soJuntos'] == true;
        _ultimo = Ultimo.talvez(d['ultimo']);
        _numeros = d['numeros'] == null ? null : _Numeros.deJson(d['numeros'] as Map<String, dynamic>);
        _medalhas = med == null
            ? null
            : (med['medalhas'] as List).map((m) => Medalha.deJson(m as Map<String, dynamic>)).toList();
        _biblioteca = ItemBiblioteca.lista(itens);
        _teuPct = {
          for (final i in itens) (i['serie'] as Map<String, dynamic>)['slug'] as String: (i['teuPct'] as num?)?.toInt(),
        };
        _erro = null;
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  // Juntar-se a uma série dele: com ele (passam a ver juntos) ou só para ti
  Future<void> _juntar(Serie s, {required bool comEle}) async {
    if (_ocupado) return;
    setState(() => _ocupado = true);
    try {
      final d = await Api.instancia.post('/series/${s.slug}/juntar', comEle ? {'com': widget.id} : {});
      if (mounted) aviso(context, d['mensagem'] as String? ?? 'Feito.');
      await _carregar();
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _ocupado = false);
    }
  }

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
                    Expanded(
                      child: Text(_pessoa?.nome ?? '',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: _pessoa == null
                    ? (_erro == null ? const Carregando() : ErroComRetry(mensagem: _erro!, aoTentar: _carregar))
                    : _corpo(p),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _corpo(Paleta p) {
    final textos = Theme.of(context).textTheme;
    final u = _pessoa!;
    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          // Cabeçalho: foto, nome e o último episódio que viu
          Center(child: Avatar(user: u, cor: p.par, tamanho: 88)),
          const SizedBox(height: 8),
          Center(child: Text(u.nome, style: textos.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
          Center(child: Text(_ehPar ? 'o teu par' : '@${u.username}', style: textos.bodySmall?.copyWith(color: p.suave))),
          const SizedBox(height: 14),
          if (_ultimo != null)
            Vidro(
              child: Text(
                'Viu o episódio ${_ultimo!.numero} de ${_ultimo!.serieNome}'
                '${_ultimo!.quando.isNotEmpty ? ' · ${_ultimo!.quando}' : ''}',
              ),
            ),
          if (_soJuntos) ...[
            const SizedBox(height: 10),
            Text('${u.nome} só mostra as séries que vê contigo.', style: textos.bodySmall?.copyWith(color: p.suave)),
          ],

          // Números dele e o que vêem juntos
          if (_numeros != null) ...[
            const SizedBox(height: 14),
            _blocoNumeros(p, _numeros!),
            if (_numeros!.seriesJuntos > 0) ...[
              const SizedBox(height: 10),
              _blocoJuntos(p, _numeros!, u),
            ],
          ],

          // Medalhas em destaque (privadas com "só juntos")
          if (_medalhas != null) ...[
            const SizedBox(height: 18),
            _blocoMedalhas(p, _medalhas!, u),
          ],

          // Biblioteca, por estado
          const SizedBox(height: 18),
          Text('A biblioteca de ${u.nome}', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          if (_biblioteca.isEmpty)
            Text('Nada para mostrar por agora.', style: TextStyle(color: p.suave))
          else
            for (final e in _estados.entries) ..._secao(p, e.key, e.value, u),
        ],
      ),
    );
  }

  // ---------- Números ----------

  // Faixa com 4 números: episódios, horas, séries e acabadas
  Widget _blocoNumeros(Paleta p, _Numeros n) {
    return Vidro(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
      child: Row(
        children: [
          _numero(p, n.episodios, 'episódios'),
          _numero(p, n.horas, 'horas'),
          _numero(p, n.series, 'séries'),
          _numero(p, n.acabadas, 'acabadas'),
        ],
      ),
    );
  }

  // Um número grande com a legenda por baixo
  Widget _numero(Paleta p, int valor, String legenda) {
    return Expanded(
      child: Column(
        children: [
          Text('$valor', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: p.parTxt)),
          const SizedBox(height: 2),
          Text(legenda, style: TextStyle(fontSize: 11.5, color: p.suave)),
        ],
      ),
    );
  }

  // "Juntos": episódios que os dois já viram nas séries que vêem juntos e quantas séries são
  Widget _blocoJuntos(Paleta p, _Numeros n, Utilizador u) {
    final textos = Theme.of(context).textTheme;
    return Vidro(
      padding: const EdgeInsets.all(14),
      tinta: LinearGradient(colors: [p.tu.withValues(alpha: .18), p.par.withValues(alpha: .18)]),
      child: Row(
        children: [
          Icon(Icons.favorite_rounded, color: p.parTxt, size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Tu e ${u.nome}', style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                Text(
                  '${n.episodiosJuntos} ${n.episodiosJuntos == 1 ? 'episódio visto' : 'episódios vistos'} pelos dois · '
                  '${n.seriesJuntos} ${n.seriesJuntos == 1 ? 'série' : 'séries'} juntos',
                  style: textos.bodySmall?.copyWith(color: p.suave),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------- Medalhas ----------

  // Título com "obtidas/total", as 3 mais difíceis já obtidas e "Ver todas"
  Widget _blocoMedalhas(Paleta p, List<Medalha> lista, Utilizador u) {
    final textos = Theme.of(context).textTheme;
    final obtidas = lista.where((m) => m.obtida).toList();
    // Dentro de cada grupo a lista vai da mais fácil à mais difícil: as últimas obtidas são as melhores
    final destaque = obtidas.reversed.take(3).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text('Medalhas · ${obtidas.length}/${lista.length}',
                  style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
            TextButton(onPressed: () => _todasMedalhas(p, lista, u), child: const Text('Ver todas')),
          ],
        ),
        const SizedBox(height: 4),
        if (destaque.isEmpty)
          Text('${u.nome} ainda não tem medalhas.', style: TextStyle(color: p.suave))
        else
          Row(
            children: [
              for (final m in destaque)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Vidro(
                      raio: 22,
                      padding: const EdgeInsets.fromLTRB(6, 12, 6, 10),
                      onTap: () => _detalheMedalha(p, m),
                      child: Column(
                        children: [
                          _circuloMedalha(p, m, 52),
                          const SizedBox(height: 8),
                          Text(m.nome,
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
                ),
              // Menos de 3: espaços vazios para os cartões não esticarem
              for (var i = destaque.length; i < 3; i++) const Expanded(child: SizedBox()),
            ],
          ),
      ],
    );
  }

  // Círculo com o emoji: com gradiente se obtida, a cinzento se não (como no ecrã Medalhas)
  Widget _circuloMedalha(Paleta p, Medalha m, double tamanho) {
    return Container(
      width: tamanho,
      height: tamanho,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: m.obtida ? LinearGradient(colors: [p.tu, p.par], begin: Alignment.topLeft, end: Alignment.bottomRight) : null,
        color: m.obtida ? null : p.trilho,
        boxShadow: m.obtida ? [BoxShadow(color: p.par.withValues(alpha: .4), blurRadius: 12)] : null,
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
          child: Text(m.emoji, style: TextStyle(fontSize: tamanho * .46)),
        ),
      ),
    );
  }

  // Todas as medalhas dele numa folha que sobe de baixo, por grupo
  void _todasMedalhas(Paleta p, List<Medalha> lista, Utilizador u) {
    final grupos = <String, List<Medalha>>{};
    for (final m in lista) {
      grupos.putIfAbsent(m.grupo, () => []).add(m);
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: .75,
        maxChildSize: .95,
        builder: (ctx, controlo) => ListView(
          controller: controlo,
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
          children: [
            Text('Medalhas de ${u.nome}',
                style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            for (final g in grupos.entries) ...[
              TituloSecao(g.key),
              GridView.count(
                crossAxisCount: 3,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: .9,
                children: [
                  for (final m in g.value)
                    InkWell(
                      borderRadius: BorderRadius.circular(16),
                      onTap: () => _detalheMedalha(p, m),
                      child: Column(
                        children: [
                          _circuloMedalha(p, m, 48),
                          const SizedBox(height: 6),
                          Text(m.nome,
                              textAlign: TextAlign.center,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: m.obtida ? p.tinta : p.suave)),
                        ],
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 14),
            ],
          ],
        ),
      ),
    );
  }

  // O que é uma medalha e quanto falta (ou "obtida")
  void _detalheMedalha(Paleta p, Medalha m) {
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
              if (m.obtida)
                Text('Obtida', style: TextStyle(color: p.parTxt, fontWeight: FontWeight.w800))
              else ...[
                BarraProgresso(pct: m.pct, cor: p.par, altura: 10),
                const SizedBox(height: 6),
                Text('${m.progressoTexto} · ${m.pct}%', style: TextStyle(fontSize: 13, color: p.suave)),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // ---------- Biblioteca ----------

  // Uma secção (ex.: "A ver · 3") com as séries desse estado; vazia → não aparece
  List<Widget> _secao(Paleta p, String estado, String titulo, Utilizador u) {
    final itens = _biblioteca.where((i) => i.estado == estado).toList();
    if (itens.isEmpty) return [];
    return [
      Padding(
        padding: const EdgeInsets.only(top: 6, bottom: 8),
        child: Text('$titulo · ${itens.length}',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(color: p.suave, fontWeight: FontWeight.w800)),
      ),
      for (final i in itens) ...[
        _cartaoSerie(p, i, u),
        const SizedBox(height: 10),
      ],
    ];
  }

  // Uma série dele: capa, a % dele (e a tua, se também a tens) e o que podes fazer
  Widget _cartaoSerie(Paleta p, ItemBiblioteca i, Utilizador u) {
    final textos = Theme.of(context).textTheme;
    final s = i.serie;
    final teu = _teuPct[s.slug];
    return Vidro(
      padding: const EdgeInsets.all(12),
      onTap: i.naTua
          ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraSerie(slug: s.slug)))
          : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Capa(serie: s, largura: 56, raio: 12),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(s.nomeCurto,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                LinhaPct(nome: u.nome, pct: i.pct, ehTu: false),
                // Também a tens: a tua barra por baixo da dele
                if (teu != null) ...[
                  const SizedBox(height: 8),
                  LinhaPct(nome: 'Tu', pct: teu, ehTu: true),
                ],
                const SizedBox(height: 10),
                if (i.vesCom)
                  _etiqueta(p, Icons.favorite_rounded, 'Vês com ${u.nome}', p.tuTxt)
                else if (i.naTua)
                  _etiqueta(p, Icons.bookmark_rounded, 'Também está na tua biblioteca', p.suave)
                else
                  _botoesJuntar(p, s, u),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Linha pequena com ícone (estado da série em relação a ti)
  Widget _etiqueta(Paleta p, IconData icone, String texto, Color cor) {
    return Row(
      children: [
        Icon(icone, size: 15, color: cor),
        const SizedBox(width: 6),
        Expanded(
          child: Text(texto, style: Theme.of(context).textTheme.labelMedium?.copyWith(color: cor, fontWeight: FontWeight.w700)),
        ),
      ],
    );
  }

  // "Ver com ele" + menu ⋯ com "Só para mim" (antes eram dois botões grandes empilhados)
  Widget _botoesJuntar(Paleta p, Serie s, Utilizador u) {
    return Row(
      children: [
        Expanded(
          child: FilledButton.icon(
            style: FilledButton.styleFrom(
              visualDensity: VisualDensity.compact,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
            onPressed: _ocupado ? null : () => _juntar(s, comEle: true),
            icon: const Icon(Icons.group_add_rounded, size: 18),
            label: Text('Ver com ${u.nome}', overflow: TextOverflow.ellipsis),
          ),
        ),
        PopupMenuButton<String>(
          enabled: !_ocupado,
          tooltip: 'Mais opções',
          icon: Icon(Icons.more_horiz_rounded, color: p.suave),
          onSelected: (_) => _juntar(s, comEle: false),
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'so', child: Text('Adicionar só para mim')),
          ],
        ),
      ],
    );
  }
}
