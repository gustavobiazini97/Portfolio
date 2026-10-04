#!/usr/bin/env python3
"""Vai buscar TODOS os episódios (número, título, filler) ao animefillerlist.com
e grava database/episodios.json, que o seed.php usa para os títulos.

Uso: python3 database/titulos.py   (corre no GitHub Actions: workflow "Títulos Anime a Dois")
Só usa a biblioteca padrão.
"""
import json
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

# slug no site → slug da app
SERIES = {
    "naruto": "naruto",
    "naruto-shippuden": "shippuden",
    "boruto-naruto-next-generations": "boruto",
}
SAIDA = Path(__file__).resolve().parent / "episodios.json"


class TabelaEpisodios(HTMLParser):
    """Lê as linhas de table.EpisodeList como {Number, Title, Type, Date}."""

    def __init__(self):
        super().__init__()
        self.na_tabela = False
        self.linhas, self.linha, self.celula = [], None, None

    def handle_starttag(self, tag, attrs):
        classe = dict(attrs).get("class") or ""
        if tag == "table" and "EpisodeList" in classe:
            self.na_tabela = True
        elif self.na_tabela and tag == "tr":
            self.linha = {}
        elif self.linha is not None and tag == "td":
            self.celula = classe.split()[0] if classe else None
            self.linha[self.celula] = ""

    def handle_endtag(self, tag):
        if tag == "table" and self.na_tabela:
            self.na_tabela = False
        elif tag == "tr" and self.linha:
            self.linhas.append(self.linha)
            self.linha = None
        elif tag == "td":
            self.celula = None

    def handle_data(self, data):
        if self.linha is not None and self.celula:
            self.linha[self.celula] += data


def buscar(slug):
    pedido = urllib.request.Request(
        f"https://www.animefillerlist.com/shows/{slug}",
        headers={"User-Agent": "Mozilla/5.0 (AnimeADois)"},
    )
    with urllib.request.urlopen(pedido, timeout=30) as r:
        leitor = TabelaEpisodios()
        leitor.feed(r.read().decode("utf-8", "replace"))

    episodios = []
    for linha in leitor.linhas:
        try:
            n = int(linha.get("Number", "").strip())
        except ValueError:
            continue
        episodios.append({
            "n": n,
            "titulo": " ".join(linha.get("Title", "").split()) or None,
            "filler": linha.get("Type", "").strip().lower() == "filler",
        })
    return sorted(episodios, key=lambda e: e["n"])


def main():
    dados = {}
    for origem, slug in SERIES.items():
        eps = buscar(origem)
        if not eps:
            raise SystemExit(f"{origem}: nenhum episódio lido (o site mudou ou bloqueou o pedido)")
        dados[slug] = eps
        print(f"{slug}: {len(eps)} episódios, {sum(e['filler'] for e in eps)} fillers, "
              f"{sum(1 for e in eps if e['titulo'])} com título")
    SAIDA.write_text(json.dumps(dados, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"Gravado em {SAIDA}")


if __name__ == "__main__":
    main()
