<?php
if (!defined('ABSPATH')) exit;

function wrse_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $matches = $wpdb->prefix . 'wrse_matches';
    $odds = $wpdb->prefix . 'wrse_odds_history';
    $logs = $wpdb->prefix . 'wrse_logs';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE $matches (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        external_ids TEXT,
        league VARCHAR(191),
        country VARCHAR(100),
        match_time_utc DATETIME,
        match_time_local DATETIME,
        home_team VARCHAR(191),
        away_team VARCHAR(191),
        status VARCHAR(50),
        minute INT,
        score_home INT DEFAULT 0,
        score_away INT DEFAULT 0,
        odds_1x2 TEXT,
        odds_ou TEXT,
        odds_ah TEXT,
        stats_json LONGTEXT,
        lineups_json LONGTEXT,
        h2h_json LONGTEXT,
        updated_at DATETIME,
        created_at DATETIME,
        KEY league_time (league, match_time_utc),
        KEY status_idx (status)
    ) $charset;");

    dbDelta("CREATE TABLE $odds (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        match_id BIGINT,
        market_type VARCHAR(20),
        odds_before LONGTEXT,
        odds_after LONGTEXT,
        created_at DATETIME,
        KEY match_market (match_id, market_type)
    ) $charset;");

    dbDelta("CREATE TABLE $logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        context VARCHAR(100),
        message TEXT,
        level VARCHAR(20),
        meta_json LONGTEXT,
        created_at DATETIME,
        KEY level_idx (level)
    ) $charset;");
}

function wrse_deactivate() {
    wp_clear_scheduled_hook('wrse_cron_1min');
    wp_clear_scheduled_hook('wrse_cron_10min');
    wp_clear_scheduled_hook('wrse_cron_daily');
}

function wrse_log($message, $level = 'info', $context = '', $meta = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'wrse_logs';
    $wpdb->insert($table, [
        'context' => sanitize_text_field($context),
        'message' => sanitize_text_field($message),
        'level' => sanitize_text_field($level),
        'meta_json' => wp_json_encode($meta),
        'created_at' => current_time('mysql')
    ]);
}

function wrse_get_tables() {
    global $wpdb;
    return [
        'matches' => $wpdb->prefix . 'wrse_matches',
        'odds'    => $wpdb->prefix . 'wrse_odds_history',
        'logs'    => $wpdb->prefix . 'wrse_logs',
    ];
}

function wrse_upsert_match($match) {
    global $wpdb;
    $tables = wrse_get_tables();
    $table = $tables['matches'];

    $fingerprint = md5(
        ($match['league'] ?? '') . '|' .
        ($match['match_time_utc'] ?? '') . '|' .
        ($match['home_team'] ?? '') . '|' .
        ($match['away_team'] ?? '')
    );

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE league=%s AND home_team=%s AND away_team=%s AND match_time_utc=%s LIMIT 1",
            $match['league'] ?? '',
            $match['home_team'] ?? '',
            $match['away_team'] ?? '',
            $match['match_time_utc'] ?? ''
        )
    );

    $row = [
        'external_ids' => isset($match['external_ids']) ? wp_json_encode($match['external_ids']) : '',
        'league' => $match['league'] ?? '',
        'country' => $match['country'] ?? '',
        'match_time_utc' => $match['match_time_utc'] ?? null,
        'match_time_local' => $match['match_time_local'] ?? null,
        'home_team' => $match['home_team'] ?? '',
        'away_team' => $match['away_team'] ?? '',
        'status' => $match['status'] ?? '',
        'minute' => intval($match['minute'] ?? 0),
        'score_home' => intval($match['score_home'] ?? 0),
        'score_away' => intval($match['score_away'] ?? 0),
        'odds_1x2' => wp_json_encode($match['odds_1x2'] ?? []),
        'odds_ou' => wp_json_encode($match['odds_ou'] ?? []),
        'odds_ah' => wp_json_encode($match['odds_ah'] ?? []),
        'stats_json' => wp_json_encode($match['stats'] ?? []),
        'lineups_json' => wp_json_encode($match['lineups'] ?? []),
        'h2h_json' => wp_json_encode($match['h2h'] ?? []),
        'updated_at' => current_time('mysql'),
    ];

    if ($existing) {
        $wpdb->update($table, $row, ['id' => $existing]);
        return $existing;
    }

    $row['created_at'] = current_time('mysql');
    $wpdb->insert($table, $row);
    return $wpdb->insert_id;
}

function wrse_get_matches_from_db($args = []) {
    global $wpdb;
    $table = wrse_get_tables()['matches'];

    $where = [];
    $params = [];

    if (!empty($args['league']) && $args['league'] !== 'all') {
        $where[] = 'league = %s';
        $params[] = $args['league'];
    }

    if (!empty($args['status'])) {
        $where[] = 'status = %s';
        $params[] = $args['status'];
    }

    if (!empty($args['date_from'])) {
        $where[] = 'match_time_utc >= %s';
        $params[] = $args['date_from'];
    }

    if (!empty($args['date_to'])) {
        $where[] = 'match_time_utc <= %s';
        $params[] = $args['date_to'];
    }

    $sql = "SELECT * FROM $table";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY match_time_utc ASC LIMIT 300';

    if (!empty($params)) {
        $prepared = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($prepared, ARRAY_A);
    }

    return $wpdb->get_results($sql, ARRAY_A);
}
