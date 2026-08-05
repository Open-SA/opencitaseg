#!/usr/bin/env python3
"""
Genera public/js/locales/<lang>.js desde locales/<lang>.po

Uso (desde la raiz del plugin):
    python3 tools/build-js-locales.py

Sin dependencias externas. Correr en CI despues de compilar los .mo
para que el diccionario del front nunca quede desfasado de los .po.
"""
import glob
import json
import os
import re

HEADER = """/*
 * opencitaseg - archivo generado automaticamente por tools/build-js-locales.py
 * NO editar a mano: los cambios se pierden en la proxima generacion.
 * Fuente: locales/%s.po
 */
"""


def parse_po(path):
    """Parser PO minimo: msgid/msgstr simples, ignora plurales y fuzzy."""
    entries = {}
    msgid = msgstr = None
    target = None
    fuzzy = False
    pending_fuzzy = False

    def flush():
        if msgid and msgstr:
            entries[msgid] = msgstr

    for raw in open(path, encoding="utf-8"):
        line = raw.strip()
        if line.startswith("#,") and "fuzzy" in line:
            pending_fuzzy = True
            continue
        if line.startswith("#") or not line:
            continue
        if line.startswith("msgid_plural"):
            target = None  # plurales solo se usan del lado PHP
            continue
        if line.startswith("msgid "):
            if not fuzzy:
                flush()
            msgid = unquote(line[6:])
            msgstr = None
            target = "id"
            fuzzy = pending_fuzzy
            pending_fuzzy = False
            continue
        if line.startswith("msgstr "):
            msgstr = unquote(line[7:])
            target = "str"
            continue
        if line.startswith("msgstr["):
            target = None
            continue
        if line.startswith('"') and target == "id":
            msgid += unquote(line)
        elif line.startswith('"') and target == "str":
            msgstr += unquote(line)

    if not fuzzy:
        flush()
    entries.pop("", None)  # header
    return entries


def unquote(s):
    s = s.strip()
    if s.startswith('"') and s.endswith('"'):
        s = s[1:-1]
    return s.replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"').replace('\\\\', '\\')


def main():
    out_dir = os.path.join("public", "js", "locales")
    os.makedirs(out_dir, exist_ok=True)

    po_files = sorted(glob.glob(os.path.join("locales", "*.po")))
    if not po_files:
        raise SystemExit("No encontre locales/*.po - corres esto desde la raiz del plugin?")

    for po in po_files:
        lang = os.path.splitext(os.path.basename(po))[0]
        entries = parse_po(po)
        body = json.dumps(entries, ensure_ascii=False, indent=2, sort_keys=True)
        js = HEADER % lang + "window.OPENCITASEG_I18N = " + body + ";\n"
        dest = os.path.join(out_dir, lang + ".js")
        with open(dest, "w", encoding="utf-8") as fh:
            fh.write(js)
        print("%-24s %2d cadenas" % (dest, len(entries)))


if __name__ == "__main__":
    main()
