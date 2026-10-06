// Criar conta: nome que aparece na app, utilizador e palavra-passe.
// As regras (tamanhos, caracteres, máximo de 2 contas) são validadas na API; aqui só o básico.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../estado/sessao.dart';
import '../widgets/comum.dart';

class EcraRegisto extends StatefulWidget {
  const EcraRegisto({super.key});

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

  @override
  void dispose() {
    for (final c in [_nome, _username, _password, _confirmar]) {
      c.dispose();
    }
    super.dispose();
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
      );
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aCriar = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
                child: Vidro(
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
                            helperText: 'É o que o teu par vê (ex.: Gus).',
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
                          onPressed: _aCriar ? null : _criar,
                          child: _aCriar
                              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Text('Criar conta'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
