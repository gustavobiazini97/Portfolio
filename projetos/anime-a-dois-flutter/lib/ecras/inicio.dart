// Início, igual ao do site:
//   topo (logótipo, estatísticas, amigos, tema, o teu avatar), último episódio do par (com o número grande),
//   a tua biblioteca em capas, a série aberta (placar com o episódio de cada um e a frase do Vs),
//   o teu estado, "Vês com… · Deixar de ver juntos", "Continuar · episódio N",
//   os convites "Quero ver contigo" e a fila "<par> está a ver".

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'adicionar.dart';
import 'amigos.dart';
import 'estatisticas.dart';
import 'perfil.dart';
import 'pessoa.dart';
import 'serie.dart';

// Resumo da série aberta (GET /series/{slug}?episodios=0): o placar do Início
class _Aberta {
  final String slug;
  final String estado;
  final bool podeRemover;
  final Progresso tu;
  final List<(Utilizador, Progresso)> companheiros;
  final String resumo;

  const _Aberta(this.slug, this.estado, this.podeRemover, this.tu, this.companheiros, this.resumo);

  // Alguém já começou a série? (senão o placar dá lugar a "Ainda não começaste…")
  bool get comecou => tu.vistos > 0 || companheiros.any((c) => c.$2.vistos > 0);
}

class EcraInicio extends StatefulWidget {
  const EcraInicio({super.key});

  @override
  State<EcraInicio> createState() => _EcraInicioState();
}

class _EcraInicioState extends State<EcraInicio> {
  // Dados do GET /inicio
  Utilizador? _user;
  Utilizador? _par;
  bool _esperaPar = false;
  Ultimo? _ultimoPar;
  List<ItemBiblioteca> _biblioteca = [];
  List<ItemBiblioteca> _doPar = [];
  List<ConviteSerie> _convites = [];
  List<Utilizador> _ligados = [];
  Map<String, dynamic> _jaCa = {};
  int _pedidosAmigos = 0;

  String? _selecionada; // slug da série aberta
  _Aberta? _aberta; // o resumo dela
  bool _aCarregar = true;
  bool _ocupado = false; // uma ação a decorrer (bloqueia os botões)
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
    Sessao.instancia.carregarEu().catchError((_) {}); // paleta e definições (falhar aqui não faz mal)
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/inicio');
      if (!mounted) return;
      setState(() {
        _user = Utilizador.deJson(d['user'] as Map<String, dynamic>);
        _par = Utilizador.talvez(d['parceiro']);
        _esperaPar = d['esperaPar'] == true;
        _ultimoPar = Ultimo.talvez(d['ultimoPar']);
        _biblioteca = ItemBiblioteca.lista(d['biblioteca']);
        _doPar = ItemBiblioteca.lista(d['doPar']);
        _convites = ((d['convites'] as List?) ?? []).map((c) => ConviteSerie.deJson(c as Map<String, dynamic>)).toList();
        _ligados = Utilizador.lista(d['ligados']);
        _jaCa = (d['jaCa'] as Map<String, dynamic>?) ?? {};
        _pedidosAmigos = (d['pedidosAmigos'] as num?)?.toInt() ?? 0;
        // Mantém a série escolhida se ainda estiver na biblioteca; senão a sugerida pela API
        if (_selecionada == null || !_biblioteca.any((i) => i.serie.slug == _selecionada)) {
          _selecionada = d['serieAtual'] as String? ?? (_biblioteca.isEmpty ? null : _biblioteca.first.serie.slug);
        }
        _erro = null;
        _aCarregar = false;
      });
      await _carregarAberta();
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  // Placar da série aberta (sem a lista de episódios)
  Future<void> _carregarAberta() async {
    final slug = _selecionada;
    if (slug == null) {
      setState(() => _aberta = null);
      return;
    }
    try {
      final d = await Api.instancia.get('/series/$slug?episodios=0');
      if (!mounted || slug != _selecionada) return; // entretanto escolheu outra
      setState(() {
        _aberta = _Aberta(
          slug,
          d['estado'] as String? ?? 'a_ver',
          d['podeRemover'] == true,
          Progresso.deJson(d['tu'] as Map<String, dynamic>),
          ((d['companheiros'] as List?) ?? []).map((c) {
            final m = c as Map<String, dynamic>;
            return (Utilizador.deJson(m['user'] as Map<String, dynamic>), Progresso.deJson(m['progresso'] as Map<String, dynamic>));
          }).toList(),
          d['resumo'] as String? ?? '',
        );
      });
    } on ApiErro {
      // sem placar: o resto do Início continua a funcionar
    }
  }

  // Escolher outra capa: muda já o realce e vai buscar o placar dela
  void _escolher(ItemBiblioteca i) {
    if (i.serie.slug == _selecionada) return;
    setState(() {
      _selecionada = i.serie.slug;
      _aberta = null;
    });
    _carregarAberta();
  }

  // Abre um ecrã e, ao voltar, recarrega (pode ter havido episódios marcados, séries novas, …)
  Future<void> _abrir(Widget ecra) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => ecra));
    if (mounted) _carregar();
  }

  // Corre uma ação da API, mostra a mensagem e recarrega o Início
  Future<void> _acao(Future<Map<String, dynamic>> Function() pedido) async {
    if (_ocupado) return;
    setState(() => _ocupado = true);
    try {
      final d = await pedido();
      if (mounted) aviso(context, d['mensagem'] as String? ?? 'Feito.');
      await _carregar();
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _ocupado = false);
    }
  }

  ItemBiblioteca? get _itemAtual {
    for (final i in _biblioteca) {
      if (i.serie.slug == _selecionada) return i;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final escuro = Theme.of(context).brightness == Brightness.dark;
    final atual = _itemAtual;

    return Scaffold(
      body: Fundo(
        acento: atual?.serie.cor,
        child: SafeArea(
          child: _aCarregar
              ? const Carregando()
              : (_erro != null && _user == null)
                  ? ErroComRetry(
                      mensagem: _erro!,
                      aoTentar: () {
                        setState(() => _aCarregar = true);
                        _carregar();
                      },
                    )
                  : RefreshIndicator(
                      onRefresh: _carregar,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(20, 14, 20, 28),
                        children: [
                          _topo(p, escuro),
                          const SizedBox(height: 14),
                          ..._conteudo(p, atual),
                        ],
                      ),
                    ),
        ),
      ),
    );
  }

  // Topo: logótipo e "anime a dois"; à direita estatísticas, amigos, tema e o teu avatar
  Widget _topo(Paleta p, bool escuro) {
    final atual = _itemAtual;
    return Row(
      children: [
        Image.asset('assets/icon/icon.png', width: 34, height: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('anime a dois', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700))),
        BotaoRedondo(
          tooltip: 'Estatísticas',
          icone: const Icon(Icons.bar_chart_rounded),
          onTap: atual == null ? null : () => _abrir(EcraEstatisticas(slug: atual.serie.slug)),
        ),
        const SizedBox(width: 8),
        Badge(
          isLabelVisible: _pedidosAmigos > 0,
          smallSize: 10,
          backgroundColor: p.parTxt,
          child: BotaoRedondo(
            tooltip: 'Amigos',
            icone: const Icon(Icons.people_outline_rounded),
            onTap: () => _abrir(const EcraAmigos()),
          ),
        ),
        const SizedBox(width: 8),
        BotaoRedondo(
          tooltip: escuro ? 'Tema claro' : 'Tema escuro',
          icone: Icon(escuro ? Icons.light_mode_outlined : Icons.dark_mode_outlined),
          onTap: Sessao.instancia.alternarTema,
        ),
        const SizedBox(width: 8),
        // O teu avatar abre o perfil
        GestureDetector(
          onTap: () => _abrir(const EcraPerfil()),
          child: Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: p.vidroBorda, width: 2),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .12), blurRadius: 16, offset: const Offset(0, 6))],
            ),
            child: Avatar(user: _user, cor: p.tu, tamanho: 42),
          ),
        ),
      ],
    );
  }

  List<Widget> _conteudo(Paleta p, ItemBiblioteca? atual) {
    final textos = Theme.of(context).textTheme;
    return [
      // ---------- Último episódio do par ----------
      if (_par != null && _ultimoPar != null) ...[
        _cartaoUltimo(p, _par!, _ultimoPar!),
        const SizedBox(height: 14),
      ] else if (_esperaPar) ...[
        Vidro(
          padding: const EdgeInsets.all(20),
          child: Text('O teu par ainda não tem conta. Quando criar, aparece aqui ao teu lado.', style: TextStyle(color: p.suave)),
        ),
        const SizedBox(height: 14),
      ],

      // ---------- A tua biblioteca ----------
      Padding(
        padding: const EdgeInsets.only(left: 4),
        child: Row(
          children: [
            const Expanded(child: Text('A tua biblioteca', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500))),
            BotaoRedondo(
              tooltip: 'Adicionar série',
              tamanho: 38,
              icone: const Icon(Icons.add_rounded),
              onTap: () => _abrir(EcraAdicionar(ligados: _ligados, jaCa: _jaCa)),
            ),
          ],
        ),
      ),
      const SizedBox(height: 10),
      if (_biblioteca.isEmpty)
        Vidro(
          padding: const EdgeInsets.all(20),
          child: Text('Ainda não tens séries. Toca em + para procurar uma, ou junta-te a uma série de um amigo.',
              style: TextStyle(color: p.suave)),
        )
      else
        _fila(p, _biblioteca, _escolher, selecionada: _selecionada),

      // ---------- Quero ver contigo ----------
      if (_convites.isNotEmpty) ...[
        const SizedBox(height: 14),
        _convitesPainel(p),
      ],

      // ---------- A série aberta ----------
      if (atual != null) ...[
        const SizedBox(height: 14),
        ..._serieAberta(p, atual),
      ],

      // ---------- <par> está a ver (só as dele que não tens) ----------
      if (_par != null && _doPar.isNotEmpty) ...[
        const SizedBox(height: 18),
        Padding(
          padding: const EdgeInsets.only(left: 4),
          child: Text('${_par!.nome} está a ver', style: textos.bodyMedium?.copyWith(fontSize: 14, color: p.suave)),
        ),
        const SizedBox(height: 10),
        _fila(p, _doPar, (i) => _folhaJuntar(p, i, _par!), dono: _par),
      ],
    ];
  }

  // "Andreia viu há 2 dias / Naruto · episódio 4" com o número grande à direita
  Widget _cartaoUltimo(Paleta p, Utilizador par, Ultimo u) {
    final tens = _biblioteca.any((i) => i.serie.slug == u.serie);
    final nomeCurto = _biblioteca.where((i) => i.serie.slug == u.serie).map((i) => i.serie.nomeCurto).firstOrNull ?? u.serieNome;
    return Vidro(
      padding: const EdgeInsets.all(20),
      // Se a série também é tua, abre-a nesse episódio; senão é só para espreitar (a foto abre o perfil)
      onTap: tens ? () => _abrir(EcraSerie(slug: u.serie, episodio: u.numero)) : null,
      child: Row(
        children: [
          GestureDetector(
            onTap: () => _abrir(EcraPessoa(id: par.id)),
            child: Avatar(user: par, cor: p.par, tamanho: 44),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${par.nome} viu ${u.quando}', style: TextStyle(fontSize: 13, color: p.suave)),
                const SizedBox(height: 2),
                Text('$nomeCurto · episódio ${u.numero}',
                    maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          Text('${u.numero}', style: TextStyle(fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: -.6, color: p.parTxt)),
        ],
      ),
    );
  }

  // Fila de capas (desliza para os lados). A tua: avatares de quem vê contigo no canto e duas barrinhas;
  // a do par: capas mais pequenas, uma barrinha (a dele).
  Widget _fila(Paleta p, List<ItemBiblioteca> itens, void Function(ItemBiblioteca) aoTocar,
      {String? selecionada, Utilizador? dono}) {
    final doPar = dono != null;
    final largura = doPar ? 84.0 : 104.0;
    return SizedBox(
      height: largura * 1.5 + (doPar ? 44 : 58),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        clipBehavior: Clip.none,
        itemCount: itens.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, n) {
          final i = itens[n];
          final escolhida = i.serie.slug == selecionada;
          final selo = i.estado == 'acabado' ? '✓ acabado' : (i.estado == 'pausa' ? 'em pausa' : '');
          return GestureDetector(
            onTap: () => aoTocar(i),
            child: SizedBox(
              width: largura,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Capa com aro na cor da série quando é a aberta
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(doPar ? 16 : 20),
                      border: Border.all(color: escolhida ? i.serie.cor : Colors.transparent, width: 2.5),
                    ),
                    child: Stack(
                      children: [
                        Opacity(
                          opacity: i.estado == 'acabado' ? .8 : 1, // acabadas: mais apagadas
                          child: Capa(serie: i.serie, largura: largura - 9, raio: doPar ? 14 : 18),
                        ),
                        // Vista com alguém: o teu avatar e o do primeiro companheiro (+N) no canto de cima
                        if (!doPar && i.companheiros.isNotEmpty)
                          Positioned(
                            top: 6,
                            right: 6,
                            child: Row(
                              children: [
                                AvataresSobrepostos(
                                  tamanho: 18,
                                  pessoas: [(_user, p.tu, true), (i.companheiros.first.user, p.par, true)],
                                ),
                                if (i.companheiros.length > 1)
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                    decoration: BoxDecoration(color: Colors.black.withValues(alpha: .65), borderRadius: BorderRadius.circular(9)),
                                    child: Text('+${i.companheiros.length - 1}',
                                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                                  ),
                              ],
                            ),
                          ),
                        // Selo "em pausa" / "✓ acabado"
                        if (selo.isNotEmpty)
                          Positioned(
                            left: 6,
                            bottom: 6,
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(color: const Color(0xB814161E), borderRadius: BorderRadius.circular(10)),
                              child: Text(selo, style: TextStyle(color: Colors.white, fontSize: doPar ? 9.5 : 10.5, fontWeight: FontWeight.w500)),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 6),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: Text(i.serie.nomeCurto,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12.5, fontWeight: escolhida ? FontWeight.w500 : FontWeight.w400)),
                  ),
                  const SizedBox(height: 6),
                  // Barrinhas: a tua e a do primeiro companheiro (na fila do par, só a dele)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: Column(
                      children: [
                        BarraProgresso(pct: i.pct, cor: doPar ? p.par : p.tu, altura: 4),
                        if (!doPar && i.companheiros.isNotEmpty) ...[
                          const SizedBox(height: 3),
                          BarraProgresso(pct: i.companheiros.first.pct, cor: p.par, altura: 4),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  // A série aberta: placar, estado, "Vês com…", ações e "Continuar · episódio N"
  List<Widget> _serieAberta(Paleta p, ItemBiblioteca i) {
    final s = i.serie;
    final a = _aberta?.slug == s.slug ? _aberta : null; // placar só quando chegou o desta série
    final comp = a?.companheiros ?? [];
    final nomes = comp.map((c) => c.$1.nome).toList();
    final titulo = comp.isEmpty
        ? 'O teu progresso em ${s.nomeCurto}'
        : 'Tu e ${comp.length == 1 ? nomes.first : '${comp.length} pessoas'} em ${s.nomeCurto}';

    // Convites que enviaste para esta série (à espera de resposta)
    final enviados = _convites.where((c) => !c.recebido && c.serie.slug == s.slug).toList();
    // Quem ainda pode ser convidado: ligados que não vêem a série contigo nem têm convite pendente
    final convidaveis = _ligados
        .where((l) => !comp.any((c) => c.$1.id == l.id) && !enviados.any((e) => e.para.id == l.id))
        .toList();

    // Continuar: se já começaste e ainda falta, leva ao episódio seguinte ao teu mais avançado
    final seguinte = (a != null && a.tu.vistos > 0 && a.tu.posicao < s.totalEpisodios) ? a.tu.posicao + 1 : null;

    return [
      // ---------- Placar (o cartão todo abre a série) ----------
      if (a == null)
        const Vidro(padding: EdgeInsets.all(28), child: Center(child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))))
      else if (a.comecou)
        Vidro(
          padding: const EdgeInsets.all(20),
          onTap: () => _abrir(EcraSerie(slug: s.slug)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Expanded(child: Text(titulo, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500))),
                  Text('${s.totalEpisodios} ep.', style: TextStyle(fontSize: 12.5, color: p.suave)),
                  Icon(Icons.chevron_right_rounded, size: 18, color: p.suave),
                ],
              ),
              const SizedBox(height: 18),
              _linhaVs(p, 'Tu', a.tu, true),
              for (final c in comp) ...[
                const SizedBox(height: 16),
                _linhaVs(p, c.$1.nome, c.$2, false),
              ],
              const SizedBox(height: 18),
              Divider(height: 1, color: p.linha),
              const SizedBox(height: 14),
              Text(a.resumo, style: TextStyle(fontSize: 13, color: p.suave)),
            ],
          ),
        )
      else
        Vidro(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${comp.isEmpty ? 'Ainda não começaste' : 'Ainda ninguém começou'} ${s.nomeCurto}'),
              const SizedBox(height: 4),
              Text('Marca o primeiro episódio e o placar aparece aqui.', style: TextStyle(fontSize: 13, color: p.suave)),
            ],
          ),
        ),
      const SizedBox(height: 14),

      // ---------- O TEU estado desta série (o dos outros é deles) ----------
      Vidro(
        raio: 22,
        padding: const EdgeInsets.all(4),
        child: Row(
          children: [
            for (final (valor, texto) in const [('a_ver', 'A ver'), ('pausa', 'Em pausa'), ('acabado', 'Acabado')])
              Expanded(
                child: GestureDetector(
                  onTap: (_ocupado || (a?.estado ?? i.estado) == valor)
                      ? null
                      : () => _acao(() => Api.instancia.put('/series/${s.slug}/estado', {'estado': valor})),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    height: 36,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: (a?.estado ?? i.estado) == valor ? s.cor : Colors.transparent,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Text(
                      texto,
                      style: TextStyle(
                        fontSize: 13,
                        color: (a?.estado ?? i.estado) == valor ? p.noPastel : p.suave,
                        fontWeight: (a?.estado ?? i.estado) == valor ? FontWeight.w500 : FontWeight.w400,
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),

      // ---------- Vês com… · Deixar de ver juntos · Ver com… · Tirar ----------
      if (_ligados.isNotEmpty || comp.isNotEmpty || (a?.podeRemover ?? false)) ...[
        const SizedBox(height: 6),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 16,
          runSpacing: 2,
          children: [
            for (final c in comp) ...[
              Text('Vês com ${c.$1.nome}', style: TextStyle(fontSize: 13, color: p.suave)),
              _linkSuave(p, 'Deixar de ver juntos', () async {
                if (await confirmar(context, 'Deixar de ver juntos?',
                    'Deixar de ver ${s.nomeCurto} com ${c.$1.nome}? Cada um fica com o seu progresso.')) {
                  _acao(() => Api.instancia.delete('/series/${s.slug}/juntos/${c.$1.id}'));
                }
              }),
            ],
            for (final e in enviados)
              Text('Convite enviado · à espera de ${e.para.nome}', style: TextStyle(fontSize: 12.5, color: p.parTxt)),
            if (convidaveis.length == 1)
              _botaoPequeno(p, 'Ver com ${convidaveis.first.nome}',
                  () => _acao(() => Api.instancia.post('/series/${s.slug}/convites', {'com': convidaveis.first.id})))
            else if (convidaveis.length > 1)
              _botaoPequeno(p, 'Ver com…', () => _folhaConvidar(p, s, convidaveis)),
            if (a?.podeRemover ?? false)
              _linkSuave(p, 'Tirar da biblioteca', () async {
                if (await confirmar(context, 'Tirar ${s.nomeCurto}?', 'Tirar ${s.nomeCurto} da tua biblioteca?', sim: 'Tirar')) {
                  _acao(() => Api.instancia.delete('/series/${s.slug}'));
                }
              }),
          ],
        ),
      ],
      const SizedBox(height: 10),

      // ---------- Continuar · episódio N ----------
      SizedBox(
        height: 52,
        child: FilledButton(
          onPressed: () => _abrir(EcraSerie(slug: s.slug, episodio: seguinte)),
          style: FilledButton.styleFrom(padding: EdgeInsets.zero),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(seguinte != null ? 'Continuar · episódio $seguinte' : 'Ver episódios de ${s.nomeCurto}',
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
              const SizedBox(width: 6),
              const Icon(Icons.chevron_right_rounded, size: 18),
            ],
          ),
        ),
      ),
    ];
  }

  // Uma linha do placar: "Tu" à esquerda, "50 · 23%" à direita (o número a negrito na cor da pessoa), barra por baixo
  Widget _linhaVs(Paleta p, String nome, Progresso pr, bool ehTu) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(nome, style: const TextStyle(fontSize: 13))),
            Text.rich(TextSpan(
              style: TextStyle(fontSize: 13, color: p.suave),
              children: [
                TextSpan(text: '${pr.posicao}', style: TextStyle(fontWeight: FontWeight.w700, color: ehTu ? p.tuTxt : p.parTxt)),
                TextSpan(text: ' · ${pr.pct}%'),
              ],
            )),
          ],
        ),
        const SizedBox(height: 8),
        BarraProgresso(pct: pr.pct, cor: ehTu ? p.tu : p.par, altura: 6),
      ],
    );
  }

  // Link sublinhado e discreto ("Deixar de ver juntos", "Agora não", …)
  Widget _linkSuave(Paleta p, String texto, VoidCallback onTap) => InkWell(
        onTap: _ocupado ? null : onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Text(texto, style: TextStyle(fontSize: 13, color: p.suave, decoration: TextDecoration.underline, decorationColor: p.suave)),
        ),
      );

  // Botão pequeno (secundário): "Ver com X"
  Widget _botaoPequeno(Paleta p, String texto, VoidCallback onTap, {bool cheio = false}) => SizedBox(
        height: 34,
        child: cheio
            ? FilledButton(
                onPressed: _ocupado ? null : onTap,
                style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 16), textStyle: const TextStyle(fontSize: 13)),
                child: Text(texto),
              )
            : OutlinedButton(
                onPressed: _ocupado ? null : onTap,
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  backgroundColor: p.campo,
                  side: BorderSide(color: p.linha),
                  textStyle: const TextStyle(fontSize: 13),
                ),
                child: Text(texto),
              ),
      );

  // Quero ver contigo: os convites recebidos ("Bora ver" / "Agora não") e os enviados ("Cancelar")
  Widget _convitesPainel(Paleta p) {
    final n = _convites.length;
    return Vidro(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              const Expanded(child: Text('Quero ver contigo', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500))),
              Text('$n ${n == 1 ? 'convite' : 'convites'}', style: TextStyle(fontSize: 12.5, color: p.suave)),
            ],
          ),
          const SizedBox(height: 8),
          for (var k = 0; k < n; k++) ...[
            if (k > 0) Divider(height: 16, color: p.linha),
            _proposta(p, _convites[k]),
          ],
        ],
      ),
    );
  }

  Widget _proposta(Paleta p, ConviteSerie c) {
    final s = c.serie;
    final info = [
      c.recebido ? '${c.de.nome} convidou-te' : 'convidaste ${c.para.nome}',
      '${s.totalEpisodios} ep.',
      if (s.tipo != null) s.tipo!,
      if (s.anos != null) s.anos!,
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Capa(serie: s, largura: 52, raio: 10),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(s.nome, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
                const SizedBox(height: 2),
                Text(info, style: TextStyle(fontSize: 12, color: p.suave)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 12,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: c.recebido
                      ? [
                          _botaoPequeno(p, 'Bora ver',
                              () => _acao(() => Api.instancia.post('/series/${s.slug}/convites/aceitar', {'de': c.de.id})),
                              cheio: true),
                          _linkSuave(p, 'Agora não', () async {
                            if (await confirmar(context, 'Recusar ${s.nomeCurto}?', 'O convite de ${c.de.nome} desaparece.', sim: 'Recusar')) {
                              _acao(() => Api.instancia.delete('/series/${s.slug}/convites/${c.de.id}'));
                            }
                          }),
                        ]
                      : [
                          Text('à espera de ${c.para.nome}', style: TextStyle(fontSize: 12.5, color: p.parTxt)),
                          _linkSuave(p, 'Cancelar', () async {
                            if (await confirmar(context, 'Cancelar o convite?', 'Cancelar o convite de ${s.nomeCurto}?', sim: 'Cancelar convite')) {
                              _acao(() => Api.instancia.delete('/series/${s.slug}/convites/${c.para.id}'));
                            }
                          }),
                        ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Escolher com quem ver esta série (par ou amigo) → convite "Quero ver contigo"
  Future<void> _folhaConvidar(Paleta p, Serie s, List<Utilizador> pessoas) async {
    final escolhido = await showModalBottomSheet<Utilizador>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text('Ver ${s.nomeCurto} com…', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w500)),
            ),
            for (final u in pessoas)
              ListTile(
                leading: Avatar(user: u, cor: p.par),
                title: Text(u.nome),
                trailing: const Text('Convidar'),
                onTap: () => Navigator.pop(ctx, u),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
              child: Text('A pessoa recebe o convite e, se aceitar, passam a ver a série juntos.',
                  style: TextStyle(fontSize: 13, color: p.suave)),
            ),
          ],
        ),
      ),
    );
    if (escolhido != null) {
      _acao(() => Api.instancia.post('/series/${s.slug}/convites', {'com': escolhido.id}));
    }
  }

  // Série de outra pessoa que ainda não tens: ver com ela, ou só para ti
  Future<void> _folhaJuntar(Paleta p, ItemBiblioteca i, Utilizador dono) async {
    final s = i.serie;
    final escolha = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Capa(serie: s, largura: 64, raio: 12),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(s.nome, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 4),
                        Text('${dono.nome} · ${i.estadoTexto} · ${i.pct}%', style: TextStyle(fontSize: 13, color: p.suave)),
                        const SizedBox(height: 8),
                        BarraProgresso(pct: i.pct, cor: p.par, altura: 6),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: () => Navigator.pop(ctx, 'com'), child: Text('Ver com ${dono.nome}')),
              const SizedBox(height: 8),
              OutlinedButton(onPressed: () => Navigator.pop(ctx, 'so'), child: const Text('Adicionar só para mim')),
            ],
          ),
        ),
      ),
    );
    if (escolha == null) return;
    _acao(() => Api.instancia.post('/series/${s.slug}/juntar', escolha == 'com' ? {'com': dono.id} : {}));
  }
}
