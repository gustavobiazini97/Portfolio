// Notificações da app (Firebase Cloud Messaging).
//
// Ao entrar (ecrã principal): pede a permissão de notificações ao Android, obtém o token deste telemóvel
// e envia-o à API (POST /dispositivos). O servidor usa-o para mandar os avisos (app/models/Fcm.php).
// Com a app fechada ou em segundo plano, é o próprio Android que mostra a notificação.
// Com a app aberta, aparece uma barrinha em baixo (o Android não mostra nada nesse caso).
// Ao terminar sessão: avisa a API (DELETE /dispositivos) e apaga o token, para não receber avisos de outra conta.
//
// Os dados do projeto Firebase estão em config.dart; vazios = notificações desligadas (o resto funciona).

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../api/api.dart';
import '../config.dart';
import '../widgets/comum.dart';
import 'sessao.dart';

class Avisos {
  Avisos._();
  static final Avisos instancia = Avisos._();

  bool _iniciado = false;
  String? _token;

  // Há projeto Firebase configurado nesta compilação?
  bool get configurado => firebaseAppId.isNotEmpty && firebaseApiKey.isNotEmpty;

  // Liga o Firebase (uma vez) e os ouvintes de mensagens e de troca de token
  Future<void> _iniciar() async {
    if (_iniciado) return;
    await Firebase.initializeApp(
      options: const FirebaseOptions(
        apiKey: firebaseApiKey,
        appId: firebaseAppId,
        messagingSenderId: firebaseSenderId,
        projectId: firebaseProjectId,
      ),
    );
    FirebaseMessaging.onMessage.listen(_emPrimeiroPlano);
    FirebaseMessaging.instance.onTokenRefresh.listen(_registar); // o Firebase troca o token de vez em quando
    _iniciado = true;
  }

  // Com sessão iniciada: pede permissão (Android 13+ mostra a pergunta uma vez) e regista este telemóvel.
  // Nunca rebenta: sem rede ou sem permissão, a app segue sem notificações.
  Future<void> ativar() async {
    if (!configurado || Api.instancia.token == null) return;
    try {
      await _iniciar();
      final permissao = await FirebaseMessaging.instance.requestPermission();
      if (permissao.authorizationStatus == AuthorizationStatus.denied) return;
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _registar(token);
    } catch (e) {
      debugPrint('Notificações indisponíveis: $e');
    }
  }

  // Envia o token à API (só com sessão iniciada)
  Future<void> _registar(String token) async {
    if (Api.instancia.token == null) return;
    _token = token;
    try {
      await Api.instancia.post('/dispositivos', {'token': token});
    } on ApiErro catch (e) {
      debugPrint('Registo das notificações falhou: ${e.mensagem}');
    }
  }

  // Antes de terminar sessão (ainda com o token da API): este telemóvel deixa de receber
  Future<void> desativar() async {
    if (!_iniciado) return;
    try {
      final token = _token ?? await FirebaseMessaging.instance.getToken();
      if (token != null) await Api.instancia.delete('/dispositivos', {'token': token});
      await FirebaseMessaging.instance.deleteToken();
    } catch (e) {
      debugPrint('Desligar notificações falhou: $e');
    }
    _token = null;
  }

  // App aberta: o Android não mostra a notificação, por isso aparece uma barrinha
  void _emPrimeiroPlano(RemoteMessage mensagem) {
    final n = mensagem.notification;
    final contexto = Sessao.instancia.navegador.currentContext;
    if (n == null || contexto == null || !contexto.mounted) return;
    aviso(contexto, [n.title, n.body].whereType<String>().where((t) => t.isNotEmpty).join(' · '));
  }
}
