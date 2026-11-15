# Helpdesk TI

Sistema de pedidos (tickets) para o departamento de informática: o funcionário abre um pedido em vez de ir à secretária do TI, e o TI trata-os por ordem de prioridade, com prazos, notificações e uma FAQ que resolve as dúvidas repetidas antes de virarem pedido.

PHP 8 + MySQL, mini-MVC **sem dependências** (nada de Composer): corre no WAMP, no Termux ou em qualquer alojamento com PHP.

## O que faz

**Funcionário**
- Página inicial com pesquisa na FAQ e os seus pedidos.
- Abre um pedido com título, descrição, categoria e anexos (fotografia do ecrã, PDF…).
- Enquanto escreve o título, aparecem **artigos da FAQ que podem resolver já**. Se resolverem, carrega em "já não preciso de pedido" e fica contado como pedido evitado.
- Não escolhe a prioridade diretamente: responde a **"a quem afeta?"** e **"quanto te atrapalha?"**.
- Acompanha a conversa, responde e recebe notificações. Quando o TI dá como resolvido, confirma e avalia de 1 a 5, ou responde e o pedido reabre.

**Técnico de TI**
- Painel com as filas: sem técnico, atribuídos a mim, prazo quase a acabar e prazo ultrapassado.
- Lista com filtros (estado, prioridade, categoria, técnico, SLA) e pesquisa por `TI-00042` ou texto.
- "Fico eu com este", atribuir a um colega, corrigir a prioridade (com motivo, que fica no histórico) e mudar a categoria.
- Respostas públicas e **notas internas** (o funcionário não vê).
- Estados: Novo → Em curso → À espera do utilizador / de terceiros → Resolvido → Fechado.
- "Transformar em artigo da FAQ" a partir de um pedido resolvido.

**Administrador**
- Contas: criar (com palavra-passe temporária), papéis, departamentos, desativar, repor palavra-passe e 2FA.
- Categorias com **técnico por defeito**, que recebe os pedidos dessa categoria automaticamente.
- Prazos do SLA por prioridade.
- Estatísticas: volume, % de respostas e resoluções a tempo, tempos médios, satisfação, desempenho por técnico e pedidos evitados pela FAQ.
- Registo de auditoria.

## Como funcionam a prioridade e os prazos

Segui o modelo dos service desks atuais (ITIL, Jira Service Management, Zendesk, Freshdesk):

- **Prioridade = impacto × urgência** (matriz ITIL). A prioridade sai da matriz em vez de ser escolhida, porque se cada pessoa a escolhesse, tudo seria "urgente":

  |                       | Posso continuar | Atrapalha | Não consigo trabalhar |
  |-----------------------|:---:|:---:|:---:|
  | Empresa toda/clientes | P3 | P2 | **P1** |
  | Equipa/departamento   | P4 | P3 | P2 |
  | Só a mim              | P4 | P4 | P3 |

- **Dois relógios por pedido**: até à **1.ª resposta** e até à **resolução**. Prazos por defeito (editáveis no admin):

  | Prioridade | 1.ª resposta | Resolução |
  |---|---|---|
  | P1 Crítica | 30 min | 4 h |
  | P2 Alta | 1 h | 1 dia útil |
  | P3 Média | 4 h | 3 dias úteis |
  | P4 Baixa | 1 dia útil | 5 dias úteis |

- Os prazos contam **só em horário de trabalho** (configurável; por defeito seg–sex 09:00–18:00, sem contar os feriados nacionais). Um P2 aberto sexta às 17:30 vence segunda de manhã.
- O relógio da resolução **para** enquanto o pedido está "à espera do utilizador" ou "de terceiros" (o atraso não é do TI) e retoma quando a pessoa responde.
- **Avisos automáticos**: quando restam 20% do prazo (ao técnico) e quando passa o prazo (ao técnico e aos admins).
- Um pedido "Resolvido" sem resposta do funcionário fecha sozinho ao fim de 3 dias.

## Notificações

Cada aviso vai para o **sino** da app (atualizado a cada minuto) e, se a pessoa quiser, por **email**. Os emails ficam numa fila e são enviados em segundo plano, para ninguém esperar pelo servidor de email.

| Acontecimento | Quem é avisado |
|---|---|
| Pedido novo | Técnico da categoria (ou toda a equipa de TI) |
| Resposta do TI / mudança de estado | Funcionário |
| Resposta do funcionário | Técnico atribuído (ou toda a equipa) |
| Atribuição | Técnico que recebeu |
| Prazo quase a acabar / ultrapassado | Técnico (+ admins, se ultrapassado) |
| Avaliação | Técnico |

## Segurança (regras do projeto)

- Só `public/` é servido; `app/`, `config/`, `storage/` (anexos e logs), `database/` e `cron/` ficam fora (e com `.htaccess` a bloquear, por precaução).
- PDO com prepared statements em todas as queries; `htmlspecialchars` em toda a saída; CSP sem `unsafe-inline` (nem estilos inline).
- CSRF em todos os POST; rotas com método fixo (GET/POST); controlo de acesso no servidor em cada pedido (um funcionário só vê os seus pedidos — os outros dão 404).
- `password_hash`; sessão com cookie HttpOnly/Secure/SameSite, ID novo no login, tempo limite por inatividade (30 min) e absoluto (10 h).
- Login: mensagem genérica, bloqueio progressivo por conta (5 falhas → 1, 2, 4… min), limite por IP e honeypot.
- **2FA (TOTP) obrigatório para administradores**, opcional para os restantes; palavras-passe temporárias obrigam a trocar no 1.º login.
- Recuperação de palavra-passe com token de uso único (30 min) guardado em hash.
- Anexos: tipo real verificado (`finfo`), lista de extensões, tamanho máximo, nome aleatório, guardados fora do `public/` e servidos por script com controlo de acesso, `nosniff` e `sandbox`.
- Erros nunca no ecrã em produção ("fail-closed"); auditoria de logins, acessos negados e mudanças de permissões.

## Correr localmente (WAMP ou Termux)

```bash
# 1. Configuração: copiar e preencher (no WAMP: root sem palavra-passe)
cp config/config.example.php config/config.php

# 2. Criar a base de dados "helpdesk" (phpMyAdmin) e as tabelas
php database/migrate.php

# 3. Dados iniciais: o 1.º admin, as categorias e a FAQ base…
php database/seed.php o.teu@email.pt "O Teu Nome"
#    …ou uma demo completa com técnicos, funcionários e pedidos de exemplo
php database/seed.php --demo

# 4. Servidor
php -S localhost:8000 -t public
```

O seed mostra as palavras-passe temporárias. O admin tem de configurar o 2FA (Google/Microsoft Authenticator, Aegis…) no primeiro login.

**Tarefas em segundo plano** (emails, alertas de SLA, fecho automático): agendar `php cron/processar.php` a cada minuto (crontab ou Agendador de Tarefas do Windows). Sem isso, a app corre-as sozinha no fim dos pedidos, no máximo uma vez por minuto.

**Testes**: `php tests/sla_totp_test.php` (contas do SLA, matriz de prioridade, vetores oficiais do TOTP).

## Pôr a funcionar na empresa

1. A **raiz do site** tem de apontar para a pasta `public/`.
2. HTTPS obrigatório (o cookie de sessão passa a `Secure` e entra o HSTS sozinho).
3. Utilizador MySQL só com o necessário:
   ```sql
   CREATE USER 'helpdesk'@'localhost' IDENTIFIED BY 'uma-password-forte';
   GRANT SELECT, INSERT, UPDATE, DELETE ON helpdesk.* TO 'helpdesk'@'localhost';
   ```
   (para o `migrate.php` usar temporariamente um utilizador com `CREATE`/`ALTER`/`INDEX`/`REFERENCES`.)
4. Email: no `config.php`, `'modo' => 'smtp'` com os dados da conta (Microsoft 365: `smtp.office365.com`, porta 587, `tls`). Em `'log'` os emails ficam em `storage/logs/emails.log`, o que dá jeito para testar.
5. `debug => false`, `url_base` com o endereço real, horário e feriados da empresa: o feriado municipal e os feriados móveis do ano (Sexta-feira Santa, Corpo de Deus) entram em `feriados` como `AAAA-MM-DD`.
6. Cópias de segurança diárias da base de dados e da pasta `storage/anexos`.

## Estrutura

```
public/        único ponto público: index.php (front controller), css/, js/, img/
app/core/      Database (PDO), Sessao, Seguranca (cabeçalhos), Controller, Sla, Totp, Mailer (SMTP), Upload, Limite, Tarefas
app/models/    Ticket, Comentario, Anexo, Historico, Utilizador, Categoria, SlaPolitica, Faq, Notificacao, Notificador, Estatisticas, Auditoria
app/controllers/ Auth, Painel, Ticket, Faq, Notificacao, Perfil, Admin
app/views/     uma pasta por controller + layout/
database/      schema.sql, migrate.php, seed.php
cron/          processar.php
storage/       anexos/ e logs/ (fora do Git)
tests/         testes sem base de dados
```
