// Cliente da API REST: junta o token a cada pedido, lê o JSON e transforma erros em ApiErro.
// Todas as respostas da API têm o formato { "ok": true|false, ... , "mensagem"?: "..." }.

import 'dart:async';
import 'dart:convert';
import 'dart:io' show SocketException;

import 'package:http/http.dart' as http;

import '../config.dart';

// Erro com uma mensagem pronta a mostrar ao utilizador
class ApiErro implements Exception {
  final String mensagem;
  final int estado; // código HTTP (0 = nem chegou ao servidor)

  ApiErro(this.mensagem, [this.estado = 0]);

  // 401: o token deixou de valer → volta ao login
  bool get naoAutenticado => estado == 401;

  @override
  String toString() => mensagem;
}

class Api {
  Api._();

  // Uma só instância para a app toda
  static final Api instancia = Api._();

  // Token da sessão (vem do login; guardado pela Sessao)
  String? token;

  // Chamado quando a API responde 401 com um token: a sessão expirou
  void Function()? aoExpirar;

  // Cabeçalhos de todos os pedidos. O X-Auth-Token vai em paralelo porque alguns
  // servidores (PHP-FPM) escondem o Authorization; a API aceita os dois.
  Map<String, String> get cabecalhos => {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
        if (token != null) 'X-Auth-Token': token!,
      };

  // URL completo de uma rota, ex.: /series/naruto
  Uri _uri(String rota) => Uri.parse('$apiUrl$rota');

  // ---------- Métodos HTTP ----------

  Future<Map<String, dynamic>> get(String rota) =>
      _enviar(() => http.get(_uri(rota), headers: cabecalhos));

  Future<Map<String, dynamic>> post(String rota, [Map<String, dynamic>? corpo]) =>
      _enviar(() => http.post(_uri(rota), headers: _comJson, body: jsonEncode(corpo ?? {})));

  Future<Map<String, dynamic>> put(String rota, [Map<String, dynamic>? corpo]) =>
      _enviar(() => http.put(_uri(rota), headers: _comJson, body: jsonEncode(corpo ?? {})));

  Future<Map<String, dynamic>> delete(String rota) =>
      _enviar(() => http.delete(_uri(rota), headers: cabecalhos));

  // Envia um ficheiro (multipart), ex.: a foto de perfil
  Future<Map<String, dynamic>> enviarFicheiro(String rota, String campo, String caminho) {
    return _enviar(() async {
      final pedido = http.MultipartRequest('POST', _uri(rota))
        ..headers.addAll(cabecalhos)
        ..files.add(await http.MultipartFile.fromPath(campo, caminho));
      return http.Response.fromStream(await pedido.send());
    });
  }

  // Cabeçalhos + corpo em JSON
  Map<String, String> get _comJson => {...cabecalhos, 'Content-Type': 'application/json'};

  // ---------- Núcleo ----------

  // Faz o pedido, lê o JSON e lança ApiErro em qualquer falha
  Future<Map<String, dynamic>> _enviar(Future<http.Response> Function() pedido) async {
    final http.Response r;
    try {
      r = await pedido().timeout(const Duration(seconds: 20));
    } on SocketException {
      throw ApiErro('Sem ligação à internet.');
    } on TimeoutException {
      throw ApiErro('O servidor demorou demasiado. Tenta outra vez.');
    } on Exception {
      // ClientException, erros de certificado (HandshakeException), etc.
      throw ApiErro('Não foi possível ligar ao servidor.');
    }

    // A API responde sempre JSON; outra coisa (ex.: página de erro do alojamento) é inesperada
    final Map<String, dynamic> dados;
    try {
      dados = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiErro('Resposta inesperada do servidor (${r.statusCode}).', r.statusCode);
    }

    if (r.statusCode >= 400 || dados['ok'] != true) {
      // Token recusado → avisa a Sessao (que volta ao login). No próprio login não há token.
      if (r.statusCode == 401 && token != null) {
        aoExpirar?.call();
      }
      throw ApiErro(dados['mensagem'] as String? ?? 'Algo correu mal.', r.statusCode);
    }
    return dados;
  }
}
