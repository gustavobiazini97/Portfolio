// Anime a Dois — app Flutter.
// Lê o token guardado e abre o ecrã principal (com a barra de baixo) ou o Login (sem sessão).
// Se o arranque falhar ou encravar, em vez de ficar tudo preto mostra o erro (para copiar)
// e um botão para continuar mesmo assim.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'config.dart';
import 'ecras/principal.dart';
import 'ecras/login.dart';
import 'estado/sessao.dart';
import 'tema/tema.dart';

// Já se mostrou a app? (erros depois disto não substituem o ecrã)
bool _arrancou = false;

Future<void> main() async {
  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized(); // necessário antes de ler o armazenamento
    String? erro;
    try {
      // Timeout: se ler as definições encravar, não fica preso no ecrã preto
      await Sessao.instancia.arrancar().timeout(const Duration(seconds: 8));
    } catch (e, st) {
      erro = '$e\n\n$st';
    }
    _arrancou = true;
    runApp(erro == null ? const AnimeADois() : _ErroArranque(erro));
  }, (e, st) {
    // Erro fora do try (antes de haver ecrã): mostra-o em vez do preto
    if (!_arrancou) {
      _arrancou = true;
      runApp(_ErroArranque('$e\n\n$st'));
    }
  });
}

class AnimeADois extends StatelessWidget {
  const AnimeADois({super.key});

  @override
  Widget build(BuildContext context) {
    final sessao = Sessao.instancia;

    // Reconstrói quando a sessão ou o tema mudam
    return ListenableBuilder(
      listenable: sessao,
      builder: (context, _) => MaterialApp(
        title: appNome,
        debugShowCheckedModeBanner: false,
        navigatorKey: sessao.navegador,
        theme: temaClaro(sessao.paleta), // as cores que a pessoa escolheu no perfil
        darkTheme: temaEscuro(sessao.paleta),
        themeMode: sessao.tema,
        // A chave força um ecrã novo ao entrar/sair (sem restos da sessão anterior)
        home: sessao.autenticado
            ? const EcraPrincipal(key: ValueKey('principal'))
            : const EcraLogin(key: ValueKey('login')),
      ),
    );
  }
}

// Ecrã de erro do arranque: o texto do erro (para copiar e enviar) e "Continuar mesmo assim"
class _ErroArranque extends StatelessWidget {
  final String erro;

  const _ErroArranque(this.erro);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      darkTheme: ThemeData.dark(useMaterial3: true),
      themeMode: ThemeMode.dark,
      home: Builder(
        builder: (context) => Scaffold(
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('A app não conseguiu arrancar 😵',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  const Text('Copia o erro e envia-o. Podes tentar continuar na mesma.'),
                  const SizedBox(height: 14),
                  Expanded(
                    child: SingleChildScrollView(
                      child: SelectableText(erro, style: const TextStyle(fontSize: 12, fontFamily: 'monospace')),
                    ),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: erro));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Erro copiado.')));
                      }
                    },
                    child: const Text('Copiar erro'),
                  ),
                  const SizedBox(height: 8),
                  // Continua sem as definições guardadas (no pior caso pede o login outra vez)
                  FilledButton(
                    onPressed: () => runApp(const AnimeADois()),
                    child: const Text('Continuar mesmo assim'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
