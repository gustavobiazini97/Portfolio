# API REST do Anime a Dois (v2)

API em JSON por cima dos mesmos Models do site (`User`, `Serie`, `Amizade`, `Comentario`, …), com as
mesmas regras: cada pessoa tem a sua **biblioteca**, cada série pode ser vista **com** uma ou mais
pessoas (par e/ou amigos), os **comentários** só se veem entre quem vê a série junto, e a
**privacidade** ("só o que vemos juntos") é respeitada no perfil dos amigos.

É usada pela app Flutter (`projetos/anime-a-dois-flutter`). O site continua a funcionar como antes.

- **Base:** `https://animeadois.alwaysdata.net/api.php`
  - Também responde em `/api/<rota>` (pelo `.htaccess`) e em `api.php?rota=/<rota>`.
- **Formato:** pedidos com `Content-Type: application/json` (ou formulário); respostas sempre JSON.
- **Ficheiros:** `public/api.php` (rotas), `app/api/*Api.php` (controllers), `app/models/Token.php`.

## Autenticação

1. `POST /auth/login` devolve um `token`.
2. Os outros pedidos levam `Authorization: Bearer <token>` (alternativa: `X-Auth-Token: <token>`).
3. Cada telemóvel tem o seu token; a base de dados só guarda o **sha256** (tabela `tokens`).
4. Expira ao fim de **90 dias sem uso**; cada uso renova o prazo.
5. Mudar a palavra-passe termina as sessões dos outros telemóveis e as sessões mantidas no site.

## Respostas

Sucesso: `{ "ok": true, ... }`. Erro: `{ "ok": false, "mensagem": "..." }`, pronta a mostrar.

| Código | Quando |
|---|---|
| 200 / 201 | Correu bem (201 ao criar conta, série ou comentário) |
| 401 | Sem token, token errado ou expirado → voltar ao login |
| 403 | Série fora da tua biblioteca, ou comentário de outra pessoa |
| 404 | Rota, série, episódio, comentário ou pessoa inexistente |
| 405 | Rota existe, mas com outro método |
| 422 | Erro de validação (mensagem do Model) |
| 500 | Erro no servidor ou na base de dados |

## Rotas

🔓 = pública · 🔒 = precisa de token

### Conta

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/auth/estado?convite=` | 🔓 | | `registoAberto`, `convite` (quem convidou) |
| POST | `/auth/login` | 🔓 | `username`, `password`, `dispositivo?` | `token`, `user` |
| POST | `/auth/registo` | 🔓 | `nome`, `username`, `password`, `password_confirmar`, `convite?` (código ou link) | `token`, `user` (201) |
| POST | `/auth/logout` | 🔒 | | termina a sessão deste telemóvel |
| GET | `/eu` | 🔒 | | `user`, `parceiro`, `paleta`, `paletas`, `soJuntos`, `admin`, `ligados`, `pedidosAmigos` |

### Início e séries

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/inicio` | 🔒 | | `biblioteca[]`, `doPar[]`, `convites[]`, `ultimoPar`, `ultimoTu`, `serieAtual`, `ligados`, `esperaPar`, `pedidosAmigos`, `jaCa` |
| GET | `/series` | 🔒 | | a tua biblioteca |
| GET | `/series/{slug}` | 🔒 | | `serie`, `estado`, `podeRemover`, `tu`, `companheiros[]`, `resumo`, `episodios[]` |
| POST | `/series/{slug}/vistos` | 🔒 | `episodios: [ids]`, `acao: marcar \| desmarcar \| alternar` | `visto`, `numeros`, `progresso`, `mensagem` |
| GET | `/series/{slug}/estatisticas` | 🔒 | | `serie`, `minutosPorEp`, `estatisticas` |

### Biblioteca

| Método | Rota | | Corpo | O que faz |
|---|---|---|---|---|
| POST | `/biblioteca` | 🔒 | `mal_id`, `info`, `episodios`, `fonte`, `modo: ver \| propor`, `com?` | adiciona (e convida `com` em "propor") |
| PUT | `/series/{slug}/estado` | 🔒 | `estado: a_ver \| pausa \| acabado` | muda o estado na tua biblioteca |
| POST | `/series/{slug}/convites` | 🔒 | `com` | "Quero ver contigo" |
| POST | `/series/{slug}/convites/aceitar` | 🔒 | `de` | aceita: passam a ver juntos |
| DELETE | `/series/{slug}/convites/{id}` | 🔒 | | recusa (ou cancela o que enviaste) |
| POST | `/series/{slug}/juntar` | 🔒 | `com?` | entra numa série de alguém ligado a ti |
| DELETE | `/series/{slug}/juntos/{id}` | 🔒 | | deixam de ver juntos |
| DELETE | `/series/{slug}` | 🔒 | | tira da tua biblioteca (só sem episódios marcados) |

`info` e `episodios` vêm da pesquisa feita no telemóvel (AniList; episódios do Jikan ou do Kitsu), tal como no site;
o servidor valida tudo em `DadosAnime`.

### Comentários

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/episodios/{id}/comentarios` | 🔒 | | os teus e os dos companheiros dessa série |
| POST | `/episodios/{id}/comentarios` | 🔒 | `texto` | lista atualizada (201) |
| DELETE | `/comentarios/{id}` | 🔒 | | lista atualizada (só o autor) |

### Amigos

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/amigos` | 🔒 | | `parceiro`, `amigos`, `recebidos`, `enviados`, `convite` (link) |
| POST | `/amigos/convite` | 🔒 | | gera um link novo (uso único, 7 dias) |
| POST | `/amigos/pedidos` | 🔒 | `username` | pedido pelo utilizador exato |
| POST | `/amigos/pedidos/{id}/aceitar` | 🔒 | | aceita |
| DELETE | `/amigos/pedidos/{id}` | 🔒 | | recusa ou cancela |
| DELETE | `/amigos/{id}` | 🔒 | | desfaz a amizade |
| GET | `/pessoas/{id}` | 🔒 | | perfil do par ou de um amigo (`ultimo`, `biblioteca` com `naTua`/`vesCom`) |

### Perfil

| Método | Rota | | Corpo |
|---|---|---|---|
| PUT | `/perfil` | 🔒 | `nome`, `username` |
| PUT | `/perfil/password` | 🔒 | `atual`, `nova`, `confirmar` |
| PUT | `/perfil/paleta` | 🔒 | `paleta` (0–5) |
| PUT | `/perfil/privacidade` | 🔒 | `so_juntos` |
| POST | `/perfil/apagar` | 🔒 | `password` |
| POST | `/perfil/foto` | 🔒 | multipart, campo `foto` |
| DELETE | `/perfil/foto` | 🔒 | |
| GET / PUT | `/perfil/notificacoes` | 🔒 | `email`, `ep_push`, `ep_email`, `com_push`, `com_email` |
| GET | `/utilizadores/{id}/foto` | 🔒 | (a imagem) |

## Objetos

**item da biblioteca** (`/inicio`, `/series`, `/pessoas/{id}`)
```json
{ "serie": { "slug": "naruto", "nome": "Naruto", "nomeCurto": "Naruto", "anos": "2002–2007",
             "totalEpisodios": 220, "capa": null, "tipo": null, "emEmissao": false,
             "minutosEp": null, "malId": 20, "cor": "#F7C59F" },
  "estado": "a_ver", "estadoTexto": "A ver", "conjunta": true, "pct": 3,
  "companheiros": [ { "user": { …user… }, "pct": 5 } ] }
```

**episódio** (`/series/{slug}`): `com` tem um booleano por companheiro, pela ordem de `companheiros`.
```json
{ "id": 5, "numero": 5, "titulo": "…", "filler": false, "recap": false, "tu": true, "com": [true], "coment": 0 }
```

## Fora da API (por agora)

- Backoffice de admin e popup de novidades: só no site.
- Sincronizar fillers/episódios novos de séries em emissão: feito pelo site quando alguém abre a série.
- Notificações nativas na app (precisaria de Firebase): continuam a chegar pela app do site e por email.
