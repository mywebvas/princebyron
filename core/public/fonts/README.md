# Self-hosted typefaces

Two families, four files, **95 KB total** — served from this directory, never
from a CDN. That is deliberate: a font CDN puts a third-party request in front of
first paint, adds a DNS+TLS round trip on mobile connections, and logs every
visitor's IP at somebody else's company. The low-bandwidth promise on this site
is easier to keep when the type is a file we ship.

| File | Family | Script | Axes | Size |
|---|---|---|---|---|
| `cinzel-latin.woff2` | Cinzel | Latin | weight 400–900 (variable) | 25 KB |
| `cinzel-latin-ext.woff2` | Cinzel | Latin Extended | weight 400–900 (variable) | 13 KB |
| `source-sans-latin.woff2` | Source Sans 3 | Latin | weight 200–900 (variable) | 23 KB |
| `source-sans-latin-ext.woff2` | Source Sans 3 | Latin Extended | weight 200–900 (variable) | 32 KB |

Both are **variable** fonts: one file covers every weight, so a headline at 700
and body copy at 400 cost nothing extra. Both are subset to the characters this
site actually uses, which is why a 125 KB Cinzel becomes 25 KB.

## Licences

Both families are licensed under the **SIL Open Font License 1.1**, which permits
bundling and self-hosting. The full licence texts sit beside the files as
`OFL-Cinzel.txt` and `OFL-SourceSans3.txt` — the OFL requires the licence to
travel with the font, so these files are a legal requirement, not documentation.

## Rebuilding the subsets

The subsets were produced with `fonttools`:

```sh
pip install fonttools brotli
# Cinzel is a static-axis variable font; Source Sans 3 likewise.
pyftsubset Cinzel[wght].ttf --output-file=cinzel-latin.woff2 --flavor=woff2 \
  --unicodes="U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD" \
  --layout-features="kern,liga,calt,onum" --no-hinting --desubroutinize
```

If the site ever starts using characters outside Latin/Latin-Extended (Cyrillic,
Greek, CJK), add the matching subset rather than un-subsetting the whole font: a
full CJK face is several megabytes and would undo the entire performance budget.

**Do not** replace these with a `@import` from Google Fonts. That is what the
project did *not* do before Sprint 3 — the fonts were named in the design tokens
but never loaded, so headlines rendered in Times New Roman on Windows and
Android.
