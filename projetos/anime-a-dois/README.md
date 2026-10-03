# Anime a Dois

Registo dos episódios vistos por duas pessoas, lado a lado: Naruto, Naruto Shippuden e Boruto.

PHP + MySQL, com um mini-MVC e o Eloquent (`illuminate/database`) a correr sem o resto do Laravel.
O GitHub Pages não corre PHP: a app funciona no WAMP, no Termux ou num alojamento com PHP.

## Estado

- [x] Base de dados e seed das séries (1013 episódios, com os fillers marcados)
- [x] Registo com nome personalizado; fecha sozinho depois da 2.ª conta
- [x] Login e logout (sessões, `password_hash`, CSRF)
- [ ] Página da série: filtro, progresso dos dois e marcar episódios
- [ ] Dashboard: último episódio do par e o Vs
- [ ] PWA e alojamento

## Instalar (WAMP)

```bash
# 1. Dependências
composer install

# 2. Configuração: copiar e preencher (no WAMP, root sem palavra-passe)
cp config/config.example.php config/config.php

# 3. Base de dados e episódios
mysql -u root < database/schema.sql
php database/seed.php
```

Depois aponta o browser para a pasta `public/`, por exemplo `http://localhost/anime-a-dois/public/`,
ou usa o servidor do PHP:

```bash
php -S localhost:8000 -t public
```

A primeira pessoa a abrir cria a conta 1, e a segunda cria a conta 2. A partir daí o registo fecha.

## Estrutura

```
app/
  bootstrap.php     autoload, helpers e ligação ao Eloquent
  core/             Controller base, Database, helpers das views
  controllers/      AuthController, HomeController
  models/           User, Serie, Episodio (a lógica vive aqui)
  views/            layout, auth, home
config/             config.example.php (o config.php fica fora do Git)
database/           schema.sql e seed.php
public/             index.php (front controller) e css/
```

Rotas no formato `index.php?c=<controller>&a=<ação>`; só as que estão listadas em `public/index.php` existem.

## Dados

Os episódios vêm de `../naruto-fillers/data/fillers.json` (o projeto irmão neste portfólio).
O `seed.php` pode correr-se outra vez sempre que esse ficheiro for atualizado: atualiza sem duplicar e não mexe nos episódios vistos.
