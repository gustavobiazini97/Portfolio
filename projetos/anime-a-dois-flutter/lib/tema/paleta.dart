// Cores da app, iguais às variáveis do public/css/app.css do site.
// "tu" e "par" dependem da paleta que cada pessoa escolhe no perfil (6 paletas, como no site);
// os companheiros (par ou amigos) usam sempre a cor "par".

import 'package:flutter/material.dart';

import '../estado/sessao.dart';

// As cores de uma paleta: pastel (barras, pontos) e as versões para texto, no claro e no escuro
class _CoresPaleta {
  final Color tu, par, tuTxt, parTxt, tuTxtEscuro, parTxtEscuro;
  const _CoresPaleta(this.tu, this.par, this.tuTxt, this.parTxt, this.tuTxtEscuro, this.parTxtEscuro);
}

// Índice = users.paleta (0 = a original); nomes em User::PALETAS do PHP
const _paletas = [
  _CoresPaleta(Color(0xFFB9D3B0), Color(0xFFF5C89A), Color(0xFF4E7A43), Color(0xFFA0581C), Color(0xFFCDE0C6), Color(0xFFF8D7B4)), // sálvia e alperce
  _CoresPaleta(Color(0xFFA9C4EE), Color(0xFFF4B6CF), Color(0xFF2F5C9E), Color(0xFFA63D6E), Color(0xFFC6D8F5), Color(0xFFF8CDE0)), // azul e rosa
  _CoresPaleta(Color(0xFFC9B6F2), Color(0xFFF2D98C), Color(0xFF5B3FA0), Color(0xFF8A6A0A), Color(0xFFDCD0F7), Color(0xFFF6E4A8)), // roxo e amarelo
  _CoresPaleta(Color(0xFF9FD8D2), Color(0xFFF5A9A0), Color(0xFF1F7A72), Color(0xFFB24238), Color(0xFFBFE8E3), Color(0xFFF8C6C0)), // turquesa e coral
  _CoresPaleta(Color(0xFFF4B6CF), Color(0xFFC7DFA0), Color(0xFFA63D6E), Color(0xFF4E7A43), Color(0xFFF8CDE0), Color(0xFFDCEBC0)), // rosa e verde
  _CoresPaleta(Color(0xFFA7D3F2), Color(0xFFF3B58F), Color(0xFF2A6B9E), Color(0xFFA0581C), Color(0xFFC4E2F7), Color(0xFFF8D0B4)), // azul e laranja
];

// Número de paletas disponíveis
int get totalPaletas => _paletas.length;

class Paleta {
  final Color bg; // fundo
  final Color vidro; // painéis translúcidos
  final Color vidroBorda;
  final Color campo; // fundo dos inputs
  final Color tinta; // texto principal
  final Color suave; // texto secundário
  final Color linha;
  final Color trilho; // parte vazia das barras
  final Color tu; // a tua cor (pastel)
  final Color par; // a cor do par / companheiros (pastel)
  final Color tuTxt; // versões escuras (claras no tema escuro) para números e texto
  final Color parTxt;
  final Color noPastel; // texto por cima de um fundo pastel
  final Color orbeTu; // manchas difusas do fundo
  final Color orbePar;
  final Color btn;
  final Color btnTxt;
  final Color erro;

  const Paleta({
    required this.bg,
    required this.vidro,
    required this.vidroBorda,
    required this.campo,
    required this.tinta,
    required this.suave,
    required this.linha,
    required this.trilho,
    required this.tu,
    required this.par,
    required this.tuTxt,
    required this.parTxt,
    required this.noPastel,
    required this.orbeTu,
    required this.orbePar,
    required this.btn,
    required this.btnTxt,
    required this.erro,
  });

  // Paleta para um índice (0–5) e um tema
  factory Paleta.para(int indice, {required bool escuro}) {
    final c = _paletas[indice.clamp(0, _paletas.length - 1)];
    if (escuro) {
      return Paleta(
        bg: const Color(0xFF0E1116),
        vidro: const Color(0x0FFFFFFF), // branco a 6%
        vidroBorda: const Color(0x1FFFFFFF),
        campo: const Color(0x12FFFFFF),
        tinta: const Color(0xFFE9ECF2),
        suave: const Color(0xFFA0A8B6),
        linha: const Color(0x1AFFFFFF),
        trilho: const Color(0x1AFFFFFF),
        tu: c.tu,
        par: c.par,
        tuTxt: c.tuTxtEscuro,
        parTxt: c.parTxtEscuro,
        noPastel: const Color(0xFF1F1A28),
        orbeTu: c.tu.withValues(alpha: 0.22),
        orbePar: c.par.withValues(alpha: 0.22),
        btn: const Color(0xFFE9ECF2),
        btnTxt: const Color(0xFF0E1116),
        erro: const Color(0xFFF08A82),
      );
    }
    return Paleta(
      bg: const Color(0xFFF5F3F8), // quase branco, ligeiramente lilás
      vidro: const Color(0x85FFFFFF), // branco a 52%
      vidroBorda: const Color(0xBFFFFFFF), // branco a 75%
      campo: const Color(0x99FFFFFF),
      tinta: const Color(0xFF1A1F2B),
      suave: const Color(0xFF5E6676),
      linha: const Color(0x1A1A1F2B),
      trilho: const Color(0x141A1F2B),
      tu: c.tu,
      par: c.par,
      tuTxt: c.tuTxt,
      parTxt: c.parTxt,
      noPastel: const Color(0xFF2A2433),
      orbeTu: c.tu.withValues(alpha: 0.55),
      orbePar: c.par.withValues(alpha: 0.55),
      btn: const Color(0xFF1A1F2B),
      btnTxt: const Color(0xFFFFFFFF),
      erro: const Color(0xFFB4413A),
    );
  }

  // Amostra de uma paleta (para escolher no perfil): [tu, par]
  static List<Color> amostra(int indice) => [_paletas[indice].tu, _paletas[indice].par];

  // Paleta do tema atual e da escolha da pessoa com sessão
  static Paleta de(BuildContext context) =>
      Paleta.para(Sessao.instancia.paleta, escuro: Theme.of(context).brightness == Brightness.dark);
}
