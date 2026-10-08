// Página de uma série da tua biblioteca, igual à do site:
//   mapa (um risco por episódio, para ti e para quem vê contigo, % e régua 1 · metade · total),
//   pista de cartões compactos que encaixam ao centro (cor = quem viu, avatares, "os dois viram"…),
//   painel do episódio ao centro, "Selecionar vários", "Comentários" e o botão de marcar.

import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import '../widgets/menu_radial.dart';
import 'comentarios.dart';

class EcraSerie extends StatefulWidget {
  final String slug;
  final int? episodio; // número a abrir ao centro (ex.: o seguinte ao teu, ou o último que o par viu)

  const EcraSerie({super.key, required this.slug, this.episodio});

  @override
  State<EcraSerie> createState() => _EcraSerieState();
}

class _EcraSerieState extends State<EcraSerie> {
  // Dados do GET /series/{slug}
  Serie? _serie;
  Utilizador? _user;
  List<Utilizador> _companheiros = []; // quem vê esta série contigo (pela ordem da API)
  List<Progresso> _progressoCom = []; // progresso de cada companheiro
  List<Episodio> _eps = [];
  Progresso _meu = const Progresso(); // o teu progresso (atualizado a cada marcação)

  PageController? _pista;
  int _atual = 0; // índice do cartão ao centro
  bool _multiplo = false; // modo "Selecionar vários"
  final Set<int> _selecionados = {}; // IDs escolhidos no modo de seleção
  bool _aGuardar = false;
  String? _erro;

  final _menu = MenuRadial(); // toque longo num cartão

  // Largura de um cartão + espaço (172 + 12, como no site)
  static const _larguraCartao = 172.0;
  static const _espaco = 12.0;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  @override
  void dispose() {
    _menu.fechar(executar: false);
    _pista?.dispose();
    super.dispose();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/series/${widget.slug}');
      if (!mounted) return;
      final serie = Serie.deJson(d['serie'] as Map<String, dynamic>);
      final eps = (d['episodios'] as List).map((e) => Episodio.deJson(e as Map<String, dynamic>)).toList();
      final meu = Progresso.deJson(d['tu'] as Map<String, dynamic>);
      final comp = ((d['companheiros'] as List?) ?? []).map((c) => c as Map<String, dynamic>).toList();

      // Cartão inicial: o pedido; senão o seguinte ao teu mais avançado (como no site)
      final numero = widget.episodio ?? math.min(meu.posicao + 1, serie.totalEpisodios);
      final indice = (numero - 1).clamp(0, math.max(0, eps.length - 1)).toInt();

      setState(() {
        _serie = serie;
        _meu = meu;
        _user = Utilizador.deJson(d['user'] as Map<String, dynamic>);
        _companheiros = comp.map((c) => Utilizador.deJson(c['user'] as Map<String, dynamic>)).toList();
        _progressoCom = comp.map((c) => Progresso.deJson(c['progresso'] as Map<String, dynamic>)).toList();
        _eps = eps;
        _atual = indice;
        _erro = null;
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  // O PageController depende da largura do ecrã (viewportFraction = cartão / ecrã): cria-se no primeiro build
  PageController _controlador(double largura) {
    final fracao = (_larguraCartao + _espaco) / largura;
    if (_pista == null || (_pista!.viewportFraction - fracao).abs() > 0.001) {
      final antigo = _pista;
      _pista = PageController(viewportFraction: fracao, initialPage: _atual);
      if (antigo != null) WidgetsBinding.instance.addPostFrameCallback((_) => antigo.dispose());
    }
    return _pista!;
  }

  // ---------- Textos (iguais aos do site) ----------

  // "Gustavo e Rui", "3 pessoas"
  String _juntar(List<String> l) => l.length == 1 ? l[0] : (l.length == 2 ? '${l[0]} e ${l[1]}' : '${l.length} pessoas');

  // Frase por baixo dos avatares de cada cartão
  String _rotulo(Episodio e) {
    if (_companheiros.isEmpty) return e.tu ? 'visto' : 'por ver';
    final viram = <String>[], faltam = <String>[];
    for (var i = 0; i < _companheiros.length; i++) {
      final viu = i < e.com.length && e.com[i];
      (viu ? viram : faltam).add(_companheiros[i].nome);
    }
    if (e.tu && faltam.isEmpty) return _companheiros.length == 1 ? 'os dois viram' : 'todos viram';
    if (e.tu) return 'falta ${_juntar(faltam)}';
    if (viram.isNotEmpty) return '${_juntar(viram)}${viram.length == 1 ? ' já viu' : ' já viram'}';
    return 'por ver';
  }

  // Texto do botão principal
  String _textoBotao() {
    if (_multiplo) {
      final n = _selecionados.length;
      if (n == 0) return 'Toca nos episódios a escolher';
      final todosVistos = _eps.where((e) => _selecionados.contains(e.id)).every((e) => e.tu);
      return todosVistos
          ? 'Desmarcar $n ${n == 1 ? 'episódio' : 'episódios'}'
          : 'Marcar $n ${n == 1 ? 'episódio' : 'episódios'} como vistos';
    }
    if (_eps.isEmpty) return '';
    final e = _eps[_atual];
    return e.tu ? 'Desmarcar episódio ${e.numero}' : 'Marcar episódio ${e.numero} como visto';
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
      _multiplo = false;
    });

    try {
      final d = await Api.instancia.post('/series/${widget.slug}/vistos', {
        'episodios': eps.map((e) => e.id).toList(),
        'acao': visto ? 'marcar' : 'desmarcar',
      });
      if (!mounted) return;
      setState(() => _meu = Progresso.deJson(d['progresso'] as Map<String, dynamic>));
      aviso(context, d['mensagem'] as String);

      // Depois de marcar o episódio do centro, desliza para o seguinte (como no site)
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

  // Botão principal: o episódio do centro, ou os escolhidos no modo de seleção
  void _botaoPrincipal() {
    if (_multiplo) {
      final escolhidos = _eps.where((e) => _selecionados.contains(e.id)).toList();
      if (escolhidos.isEmpty) return;
      _marcar(escolhidos, visto: !escolhidos.every((e) => e.tu));
    } else if (_eps.isNotEmpty) {
      final e = _eps[_atual];
      _marcar([e], visto: !e.tu, avancar: !e.tu);
    }
  }

  // Toque num cartão: no modo de seleção escolhe; fora dele centra (e o do centro alterna)
  void _tocar(int i) {
    final ep = _eps[i];
    if (_multiplo) {
      setState(() => _selecionados.contains(ep.id) ? _selecionados.remove(ep.id) : _selecionados.add(ep.id));
    } else if (i != _atual) {
      _pista?.animateToPage(i, duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
    }
  }

  // Opções do menu radial de um cartão: marcar/desmarcar, comentar, marcar até aqui, partilhar, selecionar vários
  List<OpcaoRadial> _opcoesMenu(Episodio e, int i) {
    final porVerAntes = _eps.sublist(0, i + 1).where((x) => !x.tu).toList();
    return [
      OpcaoRadial(
        icone: e.tu ? Icons.undo_rounded : Icons.check_rounded,
        texto: e.tu ? 'Desmarcar o ${e.numero}' : 'Vi o ${e.numero}',
        destaque: true,
        acao: () => _marcar([e], visto: !e.tu, avancar: !e.tu),
      ),
      OpcaoRadial(icone: Icons.chat_bubble_outline_rounded, texto: 'Comentários', acao: () => _comentarios(e)),
      if (porVerAntes.length > 1)
        OpcaoRadial(
          icone: Icons.fast_forward_rounded,
          texto: 'Marcar até aqui (${porVerAntes.length})',
          acao: () => _marcarAteAqui(e, porVerAntes),
        ),
      OpcaoRadial(icone: Icons.share_rounded, texto: 'Partilhar', acao: () => _partilharEpisodio(e)),
      OpcaoRadial(
        icone: Icons.checklist_rounded,
        texto: 'Selecionar vários',
        acao: () => setState(() {
          _multiplo = true;
          _selecionados
            ..clear()
            ..add(e.id);
        }),
      ),
    ];
  }

  // Marca todos os episódios por ver até este (inclusive), com confirmação
  Future<void> _marcarAteAqui(Episodio e, List<Episodio> porVer) async {
    final sim = await confirmar(
      context,
      'Marcar até ao episódio ${e.numero}?',
      'Ficam vistos ${porVer.length} episódios que ainda não tinhas marcado.',
      sim: 'Marcar ${porVer.length}',
    );
    if (sim) _marcar(porVer, visto: true);
  }

  // Partilha o episódio (menu do Android)
  void _partilharEpisodio(Episodio e) {
    final s = _serie;
    if (s == null) return;
    final titulo = (e.titulo ?? '').isEmpty ? '' : ' — "${e.titulo}"';
    Share.share('${e.tu ? 'Vi' : 'Vou ver'} o episódio ${e.numero} de ${s.nome}$titulo 📺\nNo Anime a Dois: animeadois.alwaysdata.net');
  }

  // Abre a folha de comentários e atualiza o número no cartão
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
    final escuro = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      body: Fundo(
        acento: serie?.cor,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
            child: Column(
              children: [
                // Topo: voltar, nome ao centro, tema (como no site)
                Row(
                  children: [
                    BotaoRedondo(
                      tooltip: 'Voltar',
                      icone: const Icon(Icons.chevron_left_rounded),
                      onTap: () => Navigator.of(context).maybePop(),
                    ),
                    Expanded(
                      child: Text(
                        serie?.nome ?? '',
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w500),
                      ),
                    ),
                    BotaoRedondo(
                      tooltip: escuro ? 'Tema claro' : 'Tema escuro',
                      icone: Icon(escuro ? Icons.light_mode_outlined : Icons.dark_mode_outlined),
                      onTap: Sessao.instancia.alternarTema,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
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
      ),
    );
  }

  Widget _corpo(Paleta p, Serie serie) {
    final atual = _eps.isEmpty ? null : _eps[_atual];
    // Ecrãs baixos: o painel do episódio sai para os cartões caberem (como no site)
    final alturaEcra = MediaQuery.sizeOf(context).height;
    final mostrarDetalhe = alturaEcra >= 760 + (_companheiros.length > 1 ? 60 : 0);

    return Column(
      children: [
        _mapa(p, serie),

        // ---------- Pista: cartões que deslizam e encaixam ao centro ----------
        Expanded(
          child: LayoutBuilder(
            builder: (context, c) {
              final controlador = _controlador(MediaQuery.sizeOf(context).width);
              final altura = math.min(236.0, c.maxHeight - 36);
              // A pista vai de ponta a ponta do ecrã (sai do padding de 20)
              return OverflowBox(
                maxWidth: MediaQuery.sizeOf(context).width,
                child: SizedBox(
                  width: MediaQuery.sizeOf(context).width,
                  child: PageView.builder(
                    controller: controlador,
                    itemCount: _eps.length,
                    padEnds: true,
                    onPageChanged: (i) => setState(() => _atual = i),
                    itemBuilder: (context, i) => Center(
                      child: SizedBox(
                        width: _larguraCartao,
                        height: math.max(150.0, altura),
                        child: _cartao(p, _eps[i], i),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),

        // ---------- Painel do episódio ao centro ----------
        if (mostrarDetalhe && atual != null && !_multiplo) ...[
          _detalhe(p, atual),
          const SizedBox(height: 10),
        ],

        // ---------- Ações ----------
        Row(
          children: [
            _pilula(
              p,
              _multiplo ? 'Cancelar' : 'Selecionar vários',
              destaque: _multiplo,
              onTap: () => setState(() {
                _multiplo = !_multiplo;
                _selecionados.clear();
              }),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _multiplo && _selecionados.isNotEmpty
                    ? '${_selecionados.length} ${_selecionados.length == 1 ? 'selecionado' : 'selecionados'}'
                    : '',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(color: p.suave),
              ),
            ),
            if (!_multiplo && atual != null)
              _pilula(
                p,
                atual.coment > 0 ? 'Comentários · ${atual.coment}' : 'Comentários',
                icone: Icons.chat_bubble_outline_rounded,
                onTap: () => _comentarios(atual),
              ),
          ],
        ),
        const SizedBox(height: 10),
        _botao(p),
      ],
    );
  }

  // Mapa: "N episódios" + legenda; uma linha por pessoa (avatar, nome, riscos, %); régua por baixo
  Widget _mapa(Paleta p, Serie serie) {
    final textos = Theme.of(context).textTheme;
    final pequeno = textos.bodySmall?.copyWith(fontSize: 12.5, color: p.suave);
    final total = serie.totalEpisodios;

    // Uma linha da tabela: [avatar + nome] [riscos] [pastilha com a %]
    TableRow linha(Utilizador? u, String nome, bool Function(Episodio) viu, int pct, bool ehTu) => TableRow(
          children: [
            Padding(
              padding: const EdgeInsets.only(right: 10, bottom: 10),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 104),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Avatar(user: u, cor: ehTu ? p.tu : p.par, tamanho: 22),
                    const SizedBox(width: 7),
                    Flexible(child: Text(nome, overflow: TextOverflow.ellipsis, style: textos.bodySmall?.copyWith(fontSize: 12.5))),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _Riscos(eps: _eps, viu: viu, cor: ehTu ? p.tu : p.par, trilho: p.trilho),
            ),
            Padding(
              padding: const EdgeInsets.only(left: 10, bottom: 10),
              child: Container(
                constraints: const BoxConstraints(minWidth: 46),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: (ehTu ? p.tu : p.par).withValues(alpha: 0.30),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$pct%',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ehTu ? p.tuTxt : p.parTxt),
                ),
              ),
            ),
          ],
        );

    return Vidro(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text('$total episódios', style: pequeno)),
              // Legenda: visto (as duas cores) e filler (tracejado)
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(3),
                  gradient: LinearGradient(colors: [p.tu, p.tu, p.par, p.par], stops: const [0, .5, .5, 1]),
                ),
              ),
              const SizedBox(width: 6),
              Text('visto', style: pequeno?.copyWith(fontSize: 11.5)),
              const SizedBox(width: 12),
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: p.trilho,
                  borderRadius: BorderRadius.circular(3),
                  border: Border.all(color: p.suave, width: 1),
                ),
              ),
              const SizedBox(width: 6),
              Text(_eps.any((e) => e.recap) ? 'filler/recap' : 'filler', style: pequeno?.copyWith(fontSize: 11.5)),
            ],
          ),
          const SizedBox(height: 12),
          Table(
            columnWidths: const {0: IntrinsicColumnWidth(), 1: FlexColumnWidth(), 2: IntrinsicColumnWidth()},
            defaultVerticalAlignment: TableCellVerticalAlignment.middle,
            children: [
              linha(_user, 'Tu', (e) => e.tu, _meu.pct, true),
              for (var c = 0; c < _companheiros.length; c++)
                linha(_companheiros[c], _companheiros[c].nome, (e) => c < e.com.length && e.com[c], _progressoCom[c].pct, false),
              // Régua 1 · metade · total, alinhada com as barras
              TableRow(
                children: [
                  const SizedBox.shrink(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      for (final n in [1, (total / 2).round(), total])
                        Text('$n', style: TextStyle(fontSize: 10.5, color: p.suave)),
                    ],
                  ),
                  const SizedBox.shrink(),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  // Um cartão da pista (compacto, como no site)
  Widget _cartao(Paleta p, Episodio e, int i) {
    final escuro = Theme.of(context).brightness == Brightness.dark;
    final ativo = i == _atual;
    final selecionado = _selecionados.contains(e.id);
    final com = e.algumCom;

    // Cor do cartão conta quem viu: a tua cor = só tu, a do par = só companheiros, degradê = os dois
    Gradient? fundo;
    Color? borda;
    if (e.tu && com) {
      fundo = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: escuro
            ? [p.tu.withValues(alpha: .70), p.par.withValues(alpha: .70)]
            : [misturar(p.tu, .95, Colors.white), misturar(p.par, .95, Colors.white)],
      );
    } else if (e.tu) {
      fundo = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: escuro
            ? [p.tu.withValues(alpha: .62), misturar(p.tu, .22, p.vidro)]
            : [misturar(p.tu, .95, Colors.white), misturar(p.tu, .55, Colors.white)],
      );
      borda = p.tuTxt.withValues(alpha: .35);
    } else if (com) {
      fundo = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: escuro
            ? [p.par.withValues(alpha: .55), misturar(p.par, .20, p.vidro)]
            : [misturar(p.par, .90, Colors.white), misturar(p.par, .50, Colors.white)],
      );
      borda = p.parTxt.withValues(alpha: .30);
    }
    if (ativo || selecionado) borda = p.tuTxt;

    final corNumero = e.tu ? p.tuTxt : (com ? p.parTxt : p.tinta);
    final corTitulo = (e.tu || com) ? p.tinta.withValues(alpha: .75) : p.suave;

    return AnimatedScale(
      scale: ativo || selecionado ? 1 : .94,
      duration: const Duration(milliseconds: 250),
      child: GestureDetector(
        onTap: () => _tocar(i),
        // Toque longo: menu radial (no modo de seleção, escolhe o cartão)
        onLongPressStart: (d) {
          if (_multiplo) {
            setState(() => _selecionados.contains(e.id) ? _selecionados.remove(e.id) : _selecionados.add(e.id));
            return;
          }
          if (i != _atual) _pista?.animateToPage(i, duration: const Duration(milliseconds: 280), curve: Curves.easeOutCubic);
          _menu.abrir(context, d.globalPosition, _opcoesMenu(e, i));
        },
        onLongPressMoveUpdate: (d) => _menu.mover(d.globalPosition),
        onLongPressEnd: (_) => _menu.fechar(),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: fundo == null ? p.vidro : null,
            gradient: fundo,
            borderRadius: BorderRadius.circular(30),
            border: Border.all(color: borda ?? p.vidroBorda, width: ativo || selecionado ? 1.5 : 1),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: escuro ? .30 : .08), blurRadius: 22, offset: const Offset(0, 10))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Cima: "episódio", etiqueta filler/recap e comentários (ou o visto do modo de seleção)
              Row(
                children: [
                  Text('episódio', style: TextStyle(fontSize: 12, color: p.suave)),
                  const Spacer(),
                  if (_multiplo)
                    Container(
                      width: 22,
                      height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: selecionado ? p.tuTxt : null,
                        border: Border.all(color: selecionado ? p.tuTxt : p.linha, width: 1.5),
                      ),
                      child: selecionado ? Icon(Icons.check_rounded, size: 14, color: p.bg) : null,
                    )
                  else if (e.filler || e.recap)
                    _Etiqueta(e.filler ? 'filler' : 'recap')
                  else if (e.coment > 0)
                    Row(
                      children: [
                        Icon(Icons.chat_bubble_outline_rounded, size: 14, color: p.tinta),
                        const SizedBox(width: 3),
                        Text('${e.coment}', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, color: p.tinta)),
                      ],
                    ),
                ],
              ),
              // Meio: número e título (no máximo 2 linhas)
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${e.numero}',
                      style: TextStyle(fontSize: 46, fontWeight: FontWeight.w700, letterSpacing: -1.4, height: 1, color: corNumero)),
                  const SizedBox(height: 6),
                  Text(e.titulo ?? '',
                      maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11.5, height: 1.35, color: corTitulo)),
                ],
              ),
              // Baixo: quem viu (avatares) e a frase
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AvataresSobrepostos(pessoas: [
                    (_user, p.tu, e.tu),
                    for (var c = 0; c < _companheiros.length; c++) (_companheiros[c], p.par, c < e.com.length && e.com[c]),
                  ]),
                  const SizedBox(height: 6),
                  Text(_rotulo(e), style: TextStyle(fontSize: 12, color: (e.tu || com) ? p.tinta : p.suave)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Painel do episódio ao centro: número, tipo, título e o estado de cada pessoa
  Widget _detalhe(Paleta p, Episodio e) {
    final tipo = e.filler ? 'filler' : (e.recap ? 'recap' : 'canónico');
    Widget pessoa(Utilizador? u, String nome, Color cor, bool viu) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            AvataresSobrepostos(pessoas: [(u, cor, viu)], tamanho: 24),
            const SizedBox(width: 6),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(nome, style: TextStyle(fontSize: 12.5, color: p.suave, height: 1.3)),
                Text(viu ? 'visto' : 'por ver', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w500, color: p.tinta, height: 1.3)),
              ],
            ),
          ],
        );

    return Vidro(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text('Episódio ${e.numero}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500))),
              if (tipo == 'canónico')
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(color: p.trilho, borderRadius: BorderRadius.circular(12)),
                  child: Text(tipo, style: TextStyle(fontSize: 12, color: p.suave)),
                )
              else
                _Etiqueta(tipo),
            ],
          ),
          if ((e.titulo ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(e.titulo!, style: TextStyle(fontSize: 13.5, color: p.suave)),
          ],
          const SizedBox(height: 8),
          Wrap(
            spacing: 22,
            runSpacing: 8,
            children: [
              pessoa(_user, 'Tu', p.tu, e.tu),
              for (var c = 0; c < _companheiros.length; c++)
                pessoa(_companheiros[c], _companheiros[c].nome, p.par, c < e.com.length && e.com[c]),
            ],
          ),
        ],
      ),
    );
  }

  // Botão em pílula de vidro ("Selecionar vários", "Comentários")
  Widget _pilula(Paleta p, String texto, {IconData? icone, bool destaque = false, required VoidCallback onTap}) {
    return SizedBox(
      height: 38,
      child: Vidro(
        raio: 19,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        onTap: onTap,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icone != null) ...[Icon(icone, size: 16, color: p.tinta), const SizedBox(width: 6)],
            Text(texto,
                style: TextStyle(
                  fontSize: 13.5,
                  color: destaque ? p.tuTxt : p.tinta,
                  fontWeight: destaque ? FontWeight.w500 : FontWeight.w400,
                )),
          ],
        ),
      ),
    );
  }

  // Botão principal: escuro para marcar; de vidro (secundário) para desmarcar
  Widget _botao(Paleta p) {
    final texto = _textoBotao();
    final desmarcar = texto.startsWith('Desmarcar');
    final desativado = _aGuardar || (_multiplo && _selecionados.isEmpty) || _eps.isEmpty;
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: desmarcar
          ? Vidro(
              raio: 26,
              padding: EdgeInsets.zero,
              onTap: desativado ? null : _botaoPrincipal,
              child: Center(child: Text(texto, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500, color: p.tinta))),
            )
          : FilledButton(
              onPressed: desativado ? null : _botaoPrincipal,
              style: FilledButton.styleFrom(padding: EdgeInsets.zero),
              child: Text(texto, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
            ),
    );
  }
}

// Etiqueta "filler"/"recap": escura e sólida, salta à vista em qualquer cor de cartão e tema
class _Etiqueta extends StatelessWidget {
  final String texto;

  const _Etiqueta(this.texto);

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 3, 10, 3),
      decoration: BoxDecoration(color: p.tinta, borderRadius: BorderRadius.circular(10)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 6, height: 6, decoration: BoxDecoration(color: p.bg.withValues(alpha: .7), shape: BoxShape.circle)),
          const SizedBox(width: 5),
          Text(texto, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, color: p.bg, letterSpacing: .2)),
        ],
      ),
    );
  }
}

// Riscos do mapa: um por episódio; preenchido = visto, mais claro = filler/recap
class _Riscos extends StatelessWidget {
  final List<Episodio> eps;
  final bool Function(Episodio) viu;
  final Color cor;
  final Color trilho;

  const _Riscos({required this.eps, required this.viu, required this.cor, required this.trilho});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(7),
      child: SizedBox(
        height: 20,
        child: CustomPaint(size: Size.infinite, painter: _PintorRiscos(eps, viu, cor, trilho)),
      ),
    );
  }
}

class _PintorRiscos extends CustomPainter {
  final List<Episodio> eps;
  final bool Function(Episodio) viu;
  final Color cor;
  final Color trilho;

  _PintorRiscos(this.eps, this.viu, this.cor, this.trilho);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(Offset.zero & size, Paint()..color = trilho);
    if (eps.isEmpty) return;
    final largura = size.width / eps.length;
    final cheio = Paint()..color = cor;
    final claro = Paint()..color = cor.withValues(alpha: .35);
    for (var i = 0; i < eps.length; i++) {
      final e = eps[i];
      if (!viu(e)) continue;
      // +0.6 para não ficarem riscas finas entre episódios seguidos
      canvas.drawRect(Rect.fromLTWH(i * largura, 0, largura + 0.6, size.height), (e.filler || e.recap) ? claro : cheio);
    }
  }

  // Redesenha sempre (os vistos mudam dentro da mesma lista)
  @override
  bool shouldRepaint(covariant _PintorRiscos antigo) => true;
}
