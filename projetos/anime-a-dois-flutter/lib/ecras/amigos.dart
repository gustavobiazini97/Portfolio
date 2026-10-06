// Amigos (igual ao site): a lista, o link de convite para quem ainda não tem conta, o pedido pelo
// utilizador exato para quem já tem, e aceitar, recusar ou desfazer. Tocar num amigo abre o perfil dele.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../config.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'pessoa.dart';

class EcraAmigos extends StatefulWidget {
  const EcraAmigos({super.key});

  @override
  State<EcraAmigos> createState() => _EcraAmigosState();
}

class _EcraAmigosState extends State<EcraAmigos> {
  final _username = TextEditingController();

  Utilizador? _par;
  List<Utilizador> _amigos = [];
  List<Utilizador> _recebidos = [];
  List<Utilizador> _enviados = [];
  ConviteAmigo? _convite;
  int _validade = 7;

  bool _aCarregar = true;
  bool _ocupado = false;
  String? _erro;

  @override
  void initState() {
    super.initState();
    _carregar();
  }

  @override
  void dispose() {
    _username.dispose();
    super.dispose();
  }

  Future<void> _carregar() async {
    try {
      final d = await Api.instancia.get('/amigos');
      if (!mounted) return;
      setState(() {
        _par = Utilizador.talvez(d['parceiro']);
        _amigos = Utilizador.lista(d['amigos']);
        _recebidos = Utilizador.lista(d['recebidos']);
        _enviados = Utilizador.lista(d['enviados']);
        _convite = ConviteAmigo.talvez(d['convite']);
        _validade = (d['validadeDias'] as num?)?.toInt() ?? 7;
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

  // Corre uma ação, mostra a mensagem e recarrega
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

  // Mensagem do convite: o link para instalar a app e o código para colar em "Tenho um convite".
  // A pessoa pode colar a mensagem inteira: a app e a API encontram o código lá dentro.
  String _mensagem() => 'Vem ver anime comigo no Anime a Dois!\n'
      '1. Instala a app: $linkApp\n'
      '2. Abre-a, toca em «Tenho um convite» e cola isto: ${_convite!.codigo}\n'
      '(O convite é de uso único e dura $_validade dias.)';

  // Partilhar pelo menu do Android (WhatsApp, Messenger, SMS…)
  Future<void> _partilhar() async {
    if (_convite == null) return;
    await Share.share(_mensagem(), subject: 'Convite para o Anime a Dois');
  }

  // Copiar a mensagem toda para colar onde quiseres
  Future<void> _copiar() async {
    if (_convite == null) return;
    await Clipboard.setData(ClipboardData(text: _mensagem()));
    if (mounted) aviso(context, 'Convite copiado. Cola-o numa mensagem.');
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
                    Text('Amigos', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              Expanded(
                child: _aCarregar
                    ? const Carregando()
                    : (_erro != null && _amigos.isEmpty && _par == null)
                        ? ErroComRetry(mensagem: _erro!, aoTentar: _carregar)
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
    return RefreshIndicator(
      onRefresh: _carregar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          // ---------- Pedidos recebidos ----------
          if (_recebidos.isNotEmpty) ...[
            Vidro(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const TituloSecao('Pedidos de amizade'),
                  for (final u in _recebidos)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Avatar(user: u, cor: p.par),
                      title: Text(u.nome),
                      subtitle: Text('@${u.username}'),
                      trailing: Wrap(
                        spacing: 4,
                        children: [
                          IconButton.filled(
                            tooltip: 'Aceitar',
                            onPressed: _ocupado ? null : () => _acao(() => Api.instancia.post('/amigos/pedidos/${u.id}/aceitar')),
                            icon: const Icon(Icons.check_rounded),
                          ),
                          IconButton(
                            tooltip: 'Recusar',
                            onPressed: _ocupado ? null : () => _acao(() => Api.instancia.delete('/amigos/pedidos/${u.id}')),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],

          // ---------- O par e os amigos ----------
          Vidro(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const TituloSecao('As tuas pessoas'),
                if (_par != null) _linhaPessoa(p, _par!, 'o teu par', removivel: false),
                for (final u in _amigos) _linhaPessoa(p, u, '@${u.username}', removivel: true),
                if (_par == null && _amigos.isEmpty)
                  Text('Ainda não tens amigos na app. Convida alguém aqui em baixo.', style: TextStyle(color: p.suave)),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // ---------- Pedir por utilizador (para quem já tem conta) ----------
          Vidro(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const TituloSecao('Já tem conta?'),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _username,
                        autocorrect: false,
                        decoration: const InputDecoration(labelText: 'Utilizador exato', prefixText: '@'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    FilledButton(
                      onPressed: _ocupado
                          ? null
                          : () async {
                              final u = _username.text.trim();
                              if (u.isEmpty) return;
                              await _acao(() => Api.instancia.post('/amigos/pedidos', {'username': u}));
                              _username.clear();
                            },
                      child: const Text('Pedir'),
                    ),
                  ],
                ),
                if (_enviados.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text('À espera de resposta', style: textos.labelMedium?.copyWith(color: p.suave)),
                  for (final u in _enviados)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      leading: Avatar(user: u, cor: p.par, tamanho: 30),
                      title: Text(u.nome),
                      trailing: TextButton(
                        onPressed: _ocupado ? null : () => _acao(() => Api.instancia.delete('/amigos/pedidos/${u.id}')),
                        child: const Text('Cancelar'),
                      ),
                    ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),

          // ---------- Link de convite (para quem ainda não tem conta) ----------
          Vidro(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const TituloSecao('Ainda não tem conta?'),
                Text(
                  'Envia-lhe a app e um convite: instala-a, cola o convite e ficam amigos. '
                  'É de uso único e dura $_validade dias.',
                  style: textos.bodySmall?.copyWith(color: p.suave),
                ),
                const SizedBox(height: 12),
                if (_convite != null) ...[
                  // Convite pronto: sem o endereço à vista, só o essencial
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: p.campo, borderRadius: BorderRadius.circular(18), border: Border.all(color: p.linha)),
                    child: Row(
                      children: [
                        Icon(Icons.card_giftcard_rounded, color: p.tuTxt),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Convite pronto', style: TextStyle(fontWeight: FontWeight.w500)),
                              Text('válido até ${_convite!.validoAteCurto}', style: textos.bodySmall?.copyWith(color: p.suave)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _partilhar,
                          icon: const Icon(Icons.share_rounded, size: 18),
                          label: const Text('Partilhar'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton.icon(
                        onPressed: _copiar,
                        icon: const Icon(Icons.copy_rounded, size: 18),
                        label: const Text('Copiar'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Center(
                    child: TextButton(
                      onPressed: _ocupado ? null : () => _acao(() => Api.instancia.post('/amigos/convite')),
                      child: Text('Criar um convite novo', style: TextStyle(color: p.suave, fontSize: 13)),
                    ),
                  ),
                ] else
                  FilledButton(
                    onPressed: _ocupado ? null : () => _acao(() => Api.instancia.post('/amigos/convite')),
                    child: const Text('Criar convite'),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Uma pessoa: tocar abre o perfil; os amigos podem ser removidos (o par não)
  Widget _linhaPessoa(Paleta p, Utilizador u, String detalhe, {required bool removivel}) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Avatar(user: u, cor: p.par),
      title: Text(u.nome),
      subtitle: Text(detalhe),
      onTap: () async {
        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => EcraPessoa(id: u.id)));
        if (mounted) _carregar();
      },
      trailing: removivel
          ? PopupMenuButton<String>(
              onSelected: (_) async {
                if (await confirmar(context, 'Desfazer amizade?', '${u.nome} deixa de ser teu amigo.', sim: 'Desfazer')) {
                  _acao(() => Api.instancia.delete('/amigos/${u.id}'));
                }
              },
              itemBuilder: (_) => const [PopupMenuItem(value: 'remover', child: Text('Desfazer amizade'))],
            )
          : const Icon(Icons.favorite_rounded, size: 18),
    );
  }
}
