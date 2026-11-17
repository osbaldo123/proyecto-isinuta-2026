import re
import zipfile

xml = zipfile.ZipFile(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx").read("word/document.xml").decode("utf-8")
marked = re.sub(r'r:embed="(rId\d+)"', r"[[[EMBED:\1]]]", xml)
text = re.sub(r"</w:p>", "\n", marked)
text = re.sub(r"<[^>]+>", "", text)
fig_re = re.compile(r"Figura(?:\s+SEQ\s+Figura\s+\\\*\s+ARABIC)?\s+(\d+)\s*:", re.I)
for i, line in enumerate(text.splitlines()):
    if "SEQ" in line or (re.search(r"Figura\s+\d+", line) and "PAGEREF" not in line):
        s = " ".join(line.split())
        if "Figura" in s and len(s) < 180:
            m = fig_re.search(s)
            print(i, "MATCH" if m else "NO", repr(s))
