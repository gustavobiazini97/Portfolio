// ThemeData da app (claro e escuro): letra arredondada, botões em pílula, campos suaves.

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'paleta.dart';

// A mesma letra do site
const String letra = 'M PLUS Rounded 1c';

ThemeData temaClaro() => _tema(Paleta.claro, Brightness.light);
ThemeData temaEscuro() => _tema(Paleta.escuro, Brightness.dark);

ThemeData _tema(Paleta p, Brightness brilho) {
  // Texto base do Material com a letra do site e as cores da paleta
  final base = ThemeData(brightness: brilho, useMaterial3: true);
  final texto = GoogleFonts.getTextTheme(letra, base.textTheme)
      .apply(bodyColor: p.tinta, displayColor: p.tinta);

  // Cantos arredondados comuns aos campos
  final cantos = OutlineInputBorder(
    borderRadius: BorderRadius.circular(18),
    borderSide: BorderSide(color: p.linha),
  );

  return base.copyWith(
    scaffoldBackgroundColor: p.bg,
    colorScheme: ColorScheme.fromSeed(seedColor: p.tu, brightness: brilho).copyWith(
      primary: p.btn,
      onPrimary: p.btnTxt,
      secondary: p.tuTxt,
      surface: p.bg,
      onSurface: p.tinta,
      error: p.erro,
    ),
    textTheme: texto,

    // Campos de texto: fundo translúcido, sem sublinhado
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: p.campo,
      labelStyle: TextStyle(color: p.suave),
      border: cantos,
      enabledBorder: cantos,
      focusedBorder: cantos.copyWith(borderSide: BorderSide(color: p.tuTxt, width: 1.4)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
    ),

    // Botão principal: pílula escura (clara no tema escuro), como no site
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: p.btn,
        foregroundColor: p.btnTxt,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
        textStyle: GoogleFonts.getFont(letra, fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: p.tinta,
        side: BorderSide(color: p.linha),
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: p.tinta, shape: const StadiumBorder()),
    ),

    // Avisos em baixo, flutuantes e arredondados
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: p.btn,
      contentTextStyle: GoogleFonts.getFont(letra, color: p.btnTxt, fontSize: 14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
    ),

    // Folha de baixo (comentários) com o fundo da app
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: p.bg,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      showDragHandle: true,
    ),
    switchTheme: SwitchThemeData(
      trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.tu : p.trilho),
      thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? p.tuTxt : p.suave),
      trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
    ),
    sliderTheme: SliderThemeData(
      activeTrackColor: p.tinta,
      inactiveTrackColor: p.trilho,
      thumbColor: p.tinta,
      overlayColor: p.tinta.withValues(alpha: 0.08),
      trackHeight: 3,
    ),
    dividerColor: p.linha,
  );
}
