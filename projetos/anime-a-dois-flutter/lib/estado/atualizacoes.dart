// Avisos de versão nova: ao abrir, a app lê o versao.json da release (gerado pelo workflow do APK)
// e, se o build publicado for maior do que o instalado, mostra um popup com as novidades e o botão
// para descarregar. "Mais tarde" só volta a avisar no dia seguinte.

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../tema/paleta.dart';

// A última versão publicada
class VersaoNova {
  final String versao; // "1.3.0"
  final int build; // número da compilação no GitHub (sobe sempre)
  final List<String> notas;
  final String apk; // link para descarregar

  VersaoNova.deJson(Map<String, dynamic> j)
      : versao = j['versao'] as String? ?? '',
        build = (j['build'] as num?)?.toInt() ?? 0,
        notas = ((j['notas'] as List?) ?? []).map((n) => n as String).toList(),
        apk = j['apk'] as String? ?? linkApp;
}

class Atualizacoes {
  Atualizacoes._();

  static const _chaveAdiada = 'atualizacao_adiada'; // "build|data" do último "Mais tarde"

  // Versão instalada, ex.: "1.2.0 (build 41)"
  static Future<String> versaoInstalada() async {
    final info = await PackageInfo.fromPlatform();
    return '${info.version} (build ${info.buildNumber})';
  }

  // Há uma versão publicada mais recente do que a instalada? (null se não, ou sem rede)
  static Future<VersaoNova?> procurar() async {
    try {
      final r = await http.get(Uri.parse(linkVersao)).timeout(const Duration(seconds: 10));
      if (r.statusCode != 200) return null;
      final nova = VersaoNova.deJson(jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>);
      final info = await PackageInfo.fromPlatform();
      final instalado = int.tryParse(info.buildNumber) ?? 0;
      return nova.build > instalado ? nova : null;
    } catch (_) {
      return null; // sem rede ou ficheiro ainda inexistente: fica para a próxima
    }
  }

  // Ao abrir a app: mostra o popup se houver versão nova (e não foi adiada hoje)
  static Future<void> verificarAoAbrir(BuildContext context) async {
    final nova = await procurar();
    if (nova == null || !context.mounted) return;
    final prefs = await SharedPreferences.getInstance();
    final hoje = DateTime.now().toIso8601String().substring(0, 10);
    if (prefs.getString(_chaveAdiada) == '${nova.build}|$hoje') return; // "Mais tarde" hoje
    if (!context.mounted) return;
    final adiou = await mostrar(context, nova);
    if (adiou) await prefs.setString(_chaveAdiada, '${nova.build}|$hoje');
  }

  // Popup da versão nova; devolve true se a pessoa escolheu "Mais tarde"
  static Future<bool> mostrar(BuildContext context, VersaoNova nova) async {
    final p = Paleta.de(context);
    final r = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Text('🚀', style: TextStyle(fontSize: 40)),
        title: Text('Nova versão ${nova.versao}'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Há uma atualização do Anime a Dois. Instala-se por cima, sem perderes nada.', style: TextStyle(color: p.suave)),
            if (nova.notas.isNotEmpty) ...[
              const SizedBox(height: 14),
              const Text('O que há de novo', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              for (final n in nova.notas)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('•  ', style: TextStyle(color: p.tuTxt, fontWeight: FontWeight.w800)),
                      Expanded(child: Text(n)),
                    ],
                  ),
                ),
            ],
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, 'tarde'), child: const Text('Mais tarde')),
          FilledButton(onPressed: () => Navigator.pop(ctx, 'atualizar'), child: const Text('Atualizar')),
        ],
      ),
    );
    if (r == 'atualizar') {
      // Abre o link no browser: descarrega o APK e o Android pergunta se quer instalar
      await launchUrl(Uri.parse(nova.apk), mode: LaunchMode.externalApplication);
      return false;
    }
    return true;
  }
}
