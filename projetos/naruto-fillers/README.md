# Naruto Fillers

Página simples para ver rapidamente só os episódios **filler** de Naruto, Naruto Shippuden e Boruto.

## Como correr

**Com PHP (recomendado — dados ao vivo de animefillerlist.com, com títulos e datas):**

```bash
cd naruto-fillers
php -S localhost:8000
```

Abre http://localhost:8000. `fillers.php` lê o site, guarda em `cache/` durante 24h e,
se o site não responder, usa a lista guardada `data/fillers.json`.

**Sem PHP (ex.: GitHub Pages):** a página usa `data/fillers.json`. Para o atualizar
com títulos e datas:

```bash
python3 scripts/update_fillers.py
```

## Personalizar

- Fundo: coloca uma foto em `assets/background.jpg` e ela substitui a ilustração.
- Desfoque/escurecimento do fundo: variáveis `--bg-blur` e `--bg-dim` em `style.css`.

## Atalho no ecrã principal (Android + Termux)

1. Instala o **Termux:Widget** da mesma loja de onde veio o Termux (F-Droid ou GitHub).
2. No Termux, com a pasta em `~/naruto-fillers`:
   ```bash
   pkg install php curl
   mkdir -p ~/.shortcuts
   cp ~/naruto-fillers/scripts/termux-shortcut.sh ~/.shortcuts/"Naruto Fillers"
   chmod +x ~/.shortcuts/"Naruto Fillers"
   ```
3. No ecrã principal: toque longo → Widgets → Termux:Widget → escolhe "Naruto Fillers".

Tocar no atalho arranca o servidor (se preciso) e abre a página com dados ao vivo.
