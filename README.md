# WP Realtime Sports Engine (WRSE)

Senior-level WordPress plugin for realtime scores and odds with multi-source crawling, caching (transient + DB + JSON fallback), REST API, shortcodes, cron workers, and admin tools.

## Installation
1. Copy `wp-re-sports` into `wp-content/plugins/`.
2. Activate via WP Admin.
3. On activation, custom tables are created: `wrse_matches`, `wrse_odds_history`, `wrse_logs`.

## Settings
- Admin page: `wp-admin/admin.php?page=wrse-settings`
- Configure source URLs (JSON + HTML), refresh intervals, league whitelist.
- Tools: run crawler manually; view latest logs.

## Shortcodes
- Scoreboard: `[wrse_scoreboard league="all" date="today" view="compact"]`
- Match Center: `[wrse_match_center match_id="123"]`
- Upcoming: `[wrse_upcoming limit="5"]`

## REST API (`/wp-json/wrse/v1`)
- `/matches?league=all&date=YYYY-MM-DD&live=true&sort=time_asc`
- `/match/{id}`
- `/odds/{match_id}`
- `/stats/{match_id}`
- `/leagues`
- `/debug/logs` (admin only)

## Cron Workers
- `wrse_cron_1min`: refresh live matches + odds tracking.
- `wrse_cron_10min`: refresh upcoming (next 48h).
- `wrse_cron_daily`: cleanup logs/odds history.

## Data Flow
Sources (2+) → normalization → merge → transient cache → DB upsert → JSON fallback (`cache/matches_today.json`, `cache/upcoming.json`).
