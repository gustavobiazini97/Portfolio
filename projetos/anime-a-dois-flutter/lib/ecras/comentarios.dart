// Folha de comentários de um episódio (abre por baixo, por cima da pista).
// Os dois veem tudo; os teus ficam à direita e apagam-se com toque longo.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

class FolhaComentarios extends StatefulWidget {
  final Episodio episodio;
  final ValueChanged<int> aoMudar; // novo número de comentários (para o balão do cartão)

  const FolhaComentarios({super.key, required this.episodio, required this.aoMudar});

  @override
  State<FolhaComentarios> createState() => _FolhaComentariosState();
}

class _FolhaComentariosState extends State<FolhaComentarios> {
  final _texto = TextEditingController();
  List<Comentario> _lista = [];
  int _max = 500; // limite de caracteres (vem da API)
  bool _aCarregar = true;
  bool _aEnviar = false;
  String? _erro;

  String get _rota => '/episodios/${widget.episodio.id}/comentarios';

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  @override
  void dispose() {
    _texto.dispose();
    super.dispose();
  }

  // A API devolve sempre a lista completa (também depois de escrever ou apagar)
  void _aplicar(Map<String, dynamic> d) {
    setState(() {
      _lista = (d['comentarios'] as List).map((c) => Comentario.deJson(c as Map<String, dynamic>)).toList();
      _max = (d['max'] as num?)?.toInt() ?? _max;
      _aCarregar = false;
      _erro = null;
    });
    widget.aoMudar(_lista.length);
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get(_rota);
      if (mounted) _aplicar(d);
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() {
        _erro = e.mensagem;
        _aCarregar = false;
      });
    }
  }

  Future<void> _enviar() async {
    final texto = _texto.text.trim();
    if (texto.isEmpty || _aEnviar) return;
    setState(() => _aEnviar = true);
    try {
      final d = await Api.instancia.post(_rota, {'texto': texto});
      if (!mounted) return;
      _texto.clear();
      _aplicar(d);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aEnviar = false);
    }
  }

  // Apagar pede confirmação (não há como desfazer)
  Future<void> _apagar(Comentario c) async {
    final sim = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Apagar comentário?'),
        content: Text(c.texto, maxLines: 4, overflow: TextOverflow.ellipsis),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Apagar')),
        ],
      ),
    );
    if (sim != true) return;
    try {
      final d = await Api.instancia.delete('/comentarios/${c.id}');
      if (mounted) _aplicar(d);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final textos = Theme.of(context).textTheme;
    final ep = widget.episodio;

    return Padding(
      // Sobe com o teclado
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.75),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Cabeçalho
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Episódio ${ep.numero}', style: textos.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  if (ep.titulo != null)
                    Text(ep.titulo!, style: textos.bodySmall?.copyWith(color: p.suave)),
                ],
              ),
            ),

            // Lista
            Flexible(
              child: _aCarregar
                  ? const Padding(padding: EdgeInsets.all(32), child: Carregando())
                  : _erro != null
                      ? Padding(padding: const EdgeInsets.all(24), child: Text(_erro!, textAlign: TextAlign.center))
                      : _lista.isEmpty
                          ? Padding(
                              padding: const EdgeInsets.all(28),
                              child: Text('Ainda ninguém comentou este episódio.',
                                  textAlign: TextAlign.center, style: TextStyle(color: p.suave)),
                            )
                          : ListView.builder(
                              shrinkWrap: true,
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                              itemCount: _lista.length,
                              itemBuilder: (_, i) => _balao(p, _lista[i]),
                            ),
            ),

            // Escrever
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 10, 10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _texto,
                        minLines: 1,
                        maxLines: 4,
                        maxLength: _max,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(hintText: 'Escreve um comentário…', counterText: ''),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton.filled(
                      tooltip: 'Enviar',
                      onPressed: _aEnviar ? null : _enviar,
                      icon: _aEnviar
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.send_rounded),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Um balão: os teus à direita (sálvia), os do par à esquerda (alperce)
  Widget _balao(Paleta p, Comentario c) {
    final textos = Theme.of(context).textTheme;
    final cor = c.meu ? p.tu : p.par;
    final avatar = Avatar(user: c.autor, cor: cor, tamanho: 30);

    final balao = Flexible(
      child: GestureDetector(
        onLongPress: c.meu ? () => _apagar(c) : null,
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          decoration: BoxDecoration(
            color: cor.withValues(alpha: 0.45),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${c.autor.nome} · ${c.quando}',
                  style: textos.labelSmall?.copyWith(fontWeight: FontWeight.w700, color: p.suave)),
              const SizedBox(height: 2),
              Text(c.texto),
            ],
          ),
        ),
      ),
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisAlignment: c.meu ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: c.meu
            ? [const SizedBox(width: 40), balao, const SizedBox(width: 8), avatar]
            : [avatar, const SizedBox(width: 8), balao, const SizedBox(width: 40)],
      ),
    );
  }
}
