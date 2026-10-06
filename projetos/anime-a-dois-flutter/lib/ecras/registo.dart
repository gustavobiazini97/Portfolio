// Criar conta: nome que aparece na app, utilizador e palavra-passe.
// Sem convite é a conta do casal (só enquanto houver lugar); com convite fica amiga de quem convidou.
// As regras (tamanhos, caracteres, convite válido) são validadas na API; aqui só o básico.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';

class EcraRegisto extends StatefulWidget {
  final String? convite; // link ou código do convite (null = conta do casal)

  const EcraRegisto({super.key, this.convite});

  @override
  State<EcraRegisto> createState() => _EcraRegistoState();
}

class _EcraRegistoState extends State<EcraRegisto> {
  final _form = GlobalKey<FormState>();
  final _nome = TextEditingController();
  final _username = TextEditingController();
  final _password = TextEditingController();
  final _confirmar = TextEditingController();
  bool _aCriar = false;

  Utilizador? _quemConvidou; // dono do convite (GET /auth/estado?convite=)
  String? _erroConvite;

  @override
  void initState() {
    super.initState();
    if (widget.convite != null) _verConvite();
  }

  @override
  void dispose() {
    for (final c in [_nome, _username, _password, _confirmar]) {
      c.dispose();
    }
    super.dispose();
  }

  // Confirma o convite antes de preencher o formulário (e mostra quem convidou)
  Future<void> _verConvite() async {
    try {
      final d = await Api.instancia.get('/auth/estado?convite=${Uri.encodeQueryComponent(widget.convite!)}');
      final c = d['convite'] as Map<String, dynamic>?;
      if (!mounted) return;
      setState(() {
        _quemConvidou = c == null ? null : Utilizador.deJson(c['de'] as Map<String, dynamic>);
        _erroConvite = c == null ? 'Esse convite não é válido ou já expirou. Pede um novo.' : null;
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erroConvite = e.mensagem);
    }
  }

  Future<void> _criar() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _aCriar = true);
    try {
      // Com sucesso a Sessao fecha este ecrã e abre o Início
      await Sessao.instancia.registar(
        nome: _nome.text,
        username: _username.text,
        password: _password.text,
        confirmar: _confirmar.text,
        convite: widget.convite,
      );
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aCriar = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    return Scaffold(
      extendBodyBehindAppBar: true,
      appBar: AppBar(title: const Text('Criar conta'), backgroundColor: Colors.transparent),
      body: Fundo(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  children: [
                    // Convite: de quem é (ou porque não serve)
                    if (widget.convite != null) ...[
                      Vidro(
                        child: Row(
                          children: [
                            if (_quemConvidou != null) ...[
                              Avatar(user: _quemConvidou, cor: p.par, tamanho: 40),
                              const SizedBox(width: 12),
                              Expanded(child: Text('Convite de ${_quemConvidou!.nome}. Ficam amigos assim que criares a conta.')),
                            ] else if (_erroConvite != null) ...[
                              Icon(Icons.link_off_rounded, color: p.erro),
                              const SizedBox(width: 12),
                              Expanded(child: Text(_erroConvite!)),
                            ] else
                              const Expanded(child: Text('A verificar o convite…')),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    Vidro(
                      padding: const EdgeInsets.all(22),
                      child: Form(
                        key: _form,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            TextFormField(
                              controller: _nome,
                              decoration: const InputDecoration(
                                labelText: 'Nome na app',
                                helperText: 'É o que os outros veem (ex.: Gus).',
                              ),
                              textCapitalization: TextCapitalization.words,
                              textInputAction: TextInputAction.next,
                              validator: (v) => (v ?? '').trim().length < 2 ? 'Pelo menos 2 caracteres.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _username,
                              decoration: const InputDecoration(
                                labelText: 'Utilizador',
                                helperText: 'Letras, números, ponto, hífen ou _ (para entrar).',
                              ),
                              autocorrect: false,
                              textInputAction: TextInputAction.next,
                              validator: (v) => (v ?? '').trim().length < 3 ? 'Pelo menos 3 caracteres.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _password,
                              obscureText: true,
                              decoration: const InputDecoration(labelText: 'Palavra-passe'),
                              textInputAction: TextInputAction.next,
                              validator: (v) => (v ?? '').length < 8 ? 'Pelo menos 8 caracteres.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _confirmar,
                              obscureText: true,
                              decoration: const InputDecoration(labelText: 'Confirmar palavra-passe'),
                              onFieldSubmitted: (_) => _criar(),
                              validator: (v) => v != _password.text ? 'As palavras-passe não coincidem.' : null,
                            ),
                            const SizedBox(height: 18),
                            FilledButton(
                              onPressed: (_aCriar || _erroConvite != null) ? null : _criar,
                              child: _aCriar
                                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Criar conta'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
