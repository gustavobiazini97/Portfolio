// Anime a Dois — app Flutter.
// Lê o token guardado e abre o ecrã principal (com a barra de baixo) ou o Login (sem sessão).

import 'package:flutter/material.dart';

import 'config.dart';
import 'ecras/principal.dart';
import 'ecras/login.dart';
import 'estado/sessao.dart';
import 'tema/tema.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized(); // necessário antes de ler o armazenamento
  await Sessao.instancia.arrancar();
  runApp(const AnimeADois());
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
