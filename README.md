# Portfolio

Portfólio pessoal de Gustavo Biazini — https://gustavobiazini97.github.io/Portfolio/

## Estrutura

```
site/       a página do portfólio (HTML, CSS e JS)
projetos/   microprojetos, cada um na sua pasta
index.html  só redireciona para site/ (o GitHub Pages abre sempre o index.html da raiz)
```

## Instalar como app

O portfólio é uma PWA: abre o site no Chrome (Android) e toca em **Instalar** no topo da página
(ou ⋮ → Adicionar ao ecrã principal → Instalar). Fica com ícone próprio, abre sem a barra do browser
e funciona offline. Carregar no ícone sem largar mostra os atalhos **Projetos** e **Contacto**.

```
site/manifest.json   nome, cores e ícones da app
site/sw.js           service worker (cache offline)
site/icons/          ícones 192/512, maskable, apple-touch-icon e ícones dos atalhos
site/screenshots/    capturas mostradas na janela de instalação
```

Ao alterar CSS/JS, sobe a versão em `CACHE` no `sw.js` para forçar a atualização nas apps instaladas.

## Projetos

| Projeto | Descrição | Ver online |
|---|---|---|
| [Naruto Fillers](projetos/naruto-fillers/) | Lista dos episódios filler de Naruto, Naruto Shippuden e Boruto. | [Abrir](https://gustavobiazini97.github.io/Portfolio/projetos/naruto-fillers/) |
