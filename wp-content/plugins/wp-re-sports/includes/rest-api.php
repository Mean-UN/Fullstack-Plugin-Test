<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('wrse/v1', '/matches', [
        'methods' => 'GET',
        'callback' => 'wrse_api_matches',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wrse/v1', '/match/(?P<id>\\d+)', [
        'methods' => 'GET',
        'callback' => 'wrse_api_match',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wrse/v1', '/odds/(?P<match_id>\\d+)', [
        'methods' => 'GET',
        'callback' => 'wrse_api_odds',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wrse/v1', '/stats/(?P<match_id>\\d+)', [
        'methods' => 'GET',
        'callback' => 'wrse_api_stats',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wrse/v1', '/leagues', [
        'methods' => 'GET',
        'callback' => 'wrse_api_leagues',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wrse/v1', '/debug/logs', [
        'methods' => 'GET',
        'callback' => 'wrse_api_logs',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);
});

function wrse_get_cached_matches() {
    $t = get_transient('wrse_matches_today');
    if (is_array($t) && !empty($t)) return $t;

    // DB fallback
    $rows = wrse_get_matches_from_db();
    if (!empty($rows)) return $rows;

    // JSON fallback
    $file = WRSE_PATH . 'cache/matches_today.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function wrse_get_cached_upcoming() {
    $t = get_transient('wrse_matches_upcoming');
    if (is_array($t) && !empty($t)) return $t;
    $file = WRSE_PATH . 'cache/upcoming.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function wrse_api_matches(WP_REST_Request $req) {
    $league = sanitize_text_field($req->get_param('league') ?? 'all');
    $live = $req->get_param('live');
    $date = sanitize_text_field($req->get_param('date') ?? '');
    $sort = sanitize_text_field($req->get_param('sort') ?? 'time_asc');

    $data = wrse_get_cached_matches();

    $out = [];
    foreach ($data as $m) {
        foreach (['odds_1x2', 'odds_ou', 'odds_ah', 'stats_json', 'lineups_json', 'h2h_json'] as $field) {
            if (isset($m[$field]) && is_string($m[$field])) {
                $decoded = json_decode($m[$field], true);
                if ($decoded !== null) {
                    $m[$field] = $decoded;
                }
            }
        }
        $mLeague = $m['league'] ?? '';
        $mStatus = $m['status'] ?? '';
        if ($league !== 'all' && $mLeague !== $league) continue;
        if ($live !== null) {
            $wantLive = filter_var($live, FILTER_VALIDATE_BOOLEAN);
            $isLive = (strtolower($mStatus) === 'live');
            if ($wantLive !== $isLive) continue;
        }
        if ($date) {
            $mt = isset($m['match_time_utc']) ? substr($m['match_time_utc'], 0, 10) : '';
            if ($mt !== $date) continue;
        }
        $out[] = $m;
    }

    usort($out, function ($a, $b) use ($sort) {
        $ta = strtotime($a['match_time_utc'] ?? '');
        $tb = strtotime($b['match_time_utc'] ?? '');
        if ($sort === 'time_desc') return $tb <=> $ta;
        return $ta <=> $tb;
    });

    return new WP_REST_Response(['data' => $out], 200);
}

function wrse_api_match(WP_REST_Request $req) {
    $id = intval($req['id']);
    $tables = wrse_get_tables();
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['matches']} WHERE id=%d", $id), ARRAY_A);
    if (!$row) return new WP_REST_Response(['error' => 'Not found'], 404);

    $row['odds_1x2'] = json_decode($row['odds_1x2'] ?? '[]', true);
    $row['odds_ou'] = json_decode($row['odds_ou'] ?? '[]', true);
    $row['odds_ah'] = json_decode($row['odds_ah'] ?? '[]', true);
    $row['stats'] = json_decode($row['stats_json'] ?? '[]', true);
    $row['lineups'] = json_decode($row['lineups_json'] ?? '[]', true);
    $row['h2h'] = json_decode($row['h2h_json'] ?? '[]', true);

    return new WP_REST_Response(['data' => $row], 200);
}

function wrse_api_odds(WP_REST_Request $req) {
    $match_id = intval($req['match_id']);
    $tables = wrse_get_tables();
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['odds']} WHERE match_id=%d ORDER BY created_at ASC", $match_id), ARRAY_A);
    return new WP_REST_Response(['data' => $rows], 200);
}

function wrse_api_stats(WP_REST_Request $req) {
    $match_id = intval($req['match_id']);
    $tables = wrse_get_tables();
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT stats_json FROM {$tables['matches']} WHERE id=%d", $match_id), ARRAY_A);
    $stats = [];
    if (!empty($row['stats_json'])) {
        $stats = json_decode($row['stats_json'], true);
    }
    return new WP_REST_Response(['data' => $stats], 200);
}

function wrse_api_leagues() {
    $data = wrse_get_cached_matches();
    $set = [];
    foreach ($data as $m) {
        $l = $m['league'] ?? '';
        if ($l) $set[$l] = true;
    }
    return new WP_REST_Response(['data' => array_keys($set)], 200);
}

function wrse_api_logs() {
    $tables = wrse_get_tables();
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$tables['logs']} ORDER BY created_at DESC LIMIT 100", ARRAY_A);
    return new WP_REST_Response(['data' => $rows], 200);
}
