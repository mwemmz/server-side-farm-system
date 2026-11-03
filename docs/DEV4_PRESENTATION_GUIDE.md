# FFMS — Developer 4 Presentation Guide

Farm Management System (FFMS)

A speaking and code reference guide for Developer 4, covering the opening slot, closing slot, and all new sprint features. Build at commit 464e607.

---

## 1. Overview

Developer 4 opens and closes the 20-minute demo. The opening establishes roles and login. The closing shows where all the money and alerts from the other three developers land. Developer 4 also owns the four new features from the latest sprint: live weather forecasting, intranet and extranet communications, farm-to-farm connections, and the farm trust score.

| Item | Detail |
|------|--------|
| Modules (original) | Security, Dashboard, Finance, Reports, Notifications, AI Assistant, Weather |
| Modules (new sprint) | Weather Forecasting, Communications (Intranet + Extranet), Farm Connections, Farm Trust Score |
| Slots | Opening 3 min (slot 1) · Closing 3 min (slot 5) |
| Demo account | Opening: worker → accountant → admin. Closing: admin |

**How to explain it in 30 seconds**

I own login and roles, the Dashboard, Finance, Reports, Notifications, the Assistant, and the new network layer: live weather, farm messaging, and trust scoring.

I connect to everyone. The other three developers send me money records and alerts, and I decide which screen each role sees. In the new sprint I also connected farms to each other across the extranet.

Say: *"I own the beginning and the end of the farm day — and now I connect farms to each other too."*

---

## 2. Segment 1 — Opening: Roles and Entry Points (3 min)

### The story

One system, four roles. Each person lands on the screen that fits their job the moment they log in. No configuration, no manual routing — the role drives everything automatically.

### How it works in code

After a successful login, `SecurityController` reads the authenticated user's role and calls `RoleHelper::homeFor()` to resolve the correct landing screen:

```php
// src/Helpers/RoleHelper.php
public static function homeFor(string $role): string {
    return match($role) {
        'worker'     => 'index.php?module=Dashboard&view=worker',
        'accountant' => 'index.php?module=Finance',
        default      => 'index.php?module=Dashboard',
    };
}
```

The sidebar is filtered by the same role. `RoleHelper::canSee()` is called for every nav item before it renders, so a worker cannot see Finance or Labour even if they know the URL. The server enforces it too — no relying on the UI to hide things:

```php
// public/index.php
$financeOnly = ['admin', 'accountant', 'farm-owner'];
if ($module === 'Finance' && !in_array($role, $financeOnly, true)) {
    die("Access Denied.");
}
```

JWT is used for the REST API layer. Every `/api/v1/` request goes through `api_require_auth()` in `bootstrap.php`, which decodes the token and returns the payload. No token, no access:

```php
// public/api/v1/bootstrap.php
function api_require_auth() {
    $token   = JwtHelper::tokenFromHeader();
    $payload = $token ? JwtHelper::verify($token) : null;
    if (!$payload) {
        api_err('Unauthorized: missing or invalid token', 401);
    }
    return $payload; // { sub: user_id, role: 'admin' }
}
```

### Minute-by-minute script

| Time | Say | Do | You should see |
|------|-----|----|----------------|
| 0:00 | "FFMS knows who you are the moment you log in." | Log in as the worker | "My day": attention card, quick buttons, no money or HR |
| 0:45 | "The worker sees only today's work." | Point at the short sidebar, then log out | Short menu with no Finance or Labour |
| 1:15 | "The accountant opens straight on Finance." | Log in as the accountant | Finance is the landing screen |
| 2:00 | "Admin and manager get the full picture." | Log in as admin, show full sidebar | Dashboard with full menu |
| 2:45 | Hand-off sentence | Stay on the Dashboard | — |

**Hand-off sentence:** *"Now Developer 1 will start the farm day from the field."*

---

## 3. Segment 2 — Closing: Finance, Reports, Notifications, Assistant (3 min)

### The story

None of these Finance records were typed manually. They were posted automatically when Developer 3 recorded a treatment, a sale, and a purchase, and when Developer 2 recorded a service and ran payroll. The system writes once at the source; Finance just reads.

### How it works in code

Every source module calls a shared `FinanceHelper::post()` at the point of saving. For example, when a livestock treatment is recorded:

```php
// src/Modules/Livestock/LivestockModel.php
if (!empty($data['cost']) && (float)$data['cost'] > 0) {
    FinanceHelper::post($this->pdo, [
        'type'           => 'expense',
        'amount'         => $data['cost'],
        'description'    => 'Treatment: ' . $data['treatment_type'],
        'category'       => 'Veterinary & medicines',
        'related_module' => 'Livestock',
        'related_id'     => $id,
    ]);
}
```

The same pattern applies to sales, procurement purchases, equipment services, and payroll. Finance never imports or polls — it receives a write at the moment each event is saved. The `related_module` and `related_id` columns mean you can always trace a Finance record back to its source record with one click.

The AI Assistant answers farm questions by querying live data across all modules:

```php
// src/Intelligence/Assistant.php
public function answer(string $question): array {
    $q = strtolower($question);
    if (str_contains($q, 'stock') || str_contains($q, 'inventory')) {
        return $this->stockSummary();
    }
    if (str_contains($q, 'attention') || str_contains($q, 'today')) {
        return $this->attentionSummary();
    }
    // ... more intent handlers
}
```

### Minute-by-minute script

| Time | Say | Do | You should see |
|------|-----|----|----------------|
| 0:00 | "None of these records were typed by Finance." | Open Finance, show treatment K50, sale K250, service K100 | Auto-posted records from each source |
| 0:50 | "Filter by category and the subtotals follow." | Filter by Veterinary, then Crop sales | Subtotals update instantly |
| 1:20 | "Loans track themselves." | Record a repayment | Balance falls, expense posted |
| 1:50 | "Needs attention — in three places at once." | Dashboard attention card, then open Notifications | Same items in both |
| 2:20 | "Ask the farm anything." | Assistant: "What needs attention today?", then Export CSV | An answer; a CSV downloads |
| 2:50 | Closing sentence | — | — |

**Closing sentence:** *"That is FFMS: one farm, four roles, one set of records. And now farms can connect to each other too."*

---

## 4. Segment 3 — Weather Forecasting (new sprint)

### The story

Weather used to be a manual data-entry form — you typed in temperature and humidity yourself. Now it is a live 7-day meteorological forecast per farm with daily agricultural advice generated from the conditions. No API key, no cost, no manual entry.

### How it works in code

`WeatherForecastService` builds a request URL using the farm's stored latitude and longitude, then calls Open-Meteo over HTTPS:

```php
// src/Services/WeatherForecastService.php
$url = sprintf(
    'https://api.open-meteo.com/v1/forecast'
    . '?latitude=%.4f&longitude=%.4f'
    . '&daily=temperature_2m_max,temperature_2m_min,'
    . 'precipitation_sum,windspeed_10m_max,weathercode,'
    . 'precipitation_probability_max'
    . '&hourly=temperature_2m,relativehumidity_2m,precipitation'
    . '&current_weather=true&timezone=auto&forecast_days=7',
    $lat, $lon
);
```

Open-Meteo returns WMO weather codes (0 = clear sky, 95 = thunderstorm, etc.). The service converts those into plain-English farm advice — no AI, just a lookup with farm-specific logic:

```php
public static function farmAdvice(int $code, float $rain, float $wind): string {
    if ($code >= 95) {
        return '⚠️ Severe weather: secure equipment, move livestock to shelter,
                postpone all field operations.';
    }
    if ($rain > 5) {
        return '💧 Significant rainfall: ideal for planting,
                reduce irrigation, check drainage channels.';
    }
    if ($code === 0 || $code === 1) {
        return '☀️ Clear skies: ideal for harvesting, drying crops,
                field operations, and spraying.';
    }
    // ... more conditions
}
```

Results are cached for one hour in the `app_settings` table so the page never stalls waiting on an external API:

```php
private function saveCache(string $key, array $data): void {
    $this->pdo->prepare(
        "INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, NOW())
         ON CONFLICT (key) DO UPDATE
         SET value = EXCLUDED.value, updated_at = NOW()"
    )->execute([$key, json_encode($data)]);
}
```

The hourly chart on the page is Chart.js reading JSON embedded in the view — temperature as a line, rainfall as bars, humidity as a dashed line, all on one canvas:

```javascript
new Chart(ctx, {
    data: {
        datasets: [
            { type: 'line', label: 'Temp (°C)',   data: temps, yAxisID: 'yTemp' },
            { type: 'bar',  label: 'Rain (mm)',   data: rain,  yAxisID: 'yRain' },
            { type: 'line', label: 'Humidity (%)', data: hum,  yAxisID: 'yTemp' }
        ]
    }
});
```

### What to say

*"Weather is not just a number on the dashboard anymore. The system reads the farm's GPS coordinates, fetches a live 7-day forecast, and tells the farmer what to do — 'Rain expected: do not spray fertiliser today', or 'Clear skies: ideal for harvesting.' It refreshes every hour and caches the result so the page is always fast. Zero cost, zero setup — it just works from the coordinates the farm entered at registration."*

### What to click

1. Open **Weather** in the sidebar
2. Select a farm from the dropdown — the forecast loads immediately from cache or live
3. Point at the current conditions hero card: temperature, wind speed, UV index
4. Point at the seven-day forecast tiles — temperature range, rain probability, condition icon
5. Point at the farm advice sentence under each tile
6. Scroll down to the hourly chart and the hourly detail table

### Do not click

A farm with no GPS coordinates set. It falls back to the Lusaka default silently, which will confuse the audience. Verify coordinates are saved at farm registration before going live.

---

## 5. Segment 4 — Communications: Intranet and Extranet (new sprint)

### The story

Two separate messaging channels in one module. Intranet is private chat between a farm's own staff — workers, managers, the owner. Extranet is secure farm-to-farm messaging, available only after two farms have an accepted connection. The separation is enforced at the database query level, not just hidden in the UI.

### How it works in code

Every read goes through `CommsService`, which checks farm membership before returning anything. For intranet, the query joins `conversation_members` and filters by `farm_id`:

```php
// src/Services/CommsService.php
public function listConversations($userId, $scope) {
    if ($scope === 'intranet') {
        $st = $this->pdo->prepare(
            "SELECT c.* FROM conversations c
             JOIN conversation_members m ON m.conversation_id = c.id
             WHERE c.scope = 'intranet'
               AND c.farm_id = ?       -- must match YOUR farm
               AND m.user_id = ?
             ORDER BY c.created_at DESC"
        );
        $st->execute([$myFarm, $userId]);
    }
}
```

Creating an extranet conversation refuses unless an accepted `farm_connections` row exists:

```php
if ($scope === 'extranet') {
    foreach ($memberIds as $mid) {
        $otherFarm = $this->farmIdForUser($mid);
        if (!$this->hasAcceptedConnection($myFarm, $otherFarm)) {
            throw new Exception(
                'Extranet requires an accepted farm connection.'
            );
        }
    }
}
```

The broadcast method posts one system message to every farm member in a single operation. It also clears any aborted Neon pooler transaction state before beginning, which was a real bug we found and fixed during smoke testing:

```php
public function broadcast($userId, $role, $title, $body) {
    // Neon pooler reuses connections — a prior error can leave an aborted
    // transaction that poisons the next BEGIN. ROLLBACK clears it.
    try { $this->pdo->query("ROLLBACK"); } catch (\Throwable $e) {}

    $st = $this->pdo->prepare(
        "INSERT INTO conversations (scope, kind, farm_id, title, created_by)
         VALUES ('intranet', 'broadcast', ?, ?, ?) RETURNING id"
    );
    $st->execute([$myFarm, $title, $userId]);
    // ... adds all farm_members, posts body as a system message
}
```

The REST API exposes both channels under `/api/v1/intranet/` and `/api/v1/extranet/`. All ten endpoints were smoke-tested live against the deployed Vercel instance and passed:

| # | Endpoint | Result |
|---|----------|--------|
| 1 | GET intranet/conversations | ✅ 200 |
| 2 | POST intranet/conversations/{id}/messages | ✅ 200 |
| 3 | GET intranet/conversations/{id}/messages | ✅ 200 |
| 4 | POST intranet/conversations/{id}/read | ✅ 200 |
| 5 | POST intranet/broadcast | ✅ 200 |
| 6 | GET extranet/conversations | ✅ 200 |
| 7 | POST extranet/conversations | ✅ 200 |
| 8 | GET extranet/connections | ✅ 200 |
| 9 | GET extranet/farms/search | ✅ 200 |
| 10 | POST messages/{id}/report | ✅ 200 |

### What to say

*"Think of what a company has: an internal messaging system for your team, and a secure external channel for talking to partner farms. A worker can message the manager without picking up a phone. A farm owner can negotiate a seed deal with another farmer without leaving the system. The two channels are completely separate — your intranet is invisible to other farms, enforced at the database query level, not just hidden in the UI."*

### What to click

1. Open **Communications** in the sidebar
2. Show the **Intranet** tab — select a conversation, send a short message, watch the chat bubble appear
3. Switch to the **Extranet** tab — point at the notice: requires an accepted connection
4. Open the broadcast panel (managers and above) and say: *"One message, every staff member, one click"*

### Do not click

Starting an extranet conversation without a pre-existing accepted connection. It returns an error that looks like a crash on stage. Make sure two farms have an accepted connection in place before the demo.

---

## 6. Segment 5 — Farm Connections (new sprint)

### The story

Before two farms can message each other through the extranet, they go through a mutual-accept handshake — the same pattern as a professional network connection request. The connection record is the key that unlocks extranet messaging and makes each farm's trust score visible to the other.

### How it works in code

`FarmConnectionService` manages the graph. A request creates a `pending` row; a decision updates it to `accepted`, `declined`, or `blocked`:

```php
// src/Services/FarmConnectionService.php
public function requestConnection($userId, $targetFarmId, $role) {
    if (!in_array($role, ['admin','manager','farm-owner','owner'], true)) {
        throw new Exception('Only owners and managers can request connections.');
    }
    $fromFarm = $this->farmIdForUser($userId);
    if ($fromFarm === $targetFarmId) {
        throw new Exception('Cannot connect a farm to itself.');
    }
    $this->pdo->prepare(
        "INSERT INTO farm_connections
             (requester_farm_id, target_farm_id, status, requested_by)
         VALUES (?, ?, 'pending', ?) RETURNING id"
    )->execute([$fromFarm, $targetFarmId, $userId]);
}
```

The farm search that populates the connection page was fixed during testing — the original query referenced `district` and `region` columns that do not exist on the `farms` table. The fix falls back to the `location` column and adds a `district` alias so existing views do not break:

```php
public function searchFarms($userId, $district, $q) {
    $sql = "SELECT id, name, location FROM farms WHERE 1=1";
    if ($q !== '') {
        $sql .= " AND (LOWER(name) LIKE LOWER(?)
                      OR LOWER(COALESCE(location,'')) LIKE LOWER(?))";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    // return location as 'district' so views do not need changing
    return array_map(fn($r) => $r + ['district' => $r['location'] ?? ''], rows);
}
```

### What to say

*"Before two farms can message each other they have to connect — exactly like a professional network request. You search by name, send the request, and the other farm accepts. Only then does the extranet open between them. And next to every farm in the search results you will see their trust score — so you can decide whether to connect before you even send the request."*

### What to click

1. Open **Connections** in the sidebar
2. Search for a farm by name
3. Point at the trust score badge next to each result
4. Show an existing connection with the green **accepted** status badge
5. Hand to Communications extranet tab to show the message channel is now open

### Do not click

Requesting a connection from a farm to itself. It is refused correctly but the error message looks odd to an audience who does not know why you would try it.

---

## 7. Segment 6 — Farm Trust Score (new sprint)

### The story

When you find another farm on the extranet, you do not just see their name. You see a score from 0 to 100 computed entirely from the records they already keep in the system. No one typed the score. It comes from real data: do they have their livestock vaccinated, are their finances categorised, have they made sales recently. A Gold farm is a reliable operation. A Bronze farm is just getting started.

### How it works in code

`FarmTrustScoreService` computes six factors and combines them with fixed weights. Each factor queries a different part of the database. For example, the compliance factor checks vaccination coverage and incident resolution rate:

```php
// src/Services/FarmTrustScoreService.php
private function complianceScore(int $farmId): array {
    $total = (int) $this->scalar(
        "SELECT COUNT(*) FROM livestock WHERE farm_id = $farmId"
    );
    $vaccinated = (int) $this->scalar(
        "SELECT COUNT(DISTINCT livestock_id) FROM vaccinations v
         JOIN livestock l ON l.id = v.livestock_id
         WHERE l.farm_id = $farmId
           AND v.vaccination_date >= CURRENT_DATE - INTERVAL '365 days'"
    );
    $vacRate = $total > 0 ? $vaccinated / $total : 1.0;

    $incidents = (int) $this->scalar(
        "SELECT COUNT(*) FROM health_incidents hi
         JOIN livestock l ON l.id = hi.livestock_id
         WHERE l.farm_id = $farmId"
    );
    $resolved = (int) $this->scalar(
        "SELECT COUNT(*) FROM health_incidents hi
         JOIN livestock l ON l.id = hi.livestock_id
         WHERE l.farm_id = $farmId
           AND LOWER(COALESCE(hi.status,'')) IN ('resolved','treated','closed')"
    );
    $incRate = $incidents > 0 ? $resolved / $incidents : 1.0;

    $score = (int) round((($vacRate + $incRate) / 2) * 100);
    return [$score, ['livestock' => $total, 'vaccinated' => $vaccinated,
                     'incidents' => $incidents, 'resolved' => $resolved]];
}
```

The final score is a weighted average across all six factors:

```php
private array $weights = [
    'data_completeness'   => 0.20,
    'sales_activity'      => 0.20,
    'financial_health'    => 0.15,
    'traceability'        => 0.15,
    'connection_standing' => 0.15,
    'compliance'          => 0.15,
];

// weighted total
$score = 0;
foreach ($this->weights as $key => $weight) {
    $score += $factors[$key] * $weight;
}
$band = match(true) {
    $score >= 85 => 'platinum',
    $score >= 65 => 'gold',
    $score >= 40 => 'silver',
    default      => 'bronze',
};
```

The result is stored in `farm_trust_scores` and reused for 24 hours before recomputing. The schema is a simple append-only table so history is free:

```sql
-- db/trust_schema.sql
CREATE TABLE IF NOT EXISTS farm_trust_scores (
    id          SERIAL PRIMARY KEY,
    farm_id     INT NOT NULL REFERENCES farms(id) ON DELETE CASCADE,
    score       INT NOT NULL CHECK (score >= 0 AND score <= 100),
    band        VARCHAR(20) NOT NULL CHECK (band IN ('bronze','silver','gold','platinum')),
    factors     JSONB,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### The six factors

| Factor | What it measures | Weight |
|--------|-----------------|--------|
| Data Completeness | Fields, crops, livestock and equipment on record | 20% |
| Sales Activity | Sales transactions in the last 90 days | 20% |
| Financial Health | Percentage of expense records that are categorised | 15% |
| Traceability | Harvest batches with a full provenance chain | 15% |
| Connection Standing | Accepted connections as a share of all decided connections | 15% |
| Compliance | Vaccinations current and health incidents resolved | 15% |

### Bands

| Band | Score | Meaning |
|------|-------|---------|
| 🥉 Bronze | 0 – 39 | Getting started |
| 🥈 Silver | 40 – 64 | Good standing, room to grow |
| 🥇 Gold | 65 – 84 | Strong and reliable operation |
| 💎 Platinum | 85 – 100 | Exemplary farm practices |

### What to say

*"When you search for another farm to connect with, you see their trust score next to their name. A Gold farm has recent sales, categorised finances, and vaccinations up to date. A Bronze farm is just getting started. No one typed that score — it comes entirely from the records the farm already keeps. The more you use the system properly, the higher your score. It is an incentive to keep good records, not just a badge."*

### What to click

1. Open **Farm Trust** in the sidebar under the Network group
2. Select a farm from the dropdown
3. Point at the score hero card: the large number, the progress bar from 0 to 100, the band badge
4. Point at the factor breakdown — six bars coloured green, amber, or red by score, each with evidence counts below
5. Point at the leaderboard on the right sidebar
6. Navigate to **Connections**, search for a farm, and show the trust badge appearing next to the result

### Do not click

Clicking Recompute Score on a farm that has no records during the live demo — it will score zero and look like a broken feature. Pre-seed at least one farm with records and pre-compute before going on stage.

---

## 8. Technology Stack (30-second block)

Say this as a single paragraph, not a list. It reads faster and sounds more confident:

*"The backend is PHP 8 with a PostgreSQL database on Neon — a serverless Postgres that scales to zero between requests, which means no idle cost. The app is deployed on Vercel using a PHP serverless runtime, so there is no server to manage or patch. Auth is JWT for the REST API and PHP sessions for the web UI. Charts are Chart.js. Weather is Open-Meteo — free, no key. Email is PHPMailer with a raw-socket SMTP fallback written in PHP so it works even if Composer packages are unavailable. The entire deployed package is under two megabytes."*

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.3 |
| Database | PostgreSQL on Neon (serverless) |
| Hosting | Vercel (PHP serverless runtime) |
| Authentication | JWT (API) + PHP sessions (web UI) |
| Charts | Chart.js |
| Weather | Open-Meteo API (free, no API key) |
| Email | PHPMailer + native PHP SMTP socket |
| Frontend styling | Tailwind CSS (CDN) |

---

## 9. Likely Questions

| Question | Answer | Who answers |
|----------|--------|-------------|
| How does the system know which screen to show? | The role is stored on the user record. After login, `RoleHelper::homeFor()` redirects to the right screen and the sidebar is filtered by the same rule on the server. | Developer 4 |
| Can a worker see Finance by typing the URL? | No. The server checks the role before rendering any Finance page and returns Access Denied. The UI hiding it is a second layer, not the only one. | Developer 4 |
| Where does the weather data come from? | Open-Meteo, a free open meteorological API. No key needed. It uses the GPS coordinates stored when the farm was registered. | Developer 4 |
| Can farms in different districts message each other? | Yes, through the Extranet. They need an accepted connection first — it is not an open channel. | Developer 4 |
| What is the trust score based on? | Six factors computed from records already in the system: data completeness, sales activity, financial health, traceability, connection standing, and health compliance. No manual input. | Developer 4 |
| Does email work on Vercel? | Yes. We use ports 587 and 465, which Vercel allows. SMTP settings are saved inside the app under Notifications → SMTP Settings — no config file, no redeployment needed. | Developer 4 |
| What if the weather API goes down? | The last cached forecast, up to one hour old, is shown with a timestamp. If there is no cache, a graceful error card appears instead of a crash. | Developer 4 |
| Who can broadcast to all staff? | Managers, farm owners, and admins only. Workers see broadcasts in their conversation list but cannot send one. The role check is server-side. | Developer 4 |
| How is the trust score updated? | It recomputes automatically every 24 hours, or you can trigger it manually with the Recompute button. The history is kept so you can see improvement over time. | Developer 4 |

---

## 10. Do Not Click

| Do not | What happens | Say instead |
|--------|--------------|-------------|
| Extranet messaging without an accepted connection | Empty list, looks broken | "Extranet opens once the connection is accepted — I show that in Connections first." |
| Trust score recompute on a farm with no records | Scores zero, looks like a bug | Use a pre-seeded farm with records |
| Farm connecting to itself | Refused, but the error looks odd on stage | Never demo this edge case |
| Notifications → Run rules as a worker | Workers can trigger a scan; known gap | "Rule execution is admin and manager only in the next build." |
| Any equipment edit screen | Not built | "Equipment edit is next on the list." |
| Leaving a browser tab idle 30+ minutes | Session expires, redirects to login | Log in again just before your slot |

---

## 11. Hand-offs

| Direction | Detail |
|-----------|--------|
| You receive (opening) | Login credentials and the role configuration |
| You hand on (opening) | The Dashboard, which Developer 1 points at for the open crop problem and Developer 2 points at for the low-stock alert |
| You receive (closing) | Treatment K50, sale K250, service K100, and payroll from Developers 1, 2, and 3 during the demo |
| You hand on (closing) | The final picture: Finance with auto-posted records, the attention card, the assistant answer |

---

## 12. Reset Checklist — Before Going Live

| Item | Who | Check |
|------|-----|-------|
| Four browser profiles logged in: admin, manager, accountant, worker | Developer 4 | Each lands on its home screen |
| Grain Silo 1 at 320 of 500 kg | Developer 2 | Storage shows 320 kg |
| One overdue vaccination | Developer 3 | Dashboard attention card shows it |
| One animal in withdrawal | Developer 3 | Worker "My day" card shows it |
| One seed item near its reorder level | Developer 2 | Inventory shows the low-stock badge |
| Demo Tractor with a 30-day service interval | Developer 2 | Equipment lists it |
| At least one farm with trust score pre-computed | Developer 4 | Farm Trust shows score and leaderboard |
| Two farms with an accepted connection | Developer 4 | Connections shows accepted status |
| Weather loaded for at least one farm | Developer 4 | Weather page shows forecast tiles |
| Site opened 10 minutes early | Developer 4 | First serverless cold start is done before audience arrives |
| 2-minute screen recording of each segment saved locally | All | Ready as fallback if network fails on stage |

---

Reference: Full Farm Management System specification, FFMS Presentation Game Plan, FFMS Developer Guide, repository mwemmz/server-side-farm-system at commit 464e607.
