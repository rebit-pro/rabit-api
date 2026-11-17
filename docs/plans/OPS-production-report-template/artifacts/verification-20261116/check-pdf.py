"""Check synthetic worksheet PDFs produced by ProductionWorksheetTest; no HTTP/browser/E2E."""
import argparse
import json
import re
from pathlib import Path

import fitz

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("directory", type=Path)
parser.add_argument("--render-dir", type=Path)
args = parser.parse_args()
results = []
for name in ["preview", "final", "bundle-300-wide"]:
    path = args.directory / ("worksheet-" + name + ".pdf")
    document = fitz.open(path)
    assert len(document) > 2, name
    spans = []
    for page in document:
        assert abs(page.rect.width - 841.89) < 1 and abs(page.rect.height - 595.28) < 1, "A4 landscape"
        for block in page.get_text("dict")["blocks"]:
            for line in block.get("lines", []):
                for span in line["spans"]:
                    if not span["text"].strip():
                        continue
                    spans.append(span)
                    x0, y0, x1, y1 = span["bbox"]
                    assert x0 >= 0 and y0 >= 0 and x1 <= page.rect.width + 0.5 and y1 <= page.rect.height + 0.5, (name, "clipped text", span)
    assert min(span["size"] for span in spans) >= 6.99, name
    file_spans = [span for span in spans if "IMG_" in span["text"] and "/" not in span["text"]]
    assert file_spans and min(span["size"] for span in file_spans) >= 7.99, "filenames >= 8pt"
    text = "\n".join(page.get_text() for page in document)
    compact = re.sub(r"[\s\u200b]", "", text)
    result = {"file": path.name, "pages": len(document), "minFontPt": min(span["size"] for span in spans),
              "minFilenameFontPt": min(span["size"] for span in file_spans), "layout": "A4-L", "clippedSpans": 0}
    if name == "bundle-300-wide":
        for index in range(300):
            assert compact.count(f"IMG_{index:04d}.JPG") == 1, index
        assert "очень_длинное_имя_" * 18 + "0007.JPG" in compact, "long name was changed/lost"
        for index in range(10):
            assert "Продукция" + str(index) in compact, "missing dynamic column"
        # The order amount occurs in one bold cell; the separate electronic aggregate is regular text.
        assert sum("123,45" in span["text"] and "Bold" in span["font"] for span in spans) == 1, "repeated order money"
        result.update({"bundleFilenames": 300, "longFilenameIntact": True, "dynamicColumns": 10, "orderMoneyCells": 1})
    else:
        for index in range(200):
            assert "MF-bulk-" + str(index) in compact, "missing order"
        if name == "final":
            assert "7c9e6679-7425-40de-944b-e07fc1f90ae7" in compact, "selected version ID"
            assert compact.count("Школа/Группа/Печать/399шт/IMG_0007.jpg") == 200, "saved paths changed/lost"
        result["orders"] = 200
    if args.render_dir:
        args.render_dir.mkdir(parents=True, exist_ok=True)
        for number in sorted({0, 1, len(document) - 1}):
            document[number].get_pixmap(matrix=fitz.Matrix(1.2, 1.2)).save(args.render_dir / f"{name}-page-{number + 1}.png")
    results.append(result)
print(json.dumps({"status": "PASS", "tool": "PyMuPDF " + fitz.VersionBind, "pdfs": results}, ensure_ascii=False, indent=2))
