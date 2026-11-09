# Public website assets and licenses

All product visuals on the public website (hero preview, walkthrough, feature stories, team and security panels) are HTML/CSS/SVG illustrations built from the existing application UI vocabulary with **fictional sample data**. No real borrower documents, customer data, logos or screenshots of customer files are used.

## Photography

Downloaded once to local files and served from `public/images/site/` (no hotlinking, no third-party requests). Optimized with Pillow 13 into AVIF, WebP and progressive JPEG at 640 px and 1200 px widths; EXIF metadata is not carried over. Every `<img>` declares `width`/`height`, meaningful `alt` text and `loading="lazy"` (both photos are below the fold).

| File stem | Used on | Photographer | Source | License |
|---|---|---|---|---|
| `mortgage-review-{640,1200}.{avif,webp,jpg}` (3:2 crop) | Homepage “Built for your workflow” | RDNE Stock project | https://www.pexels.com/photo/a-person-holding-loan-documents-8292879/ | [Pexels License](https://www.pexels.com/license/) — free to use and modify, attribution not required; identifiable people must not appear in a bad light, and use must not imply their endorsement of the product |
| `townhomes-{640,1200}.{avif,webp,jpg}` (16:9) | Homepage “Review property and rental income details together.” | Curtis Adams | https://www.pexels.com/photo/exterior-of-residential-homes-15409440/ | [Pexels License](https://www.pexels.com/license/) |

Notes:
- The mortgage-review photo is a stock image showing a blank/prop loan application form; it is not a real borrower document. Its alt text describes the scene and no caption implies the person is a customer or endorses the product.
- The townhomes photo is illustrative only; the rental figures shown with it are fictional and labelled “Sample data”.
- Licenses were read from the Pexels photo pages on 2026-11-09 (Asia/Dhaka). Before public launch, confirm the brand team approves these choices or replace them with supplied brand photography (same file names and dimensions keep the layout stable).

## Icons and illustrations

Inline SVG icons are hand-written simple paths (lock, people, shield, house). The brand mark is the existing MortgageLens house icon. No icon library or external font is loaded.
