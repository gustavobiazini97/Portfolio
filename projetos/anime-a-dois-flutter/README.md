# Anime a Dois — app Flutter

App Android nativa do [Anime a Dois](../anime-a-dois). Usa a **API REST** do site
(`api.php`, documentada em [`../anime-a-dois/docs/API.md`](../anime-a-dois/docs/API.md)):
os dados são os mesmos, por isso o que marcas na app aparece no site e vice-versa.

## Descarregar o APK

Cada push que mexa nesta pasta compila o APK no GitHub Actions (workflow **APK Anime a Dois**).
O APK mais recente (debug, arm64) fica sempre no mesmo link:

**https://github.com/gustavobiazini97/Portfolio/releases/download/anime-a-dois-apk/anime-a-dois.apk**

A app usa a API em `https://animeadois.alwaysdata.net/api`.

No telemóvel: abrir o link, descarregar e instalar (o Android pede para autorizar "fontes desconhecidas").

### Atualizar sem desinstalar (opcional)

Sem configuração, cada APK é assinado com uma chave diferente e o Android só deixa instalar por cima
depois de desinstalar a versão anterior. Para ter sempre a mesma chave, cria-a uma vez (no Termux ou no PC):

```bash
keytool -genkeypair -keystore anime.keystore -alias androiddebugkey \
        -storepass android -keypass android -keyalg RSA -keysize 2048 -validity 10000 \
        -dname "CN=Anime a Dois"
base64 -w0 anime.keystore
```

e cola o resultado num secret do repo chamado **`ANDROID_KEYSTORE_B64`**
(Settings → Secrets and variables → Actions). Guarda o `anime.keystore` fora do Git.

## Ecrãs

| Ecrã | O que faz |
|---|---|
| Login / Criar conta | Token guardado no telemóvel; "Criar conta" só aparece enquanto há lugar |
| Início | Último episódio que o par viu e um cartão por série com as barras dos dois e o Vs |
| Série | Mapa dos dois, pista de cartões que desliza, régua para saltar episódios, marcar com um toque, seleção múltipla com toque longo |
| Comentários | Folha por baixo da pista; os teus apagam-se com toque longo |
| Estatísticas | Tu vs par, ritmo semanal, previsão, arcos com selo ✓ / ½, curiosidades |
| Perfil | Foto (galeria/câmara), nome, utilizador, palavra-passe, notificações, tema, terminar sessão |

Visual igual ao site: vidro fosco, sálvia (tu) + alperce (par), cor de acento por série,
letra M PLUS Rounded 1c e tema claro/escuro.

## Estrutura

```
lib/
  main.dart            arranque: Login ou Início conforme haja token
  config.dart          endereço da API (muda-se com --dart-define=API_URL=…)
  api/api.dart         cliente HTTP: token, JSON, erros (ApiErro)
  api/modelos.dart     JSON → classes (Serie, Episodio, Comentario, Estatisticas…)
  estado/sessao.dart   sessão (token) e tema, partilhados pela app
  tema/                cores (as mesmas do app.css) e ThemeData
  widgets/comum.dart   Fundo, Vidro, Avatar, BarraProgresso, LinhaPessoa…
  ecras/               login, registo, inicio, serie, comentarios, estatisticas, perfil
test/modelos_test.dart testes da conversão do JSON
assets/icon/           ícone (o mesmo da PWA)
```

## Correr no PC (com o Flutter instalado)

A pasta `android/` não está no Git; gera-se uma vez:

```bash
flutter create --platforms android --org pt.gustavobiazini --project-name anime_a_dois .
flutter pub get
dart run flutter_launcher_icons
flutter run
```

Para usar o PHP local em vez do alwaysdata (emulador Android):

```bash
php -S 0.0.0.0:8000 -t ../anime-a-dois/public
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api.php
```

> Em builds de release, o Android só deixa usar `http://` (sem s) se isso for permitido no manifest;
> com o alwaysdata (`https://`) não é preciso nada.
