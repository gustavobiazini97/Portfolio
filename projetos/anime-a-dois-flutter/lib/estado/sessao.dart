// Estado global da app: o token (sessão iniciada ou não) e o tema claro/escuro.
// É um ChangeNotifier: o MaterialApp ouve-o e troca de ecrã/tema quando muda.

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

  ThemeMode tema = ThemeMode.light; // claro por defeito, como no site

  // Há sessão se houver token (se já não valer, o primeiro pedido dá 401 e volta ao login)
  bool get autenticado => Api.instancia.token != null;

  // Lê o token e o tema guardados; corre uma vez, antes de abrir a app
  Future<void> arrancar() async {
    final prefs = await SharedPreferences.getInstance();
    tema = prefs.getString(_chaveTema) == 'escuro' ? ThemeMode.dark : ThemeMode.light;
    Api.instancia.token = prefs.getString(_chaveToken);
    Api.instancia.aoExpirar = () => _limpar();
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

  // POST /auth/registo; a API devolve logo um token (entra sem pedir login)
  Future<Utilizador> registar({
    required String nome,
    required String username,
    required String password,
    required String confirmar,
  }) async {
    final d = await Api.instancia.post('/auth/registo', {
      'nome': nome.trim(),
      'username': username.trim(),
      'password': password,
      'password_confirmar': confirmar,
      'dispositivo': 'App Android',
    });
    await _guardarToken(d['token'] as String);
    return Utilizador.deJson(d['user'] as Map<String, dynamic>);
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

  // ---------- Tema ----------

  Future<void> alternarTema() async {
    tema = tema == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveTema, tema == ThemeMode.dark ? 'escuro' : 'claro');
  }

  // ---------- Interno ----------

  Future<void> _guardarToken(String token) async {
    Api.instancia.token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_chaveToken, token);
    _voltarAoInicio();
    notifyListeners();
  }

  // Apaga o token e volta ao ecrã de login
  Future<void> _limpar() async {
    Api.instancia.token = null;
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
