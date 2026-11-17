"""Mapea figuras de implementación: imagen inmediatamente anterior a cada caption."""
import re
import zipfile

DOC = r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx"
z = zipfile.ZipFile(DOC)
xml = z.read("word/document.xml").decode("utf-8")
rels = z.read("word/_rels/document.xml.rels").decode("utf-8")
idmap = dict(re.findall(r'Id="(rId\d+)"[^>]*Target="([^"]+)"', rels))

IMPL = [15, 16, 17, 18, 19, 20, 23, 24, 25, 28, 29, 30, 31, 32, 33, 34, 37, 38]

xml2 = re.sub(r'r:embed="(rId\d+)"', r">[[[EMBED:\1]]]<", xml)
paras = re.split(r"</w:p>", xml2)
fig_re = re.compile(r"Figura(?:\s+SEQ\s+Figura\s+\\\*\s+ARABIC)?\s+(\d+)\s*:", re.I)

events = []
for para in paras:
    plain = re.sub(r"<[^>]+>", "", para)
    embeds = re.findall(r"\[\[\[EMBED:(rId\d+)\]\]\]", plain)
    if "PAGEREF" in plain:
        continue
    m = fig_re.search(plain)
    for e in embeds:
        events.append(("img", e))
    if m:
        events.append(("fig", int(m.group(1))))

last = None
found: dict[int, str] = {}
for kind, val in events:
    if kind == "img":
        last = val
    elif val in IMPL and last:
        found[val] = last

for fig in IMPL:
    rid = found.get(fig)
    print(f"Figura {fig:02d} -> {rid} -> {idmap.get(rid) if rid else None}")

print("--- secuencia fig/img 13-25 ---")
for kind, val in events:
    if kind == "fig" and 13 <= val <= 25:
        print(f"  FIG {val}")
    if kind == "img":
        # only print media around s1-s2
        media = idmap.get(val, "")
        num = re.search(r"image(\d+)", media)
        if num and 14 <= int(num.group(1)) <= 28:
            print(f"  IMG {media}")
