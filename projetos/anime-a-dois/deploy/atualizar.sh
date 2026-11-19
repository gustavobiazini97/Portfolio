#!/bin/bash
# Atualização automática do Anime a Dois, a correr NO alwaysdata (tarefa agendada, de 5 em 5 minutos).
#
# É o servidor que vai buscar o código ao GitHub (repo público, sem palavra-passe):
# o GitHub não guarda nenhum segredo nem tem acesso ao servidor.
#
# Em cada execução:
#   1. vê se o main tem alterações na app (ou no fillers.json);
#   2. só avança se os testes do GitHub ("testes") passaram nesse commit;
#   3. copia a app para ~/anime-a-dois (sem tocar no config.php nem no vendor/);
#   4. composer install + migrate.php + seed.php.
#
# Instalação (uma vez, no SSH do alwaysdata):
#   git clone --depth 1 --filter=blob:none --sparse https://github.com/gustavobiazini97/Portfolio.git ~/repo-portfolio
#   git -C ~/repo-portfolio sparse-checkout set projetos/anime-a-dois projetos/naruto-fillers/data
#   bash ~/repo-portfolio/projetos/anime-a-dois/deploy/atualizar.sh
#
# Tudo dentro de main(): o bash lê a função inteira antes de a correr,
# por isso o git pode atualizar este ficheiro a meio sem estragar a execução.

main() {
    set -euo pipefail

    # Pastas e endereços
    local REPO="$HOME/repo-portfolio"                         # clone do GitHub (só as pastas necessárias)
    local SITE="$HOME/anime-a-dois"                           # pasta servida pelo site (public/ é a raiz)
    local GITHUB_API="https://api.github.com/repos/gustavobiazini97/Portfolio"
    local ESTADO="$HOME/.anime-a-dois-deploy"                 # o que já foi publicado
    local TRINCO="$HOME/.anime-a-dois-deploy.lock"            # impede duas execuções ao mesmo tempo
    local APP="projetos/anime-a-dois"
    local FILLERS="projetos/naruto-fillers/data/fillers.json"

    # Mensagens com data (ficam no log da tarefa: ~/admin/logs/jobs/)
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

    # Trinco: mkdir é atómico; se já existe, outra execução está a correr
    if ! mkdir "$TRINCO" 2>/dev/null; then
        # Trinco com mais de 30 min = execução que morreu a meio: limpa e continua
        if [ -n "$(find "$TRINCO" -maxdepth 0 -mmin +30 2>/dev/null)" ]; then
            rmdir "$TRINCO" && mkdir "$TRINCO"
        else
            exit 0
        fi
    fi
    # (aspas duplas: o caminho fica fixo já aqui, porque o trap corre depois de main() acabar)
    # shellcheck disable=SC2064
    trap "rmdir '$TRINCO' 2>/dev/null || true" EXIT

    # Primeira vez: o clone ainda não existe
    if [ ! -d "$REPO/.git" ]; then
        log "A clonar o repositório…"
        git clone -q --depth 1 --filter=blob:none --sparse \
            https://github.com/gustavobiazini97/Portfolio.git "$REPO"
        git -C "$REPO" sparse-checkout set "$APP" "projetos/naruto-fillers/data"
    fi

    # Último commit do main (só o mais recente; o histórico não faz falta)
    git -C "$REPO" fetch -q --depth 1 origin main
    local COMMIT
    COMMIT=$(git -C "$REPO" rev-parse origin/main)

    # "Impressão digital" do que interessa: a pasta da app + o fillers.json.
    # Commits noutros projetos do repo não mudam isto e não disparam nada.
    local VERSAO
    VERSAO="$(git -C "$REPO" rev-parse "origin/main:$APP"):$(git -C "$REPO" rev-parse "origin/main:$FILLERS")"

    local ANTERIOR=""
    [ -f "$ESTADO" ] && ANTERIOR=$(cat "$ESTADO")
    if [ "$VERSAO" = "$ANTERIOR" ]; then
        exit 0                                                # nada de novo
    fi

    # Só publica se os testes do GitHub passaram neste commit.
    # Exceções: primeira instalação (sem estado anterior) e quando só mudou o fillers.json
    # (os testes só correm para mudanças na pasta da app).
    if [ -n "$ANTERIOR" ] && [ "${VERSAO%%:*}" != "${ANTERIOR%%:*}" ]; then
        local TESTES
        TESTES=$(curl -fsS --max-time 20 -H "Accept: application/vnd.github+json" \
            "$GITHUB_API/commits/$COMMIT/check-runs?check_name=testes" \
            | php -r '
                $r = json_decode(stream_get_contents(STDIN), true)["check_runs"] ?? [];
                $c = array_column($r, "conclusion");
                if (!$r)                                  { echo "sem-testes"; }
                elseif (in_array("failure", $c, true) || in_array("cancelled", $c, true)) { echo "falhou"; }
                elseif (in_array(null, $c, true))         { echo "a-correr"; }
                elseif (in_array("success", $c, true))    { echo "ok"; }
                else                                      { echo "falhou"; }
            ') || TESTES="erro"

        case "$TESTES" in
            ok)         ;;                                                       # segue
            a-correr)   log "Testes ainda a correr em ${COMMIT:0:7}; tento daqui a pouco."; exit 0 ;;
            sem-testes)
                # Commits feitos pelos próprios workflows (ex.: títulos dos episódios) não disparam os testes.
                # Se passados 20 min continua sem testes, é um desses: publica.
                local IDADE=$(( $(date +%s) - $(git -C "$REPO" log -1 --format=%ct origin/main) ))
                if [ "$IDADE" -lt 1200 ]; then
                    log "Ainda sem testes para ${COMMIT:0:7}; tento daqui a pouco."; exit 0
                fi
                log "Sem testes para ${COMMIT:0:7} ao fim de 20 min (commit automático): publico." ;;
            falhou)     log "Testes FALHARAM em ${COMMIT:0:7}: não publico."; exit 0 ;;
            *)          log "Não consegui ver os testes no GitHub; tento daqui a pouco."; exit 0 ;;
        esac
    fi

    log "A publicar ${COMMIT:0:7}…"
    git -C "$REPO" checkout -q --force "$COMMIT"

    # Copia a app; --delete apaga o que já não existe no repo,
    # exceto o que é só do servidor (credenciais, dependências e a cópia do fillers.json)
    mkdir -p "$SITE"
    rsync -a --delete \
        --exclude '/config/config.php' \
        --exclude '/vendor/' \
        --exclude '/database/fillers.json' \
        "$REPO/$APP/" "$SITE/"
    cp "$REPO/$FILLERS" "$SITE/database/fillers.json"

    # Sem config.php não há base de dados: avisa e não marca como publicado
    if [ ! -f "$SITE/config/config.php" ]; then
        log "Falta $SITE/config/config.php (copiar do config.example.php e preencher)."
        exit 1
    fi

    cd "$SITE"
    composer install -q --no-dev --optimize-autoloader --no-interaction --no-progress
    php database/migrate.php                                  # tabelas (não apaga dados)
    php database/seed.php                                     # episódios (não mexe em contas nem vistos)

    echo "$VERSAO" > "$ESTADO"
    log "Publicado ${COMMIT:0:7}."
}

main "$@"
