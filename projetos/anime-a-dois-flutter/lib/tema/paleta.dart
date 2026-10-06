// Cores da app, iguais às variáveis do public/css/app.css do site.
// Sálvia = tu, alperce = o teu par; cada série tem a sua cor de acento (vem da API).

import 'package:flutter/material.dart';

class Paleta {
  final Color bg; // fundo
  final Color vidro; // painéis translúcidos
  final Color vidroBorda;
  final Color campo; // fundo dos inputs
  final Color tinta; // texto principal
  final Color suave; // texto secundário
  final Color linha;
  final Color trilho; // parte vazia das barras
  final Color tu; // sálvia (pastel)
  final Color par; // alperce (pastel)
  final Color tuTxt; // versões escuras para números e texto
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

  // Tema claro (por defeito)
  static const claro = Paleta(
    bg: Color(0xFFF5F3F8), // quase branco, ligeiramente lilás
    vidro: Color(0x85FFFFFF), // branco a 52%
    vidroBorda: Color(0xBFFFFFFF), // branco a 75%
    campo: Color(0x99FFFFFF),
    tinta: Color(0xFF1A1F2B),
    suave: Color(0xFF5E6676),
    linha: Color(0x1A1A1F2B),
    trilho: Color(0x141A1F2B),
    tu: Color(0xFFB9D3B0),
    par: Color(0xFFF5C89A),
    tuTxt: Color(0xFF4E7A43),
    parTxt: Color(0xFFA0581C),
    noPastel: Color(0xFF2A2433),
    orbeTu: Color(0x8CB9D3B0),
    orbePar: Color(0x8CF5C89A),
    btn: Color(0xFF1A1F2B),
    btnTxt: Color(0xFFFFFFFF),
    erro: Color(0xFFB4413A),
  );

  // Tema escuro
  static const escuro = Paleta(
    bg: Color(0xFF0E1116),
    vidro: Color(0x0FFFFFFF), // branco a 6%
    vidroBorda: Color(0x1FFFFFFF),
    campo: Color(0x12FFFFFF),
    tinta: Color(0xFFE9ECF2),
    suave: Color(0xFFA0A8B6),
    linha: Color(0x1AFFFFFF),
    trilho: Color(0x1AFFFFFF),
    tu: Color(0xFFB9D3B0),
    par: Color(0xFFF5C89A),
    tuTxt: Color(0xFFCDE0C6),
    parTxt: Color(0xFFF8D7B4),
    noPastel: Color(0xFF1F1A28),
    orbeTu: Color(0x3896BE87),
    orbePar: Color(0x38E6AA6E),
    btn: Color(0xFFE9ECF2),
    btnTxt: Color(0xFF0E1116),
    erro: Color(0xFFF08A82),
  );

  // Paleta do tema atual
  static Paleta de(BuildContext context) =>
      Theme.of(context).brightness == Brightness.dark ? escuro : claro;
}
