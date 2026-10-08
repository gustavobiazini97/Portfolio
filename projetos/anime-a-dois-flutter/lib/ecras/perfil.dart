// Perfil (igual ao site): foto, nome e utilizador, palavra-passe, cores (6 paletas), privacidade,
// notificações, tema, terminar sessão e apagar conta.

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api/api.dart';
import '../api/modelos.dart';
import '../estado/sessao.dart';
import '../tema/paleta.dart';
import '../widgets/comum.dart';
import '../estado/atualizacoes.dart';
import 'medalhas.dart';
import 'retrospetiva.dart';

class EcraPerfil extends StatefulWidget {
  const EcraPerfil({super.key});

  @override
  State<EcraPerfil> createState() => _EcraPerfilState();
}

class _EcraPerfilState extends State<EcraPerfil> {
  Utilizador? _user;
  Preferencias? _pref;
  String? _erro;

  // Campos
  final _nome = TextEditingController();
  final _username = TextEditingController();
  final _atual = TextEditingController();
  final _nova = TextEditingController();
  final _confirmar = TextEditingController();
  final _email = TextEditingController();

  // Botões a rodar (um por secção)
  bool _aGuardarNome = false;
  bool _aGuardarPass = false;
  bool _aGuardarNotif = false;
  bool _aGuardarFoto = false;
  bool _soJuntos = false; // privacidade: os amigos só veem as séries que vês com eles
  String _versao = ''; // versão instalada (ex.: "1.3.0 (build 45)")
  bool _aProcurar = false;

  @override
  void initState() {
    super.initState();
    Atualizacoes.versaoInstalada().then((v) {
      if (mounted) setState(() => _versao = v);
    });
    _carregar();
  }

  @override
  void dispose() {
    for (final c in [_nome, _username, _atual, _nova, _confirmar, _email]) {
      c.dispose();
    }
    super.dispose();
  }

  // GET /eu (pela Sessao: também traz a paleta e a privacidade) + GET /perfil/notificacoes
  Future<void> _carregar() async {
    try {
      final r = await Future.wait([Api.instancia.get('/eu'), Api.instancia.get('/perfil/notificacoes')]);
      await Sessao.instancia.carregarEu();
      if (!mounted) return;
      setState(() {
        _soJuntos = Sessao.instancia.soJuntos;
        _definirUser(Utilizador.deJson(r[0]['user'] as Map<String, dynamic>));
        _pref = Preferencias.deJson(r[1]['preferencias'] as Map<String, dynamic>);
        _email.text = _pref!.email;
        _erro = null;
      });
    } on ApiErro catch (e) {
      if (mounted) setState(() => _erro = e.mensagem);
    }
  }

  void _definirUser(Utilizador u) {
    _user = u;
    _nome.text = u.nome;
    _username.text = u.username;
  }

  // ---------- Ações ----------

  Future<void> _guardarNome() async {
    setState(() => _aGuardarNome = true);
    try {
      final d = await Api.instancia.put('/perfil', {'nome': _nome.text, 'username': _username.text});
      if (!mounted) return;
      setState(() => _definirUser(Utilizador.deJson(d['user'] as Map<String, dynamic>)));
      aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardarNome = false);
    }
  }

  Future<void> _guardarPassword() async {
    setState(() => _aGuardarPass = true);
    try {
      final d = await Api.instancia.put('/perfil/password', {
        'atual': _atual.text,
        'nova': _nova.text,
        'confirmar': _confirmar.text,
      });
      if (!mounted) return;
      _atual.clear();
      _nova.clear();
      _confirmar.clear();
      aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardarPass = false);
    }
  }

  Future<void> _guardarNotificacoes() async {
    final pref = _pref;
    if (pref == null) return;
    pref.email = _email.text.trim();
    setState(() => _aGuardarNotif = true);
    try {
      final d = await Api.instancia.put('/perfil/notificacoes', pref.paraJson());
      if (!mounted) return;
      setState(() => _pref = Preferencias.deJson(d['preferencias'] as Map<String, dynamic>));
      aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardarNotif = false);
    }
  }

  // Escolhe a origem (galeria ou câmara), reduz a imagem e envia-a
  Future<void> _mudarFoto() async {
    final origem = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_rounded),
              title: const Text('Escolher da galeria'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded),
              title: const Text('Tirar foto'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (origem == null) return;

    // O servidor corta ao quadrado e reduz para 320px; aqui só se evita enviar fotos enormes
    final ficheiro = await ImagePicker().pickImage(source: origem, maxWidth: 1200, imageQuality: 85);
    if (ficheiro == null || !mounted) return;

    setState(() => _aGuardarFoto = true);
    try {
      final d = await Api.instancia.enviarFicheiro('/perfil/foto', 'foto', ficheiro.path);
      if (!mounted) return;
      setState(() => _user = Utilizador.deJson(d['user'] as Map<String, dynamic>));
      aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardarFoto = false);
    }
  }

  Future<void> _removerFoto() async {
    setState(() => _aGuardarFoto = true);
    try {
      final d = await Api.instancia.delete('/perfil/foto');
      if (!mounted) return;
      setState(() => _user = Utilizador.deJson(d['user'] as Map<String, dynamic>));
      aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    } finally {
      if (mounted) setState(() => _aGuardarFoto = false);
    }
  }

  // Privacidade: guarda logo ao mudar o interruptor
  Future<void> _mudarPrivacidade(bool valor) async {
    setState(() => _soJuntos = valor);
    try {
      final d = await Api.instancia.put('/perfil/privacidade', {'so_juntos': valor});
      Sessao.instancia.soJuntos = valor;
      if (mounted) aviso(context, d['mensagem'] as String);
    } on ApiErro catch (e) {
      if (!mounted) return;
      setState(() => _soJuntos = !valor);
      aviso(context, e.mensagem, erro: true);
    }
  }

  // Cores: muda já (a Sessao redesenha a app) e desfaz se a API recusar
  Future<void> _mudarPaleta(int indice) async {
    try {
      await Sessao.instancia.mudarPaleta(indice);
      if (mounted) aviso(context, 'Cores guardadas.');
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    }
  }

  // Procurar atualizações à mão: popup se houver, aviso se não
  Future<void> _procurarAtualizacoes() async {
    setState(() => _aProcurar = true);
    final nova = await Atualizacoes.procurar();
    if (!mounted) return;
    setState(() => _aProcurar = false);
    if (nova == null) {
      aviso(context, 'Tens a versão mais recente. ✨');
    } else {
      await Atualizacoes.mostrar(context, nova);
    }
  }

  // Apagar conta: pede a palavra-passe numa janela e confirma
  Future<void> _apagarConta() async {
    final campo = TextEditingController();
    final sim = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Apagar a tua conta?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Apaga a tua biblioteca, os episódios vistos, os comentários e as amizades. Não dá para desfazer.'),
            const SizedBox(height: 12),
            TextField(controller: campo, obscureText: true, decoration: const InputDecoration(labelText: 'Palavra-passe')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Apagar')),
        ],
      ),
    );
    final password = campo.text;
    campo.dispose();
    if (sim != true) return;
    try {
      await Api.instancia.post('/perfil/apagar', {'password': password});
      await Sessao.instancia.contaApagada();
    } on ApiErro catch (e) {
      if (mounted) aviso(context, e.mensagem, erro: true);
    }
  }

  // ---------- Ecrã ----------

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
                    // Nos separadores da barra de baixo não há para onde voltar
                    if (Navigator.of(context).canPop()) const BackButton() else const SizedBox(width: 16),
                    Text('Perfil', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              Expanded(
                child: _user == null
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
    final escuro = Theme.of(context).brightness == Brightness.dark;
    final pref = _pref;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        // ---------- Foto ----------
        Center(
          child: Stack(
            alignment: Alignment.center,
            children: [
              Avatar(user: _user, cor: p.tu, tamanho: 96),
              if (_aGuardarFoto) const CircularProgressIndicator(),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TextButton.icon(
              onPressed: _aGuardarFoto ? null : _mudarFoto,
              icon: const Icon(Icons.photo_camera_rounded, size: 18),
              label: const Text('Mudar foto'),
            ),
            if (_user?.foto != null)
              TextButton(onPressed: _aGuardarFoto ? null : _removerFoto, child: const Text('Remover')),
          ],
        ),
        const SizedBox(height: 10),

        // ---------- Nome ----------
        Vidro(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const TituloSecao('Os teus dados'),
              TextField(controller: _nome, decoration: const InputDecoration(labelText: 'Nome na app')),
              const SizedBox(height: 10),
              TextField(
                controller: _username,
                autocorrect: false,
                decoration: const InputDecoration(labelText: 'Utilizador (para entrar)'),
              ),
              const SizedBox(height: 14),
              FilledButton(onPressed: _aGuardarNome ? null : _guardarNome, child: const Text('Guardar')),
            ],
          ),
        ),
        const SizedBox(height: 14),

        // ---------- Palavra-passe ----------
        Vidro(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const TituloSecao('Palavra-passe'),
              TextField(controller: _atual, obscureText: true, decoration: const InputDecoration(labelText: 'Atual')),
              const SizedBox(height: 10),
              TextField(controller: _nova, obscureText: true, decoration: const InputDecoration(labelText: 'Nova (mín. 8)')),
              const SizedBox(height: 10),
              TextField(
                controller: _confirmar,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Confirmar a nova'),
              ),
              const SizedBox(height: 6),
              Text('Os outros telemóveis com sessão iniciada vão ter de entrar outra vez.',
                  style: textos.labelSmall?.copyWith(color: p.suave)),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: _aGuardarPass ? null : _guardarPassword,
                child: const Text('Mudar palavra-passe'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        // ---------- Notificações ----------
        if (pref != null) ...[
          Vidro(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const TituloSecao('Notificações'),
                Text(
                  'O que recebes quando o teu par marca episódios ou comenta. '
                  '"Telemóvel" é a app do site instalada (Web Push); esta app ainda não recebe notificações.',
                  style: textos.bodySmall?.copyWith(color: p.suave),
                ),
                const SizedBox(height: 6),
                _interruptor('Episódios → telemóvel', pref.epPush, (v) => pref.epPush = v),
                _interruptor('Episódios → email', pref.epEmail, (v) => pref.epEmail = v),
                _interruptor('Comentários → telemóvel', pref.comPush, (v) => pref.comPush = v),
                _interruptor('Comentários → email', pref.comEmail, (v) => pref.comEmail = v),
                const SizedBox(height: 8),
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(labelText: 'Email (opcional)'),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _aGuardarNotif ? null : _guardarNotificacoes,
                  child: const Text('Guardar notificações'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
        ],

        // ---------- Conquistas: medalhas e retrospetivas ----------
        Row(
          children: [
            Expanded(
              child: Vidro(
                padding: const EdgeInsets.all(16),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EcraMedalhas())),
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('🏅', style: TextStyle(fontSize: 28)),
                    SizedBox(height: 6),
                    Text('Medalhas', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text('as tuas conquistas', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Vidro(
                padding: const EdgeInsets.all(16),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EcraRetrospetivas())),
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('📼', style: TextStyle(fontSize: 28)),
                    SizedBox(height: 6),
                    Text('Retrospetivas', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text('mês a mês e o ano', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),

        // ---------- Cores (só para ti; as mesmas do site) ----------
        Vidro(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const TituloSecao('Cores'),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  for (var i = 0; i < totalPaletas; i++)
                    ChoiceChip(
                      selected: Sessao.instancia.paleta == i,
                      onSelected: (_) => _mudarPaleta(i),
                      avatar: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          for (final c in Paleta.amostra(i))
                            Container(
                              width: 10,
                              height: 10,
                              margin: const EdgeInsets.only(right: 2),
                              decoration: BoxDecoration(color: c, shape: BoxShape.circle),
                            ),
                        ],
                      ),
                      label: Text(i < Sessao.instancia.paletas.length ? Sessao.instancia.paletas[i] : 'Paleta ${i + 1}'),
                      shape: const StadiumBorder(),
                    ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        // ---------- Privacidade ----------
        Vidro(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
          child: SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Os amigos só veem o que vemos juntos'),
            subtitle: const Text('O teu par vê sempre tudo.'),
            value: _soJuntos,
            onChanged: _mudarPrivacidade,
          ),
        ),
        const SizedBox(height: 14),

        // ---------- Aparência e sessão ----------
        Vidro(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
          child: Column(
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Tema escuro'),
                value: escuro,
                onChanged: (_) => Sessao.instancia.alternarTema(),
              ),
              const Divider(height: 1),
              // Versão instalada e procurar atualizações
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.system_update_rounded),
                title: const Text('Procurar atualizações'),
                subtitle: Text(_versao.isEmpty ? '' : 'Versão $_versao'),
                trailing: _aProcurar ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : null,
                onTap: _aProcurar ? null : _procurarAtualizacoes,
              ),
              const Divider(height: 1),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(Icons.logout_rounded, color: p.erro),
                title: Text('Terminar sessão', style: TextStyle(color: p.erro, fontWeight: FontWeight.w700)),
                onTap: Sessao.instancia.sair,
              ),
              const Divider(height: 1),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(Icons.delete_forever_rounded, color: p.erro),
                title: Text('Apagar conta', style: TextStyle(color: p.erro)),
                onTap: _apagarConta,
              ),
            ],
          ),
        ),
      ],
    );
  }

  // Interruptor de uma preferência (muda já no ecrã; só vai para a API ao carregar em Guardar)
  Widget _interruptor(String texto, bool valor, void Function(bool) definir) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      dense: true,
      title: Text(texto),
      value: valor,
      onChanged: (v) => setState(() => definir(v)),
    );
  }
}
