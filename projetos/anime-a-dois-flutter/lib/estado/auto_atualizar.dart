// Atualização automática: enquanto um ecrã está à vista, pede os dados de novo ao servidor
// a cada [intervaloAuto] (e logo que a app volta do fundo). Assim a barra do par mexe sozinha
// quando ele marca um episódio, sem sair e voltar a entrar.
// Só corre quando faz sentido: app em primeiro plano, ecrã no topo (nada aberto por cima),
// separador aberto (nos ecrãs da barra de baixo) e sem nenhuma ação a decorrer.

import 'dart:async';

import 'package:flutter/widgets.dart';

// De quanto em quanto tempo se pergunta ao servidor
const intervaloAuto = Duration(seconds: 15);

// Separador aberto na barra de baixo (o EcraPrincipal atualiza-o)
final separadorAtual = ValueNotifier<int>(0);

mixin AutoAtualizar<T extends StatefulWidget> on State<T> {
  Timer? _relogio;
  AppLifecycleListener? _ciclo;
  bool _emCurso = false; // nunca dois pedidos ao mesmo tempo

  // Cada ecrã diz como recarrega sem mostrar "a carregar" nem trocar o conteúdo por um erro
  Future<void> atualizarEmSilencio();

  // false enquanto há algo a meio (a guardar, a enviar, seleção aberta…)
  bool get podeAtualizar => true;

  // Índice na barra de baixo, para os ecrãs que são separadores (null = ecrã normal)
  int? get separador => null;

  // Chamar no initState
  void iniciarAutoAtualizar() {
    _relogio = Timer.periodic(intervaloAuto, (_) => _tique());
    _ciclo = AppLifecycleListener(onResume: _tique); // voltou do fundo: atualiza já
  }

  Future<void> _tique() async {
    if (!mounted || _emCurso || !podeAtualizar) return;
    if (WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) return;
    if (ModalRoute.of(context)?.isCurrent == false) return; // há outro ecrã/folha por cima
    final s = separador;
    if (s != null && separadorAtual.value != s) return; // separador escondido
    _emCurso = true;
    try {
      await atualizarEmSilencio();
    } catch (_) {
      // Sem rede ou servidor em baixo: tenta outra vez no próximo tique, sem incomodar
    } finally {
      _emCurso = false;
    }
  }

  @override
  void dispose() {
    _relogio?.cancel();
    _ciclo?.dispose();
    super.dispose();
  }
}
