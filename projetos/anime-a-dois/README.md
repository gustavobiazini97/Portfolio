# Anime a Dois

Registo dos episódios vistos por duas pessoas, lado a lado: Naruto, Naruto Shippuden e Boruto.

PHP + MySQL, com um mini-MVC e o Eloquent (`illuminate/database`) a correr sem o resto do Laravel.
O GitHub Pages não corre PHP: a app funciona no WAMP, no Termux ou num alojamento com PHP.

## Estado

- [x] Base de dados e seed das séries (1013 episódios, com os fillers marcados)
- [x] Registo com nome personalizado; fecha sozinho depois da 2.ª conta
- [x] Login e logout (sessões, `password_hash`, CSRF)
- [x] Página da série: mapa dos dois, pista de episódios que desliza, marcar/desmarcar
- [x] Dashboard: separadores por série, último episódio do par e o Vs
- [x] Visual em vidro (sálvia + alperce), tema claro/escuro
- [x] Deploy automático para o alwaysdata
- [x] PWA: instalável no Android, atalhos por série no ícone, página offline
- [x] Perfil: foto (guardada na base de dados), nome, utilizador, palavra-passe, terminar sessão
- [x] Comentários por episódio (os dois veem; cada um apaga os seus)
- [x] Notificações ao par (episódios e comentários) por telemóvel (Web Push) e/ou email, escolhidas no perfil
- [x] Estatísticas por série: números dos dois, ritmo semanal com previsão, arcos (`database/arcos.json`) e curiosidades
- [x] Títulos de todos os episódios (`database/titulos.py`, workflow "Títulos Anime a Dois")
- [x] API REST com login por token (`public/api.php`, documentada em `docs/API.md`), usada pela app Flutter em `../anime-a-dois-flutter`

## Online (alwaysdata)

A app está alojada no alwaysdata e instala-se no Android a partir do browser.
O deploy é automático: cada push que mexa nesta pasta corre o workflow
`.github/workflows/anime-a-dois.yml`, que:

1. instala as dependências (`composer install`);
2. gera o `config.php` com os dados do alwaysdata;
3. envia a app por SSH;
4. corre `database/migrate.php` e `database/seed.php` no servidor.

Só precisa do secret **`AD_PASSWORD`** no repo (Settings → Secrets and variables → Actions).
As chaves VAPID das notificações são geradas no próprio servidor na primeira utilização (tabela `config_app`).
No painel do alwaysdata, o site aponta para a pasta `anime-a-dois/public/`.

## Correr localmente (WAMP ou Termux)

```bash
# 1. Dependências
composer install

# 2. Configuração: copiar e preencher (no WAMP, root sem palavra-passe)
cp config/config.example.php config/config.php

# 3. Criar a base de dados anime_a_dois (phpMyAdmin ou mysql) e depois:
php database/migrate.php
php database/seed.php

# 4. Servidor
php -S localhost:8000 -t public
```

A primeira pessoa a abrir cria a conta 1, e a segunda cria a conta 2. A partir daí o registo fecha.

## Estrutura

```
app/
  bootstrap.php     autoload, helpers e ligação ao Eloquent
  core/             Controller base, Database, helpers das views
  api/              controllers da API REST (AuthApi, SeriesApi, ComentariosApi, PerfilApi)
  controllers/      AuthController, HomeController, SerieController, PerfilController, EstatisticasController
  models/           User, Serie, Episodio, Foto, Comentario, Preferencia, Subscricao, Notificador, Estatisticas, Token (a lógica vive aqui)
  views/            layout, auth, home, serie, perfil
config/             config.example.php (o config.php fica fora do Git)
database/           schema.sql, migrate.php e seed.php
docs/API.md         documentação da API REST
public/             index.php (site), api.php (API), css/ e js/
```

Rotas no formato `index.php?c=<controller>&a=<ação>`; só as que estão listadas em `public/index.php` existem.

## Dados

Os episódios vêm de `../naruto-fillers/data/fillers.json` (o projeto irmão neste portfólio).
O `seed.php` pode correr-se outra vez sempre que esse ficheiro for atualizado: atualiza sem duplicar e não mexe nos episódios vistos.
