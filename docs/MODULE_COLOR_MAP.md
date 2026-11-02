# Module / Segment Color Map (for developers)

Color coding used across the FFMS UI (Tailwind palette) and the API route table.
Use these consistently when adding new modules.

| Segment | Color | Tailwind classes | Modules / Routes |
|---|---|---|---|
| Core farm data | Emerald | `bg-emerald-600`, `text-emerald-700`, `border-emerald-400` | Farm, Field, Crop, Livestock, Inventory |
| Operations (Ops engine) | Teal | `bg-teal-600`, `text-teal-700` | Ops (soil, planting, fertilizer, sprays, weights, breeding, production, mortality, feed, equipment costing, tasks, harvest, quality, dispatches, customers, markets, orders, invoices, contracts, suppliers) |
| Finance & analytics | Rose/Indigo | `bg-rose-500`, `text-indigo-700` | Finance, Analytics, Reports, Credit Passport |
| Labour & HR | Amber | `bg-amber-500`, `text-amber-700` | Labour, Leave, Payroll, Shifts, Training |
| Storage & harvest | Sky | `bg-sky-500`, `text-sky-700` | Storage, Harvest, Packaging, Quality |
| Equipment & weather | Slate/Violet | `bg-slate-700`, `bg-violet-500` | Equipment, Weather, Irrigation |
| Communication Hub | Blue | `bg-blue-600`, `text-blue-700` | Intranet, Extranet, Broadcasts, Messages |
| Traceability | Green | `bg-green-600`, `text-green-700` | Harvest batches, batch events, public chain |
| Pest & health | Red | `bg-red-600`, `text-red-700` | Health incidents, outbreaks, treatments |
| Security / admin | Slate | `bg-slate-800`, `text-slate-700` | Security, Users, Audit, Notifications rules |

## API route prefixes
- `/api/v1/analytics/*` — emerald/indigo
- `/api/v1/extranet/*` — blue
- `/api/v1/intranet/*` — blue
- `/api/v1/credit-passport/*` — indigo
- `/api/v1/traceability/*` — green
- `/api/v1/planner/*` — amber
- `/api/v1/assistant/*` — slate

## UI conventions
- Section cards: `glass-card` with a 4px top border in the segment color.
- Primary action buttons: segment color gradient.
- Destructive actions: red (`bg-red-600`).
- Money/finance figures: rose (`text-rose-600`).
- Status badges: emerald=ok, amber=warning, red=critical, slate=neutral.