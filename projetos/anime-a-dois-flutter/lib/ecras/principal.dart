// Ecrã principal com a barra de navegação em baixo:
//   Início · Biblioteca · [ + ] · Amigos · Perfil
// O "+" ao centro (flutuante) abre a pesquisa para adicionar uma série.
// Cada separador mantém-se vivo (IndexedStack); ao voltar a um separador ele recarrega os dados.

import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../estado/sessao.dart';
import '../tema/paleta.dart';
import 'adicionar.dart';
import 'amigos.dart';
import 'biblioteca.dart';
import 'inicio.dart';
import 'perfil.dart';

class EcraPrincipal extends StatefulWidget {
  const EcraPrincipal({super.key});

  @override
  State<EcraPrincipal> createState() => _EcraPrincipalState();
}

class _EcraPrincipalState extends State<EcraPrincipal> {
  int _atual = 0; // separador aberto

  // Versão de cada separador: mudar a chave faz o ecrã recarregar os dados
  final List<int> _versoes = [0, 0, 0, 0];

  // Separadores (o "+" não é um separador: está entre o 2.º e o 3.º)
  static const _itens = [
    (Icons.home_outlined, Icons.home_rounded, 'Início'),
    (Icons.video_library_outlined, Icons.video_library_rounded, 'Biblioteca'),
    (Icons.people_outline_rounded, Icons.people_rounded, 'Amigos'),
    (Icons.person_outline_rounded, Icons.person_rounded, 'Perfil'),
  ];

  void _escolher(int i) {
    HapticFeedback.selectionClick();
    setState(() {
      if (i != _atual) _versoes[i]++; // ao voltar a um separador, dados frescos
      _atual = i;
    });
  }

  // "+": pesquisa de séries; ao voltar, o Início e a Biblioteca recarregam
  Future<void> _adicionar() async {
    HapticFeedback.mediumImpact();
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EcraAdicionar()));
    if (!mounted) return;
    setState(() {
      _versoes[0]++;
      _versoes[1]++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final ecras = [
      EcraInicio(key: ValueKey('inicio-${_versoes[0]}')),
      EcraBiblioteca(key: ValueKey('biblioteca-${_versoes[1]}')),
      EcraAmigos(key: ValueKey('amigos-${_versoes[2]}')),
      EcraPerfil(key: ValueKey('perfil-${_versoes[3]}')),
    ];

    return Scaffold(
      body: IndexedStack(index: _atual, children: ecras),
      bottomNavigationBar: _barra(context),
    );
  }

  // Barra de vidro com os 4 separadores e o "+" redondo ao centro
  Widget _barra(BuildContext context) {
    final p = Paleta.de(context);
    final pedidos = Sessao.instancia.pedidosAmigos;

    Widget item(int i) {
      final (vazio, cheio, texto) = _itens[i];
      final ativo = i == _atual;
      final icone = Icon(ativo ? cheio : vazio, size: 24, color: ativo ? p.tinta : p.suave);
      return Expanded(
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () => _escolher(i),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Amigos: bolinha quando há pedidos por responder
                i == 2 && pedidos > 0
                    ? Badge(smallSize: 9, backgroundColor: p.parTxt, child: icone)
                    : icone,
                const SizedBox(height: 3),
                Text(texto,
                    style: TextStyle(fontSize: 11, color: ativo ? p.tinta : p.suave, fontWeight: ativo ? FontWeight.w700 : FontWeight.w400)),
                // Tracinho por baixo do separador aberto
                AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  margin: const EdgeInsets.only(top: 4),
                  width: ativo ? 18 : 0,
                  height: 3,
                  decoration: BoxDecoration(color: p.tuTxt, borderRadius: BorderRadius.circular(2)),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
        child: SizedBox(
          height: 74,
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.center,
            children: [
              // Fundo de vidro arredondado
              ClipRRect(
                borderRadius: BorderRadius.circular(28),
                child: BackdropFilter(
                  filter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                  child: Container(
                    decoration: BoxDecoration(
                      color: p.vidro.withValues(alpha: (p.vidro.a + .25).clamp(0, 1)),
                      borderRadius: BorderRadius.circular(28),
                      border: Border.all(color: p.vidroBorda),
                    ),
                    child: Row(
                      children: [
                        item(0),
                        item(1),
                        const SizedBox(width: 72), // lugar do "+"
                        item(2),
                        item(3),
                      ],
                    ),
                  ),
                ),
              ),
              // "+" flutuante, um pouco acima da barra
              Positioned(
                top: -18,
                child: GestureDetector(
                  onTap: _adicionar,
                  child: Container(
                    width: 62,
                    height: 62,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [p.tu, p.par]),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .22), blurRadius: 18, offset: const Offset(0, 8))],
                      border: Border.all(color: p.bg, width: 4),
                    ),
                    child: Icon(Icons.add_rounded, size: 32, color: p.noPastel),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
