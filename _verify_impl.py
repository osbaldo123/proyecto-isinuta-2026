import re
import zipfile

z = zipfile.ZipFile(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
print("image52", "word/media/image52.png" in z.namelist())
print("rId80", "rId80" in z.read("word/_rels/document.xml.rels").decode())
ids = re.findall(r'r:embed="(rId\d+)"', z.read("word/document.xml").decode())
print("windows", [ids[i : i + 7] for i, r in enumerate(ids) if r == "rId46"][:2])
