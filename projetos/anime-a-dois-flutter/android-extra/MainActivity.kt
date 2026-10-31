// MainActivity da app (o workflow copia-o por cima do que o "flutter create" gera).
// Acrescenta o canal "anime_a_dois/instalar", usado para atualizar dentro da app:
//   - "pasta"    → devolve a pasta da cache (onde o Dart guarda o APK descarregado)
//   - "instalar" → entrega o APK ao instalador do Android (PackageInstaller; não precisa de FileProvider)
// O Android responde com um Intent de volta a esta Activity (onNewIntent): se pedir confirmação,
// abre o ecrã "Instalar" do sistema; se falhar, mostra um aviso.
package pt.gustavobiazini.anime_a_dois

import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageInstaller
import android.os.Build
import android.widget.Toast
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    companion object {
        private const val CANAL = "anime_a_dois/instalar"
        private const val ACAO_RESULTADO = "pt.gustavobiazini.anime_a_dois.RESULTADO_INSTALAR"
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CANAL).setMethodCallHandler { call, result ->
            when (call.method) {
                // Pasta da cache da app
                "pasta" -> result.success(cacheDir.absolutePath)
                // Instalar o APK do caminho indicado
                "instalar" -> {
                    val caminho = call.argument<String>("caminho")
                    if (caminho == null) {
                        result.error("sem_caminho", "Falta o caminho do APK", null)
                    } else {
                        try {
                            instalar(File(caminho))
                            result.success(true)
                        } catch (e: Exception) {
                            result.error("instalar", e.message, null)
                        }
                    }
                }
                else -> result.notImplemented()
            }
        }
    }

    // Abre uma sessão do PackageInstaller, copia o APK para lá e confirma.
    // O resultado chega por Intent a esta Activity (ver onNewIntent).
    private fun instalar(apk: File) {
        val instalador = packageManager.packageInstaller
        val parametros = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL)
        val id = instalador.createSession(parametros)
        instalador.openSession(id).use { sessao ->
            apk.inputStream().use { entrada ->
                sessao.openWrite("anime-a-dois.apk", 0, apk.length()).use { saida ->
                    entrada.copyTo(saida)
                    sessao.fsync(saida)
                }
            }
            // Intent de volta para esta Activity (singleTop → onNewIntent); mutável a partir do Android 12,
            // porque o sistema acrescenta-lhe o estado da instalação
            val volta = Intent(this, MainActivity::class.java).setAction(ACAO_RESULTADO)
            var bandeiras = PendingIntent.FLAG_UPDATE_CURRENT
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) bandeiras = bandeiras or PendingIntent.FLAG_MUTABLE
            val pendente = PendingIntent.getActivity(this, id, volta, bandeiras)
            sessao.commit(pendente.intentSender)
        }
    }

    // Resposta do instalador
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        if (intent.action != ACAO_RESULTADO) return
        when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            // O Android quer que a pessoa confirme: abre o ecrã "Instalar" (e, da 1.ª vez, a permissão da fonte)
            PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                @Suppress("DEPRECATION")
                val confirmar = intent.getParcelableExtra<Intent>(Intent.EXTRA_INTENT)
                if (confirmar != null) startActivity(confirmar.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
            }
            // Instalada (normalmente a app já foi fechada e reaberta pelo sistema antes de chegar aqui)
            PackageInstaller.STATUS_SUCCESS -> {}
            // Cancelado pela pessoa: não diz nada
            PackageInstaller.STATUS_FAILURE_ABORTED -> {}
            else -> {
                val msg = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE) ?: "erro desconhecido"
                Toast.makeText(this, "Não deu para instalar: $msg", Toast.LENGTH_LONG).show()
            }
        }
    }
}
