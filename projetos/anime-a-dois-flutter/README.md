# Anime a Dois — app Flutter

App Android nativa do [Anime a Dois](../anime-a-dois). Usa a **API REST** do site
(`api.php`, documentada em [`../anime-a-dois/docs/API.md`](../anime-a-dois/docs/API.md)):
os dados são os mesmos, por isso o que marcas na app aparece no site e vice-versa.

## Descarregar o APK

Cada push que mexa nesta pasta compila o APK no GitHub Actions (workflow **APK Anime a Dois**).
O APK mais recente (release, arm64) fica sempre no mesmo link:

**https://github.com/gustavobiazini97/Portfolio/releases/download/anime-a-dois-apk/anime-a-dois.apk**

A app usa a API em `https://animeadois.alwaysdata.net/api.php`.

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

Os mesmos do site (API v2): cada pessoa tem a sua biblioteca e cada série pode ser vista com o par e/ou amigos.

| Ecrã | O que faz |
|---|---|
| Entrar / Criar conta | Token guardado no telemóvel; "Criar conta" enquanto há lugar para o casal; "Tenho um convite" para amigos |
| Início | Último episódio do par, biblioteca em capas, série escolhida com o Vs e o estado, ver com…, deixar de ver juntos, tirar, convites "Quero ver contigo" e a fila do par |
| Adicionar | Pesquisa no AniList (pelo telemóvel) e episódios do Jikan/Kitsu; "Adicionar" ou "Ver com…" |
| Série | Mapa com todos os companheiros, pista de cartões, régua, marcar com um toque, seleção múltipla com toque longo |
| Comentários | Só os teus e os de quem vê a série contigo |
| Estatísticas | Tu e cada companheiro, ritmo semanal, previsão, arcos, curiosidades |
| Amigos | Pedidos, link de convite (copiar), pedir por utilizador, desfazer amizade |
| Perfil de amigo | Último episódio e biblioteca dele (respeita "só juntos"); juntar-se a séries |
| Perfil | Foto, nome, utilizador, palavra-passe, 6 paletas, privacidade, notificações, tema, sair, apagar conta |

## Estrutura

```
lib/
  main.dart            arranque: Login ou Início conforme haja token
  config.dart          endereço da API (muda-se com --dart-define=API_URL=…)
  api/api.dart         cliente HTTP: token, JSON, erros (ApiErro)
  api/modelos.dart     JSON → classes (Serie, ItemBiblioteca, Episodio, ConviteSerie, Estatisticas…)
  api/anime.dart       pesquisa no AniList e episódios do Jikan/Kitsu (pelo telemóvel)
  estado/sessao.dart   sessão (token) e tema, partilhados pela app
  tema/                cores (as mesmas do app.css) e ThemeData
  widgets/comum.dart   Fundo, Vidro, Avatar, BarraProgresso, LinhaPessoa…
  ecras/               login, registo, inicio, adicionar, serie, comentarios, estatisticas,
                       amigos, pessoa, perfil
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
