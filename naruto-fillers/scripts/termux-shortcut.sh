#!/data/data/com.termux/files/usr/bin/bash
# Atalho para o Termux:Widget: arranca o servidor PHP (se ainda não estiver
# a correr) e abre a página no browser.
#   cp ~/naruto-fillers/scripts/termux-shortcut.sh ~/.shortcuts/"Naruto Fillers"
#   chmod +x ~/.shortcuts/"Naruto Fillers"

DIR="$HOME/naruto-fillers"
PORT=8000

if ! curl -s -o /dev/null "http://localhost:$PORT"; then
  cd "$DIR" || { echo "Pasta $DIR não encontrada"; exit 1; }
  nohup php -S "localhost:$PORT" >/dev/null 2>&1 &
  sleep 1
fi

termux-open-url "http://localhost:$PORT"
