// Adicionar série: pesquisa no AniList (pelo telemóvel, como no site), escolhe e entra na tua biblioteca.
// "Ver com…" adiciona e convida o par ou um amigo ("Quero ver contigo").
// Os episódios vêm do Jikan (com fillers) ou do Kitsu (só títulos) e a API do site valida tudo.

import 'dart:async';

import 'package:flutter/material.dart';

import '../api/anime.dart';
import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

class EcraAdicionar extends StatefulWidget {
  // Par + amigos (a quem se pode propor a série) e mal_id → 'biblioteca' | 'convite'.
  // Vindo do "+" da barra de baixo não há estes dados: o ecrã vai buscá-los ao GET /inicio.
  final List<Utilizador>? ligados;
  final Map<String, dynamic>? jaCa;

  const EcraAdicionar({super.key, this.ligados, this.jaCa});

  @override
  State<EcraAdicionar> createState() => _EcraAdicionarState();
}

class _EcraAdicionarState extends State<EcraAdicionar> {
  final _texto = TextEditingController();
  Timer? _espera; // "parou de escrever"
  int _pedido = 0; // número do último pedido: respostas antigas são ignoradas

  List<ResultadoAnime> _resultados = [];
  String _estado = 'Escreve o nome de um anime (em inglês ou japonês).';
  bool _erro = false;

  int? _aAdicionar; // mal_id a ser adicionado (mostra o progresso nesse cartão)
  String _progresso = '';

  late List<Utilizador> _ligados = widget.ligados ?? [];
  late Map<String, dynamic> _jaCa = widget.jaCa ?? {};

  @override
  void initState() {
    super.initState();
    if (widget.ligados == null || widget.jaCa == null) _carregarContexto();
  }

  // A quem se pode propor e o que já está na biblioteca (para marcar os resultados)
  Future<void> _carregarContexto() async {
    try {
      final d = await Api.instancia.get('/inicio');
      if (!mounted) return;
      setState(() {
        _ligados = Utilizador.lista(d['ligados']);
        _jaCa = (d['jaCa'] as Map<String, dynamic>?) ?? {};
      });
    } on ApiErro {
      // sem isto a pesquisa funciona na mesma (só sem o "Ver com…" e as marcas)
    }
  }

  @override
  void dispose() {
    _espera?.cancel();
    _texto.dispose();
    super.dispose();
  }

  // Pesquisa 350 ms depois de parar de escrever
  void _escreveu(String q) {
    _espera?.cancel();
    if (q.trim().length < 2) {
      setState(() {
        _pedido++;
        _resultados = [];
        _estado = 'Escreve o nome de um anime (em inglês ou japonês).';
        _erro = false;
      });
      return;
    }
    _espera = Timer(const Duration(milliseconds: 350), () => _pesquisar(q.trim()));
  }

  Future<void> _pesquisar(String q) async {
    final meu = ++_pedido;
    setState(() {
      _estado = 'A procurar…';
      _erro = false;
    });
    try {
      final r = await Anime.pesquisar(q);
      if (!mounted || meu != _pedido) return; // entretanto escreveu outra coisa
      setState(() {
        _resultados = r;
        _estado = r.isEmpty ? 'Nada encontrado. Experimenta o nome em inglês ou japonês.' : '';
      });
    } on ApiErro catch (e) {
      if (!mounted || meu != _pedido) return;
      setState(() {
        _estado = e.mensagem;
        _erro = true;
      });
    }
  }

  // Busca os episódios e envia tudo à API; com "com" fica também o convite
  Future<void> _adicionar(ResultadoAnime r, {Utilizador? com}) async {
    if (_aAdicionar != null) return;
    setState(() {
      _aAdicionar = r.malId;
      _progresso = 'A buscar os episódios…';
    });
    try {
      final eps = await Anime.episodios(r.malId, aoAvancar: (n) {
        if (mounted) setState(() => _progresso = 'A buscar os episódios… $n');
      });
      if (mounted) setState(() => _progresso = 'A guardar…');
      final d = await Api.instancia.post('/biblioteca', {
        'mal_id': r.malId,
        'info': r.paraInfo(),
        'episodios': eps.lista,
        'fonte': eps.fonte,
        'modo': com == null ? 'ver' : 'propor',
        if (com != null) 'com': com.id,
      });
      if (!mounted) return;
      aviso(context, d['mensagem'] as String? ?? 'Adicionada.');
      Navigator.of(context).pop(); // volta ao Início, que recarrega
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aAdicionar = null);
    }
  }

  // Escolher com quem ver (par ou amigo)
  Future<void> _escolherCom(ResultadoAnime r) async {
    final p = Paleta.de(context);
    final escolhido = await showModalBottomSheet<Utilizador>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text('Ver ${r.nome} com…', style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
            for (final u in _ligados)
              ListTile(
                leading: Avatar(user: u, cor: p.par),
                title: Text(u.nome),
                subtitle: Text('@${u.username}'),
                onTap: () => Navigator.pop(ctx, u),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (escolhido != null) _adicionar(r, com: escolhido);
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
                padding: const EdgeInsets.fromLTRB(4, 6, 16, 6),
                child: Row(
                  children: [
                    BackButton(onPressed: _aAdicionar == null ? () => Navigator.of(context).pop() : null),
                    Expanded(
                      child: TextField(
                        controller: _texto,
                        autofocus: true,
                        textInputAction: TextInputAction.search,
                        decoration: const InputDecoration(hintText: 'Procurar anime…', prefixIcon: Icon(Icons.search_rounded)),
                        onChanged: _escreveu,
                        onSubmitted: (q) {
                          _espera?.cancel();
                          if (q.trim().length >= 2) _pesquisar(q.trim());
                        },
                      ),
                    ),
                  ],
                ),
              ),
              if (_estado.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(_estado, textAlign: TextAlign.center, style: TextStyle(color: _erro ? p.erro : p.suave)),
                ),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                  itemCount: _resultados.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, n) => _cartao(p, _resultados[n]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Um resultado: capa, nome, resumo e os botões (ou "já está na tua biblioteca")
  Widget _cartao(Paleta p, ResultadoAnime r) {
    final textos = Theme.of(context).textTheme;
    final situacao = _jaCa['${r.malId}'] as String?;
    final esteAAdicionar = _aAdicionar == r.malId;
    final serieFalsa = Serie(slug: '', nome: r.nome, nomeCurto: r.nome, totalEpisodios: r.episodios ?? 0, capa: r.capa, cor: p.par);

    return Vidro(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Capa(serie: serieFalsa, largura: 64, raio: 12),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(r.nome, style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                if (r.resumo.isNotEmpty) Text(r.resumo, style: textos.bodySmall?.copyWith(color: p.suave)),
                const SizedBox(height: 8),
                if (situacao == 'biblioteca')
                  Text('Já está na tua biblioteca', style: textos.labelMedium?.copyWith(color: p.tuTxt, fontWeight: FontWeight.w700))
                else if (situacao == 'convite')
                  Text('Tens um convite para esta: aceita-o no Início',
                      style: textos.labelMedium?.copyWith(color: p.parTxt, fontWeight: FontWeight.w700))
                else if (esteAAdicionar)
                  Row(
                    children: [
                      const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_progresso, style: textos.labelMedium)),
                    ],
                  )
                else
                  Wrap(
                    spacing: 8,
                    children: [
                      FilledButton(
                        onPressed: _aAdicionar == null ? () => _adicionar(r) : null,
                        child: const Text('Adicionar'),
                      ),
                      if (_ligados.isNotEmpty)
                        OutlinedButton(
                          onPressed: _aAdicionar == null ? () => _escolherCom(r) : null,
                          child: const Text('Ver com…'),
                        ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
