// Perfil do teu par ou de um amigo (igual ao site): foto, último episódio e a biblioteca dele.
// Se ele escolheu "só o que vemos juntos", aparecem só as séries que vê contigo.
// Nas séries que ainda não tens dá para te juntares (com ele, ou só para ti).

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'serie.dart';

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
  List<ItemBiblioteca> _biblioteca = [];
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
      setState(() {
        _pessoa = Utilizador.deJson(d['pessoa'] as Map<String, dynamic>);
        _ehPar = d['ehPar'] == true;
        _soJuntos = d['soJuntos'] == true;
        _ultimo = Ultimo.talvez(d['ultimo']);
        _biblioteca = ItemBiblioteca.lista(d['biblioteca']);
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
          const SizedBox(height: 18),
          Text('A biblioteca de ${u.nome}', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 10),
          if (_biblioteca.isEmpty)
            Text('Nada para mostrar por agora.', style: TextStyle(color: p.suave))
          else
            for (final i in _biblioteca) ...[
              _cartaoSerie(p, i, u),
              const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }

  // Uma série dele: capa, estado, a % dele e os botões (abrir, se a tens; juntar, se não)
  Widget _cartaoSerie(Paleta p, ItemBiblioteca i, Utilizador u) {
    final textos = Theme.of(context).textTheme;
    final s = i.serie;
    return Vidro(
      padding: const EdgeInsets.all(12),
      onTap: i.naTua
          ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraSerie(slug: s.slug)))
          : null,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Capa(serie: s, largura: 60, raio: 12),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(s.nomeCurto, style: textos.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                Text(i.estadoTexto, style: textos.labelSmall?.copyWith(color: p.suave)),
                const SizedBox(height: 6),
                LinhaPct(nome: u.nome, pct: i.pct, ehTu: false),
                const SizedBox(height: 8),
                if (i.vesCom)
                  Text('Vês com ${u.nome}', style: textos.labelMedium?.copyWith(color: p.tuTxt, fontWeight: FontWeight.w700))
                else if (i.naTua)
                  Text('Também está na tua biblioteca', style: textos.labelMedium?.copyWith(color: p.suave))
                else
                  Wrap(
                    spacing: 8,
                    children: [
                      FilledButton(
                        onPressed: _ocupado ? null : () => _juntar(s, comEle: true),
                        child: Text('Ver com ${u.nome}'),
                      ),
                      OutlinedButton(
                        onPressed: _ocupado ? null : () => _juntar(s, comEle: false),
                        child: const Text('Só para mim'),
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
