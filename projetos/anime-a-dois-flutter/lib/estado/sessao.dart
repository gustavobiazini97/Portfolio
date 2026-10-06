// Estado global da app: o token (sessão iniciada ou não), o tema claro/escuro, a paleta de cores
// e os dados da pessoa com sessão (GET /eu). É um ChangeNotifier: o MaterialApp ouve-o e troca
// de ecrã, de tema ou de cores quando muda.

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api.dart';
import '../api/modelos.dart';

class Sessao extends ChangeNotifier {
  Sessao._();

  static final Sessao instancia = Sessao._();

  // Navegador da app: permite voltar ao início de qualquer sítio (ex.: quando o token expira)
  final GlobalKey<NavigatorState> navegador = GlobalKey<NavigatorState>();

  // Chaves no armazenamento do telemóvel
  static const _chaveToken = 'token';
  static const _chaveTema = 'tema';
  static const _chavePaleta = 'paleta';

  ThemeMode tema = ThemeMode.light; // claro por defeito, como no site
  int paleta = 0; // cores escolhidas no perfil (0 = sálvia e alperce)

  // Dados de GET /eu (preenchidos por carregarEu)
  Utilizador? eu;
  Utilizador? parceiro;
  List<Utilizador> ligados = []; // par + amigos: a quem podes convidar
  List<String> paletas = []; // nomes das paletas
  bool soJuntos = false; // privacidade: amigos só veem o que vês com eles
  int pedidosAmigos = 0; // pedidos de amizade por responder

  // Há sessão se houver token (se já não valer, o primeiro pedido dá 401 e volta ao login)
  bool get autenticado => Api.instancia.token != null;

  // Lê o token, o tema e a paleta guardados; corre uma vez, antes de abrir a app
  Future<void> arrancar() async {
    final prefs = await SharedPreferences.getInstance();
    tema = prefs.getString(_chaveTema) == 'escuro' ? ThemeMode.dark : ThemeMode.light;
    paleta = prefs.getInt(_chavePaleta) ?? 0;
    Api.instancia.token = prefs.getString(_chaveToken);
    Api.instancia.aoExpirar = () => _limpar();
  }

  // GET /eu: quem és, o teu par, os teus amigos e as definições (a app chama isto no Início e no perfil)
  Future<void> carregarEu() async {
    final d = await Api.instancia.get('/eu');
    eu = Utilizador.deJson(d['user'] as Map<String, dynamic>);
    parceiro = Utilizador.talvez(d['parceiro']);
    ligados = Utilizador.lista(d['ligados']);
    paletas = ((d['paletas'] as List?) ?? []).map((p) => p as String).toList();
    soJuntos = d['soJuntos'] == true;
    pedidosAmigos = (d['pedidosAmigos'] as num?)?.toInt() ?? 0;
    await _guardarPaleta((d['paleta'] as num?)?.toInt() ?? 0);
    notifyListeners();
  }

  // ---------- Conta ----------

  // POST /auth/login; lança ApiErro com a mensagem da API se falhar
  Future<void> entrar(String username, String password) async {
    final d = await Api.instancia.post('/auth/login', {
      'username': username.trim(),
      'password': password,
      'dispositivo': 'App Android',
    });
    await _guardarToken(d['token'] as String);
  }

  // POST /auth/registo; com convite (código ou link) a conta fica amiga de quem convidou.
  // A API devolve logo um token (entra sem pedir login).
  Future<void> registar({
    required String nome,
    required String username,
    required String password,
    required String confirmar,
    String? convite,
  }) async {
    final d = await Api.instancia.post('/auth/registo', {
      'nome': nome.trim(),
      'username': username.trim(),
      'password': password,
      'password_confirmar': confirmar,
      if (convite != null && convite.trim().isNotEmpty) 'convite': convite.trim(),
      'dispositivo': 'App Android',
    });
    await _guardarToken(d['token'] as String);
  }

  // Termina a sessão neste telemóvel (avisa a API; se falhar, sai na mesma)
  Future<void> sair() async {
    try {
      await Api.instancia.post('/auth/logout');
    } on ApiErro {
      // sem rede ou token já inválido: não há nada a fazer do lado do servidor
    }
    await _limpar();
  }

  // Depois de apagar a conta: o token já não existe no servidor
  Future<void> contaApagada() => _limpar();

  // ---------- Aparência ----------

  Future<void> alternarTema() async {
    tema = tema == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveTema, tema == ThemeMode.dark ? 'escuro' : 'claro');
  }

  // PUT /perfil/paleta: muda as cores já, e desfaz se a API recusar
  Future<void> mudarPaleta(int nova) async {
    final antes = paleta;
    await _guardarPaleta(nova);
    notifyListeners();
    try {
      await Api.instancia.put('/perfil/paleta', {'paleta': nova});
    } on ApiErro {
      await _guardarPaleta(antes);
      notifyListeners();
      rethrow;
    }
  }

  // ---------- Interno ----------

  Future<void> _guardarPaleta(int valor) async {
    paleta = valor;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_chavePaleta, valor);
  }

  Future<void> _guardarToken(String token) async {
    Api.instancia.token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveToken, token);
    _voltarAoInicio();
    notifyListeners();
  }

  // Apaga o token e os dados da pessoa e volta ao ecrã de login
  Future<void> _limpar() async {
    Api.instancia.token = null;
    eu = null;
    parceiro = null;
    ligados = [];
    pedidosAmigos = 0;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_chaveToken);
    _voltarAoInicio();
    notifyListeners();
  }

  // Fecha os ecrãs abertos por cima (perfil, série, …) para o "home" ficar visível
  void _voltarAoInicio() {
    navegador.currentState?.popUntil((rota) => rota.isFirst);
  }
}
