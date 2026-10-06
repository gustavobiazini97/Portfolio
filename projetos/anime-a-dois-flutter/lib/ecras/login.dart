// Ecrã de entrada: utilizador + palavra-passe. Mostra "Criar conta" enquanto o registo estiver aberto.

import 'package:flutter/material.dart';

import '../api/api.dart';
import '../config.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import 'registo.dart';

class EcraLogin extends StatefulWidget {
  const EcraLogin({super.key});

  @override
  State<EcraLogin> createState() => _EcraLoginState();
}

class _EcraLoginState extends State<EcraLogin> {
  final _form = GlobalKey<FormState>();
  final _username = TextEditingController();
  final _password = TextEditingController();

  bool _aEntrar = false; // botão a rodar enquanto espera pela API
  bool _verPassword = false;
  bool _registoAberto = false; // só aparece "Criar conta" se ainda houver lugar

  @override
  void initState() {
    super.initState();
    _verRegisto();
  }

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  // GET /auth/estado: falhar aqui não impede o login, por isso o erro é ignorado
  Future<void> _verRegisto() async {
    try {
      final d = await Api.instancia.get('/auth/estado');
      if (mounted) setState(() => _registoAberto = d['registoAberto'] == true);
    } on ApiErro {
      // sem rede: o botão de criar conta simplesmente não aparece
    }
  }

  Future<void> _entrar() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _aEntrar = true);
    try {
      // Se correr bem, a Sessao muda e a app abre o Início sozinha
      await Sessao.instancia.entrar(_username.text, _password.text);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aEntrar = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = Paleta.de(context);
    final textos = Theme.of(context).textTheme;

    return Scaffold(
      body: Fundo(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  children: [
                    // Logótipo e título
                    Image.asset('assets/icon/icon.png', width: 96, height: 96),
                    const SizedBox(height: 8),
                    Text(appNome, style: textos.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Text(
                      'Os episódios que vocês já viram, lado a lado.',
                      textAlign: TextAlign.center,
                      style: textos.bodyMedium?.copyWith(color: p.suave),
                    ),
                    const SizedBox(height: 24),

                    // Formulário
                    Vidro(
                      padding: const EdgeInsets.all(22),
                      child: Form(
                        key: _form,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            TextFormField(
                              controller: _username,
                              decoration: const InputDecoration(labelText: 'Utilizador'),
                              autocorrect: false,
                              textInputAction: TextInputAction.next,
                              autofillHints: const [AutofillHints.username],
                              validator: (v) => (v == null || v.trim().isEmpty) ? 'Escreve o teu utilizador.' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _password,
                              obscureText: !_verPassword,
                              decoration: InputDecoration(
                                labelText: 'Palavra-passe',
                                suffixIcon: IconButton(
                                  tooltip: _verPassword ? 'Esconder' : 'Mostrar',
                                  icon: Icon(_verPassword ? Icons.visibility_off_rounded : Icons.visibility_rounded),
                                  onPressed: () => setState(() => _verPassword = !_verPassword),
                                ),
                              ),
                              autofillHints: const [AutofillHints.password],
                              onFieldSubmitted: (_) => _entrar(),
                              validator: (v) => (v == null || v.isEmpty) ? 'Escreve a palavra-passe.' : null,
                            ),
                            const SizedBox(height: 18),
                            FilledButton(
                              onPressed: _aEntrar ? null : _entrar,
                              child: _aEntrar
                                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Entrar'),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Criar conta (só enquanto não existem as duas)
                    if (_registoAberto) ...[
                      const SizedBox(height: 14),
                      TextButton(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const EcraRegisto()),
                        ),
                        child: const Text('Ainda não tens conta? Criar conta'),
                      ),
                    ],
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
