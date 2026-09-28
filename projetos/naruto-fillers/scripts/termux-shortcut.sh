#!/data/data/com.termux/files/usr/bin/bash
# Atalho para o Termux:Widget: arranca o servidor PHP (se ainda não estiver
# a correr) e abre a página no browser.
#   cp ~/naruto-fillers/scripts/termux-shortcut.sh ~/.shortcuts/"Naruto Fillers"
#   chmod +x ~/.shortcuts/"Naruto Fillers"

DIR="$HOME/naruto-fillers"
URL="http://localhost:8000"

# Verifica se a porta responde (só com bash, sem ferramentas externas).
up() { (exec 3<>/dev/tcp/127.0.0.1/8000) 2>/dev/null; }

if up; then
  termux-open-url "$URL"
  exit 0
fi

cd "$DIR" || { echo "Pasta $DIR não encontrada"; read -r; exit 1; }
php -S localhost:8000 >/dev/null 2>&1 &

# Só abre o browser quando o servidor já responde.
for _ in $(seq 30); do
  up && break
  sleep 0.2
done
termux-open-url "$URL"

# O servidor vive enquanto esta sessão do Termux estiver aberta
# (fechar a sessão ou Ctrl+C desliga-o).
echo "Servidor ligado em $URL — fecha esta sessão para o desligar."
wait
