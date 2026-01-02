<?php
if (!defined('ABSPATH')) exit;

function wrse_track_odds_movement() {
    $matches = wrse_get_cached_matches();
    if (empty($matches)) return;

    $tables = wrse_get_tables();
    global $wpdb;
    $matchesTable = $tables['matches'];
    $oddsTable = $tables['odds'];

    foreach ($matches as $m) {
        $matchTime = $m['match_time_utc'] ?? ($m['start_time_utc'] ?? '');
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $matchesTable WHERE league=%s AND home_team=%s AND away_team=%s AND match_time_utc=%s LIMIT 1",
            $m['league'] ?? '', $m['home_team'] ?? '', $m['away_team'] ?? '', $matchTime
        ));
        if (!$id) continue;

        $markets = [
            '1X2' => is_string($m['odds_1x2'] ?? null) ? json_decode($m['odds_1x2'], true) : ($m['odds_1x2'] ?? null),
            'OU'  => is_string($m['odds_ou'] ?? null) ? json_decode($m['odds_ou'], true) : ($m['odds_ou'] ?? null),
            'AH'  => is_string($m['odds_ah'] ?? null) ? json_decode($m['odds_ah'], true) : ($m['odds_ah'] ?? null),
        ];

        foreach ($markets as $type => $current) {
            if (empty($current)) continue;
            $currentStr = wp_json_encode($current);

            $last = $wpdb->get_var($wpdb->prepare(
                "SELECT odds_after FROM $oddsTable WHERE match_id=%d AND market_type=%s ORDER BY created_at DESC LIMIT 1",
                $id, $type
            ));

            if ($last === null) {
                $wpdb->insert($oddsTable, [
                    'match_id' => $id,
                    'market_type' => $type,
                    'odds_before' => '',
                    'odds_after' => $currentStr,
                    'created_at' => current_time('mysql')
                ]);
                continue;
            }

            if ($last !== $currentStr) {
                $wpdb->insert($oddsTable, [
                    'match_id' => $id,
                    'market_type' => $type,
                    'odds_before' => $last,
                    'odds_after' => $currentStr,
                    'created_at' => current_time('mysql')
                ]);
                wrse_log("Odds changed: match_id=$id market=$type", 'info', 'odds');
            }
        }
    }
}
