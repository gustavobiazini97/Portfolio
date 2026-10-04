# Changelog — Anime a Dois (e portfólio como app)

> Registo do que foi feito, para quem pegar no projeto a seguir (pessoa ou chat) saber o ponto de situação.
> Atualizar este ficheiro sempre que houver mudanças.

3 e 4 de outubro de 2026 · repo `gustavobiazini97/Portfolio`

---

## 1. Portfólio como app (PWA)

Já está tudo no `main` (PRs #11, #12 e #13).

- **Instalável no Android** a partir do browser, com `manifest.json`, service worker, ícones e uma página offline.
- **Ícone de arranque sem cartão** à volta. O chip "Android" no portfólio também foi atualizado.
- **Botões "Instalar" e "Partilhar"**, mais atalhos no ícone (toque longo) e capturas de ecrã para a janela de instalação.

---

## 2. Anime a Dois

Serve para registar os episódios de Naruto, Naruto Shippuden e Boruto que tu e a Andreia já viram, lado a lado.

- **Onde está:** `projetos/anime-a-dois`, no ramo `feat/anime-a-dois`. Juntado ao `main` no PR #14.
- **Online:** alojada no alwaysdata, com o site a apontar para `anime-a-dois/public/`.

### Stack e arquitetura

- PHP 8.3 com um mini-MVC: front controller `public/index.php?c=&a=` e uma lista branca de rotas.
- Eloquent (`illuminate/database`) a correr sem o resto do Laravel.
- MySQL/MariaDB, com `database/migrate.php` (`CREATE TABLE IF NOT EXISTS`) e `database/seed.php` (upsert que não mexe nos vistos).
- A lógica fica nos Models. Os controllers usam try/catch, mensagens flash e PRG. Todo o CSS está em `public/css/app.css`, com comentários em PHP e CSS.

### Base de dados

| Tabela | Para quê |
|---|---|
| `users` | as duas contas (nome, username, palavra-passe) |
| `series` | Naruto, Shippuden e Boruto |
| `episodios` | 1013 episódios, com título e indicação de filler |
| `vistos` | quem viu cada episódio e quando |
| `fotos` | fotos de perfil (webp de 320 px, guardadas na BD) |
| `comentarios` | comentários por episódio |
| `preferencias` | o que cada um quer receber nas notificações, e por onde |
| `subscricoes` | telemóveis subscritos às notificações |
| `config_app` | chaves VAPID, geradas no servidor |

### Funcionalidades

**Contas**
- Registo com nome personalizado. Fecha sozinho depois da 2.ª conta.
- Login e logout com sessões, `password_hash` e CSRF.

**Início**
- Separadores por série.
- Último episódio que o par viu, num cartão que abre a série nesse episódio.
- Vs da série: uma barra por pessoa e quem vai à frente.
- Botão de convite enquanto o par ainda não tem conta.
- Botões de Estatísticas, tema e perfil (o teu avatar) na barra de topo.

**Página da série**
- Mapa dos dois com barras sempre do mesmo tamanho, seja qual for o comprimento do nome.
- Pista de cartões que desliza na horizontal. Cada cartão mostra:
  - o número e o título do episódio;
  - a etiqueta "filler" bem visível;
  - os avatares de quem viu;
  - um balão quando o episódio tem comentários.
- Os episódios vistos têm um destaque próprio.
- Marcar sem recarregar a página, e seleção múltipla para marcar vários de uma vez.
- Cabe sempre no ecrã, sem scroll, também na app instalada (o botão fica acima da barra do Android).

**Comentários**
- Cada episódio tem os seus comentários. Os dois veem tudo e cada um apaga os seus.

**Perfil**
- Foto (tira-se ou escolhe-se; é recortada e reduzida).
- Nome, nome de utilizador e palavra-passe editáveis.
- Terminar sessão.

**Notificações**
- O par recebe uma notificação quando marcas episódios ou comentas.
- No perfil, cada um escolhe que tipos quer receber e por onde: telemóvel (Web Push), email, ou os dois.
- Há um botão para testar.

**Estatísticas** (o ecrã mais recente)
- Cartões tu vs Andreia com episódios, horas (23 min por episódio) e fillers vistos.
- Ritmo: episódios por semana nas últimas 6 semanas e a previsão de quando acabas a série.
- Arcos:
  - os canónicos estão em `database/arcos.json`;
  - os intervalos entre eles são preenchidos sozinhos, como "Filler" ou "Episódios avulsos";
  - cada arco tem duas barras e um selo: ✓ (acabaram os dois) ou ½ (acabou um).
  - O Boruto fica sem arcos, por falta de dados fiáveis.
- Curiosidades: comentários trocados e máximo de episódios marcados num dia.

**Visual**
- Vidro (glassmorphism), letra pequena e arredondada (M PLUS Rounded 1c).
- Sálvia para ti (`#B9D3B0`), alperce para a Andreia (`#F5C89A`) e uma cor de destaque por série.
- Tema claro/escuro com botão, guardado no telemóvel.
- Logótipo com dois ecrãs empilhados.

**App instalável (PWA)**
- Instala-se no Android, com atalhos por série no ícone e uma página offline.
- O ecrã de arranque mostra só o logótipo sobre fundo claro, sem quadrado. É a correção de hoje e ainda não foi confirmada no telemóvel.

### Dados dos episódios

- **Fillers:** vêm de `../naruto-fillers/data/fillers.json`, o projeto irmão no portfólio.
- **Títulos dos 1013 episódios:** estão em `database/episodios.json`. São gerados por `database/titulos.py`, que o workflow "Títulos Anime a Dois" corre no GitHub.
- **Arcos:** estão em `database/arcos.json`.

### Deploy

O workflow `.github/workflows/anime-a-dois.yml` corre em cada push para `feat/anime-a-dois` ou `main` que mexa na pasta da app. Faz isto:

1. `composer install`;
2. gera o `config.php` a partir do secret `AD_PASSWORD` (a palavra-passe nunca passa pelo chat nem pelo código);
3. envia a app por SSH (`rsync --delete`);
4. corre `migrate.php` e `seed.php` no servidor.

### Histórico (commits do Anime a Dois)

| Data | Commit |
|---|---|
| 03/10 | Base de dados, seed e login/registo |
| 03/10 | Deploy automático para o alwaysdata |
| 03/10 | Visual em vidro, página da série e dashboard |
| 03/10 | App instalável (PWA) |
| 03/10 | Marcar sem recarregar e seleção múltipla |
| 03/10 | Página da série cabe sempre no ecrã |
| 03/10 | Logótipo novo (dois ecrãs empilhados) |
| 03/10 | Botão "Instalar app" no login e no registo |
| 03/10 | Botão da série acima da barra do Android |
| 03/10 | Cartões compactos, página da série sem scroll |
| 03/10 | Episódios vistos com destaque próprio |
| 03/10 | Perfil com foto e nome de utilizador editável |
| 03/10 | Cartões do início clicáveis e convite ao par |
| 03/10 | "Quem viu" com avatares |
| 03/10 | Títulos de todos os episódios e "filler" bem visível |
| 03/10 | Comentários por episódio |
| 04/10 | Mapa da série com barras sempre iguais |
| 04/10 | Notificações ao par (telemóvel e email) |
| 04/10 | Ecrã de Estatísticas |
| 04/10 | Ícone e ecrã de arranque sem quadrado |
| 04/10 | Cartão do Anime a Dois no portfólio e este changelog |

---

## 3. O que falta

- Confirmar no telemóvel o arranque sem quadrado. Para ver já, desinstala e volta a instalar a app.
- Confirmar no telemóvel a entrega real das notificações. Só foi testada com um servidor falso, e o email depende do `mail()` do alwaysdata.
- Novas ideias que ainda tens para a app.
