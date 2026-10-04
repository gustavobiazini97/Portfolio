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

Serve para registar os episódios que tu e a Andreia já viram, lado a lado. Começou com Naruto, Shippuden e Boruto; agora aceita qualquer anime do MyAnimeList.

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
| `series` | a biblioteca e as propostas: Naruto, Shippuden e Boruto do seed, mais as que vêm do MyAnimeList (capa, estado, cor, quem adicionou) |
| `episodios` | os episódios de cada série, com título, filler e recap |
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
- Biblioteca: fila de capas que desliza na horizontal, com as duas barrinhas de progresso por baixo de cada uma.
- Estados por série (a ver, em pausa, acabado). Os acabados vão para o fim da fila; dentro de cada estado, a série mais mexida vem primeiro.
- Tirar da biblioteca uma série que ainda ninguém começou.
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

**Adicionar série** (botão + no Início)
- Pesquisa e capas pelo **AniList**; títulos dos episódios pelo **Kitsu**; fillers e recaps pelo **Jikan** (MyAnimeList).
- O Jikan público está em baixo desde 28/08/2026 (504/timeouts; issue #612 do jikan-rest). A app tenta-o 6 s e, se falhar, fica 30 min sem tentar; as séries ficam com `sincronizada_em` a null e a página da série volta a tentar (no máximo 1×/hora) até os fillers chegarem.
- **Quem fala com as APIs é o telemóvel**, não o servidor (o alwaysdata não chegava ao Jikan). O servidor valida tudo em `app/models/DadosAnime.php` (capas só de anilist.co, myanimelist.net ou kitsu). Endereços em `apis` no config.
- "Adicionar" põe a série logo na biblioteca; "Quero ver com Andreia" fica como proposta.
- Vêm todos os episódios com título; filler e recap quando o Jikan responde (os do Kitsu nunca apagam os do Jikan).
- Séries em emissão: ao abrir a série (no máximo 1×/hora; 12 h depois de dados completos), o telemóvel vai buscar os episódios novos e avisa ("Saíram 3 episódios novos · toca para ver").
- As capas que faltam (as do Naruto, na primeira visita) também são buscadas pelo telemóvel.
- A mesma série não entra duas vezes (o `mal_id` é único; as do Naruto já têm o seu).

**Bibliotecas individuais** (tabela `bibliotecas`)
- Cada um tem a sua biblioteca e o seu estado em cada série (a ver, em pausa, acabado).
- Série **conjunta** = está nas duas bibliotecas; só passa a conjunta por convite aceite. Capas das conjuntas têm os dois avatares.
- Ao adicionar: "Só para mim" ou "Quero ver com…" (fica na tua + convite). Numa série só tua, "Ver com…" envia o convite.
- Fila "A … está a ver" com as séries só do par: tocar abre uma folha com o progresso dele e "Adicionar à minha biblioteca" (passa a conjunta e o par é avisado).
- Tirar da biblioteca: só da tua e só se ainda não marcaste episódios dela; a do par fica igual.
- Notificações de episódios e comentários só nas séries conjuntas; séries só tuas não avisam o par.
- O `migrate.php` criou as bibliotecas a partir das séries antigas (as do seed para os dois, as adicionadas só para quem as adicionou).

**Perfil do par e atualizações**
- Tocar na foto do par (Início) abre o perfil dele: último episódio visto e a biblioteca (`?c=perfil&a=pessoa&id=`; só o par).
- Perfil → "Atualizações": mostra a versão (id da última novidade) e o botão "Procurar atualizações" (apaga as caches e recarrega).
- Service worker v8: CSS/JS com rede primeiro (a app atualiza ao abrir; offline usa a cache).

**Amigos**
- Página Amigos (ícone no Início): lista, pedidos, link de convite e adicionar por utilizador exato (sem pesquisa parcial).
- Link de convite (`convites_amigo`): uso único, 7 dias; abre o registo (que é fechado) e a conta nasce amiga de quem convidou, sem par e com a biblioteca vazia.
- Os amigos veem o perfil e a biblioteca uns dos outros (`?c=perfil&a=pessoa`); não partilham séries. O par é só um: `users.par_id` (o `migrate.php` liga as duas contas existentes).
- Tabelas novas: `amizades` (duas linhas por amizade), `pedidos_amizade`, `convites_amigo`.

**Séries com amigos** (companheiro por série)
- Cada série pode ser vista com UMA pessoa (o par ou um amigo): `bibliotecas.com_id` nas duas linhas (cada um aponta para o outro). O Vs, os episódios dele, os comentários, as estatísticas e as notificações dessa série são só com esse companheiro.
- Convites "Quero ver contigo" passam a ser da tabela `convites_serie` (de → para). "Ver com…" (série aberta) e "Só para mim / Ver com…" (pesquisa) escolhem a pessoa; aceitar liga as bibliotecas (e mantém o progresso de quem já tinha a série).
- Fila "está a ver" do par e perfil de um amigo: tocar numa série abre uma folha com "Adicionar só para mim" ou "Ver com …" (aviso ao outro; só se ele ainda não a vê com outra pessoa).
- "Deixar de ver juntos" separa (cada um fica com o seu progresso); terminar a amizade também separa.
- Comentários: só os teus e os do companheiro da série (antes eram de qualquer conta).
- O `migrate.php` liga as séries que o casal já tinha as duas e converte os convites antigos (marcador `com_id_migrado` em `config_app`).

**Quero ver contigo**
- Convites recebidos ("Bora ver" / "Agora não") e enviados ("à espera de…" / "Cancelar").

**O que há de novo** (popup)
- Ao abrir o Início, cada um vê as novidades que ainda não viu; ao fechar, ficam vistas até à próxima.
- A lista está em `database/novidades.json` (`users.novidades_vistas` guarda a última vista). Contas novas começam sem nada por ver.
- **A cada atualização da app, acrescentar uma entrada nova no fim desse ficheiro.**

**Comentários**
- Cada episódio tem os seus comentários. Os dois veem tudo e cada um apaga os seus.

**Perfil**
- Foto (tira-se ou escolhe-se; é recortada e reduzida).
- Nome, nome de utilizador e palavra-passe editáveis.
- Terminar sessão.

**Notificações**
- O par recebe uma notificação quando marcas episódios, comentas, adicionas ou propões uma série (com a capa) e quando aceitas uma proposta.
- No perfil, cada um escolhe que tipos quer receber e por onde: telemóvel (Web Push), email, ou os dois.
- Há um botão para testar.

**Estatísticas** (o ecrã mais recente)
- Cartões tu vs Andreia com episódios, horas (duração do MyAnimeList, ou 23 min nas do Naruto) e fillers vistos.
- Os separadores deslizam quando há muitas séries.
- Ritmo: episódios por semana nas últimas 6 semanas e a previsão de quando acabas a série.
- Arcos:
  - os canónicos estão em `database/arcos.json`;
  - os intervalos entre eles são preenchidos sozinhos, como "Filler" ou "Episódios avulsos";
  - cada arco tem duas barras e um selo: ✓ (acabaram os dois) ou ½ (acabou um).
  - O Boruto fica sem arcos, por falta de dados fiáveis.
- Curiosidades: comentários trocados e máximo de episódios marcados num dia.

**Visual**
- Vidro (glassmorphism), letra pequena e arredondada (M PLUS Rounded 1c).
- Sálvia para ti (`#B9D3B0`), alperce para a Andreia (`#F5C89A`) e uma cor de destaque por série (as novas recebem uma de 8 cores pastel).
- Tema claro/escuro com botão, guardado no telemóvel.
- Logótipo com dois ecrãs empilhados.

**App instalável (PWA)**
- Instala-se no Android, com atalhos por série no ícone e uma página offline.
- O ecrã de arranque mostra só o logótipo sobre fundo claro, sem quadrado. É a correção de hoje e ainda não foi confirmada no telemóvel.

### Dados dos episódios

- **Fillers:** vêm de `../naruto-fillers/data/fillers.json`, o projeto irmão no portfólio.
- **Títulos dos 1013 episódios:** estão em `database/episodios.json`. São gerados por `database/titulos.py`, que o workflow "Títulos Anime a Dois" corre no GitHub.
- **Arcos:** estão em `database/arcos.json`.
- **Séries novas:** tudo vem do Jikan (`jikan_url` no config), pedido pelo browser. Colunas novas em bases de dados antigas são acrescentadas pelo `migrate.php`.

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
| 04/10 | Biblioteca, adicionar série pelo MyAnimeList e "Quero ver contigo" |
| 04/10 | Jikan chamado pelo telemóvel (o alwaysdata não chega lá): pesquisa rápida e sem erro |
| 04/10 | Popup "O que há de novo" |
| 04/10 | AniList + Kitsu no lugar do Jikan (em baixo); fillers chegam quando o Jikan voltar |
| 04/10 | Bibliotecas individuais, séries conjuntas por convite |
| 04/10 | Perfil do par, adicionar séries da fila do par, botão de atualizações |
| 04/10 | Amigos: link de convite, pedidos, perfil do amigo, `par_id` |
| 04/10 | Séries com amigos: companheiro por série (`com_id`), convites por pessoa, comentários isolados |

---

## 3. O que falta

- Confirmar no telemóvel o arranque sem quadrado. Para ver já, desinstala e volta a instalar a app.
- Confirmar no telemóvel a entrega real das notificações. Só foi testada com um servidor falso, e o email depende do `mail()` do alwaysdata.
- Novas ideias que ainda tens para a app.
