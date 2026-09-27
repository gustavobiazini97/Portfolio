#!/usr/bin/env python3
"""Atualiza data/fillers.json com os episódios filler de animefillerlist.com.

Uso:  python3 scripts/update_fillers.py

Só usa a biblioteca padrão. Útil quando a página é servida sem PHP
(ex.: GitHub Pages): a cópia local passa a ter títulos e datas.
"""
import json
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

SHOWS = {
    "naruto": {"name": "Naruto", "total": 220, "years": "2002–2007"},
    "naruto-shippuden": {"name": "Naruto Shippuden", "total": 500, "years": "2007–2017"},
    "boruto-naruto-next-generations": {"name": "Boruto", "total": 293, "years": "2017–2023"},
}
OUT = Path(__file__).resolve().parent.parent / "data" / "fillers.json"


class EpisodeTable(HTMLParser):
    """Recolhe as linhas de table.EpisodeList como {Number, Title, Type, Date}."""

    def __init__(self):
        super().__init__()
        self.in_table = False
        self.rows, self.row, self.cell = [], None, None

    def handle_starttag(self, tag, attrs):
        cls = dict(attrs).get("class") or ""
        if tag == "table" and "EpisodeList" in cls:
            self.in_table = True
        elif self.in_table and tag == "tr":
            self.row = {}
        elif self.row is not None and tag == "td":
            self.cell = cls.split()[0] if cls else None
            self.row[self.cell] = ""

    def handle_endtag(self, tag):
        if tag == "table" and self.in_table:
            self.in_table = False
        elif tag == "tr" and self.row:
            self.rows.append(self.row)
            self.row = None
        elif tag == "td":
            self.cell = None

    def handle_data(self, data):
        if self.row is not None and self.cell:
            self.row[self.cell] += data


def fetch(slug):
    req = urllib.request.Request(
        f"https://www.animefillerlist.com/shows/{slug}",
        headers={"User-Agent": "Mozilla/5.0 (NarutoFillers)"},
    )
    with urllib.request.urlopen(req, timeout=20) as r:
        parser = EpisodeTable()
        parser.feed(r.read().decode("utf-8", "replace"))
    return [
        {"n": int(row["Number"].strip()), "title": row.get("Title", "").strip() or None,
         "date": row.get("Date", "").strip() or None}
        for row in parser.rows
        if row.get("Type", "").strip().lower() == "filler"
    ]


def main():
    data = {"source": "offline", "shows": {}}
    for slug, meta in SHOWS.items():
        episodes = fetch(slug)
        print(f"{meta['name']}: {len(episodes)} fillers")
        data["shows"][slug] = {**meta, "episodes": episodes}
    OUT.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"Gravado em {OUT}")


if __name__ == "__main__":
    main()
