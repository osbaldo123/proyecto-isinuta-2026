"""Corrige el orden de capturas del Sprint 3 (quita duplicado y enlaza estado de cuenta)."""
import re
import shutil
import zipfile
from pathlib import Path

DOC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_fix_s3")


def main() -> None:
    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir()

    tmp = WORK / "out.docx"
    with zipfile.ZipFile(DOC, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        rels = zin.read("word/_rels/document.xml.rels").decode("utf-8")
        document = zin.read("word/document.xml").decode("utf-8")

        if "image52.png" not in rels:
            insert = (
                '<Relationship Id="rId80" '
                'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                'Target="media/image52.png"/>'
            )
            rels = rels.replace("</Relationships>", insert + "</Relationships>")

        # Secuencia actual de embeds de implementación S3 alrededor de rId46..rId51
        # Objetivo: rId46,47,48,49,50,51,80
        pattern = re.compile(
            r'(r:embed="rId46".*?r:embed="rId47".*?r:embed="rId48".*?)r:embed="rId46"(.*?r:embed="rId49".*?r:embed="rId50".*?)r:embed="rId51"'
            ,
            re.DOTALL,
        )

        def repl(m: re.Match[str]) -> str:
            return (
                m.group(1)
                + 'r:embed="rId49"'
                + m.group(2)
                + 'r:embed="rId80"'
            )

        new_doc, n = pattern.subn(repl, document, count=1)
        if n != 1:
            # fallback más simple: reemplazar la 2ª aparición de rId46 y luego la de rId51 previa a Sprint4
            parts = document.split('r:embed="rId46"')
            if len(parts) >= 3:
                document = parts[0] + 'r:embed="rId46"' + parts[1] + 'r:embed="rId49"' + 'r:embed="rId46"'.join(parts[2:])
                # ahora puede haber quedado rId49 duplicado seguido del rId49 original: arreglar cadena completa
            # Rehacer con lista de embeds objetivo en el tramo
            embeds = list(re.finditer(r'r:embed="(rId\d+)"', document))
            # localizar índice del primer rId46 que precede a rId47
            ids = [m.group(1) for m in embeds]
            try:
                i = ids.index("rId46")
                # verificar ventana
                window = ids[i : i + 7]
                print("ventana actual", window)
                if window[:3] == ["rId46", "rId47", "rId48"]:
                    # reemplazar embeds i..i+6
                    target = ["rId46", "rId47", "rId48", "rId49", "rId50", "rId51", "rId80"]
                    # reconstruir desde el final hacia adelante con spans
                    spans = [(m.start(), m.end(), m.group(1)) for m in embeds]
                    # solo modificar índices i..i+6
                    chars = list(document)
                    # más seguro: usar substrings
                    pieces = []
                    last = 0
                    for k, (start, end, _rid) in enumerate(spans):
                        pieces.append(document[last:start])
                        if i <= k <= i + 6:
                            pieces.append(f'r:embed="{target[k - i]}"')
                        else:
                            pieces.append(document[start:end])
                        last = end
                    pieces.append(document[last:])
                    new_doc = "".join(pieces)
                    n = 1
                    print("fallback ventana aplicada", target)
                else:
                    new_doc = document
                    n = 0
            except ValueError:
                new_doc = document
                n = 0
        else:
            # pattern replaced only part - need fix because group2 still has rId49
            # Actually after sub: ...48, rId49, [group2 starts with stuff before old rId49 which is still there]
            # The pattern was wrong. Use fallback always.
            new_doc = document
            n = 0

        if n != 1:
            embeds = list(re.finditer(r'r:embed="(rId\d+)"', document))
            ids = [m.group(1) for m in embeds]
            i = ids.index("rId46")
            window = ids[i : i + 7]
            print("ventana actual", window)
            target = ["rId46", "rId47", "rId48", "rId49", "rId50", "rId51", "rId80"]
            spans = [(m.start(), m.end()) for m in embeds]
            pieces = []
            last = 0
            for k, (start, end) in enumerate(spans):
                pieces.append(document[last:start])
                if i <= k <= i + 6:
                    pieces.append(f'r:embed="{target[k - i]}"')
                else:
                    pieces.append(document[start:end])
                last = end
            pieces.append(document[last:])
            new_doc = "".join(pieces)
            print("aplicado target", target)

        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename == "word/document.xml":
                data = new_doc.encode("utf-8")
            elif item.filename == "word/_rels/document.xml.rels":
                data = rels.encode("utf-8")
            zout.writestr(item, data)

        if "word/media/image52.png" not in zin.namelist():
            raise SystemExit("Falta image52.png en el docx; vuelve a correr _replace_impl_images.py")

    shutil.copy2(tmp, DOC)
    print("OK", DOC)


if __name__ == "__main__":
    main()
