import zipfile
import re

z = zipfile.ZipFile(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
xml = z.read("word/document.xml").decode("utf-8")
rels = z.read("word/_rels/document.xml.rels").decode("utf-8")
embeds = re.findall(r'r:embed="(rId\d+)"', xml)
pairs = re.findall(r'<Relationship[^>]*Id="(rId\d+)"[^>]*Target="([^"]+)"', rels)
idmap = dict(pairs)
for i, e in enumerate(embeds, 1):
    print(f"{i:02d} {e} -> {idmap.get(e)}")
