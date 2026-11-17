import re
import zipfile

z = zipfile.ZipFile(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
xml = z.read("word/document.xml").decode("utf-8")
rels = z.read("word/_rels/document.xml.rels").decode("utf-8")
embeds = re.findall(r'r:embed="(rId\d+)"', xml)
idmap = dict(re.findall(r'Id="(rId\d+)"[^>]*Target="([^"]+)"', rels))
for i, e in enumerate(embeds, 1):
    print(f"{i:02d} {e} -> {idmap.get(e)}")
