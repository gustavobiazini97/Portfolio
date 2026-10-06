// Início (igual ao do site): o último episódio do par, a tua biblioteca (fila de capas), a série escolhida
// com o Vs, o teu estado e as ações (ver com alguém, deixar de ver juntos, tirar), os convites
// "Quero ver contigo" e a fila "O teu par está a ver".

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../config.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'adicionar.dart';
import 'amigos.dart';
import 'estatisticas.dart';
import 'perfil.dart';
import 'pessoa.dart';
import 'serie.dart';

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

  String? _selecionada; // slug da série aberta no painel do Vs
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
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
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
          child: Column(
            children: [
              // ---------- Barra de topo ----------
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 6, 6),
                child: Row(
                  children: [
                    Image.asset('assets/icon/icon.png', width: 32, height: 32),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(appNome,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    ),
                    IconButton(
                      tooltip: 'Estatísticas',
                      icon: const Icon(Icons.insights_rounded),
                      onPressed: atual == null ? null : () => _abrir(EcraEstatisticas(slug: atual.serie.slug)),
                    ),
                    // Amigos (bolinha se há pedidos por responder)
                    IconButton(
                      tooltip: 'Amigos',
                      icon: Badge(
                        isLabelVisible: _pedidosAmigos > 0,
                        label: Text('$_pedidosAmigos'),
                        backgroundColor: p.parTxt,
                        child: const Icon(Icons.group_rounded),
                      ),
                      onPressed: () => _abrir(const EcraAmigos()),
                    ),
                    IconButton(
                      tooltip: escuro ? 'Tema claro' : 'Tema escuro',
                      icon: Icon(escuro ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
                      onPressed: Sessao.instancia.alternarTema,
                    ),
                    // O teu avatar abre o perfil
                    IconButton(
                      tooltip: 'Perfil',
                      onPressed: () => _abrir(const EcraPerfil()),
                      icon: Avatar(user: _user, cor: p.tu, tamanho: 32),
                    ),
                  ],
                ),
              ),

              Expanded(child: _conteudo(p, atual)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _conteudo(Paleta p, ItemBiblioteca? atual) {
    if (_aCarregar) return const Carregando();
    if (_erro != null && _user == null) {
      return ErroComRetry(
        mensagem: _erro!,
        aoTentar: () {
          setState(() => _aCarregar = true);
          _carregar();
        },
      );
    }

    final textos = Theme.of(context).textTheme;
    final recebidos = _convites.where((c) => c.recebido).toList();
    final enviados = _convites.where((c) => !c.recebido).toList();

    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 6, 16, 28),
        children: [
          Text('Olá, ${_user?.nome ?? ''}', style: textos.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 14),

          // O que o par viu por último (abre a série nesse episódio, se for tua; senão o perfil dele)
          if (_ultimoPar != null && _par != null) ...[
            _cartaoUltimo(p, _par!, _ultimoPar!),
            const SizedBox(height: 14),
          ],

          // Ainda falta o par criar conta
          if (_esperaPar) ...[
            Vidro(
              child: Row(
                children: [
                  Icon(Icons.favorite_border_rounded, color: p.parTxt),
                  const SizedBox(width: 12),
                  const Expanded(child: Text('O teu par ainda não tem conta. Quando criar, aparece aqui ao teu lado.')),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],

          // ---------- A tua biblioteca ----------
          Row(
            children: [
              Expanded(
                child: Text('A tua biblioteca', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
              ),
              FilledButton.tonalIcon(
                onPressed: () => _abrir(EcraAdicionar(ligados: _ligados, jaCa: _jaCa)),
                icon: const Icon(Icons.add_rounded),
                label: const Text('Adicionar'),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (_biblioteca.isEmpty)
            Vidro(
              child: Text(
                'Ainda não tens séries. Toca em Adicionar para procurar uma, ou junta-te a uma série de um amigo.',
                style: TextStyle(color: p.suave),
              ),
            )
          else
            _fila(p, _biblioteca, (i) => setState(() => _selecionada = i.serie.slug), selecionada: _selecionada),

          // ---------- A série escolhida: Vs, estado e ações ----------
          if (atual != null) ...[
            const SizedBox(height: 14),
            _painelSerie(p, atual),
          ],

          // ---------- Quero ver contigo ----------
          if (_convites.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text('Quero ver contigo', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            for (final c in recebidos) _cartaoConvite(p, c),
            for (final c in enviados) _cartaoConvite(p, c),
          ],

          // ---------- O teu par está a ver (só as dele que não tens) ----------
          if (_par != null && _doPar.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text('${_par!.nome} está a ver', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            _fila(p, _doPar, (i) => _folhaJuntar(p, i, _par!), dono: _par),
          ],
        ],
      ),
    );
  }

  // "Andreia viu o episódio 12 · há 2 horas"
  Widget _cartaoUltimo(Paleta p, Utilizador par, Ultimo u) {
    final textos = Theme.of(context).textTheme;
    final tens = _biblioteca.any((i) => i.serie.slug == u.serie);
    return Vidro(
      onTap: () => _abrir(tens ? EcraSerie(slug: u.serie, episodio: u.numero) : EcraPessoa(id: par.id)),
      child: Row(
        children: [
          Avatar(user: par, cor: p.par, tamanho: 44),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${par.nome} viu o episódio ${u.numero}',
                    style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(
                  [u.serieNome, if (u.titulo != null) u.titulo!].join(' · '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: textos.bodySmall?.copyWith(color: p.suave),
                ),
                if (u.quando.isNotEmpty)
                  Text(u.quando, style: textos.labelSmall?.copyWith(color: p.parTxt, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: p.suave),
        ],
      ),
    );
  }

  // Fila de capas que desliza para os lados. Cada capa: estado, a percentagem e a dos companheiros.
  //   dono = de quem é a fila (null = tua); selecionada = slug com aro
  Widget _fila(Paleta p, List<ItemBiblioteca> itens, void Function(ItemBiblioteca) aoTocar,
      {String? selecionada, Utilizador? dono}) {
    final textos = Theme.of(context).textTheme;
    return SizedBox(
      height: 232,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: itens.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, n) {
          final i = itens[n];
          final escolhida = i.serie.slug == selecionada;
          return GestureDetector(
            onTap: () => aoTocar(i),
            child: SizedBox(
              width: 116,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Stack(
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.all(3),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(21),
                          border: Border.all(color: escolhida ? i.serie.cor : Colors.transparent, width: 2.5),
                        ),
                        child: Capa(serie: i.serie, largura: 106),
                      ),
                      // Vista com alguém: o avatar do primeiro companheiro (+N se houver mais)
                      if (i.companheiros.isNotEmpty)
                        Positioned(
                          right: 8,
                          bottom: 8,
                          child: Row(
                            children: [
                              Avatar(user: i.companheiros.first.user, cor: p.par, tamanho: 24),
                              if (i.companheiros.length > 1)
                                Container(
                                  margin: const EdgeInsets.only(left: 3),
                                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                  decoration: BoxDecoration(color: p.par, borderRadius: BorderRadius.circular(10)),
                                  child: Text('+${i.companheiros.length - 1}',
                                      style: TextStyle(color: p.noPastel, fontSize: 11, fontWeight: FontWeight.w800)),
                                ),
                            ],
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(i.serie.nomeCurto,
                      maxLines: 1, overflow: TextOverflow.ellipsis, style: textos.labelLarge?.copyWith(fontWeight: FontWeight.w800)),
                  Text('${i.estadoTexto} · ${i.pct}%', style: textos.labelSmall?.copyWith(color: p.suave)),
                  const SizedBox(height: 4),
                  // Barrinhas: a do dono da fila e a do primeiro companheiro
                  BarraProgresso(pct: i.pct, cor: dono == null ? p.tu : p.par, altura: 4),
                  if (i.companheiros.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    BarraProgresso(pct: i.companheiros.first.pct, cor: dono == null ? p.par : p.tu, altura: 4),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  // Painel da série escolhida: com quem vês, as barras, o teu estado e as ações
  Widget _painelSerie(Paleta p, ItemBiblioteca i) {
    final textos = Theme.of(context).textTheme;
    final s = i.serie;
    final comp = i.companheiros;
    final titulo = comp.isEmpty
        ? '${s.nomeCurto} · só tua'
        : 'Tu e ${comp.length == 1 ? comp.first.user.nome : '${comp.length} pessoas'} em ${s.nomeCurto}';
    // Pessoas ligadas a ti que ainda não vêem esta série contigo
    final porConvidar = _ligados.where((l) => !comp.any((c) => c.user.id == l.id)).toList();

    return Vidro(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Título + abrir a série
          Row(
            children: [
              Expanded(child: Text(titulo, style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
              FilledButton(
                onPressed: () => _abrir(EcraSerie(slug: s.slug)),
                style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
                child: const Text('Abrir'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          LinhaPct(nome: _user?.nome ?? 'Tu', pct: i.pct, ehTu: true),
          for (final c in comp) ...[
            const SizedBox(height: 8),
            LinhaPct(nome: c.user.nome, pct: c.pct, ehTu: false),
          ],
          const SizedBox(height: 14),

          // O TEU estado desta série (o dos outros é deles)
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'a_ver', label: Text('A ver')),
              ButtonSegment(value: 'pausa', label: Text('Em pausa')),
              ButtonSegment(value: 'acabado', label: Text('Acabado')),
            ],
            selected: {i.estado},
            showSelectedIcon: false,
            onSelectionChanged: _ocupado
                ? null
                : (novo) => _acao(() => Api.instancia.put('/series/${s.slug}/estado', {'estado': novo.first})),
          ),
          const SizedBox(height: 10),

          // Ações: ver com alguém, deixar de ver juntos, tirar
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              if (porConvidar.isNotEmpty)
                OutlinedButton.icon(
                  onPressed: _ocupado ? null : () => _folhaConvidar(p, s, porConvidar),
                  icon: const Icon(Icons.person_add_alt_rounded, size: 18),
                  label: const Text('Ver com…'),
                ),
              for (final c in comp)
                TextButton(
                  onPressed: _ocupado
                      ? null
                      : () async {
                          if (await confirmar(context, 'Deixar de ver juntos?',
                              'Tu e ${c.user.nome} passam a ver ${s.nomeCurto} cada um a sua. Ninguém perde o progresso.')) {
                            _acao(() => Api.instancia.delete('/series/${s.slug}/juntos/${c.user.id}'));
                          }
                        },
                  child: Text('Deixar de ver com ${c.user.nome}'),
                ),
              // Só dá para tirar enquanto não marcaste episódios (a API confirma)
              if (i.pct == 0)
                TextButton(
                  onPressed: _ocupado
                      ? null
                      : () async {
                          if (await confirmar(context, 'Tirar ${s.nomeCurto}?', 'Sai da tua biblioteca. A dos outros fica igual.',
                              sim: 'Tirar')) {
                            _acao(() => Api.instancia.delete('/series/${s.slug}'));
                          }
                        },
                  child: Text('Tirar da biblioteca', style: TextStyle(color: p.erro)),
                ),
            ],
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
              child: Text('Ver ${s.nomeCurto} com…', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
            for (final u in pessoas)
              ListTile(
                leading: Avatar(user: u, cor: p.par),
                title: Text(u.nome),
                subtitle: Text(u.id == _par?.id ? 'o teu par' : '@${u.username}'),
                onTap: () => Navigator.pop(ctx, u),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (escolhido != null) {
      _acao(() => Api.instancia.post('/series/${s.slug}/convites', {'com': escolhido.id}));
    }
  }

  // Um convite: recebido (aceitar / recusar) ou enviado (cancelar)
  Widget _cartaoConvite(Paleta p, ConviteSerie c) {
    final textos = Theme.of(context).textTheme;
    final s = c.serie;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Vidro(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Capa(serie: s, largura: 54, raio: 12),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(s.nomeCurto, style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  Text(
                    c.recebido ? '${c.de.nome} quer ver contigo' : 'Convidaste ${c.para.nome}',
                    style: textos.bodySmall?.copyWith(color: p.suave),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: c.recebido
                        ? [
                            FilledButton(
                              onPressed: _ocupado
                                  ? null
                                  : () => _acao(() => Api.instancia.post('/series/${s.slug}/convites/aceitar', {'de': c.de.id})),
                              child: const Text('Aceitar'),
                            ),
                            TextButton(
                              onPressed: _ocupado ? null : () => _acao(() => Api.instancia.delete('/series/${s.slug}/convites/${c.de.id}')),
                              child: const Text('Recusar'),
                            ),
                          ]
                        : [
                            TextButton(
                              onPressed: _ocupado ? null : () => _acao(() => Api.instancia.delete('/series/${s.slug}/convites/${c.para.id}')),
                              child: const Text('Cancelar convite'),
                            ),
                          ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Série de outra pessoa que ainda não tens: ver com ela, ou só para ti
  Future<void> _folhaJuntar(Paleta p, ItemBiblioteca i, Utilizador dono) async {
    final s = i.serie;
    final escolha = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) {
        final textos = Theme.of(ctx).textTheme;
        return SafeArea(
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
                          Text(s.nome, style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                          Text('${dono.nome} · ${i.estadoTexto} · ${i.pct}%', style: textos.bodySmall?.copyWith(color: p.suave)),
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
        );
      },
    );
    if (escolha == null) return;
    _acao(() => Api.instancia.post('/series/${s.slug}/juntar', escolha == 'com' ? {'com': dono.id} : {}));
  }
}
