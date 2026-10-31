// Atualizar dentro da app: descarrega o APK novo para a cache (com barra de progresso) e entrega-o
// ao instalador do Android (PackageInstaller, no MainActivity.kt). Sem browser nem GitHub pelo meio.
// Da 1.ª vez o Android pede "permitir instalar desta fonte"; o "Instalar" do sistema aparece sempre.

import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';

import '../tema/paleta.dart';

class Instalador {
  Instalador._();

  // Canal para o lado Android (MainActivity.kt): "pasta" (cache) e "instalar" (caminho do APK)
  static const _canal = MethodChannel('anime_a_dois/instalar');

  // Texto da percentagem: "12,3 / 29,9 MB" (sem tamanho conhecido, só o que já veio)
  static String textoProgresso(int recebido, int? total) {
    String mb(int b) => (b / (1024 * 1024)).toStringAsFixed(1).replaceAll('.', ',');
    if (total == null || total <= 0) return '${mb(recebido)} MB';
    return '${mb(recebido)} / ${mb(total)} MB';
  }

  // Popup com a barra; descarrega e abre o instalador. Se falhar, oferece o browser (como antes).
  static Future<void> atualizar(BuildContext context, String link) async {
    final progresso = ValueNotifier<(int, int?)>((0, null)); // (recebido, total)
    final cliente = http.Client();
    var cancelado = false;

    // Janela da transferência (não fecha ao tocar fora; só pelo "Cancelar")
    final janela = showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        final p = Paleta.de(ctx);
        return PopScope(
          canPop: false,
          child: AlertDialog(
            icon: const Text('📦', style: TextStyle(fontSize: 36)),
            title: const Text('A descarregar…'),
            content: ValueListenableBuilder<(int, int?)>(
              valueListenable: progresso,
              builder: (_, v, __) {
                final (recebido, total) = v;
                final fracao = (total != null && total > 0) ? recebido / total : null;
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Barra (indeterminada enquanto não se sabe o tamanho)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: LinearProgressIndicator(value: fracao, minHeight: 8),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      fracao == null
                          ? textoProgresso(recebido, total)
                          : '${(fracao * 100).round()}%  ·  ${textoProgresso(recebido, total)}',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: p.suave),
                    ),
                  ],
                );
              },
            ),
            actions: [
              TextButton(
                onPressed: () {
                  cancelado = true;
                  cliente.close(); // corta a transferência a meio
                  Navigator.pop(ctx);
                },
                child: const Text('Cancelar'),
              ),
            ],
          ),
        );
      },
    );

    String? erro;
    try {
      // Pasta da cache da app (a mesma de cada vez: o APK antigo é substituído, não se acumulam)
      final pasta = await _canal.invokeMethod<String>('pasta');
      final ficheiro = File('$pasta/anime-a-dois-update.apk');

      // Pedido em stream (segue o redirecionamento do /app para o GitHub)
      final resposta = await cliente.send(http.Request('GET', Uri.parse(link))).timeout(const Duration(seconds: 20));
      if (resposta.statusCode != 200) throw HttpException('HTTP ${resposta.statusCode}');
      final total = resposta.contentLength;
      final saida = ficheiro.openWrite();
      var recebido = 0;
      try {
        await for (final bloco in resposta.stream.timeout(const Duration(seconds: 30))) {
          saida.add(bloco);
          recebido += bloco.length;
          progresso.value = (recebido, total);
        }
      } finally {
        await saida.close();
      }
      if (total != null && total > 0 && recebido != total) throw const HttpException('transferência incompleta');

      // Fecha a janela e passa ao instalador do Android
      if (context.mounted && !cancelado) Navigator.of(context, rootNavigator: true).pop();
      if (!cancelado) await _canal.invokeMethod('instalar', {'caminho': ficheiro.path});
    } catch (e) {
      if (!cancelado) {
        erro = '$e';
        if (context.mounted) Navigator.of(context, rootNavigator: true).pop();
      }
    } finally {
      cliente.close();
    }
    await janela;
    progresso.dispose();

    // Falhou (rede, espaço…): explica e deixa usar o browser como antes
    if (erro != null && context.mounted) {
      final browser = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Não deu para atualizar'),
          content: const Text('A transferência falhou. Tenta outra vez mais logo, ou descarrega pelo browser.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Fechar')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Abrir no browser')),
          ],
        ),
      );
      if (browser == true) await launchUrl(Uri.parse(link), mode: LaunchMode.externalApplication);
    }
  }
}
