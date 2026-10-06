# API REST do Anime a Dois

API em JSON por cima dos mesmos Models do site (`User`, `Serie`, `Episodio`, `Comentario`, …).
É usada pela app Flutter (`projetos/anime-a-dois-flutter`). O site continua a funcionar como antes.

- **Base:** `https://animeadois.alwaysdata.net/api` (o `.htaccess` passa `/api/<rota>` ao `api.php`; é a que a app usa)
  - Também responde em `https://animeadois.alwaysdata.net/api.php/<rota>`.
  - Sem `PATH_INFO`: `api.php?rota=/series`.
- **Formato:** pedidos com `Content-Type: application/json` (ou formulário); respostas sempre JSON.
- **Ficheiros:** `public/api.php` (rotas), `app/api/*Api.php` (controllers), `app/models/Token.php`.

## Autenticação

1. `POST /auth/login` devolve um `token`.
2. Os outros pedidos levam o cabeçalho `Authorization: Bearer <token>`.
   - Alternativa para servidores que escondem o `Authorization`: `X-Auth-Token: <token>`.
3. Cada telemóvel tem o seu token. A base de dados só guarda o **sha256** do token (tabela `tokens`).
4. O token expira ao fim de **90 dias sem uso**; cada uso renova o prazo.
5. Mudar a palavra-passe pela API termina as sessões dos outros telemóveis.

## Respostas

Sucesso: `{ "ok": true, ... }`. Erro: `{ "ok": false, "mensagem": "..." }`, com a mensagem pronta a mostrar.

| Código | Quando |
|---|---|
| 200 / 201 | Correu bem (201 ao criar conta ou comentário) |
| 401 | Sem token, token errado ou expirado → voltar ao login |
| 403 | Sem permissão (ex.: apagar o comentário do outro) |
| 404 | Rota, série, episódio ou comentário inexistente |
| 405 | Rota existe, mas com outro método |
| 422 | Erro de validação (nome curto, palavra-passe errada, …) |
| 500 | Erro no servidor ou na base de dados |

## Rotas

🔓 = pública · 🔒 = precisa de token

### Conta

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/auth/estado` | 🔓 | | `registoAberto` |
| POST | `/auth/login` | 🔓 | `username`, `password`, `dispositivo?` | `token`, `user` |
| POST | `/auth/registo` | 🔓 | `nome`, `username`, `password`, `password_confirmar`, `dispositivo?` | `token`, `user` (201) |
| POST | `/auth/logout` | 🔒 | | termina a sessão deste telemóvel |
| GET | `/eu` | 🔒 | | `user`, `parceiro` |

### Séries e episódios

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/inicio` | 🔒 | | `user`, `parceiro`, `ultimoPar`, `ultimoTu`, `serieAtual`, `series[]` |
| GET | `/series` | 🔒 | | `series[]` |
| GET | `/series/{slug}` | 🔒 | | `serie`, `user`, `parceiro`, `episodios[]` |
| POST | `/series/{slug}/vistos` | 🔒 | `episodios: [ids]`, `acao: marcar \| desmarcar \| alternar` | `visto`, `numeros`, `progresso`, `mensagem` |
| GET | `/series/{slug}/estatisticas` | 🔒 | | `serie`, `estatisticas` |

### Comentários

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| GET | `/episodios/{id}/comentarios` | 🔒 | | `episodio`, `comentarios[]`, `max` |
| POST | `/episodios/{id}/comentarios` | 🔒 | `texto` | lista atualizada (201) |
| DELETE | `/comentarios/{id}` | 🔒 | | lista atualizada (só o autor) |

### Perfil

| Método | Rota | | Corpo | Resposta |
|---|---|---|---|---|
| PUT | `/perfil` | 🔒 | `nome`, `username` | `user` |
| PUT | `/perfil/password` | 🔒 | `atual`, `nova`, `confirmar` | `mensagem` |
| POST | `/perfil/foto` | 🔒 | multipart, campo `foto` | `user` |
| DELETE | `/perfil/foto` | 🔒 | | `user` |
| GET | `/perfil/notificacoes` | 🔒 | | `preferencias` |
| PUT | `/perfil/notificacoes` | 🔒 | `email`, `ep_push`, `ep_email`, `com_push`, `com_email` | `preferencias` |
| GET | `/utilizadores/{id}/foto` | 🔒 | | a imagem (webp/jpeg) |

## Objetos

**user**
```json
{ "id": 1, "nome": "Gus", "username": "gus", "inicial": "G",
  "foto": "https://…/api.php/utilizadores/1/foto?v=1791317699" }
```

**serie** (em `/inicio`, `/series` e `/series/{slug}`)
```json
{ "slug": "naruto", "nome": "Naruto", "nomeCurto": "Naruto", "anos": "2002–2007",
  "totalEpisodios": 220, "cor": "#F7C59F",
  "tu":  { "vistos": 12, "pct": 5, "posicao": 12 },
  "par": { "vistos": 5,  "pct": 2, "posicao": 5 },
  "resumo": "Vais 7 episódios à frente." }
```
`par` é `null` enquanto o par não tem conta. `posicao` é o episódio mais avançado (o que conta no Vs).
`/series/{slug}` acrescenta `totalFillers`.

**episódio** (em `/series/{slug}`)
```json
{ "id": 26, "numero": 26, "titulo": "Special Report: Live from the Forest of Death!",
  "filler": true, "tu": false, "par": true, "coment": 2 }
```

**comentário**
```json
{ "id": 1, "texto": "Que episódio!", "autor": { …user… }, "meu": true,
  "criadoEm": "2026-10-06T21:14:47+01:00", "quando": "agora mesmo" }
```

## Exemplos (curl)

```bash
B=https://animeadois.alwaysdata.net/api

# Entrar e guardar o token
T=$(curl -s -X POST $B/auth/login -H 'Content-Type: application/json' \
      -d '{"username":"gus","password":"…"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

# Início
curl -s $B/inicio -H "Authorization: Bearer $T"

# Marcar os episódios 1 a 3 do Naruto (ids da lista de /series/naruto)
curl -s -X POST $B/series/naruto/vistos -H "Authorization: Bearer $T" \
     -H 'Content-Type: application/json' -d '{"episodios":[1,2,3],"acao":"marcar"}'

# Comentar o episódio com id 3
curl -s -X POST $B/episodios/3/comentarios -H "Authorization: Bearer $T" \
     -H 'Content-Type: application/json' -d '{"texto":"Que episódio!"}'
```

## Notas

- As notificações ao par continuam a funcionar quando se marca ou comenta pela API (Web Push para a app do site e/ou email).
  A app Flutter ainda não recebe notificações nativas (isso precisaria de Firebase Cloud Messaging).
- CORS aberto (`*`): não há cookies, só o token no cabeçalho.
