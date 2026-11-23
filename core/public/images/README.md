# Image derivatives

Every photograph and product shot in this directory is shipped at four widths in
two formats, generated ahead of release by `tools/images.php`. No CDN, no image
service, no runtime dependency — just files, because the deployment target is a
shared host that runs nothing extra.

| Width | Used for |
|---|---|
| 480 | phone cards, phone hero |
| 960 | phone hero at 2x, tablet cards, desktop cards |
| 1600 | tablet/desktop hero |
| 2400 | desktop hero at 2x (only where the master is large enough) |

Format order is **WebP first, JPEG as the fallback**, selected by `<picture>`.

## Why this exists (review P2-6)

Before Sprint 3 every page shipped one enormous file per image: the portrait was
927 KB, the hoodie 1.03 MB, the album art 710 KB. A phone downloading a 1463 px
portrait to fill a 390 px box wastes ~90% of the transfer and delays LCP. The
same page also had no intrinsic `width`/`height`, so the layout shifted when each
image arrived (CLS).

Measured result after generation, hero at 480 px:

| | Before | After (480 px WebP) |
|---|---|---|
| Hero portrait | 277 KB | 31 KB |
| Bio portrait | 927 KB | 14 KB |
| Product card | ~1.0 MB | 36 KB |

## Regenerating

```sh
php tools/images.php --force
```

The script:

- **never upscales** — a 1463 px master produces 480/960 only, because a
  2400 px file from the same source is a larger file with no more detail;
- writes `{name}-{width}.{webp,jpg}` beside the original;
- is idempotent — `php tools/images.php` without `--force` only fills gaps;
- degrades honestly: if the PHP build has no WebP support, it writes the JPEG
  derivatives and skips the WebP slot rather than mislabelling a JPEG as WebP.

**Keep the originals.** They are the masters used to regenerate derivatives, and
the `<x-img>` component falls back to them if the derivatives are missing.

## Adding an image

1. Put the master here (or in `products/`, `music/`).
2. Run `php tools/images.php`.
3. Reference it with the component, never a bare `<img>`:

```blade
<x-img
  src="images/products/pb-tee.jpg"
  alt="Prince Byron heavyweight tee, front"
  sizes="(max-width: 620px) 92vw, 360px"
/>
```

The component reads the directory, so the `srcset` can only ever contain files
that exist — hand-written `srcset` lists silently 404 on the largest screen,
which is the one nobody tests on.
