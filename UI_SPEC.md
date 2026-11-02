# Campus Manager Dashboard UI Spec

## Reference and measurement note

The supplied attachment contains the written TeamHub brief but no reference image. The palette and proportions below are therefore implementation estimates based on that brief, not color-picker samples. They should be tuned against the image if it is supplied later.

## Tokens

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--primary` | `#176B50` | `#56B88D` | Primary actions and active navigation |
| `--mint-100` | `#E4F3EC` | `#243D30` | Soft mint surfaces and pills |
| `--mint-300` | `#A7DCC2` | `#72C69F` | Chart fills and progress bars |
| `--mint-500` | `#4BAE84` | `#56B88D` | Chart line and emphasis |
| `--bg` | `#F3F7F5` | `#14201B` | Page background |
| `--surface` | `#FFFFFF` | `#1B2922` | Cards and sidebar |
| `--border` | `#E3EBE6` | `#304238` | Hairline dividers |
| `--text` | `#1D2C25` | `#E8F1EB` | Main text |
| `--text-2` | `#596C62` | `#B6C6BB` | Secondary text |
| `--text-3` | `#85958C` | `#8EA296` | Captions and muted labels |
| `--ok-fg` | `#17734F` | `#8FD8AE` | Approved / active |
| `--danger-fg` | `#B24448` | `#E99A9A` | Rejected / alert |
| `--warn-fg` | `#9A6414` | `#E5C27C` | Pending |
| `--info-fg` | `#316F82` | `#8FC6D4` | Informational |

The full semantic colors, mint steps, surfaces, shadows, radii, typography, and spacing scale are defined in `assets/css/style.css` under `:root` and `[data-theme='dark']`. The selected primary is intentionally a darker mint-green so white text remains readable on filled controls.

## Typography

Plus Jakarta Sans is the single family for headings, labels, and body copy, with Inter / DM Sans / system sans fallbacks. No serif is used. Page title is about 25px, card title 16px, labels 11px, body 14px, and caption 12px.

## Shape, spacing, and icons

- Cards: 16px radius, 20px on larger feature cards; soft 2–10px shadows in light mode.
- Pills: fully rounded; controls and chips use the same radius token.
- Spacing scale: 4, 8, 12, 16, 20, 24, and 32px.
- Navigation and controls use the existing inline SVG line icon set at 17–20px.
- Hairline borders use the shared border tokens; focus rings use the primary token.

## Layout

- Desktop sidebar: 252px, expanded by default; 72px top bar.
- Main content: max width 1440px, 24px side gutters, 20px dashboard grid gaps.
- KPI row: five equal columns at wide desktop, then three/two/one columns at narrower widths.
- Dashboard workspace: wide activity/table column plus a 340px right rail, collapsing to one column below 1100px.
- Mobile sidebar: off-canvas drawer; content and cards stack at 768px and 375px.
- Footer follows document content and does not overlay it.

## Component inventory

| TeamHub pattern | Campus Manager component |
| --- | --- |
| Active mint navigation pill | `includes/sidebar.php` `.nav__link.is-active` |
| Search, round actions, user chip | `includes/navbar.php` `.topbar` |
| Leave summary cards with rings | `dashboard.php` `.metrics-grid` / `.metric-card` |
| Performance overview / mint area chart | Dashboard enrollment activity |
| Rounded hours columns | Dashboard grade or enrollment category chart |
| Donut and legend | Dashboard grade distribution |
| Horizontal mint progress bars | Dashboard active students by faculty (`render_bar_chart`) |
| Profile / employee details | Existing student and lecturer detail templates |
| Dense management tables | Existing module list templates and shared `.table` styles |
| Internal notes list | Dashboard campus notices |
| Light identity card | Dashboard student identification card |
| Colored quick-link tiles | Dashboard campus quick access |
| Calendar widget | Not added: no academic-calendar data source or route exists in this project |
| Footer links | Existing build and institutional footer; no Privacy or Terms routes exist |

## Fidelity limitations

The image needed for measured sampling and side-by-side comparison was not present in the attachment. The exact TeamHub logo, its image-specific values, a calendar data source, and Privacy / Terms destinations are unavailable. Campus Manager branding, existing routes, data, and server-side behavior are retained.
