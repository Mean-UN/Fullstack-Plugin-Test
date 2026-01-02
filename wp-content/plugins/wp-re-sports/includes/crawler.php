<?php
if (!defined('ABSPATH')) exit;

/**
 * Team alias normalization (minimum needed; expand via filter)
 */
function wrse_normalize_team($name) {
    $n = trim(mb_strtolower($name));
    $map = [
        'man utd' => 'Manchester United',
        'manchester utd' => 'Manchester United',
        'chelsea' => 'Chelsea',
        'chelsea fc' => 'Chelsea',
    ];
    $map = apply_filters('wrse_team_alias_map', $map);
    return $map[$n] ?? ucwords($n);
}

function wrse_build_match($args) {
    return [
        'external_ids' => $args['external_ids'] ?? [],
        'league' => sanitize_text_field($args['league'] ?? ''),
        'country' => sanitize_text_field($args['country'] ?? ''),
        'match_time_utc' => sanitize_text_field($args['match_time_utc'] ?? ''),
        'match_time_local' => sanitize_text_field($args['match_time_local'] ?? ''),
        'home_team' => wrse_normalize_team($args['home_team'] ?? ''),
        'away_team' => wrse_normalize_team($args['away_team'] ?? ''),
        'status' => sanitize_text_field($args['status'] ?? 'Scheduled'),
        'minute' => intval($args['minute'] ?? 0),
        'score_home' => intval($args['score_home'] ?? 0),
        'score_away' => intval($args['score_away'] ?? 0),
        'odds_1x2' => $args['odds_1x2'] ?? [],
        'odds_ou' => $args['odds_ou'] ?? [],
        'odds_ah' => $args['odds_ah'] ?? [],
        'stats' => $args['stats'] ?? [],
        'lineups' => $args['lineups'] ?? [],
        'h2h' => $args['h2h'] ?? [],
        'source' => sanitize_text_field($args['source'] ?? ''),
        'updated_at' => current_time('mysql'),
    ];
}

function wrse_fetch_json_source($url, $cacheFile) {
    $body = '';
    $resp = wp_remote_get($url, ['timeout' => 6]);
    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
        $body = wp_remote_retrieve_body($resp);
    } elseif (file_exists($cacheFile)) {
        $body = file_get_contents($cacheFile);
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/**
 * Source A: JSON (remote or cached)
 */
function wrse_fetch_source_a_json() {
    $opts = get_option('wrse_settings', []);
    $url = !empty($opts['source_a_url']) ? esc_url_raw($opts['source_a_url']) : '';
    $file = WRSE_PATH . 'cache/source-a.json';

    $rows = $url ? wrse_fetch_json_source($url, $file) : (file_exists($file) ? json_decode(file_get_contents($file), true) : []);
    if (!is_array($rows)) $rows = [];

    $out = [];
    foreach ($rows as $m) {
        $out[] = wrse_build_match([
            'league' => $m['league'] ?? '',
            'country' => $m['country'] ?? '',
            'match_time_utc' => $m['match_time_utc'] ?? '',
            'match_time_local' => $m['match_time_local'] ?? '',
            'home_team' => $m['home'] ?? '',
            'away_team' => $m['away'] ?? '',
            'status' => $m['status'] ?? 'Scheduled',
            'minute' => $m['minute'] ?? 0,
            'score_home' => $m['score_home'] ?? 0,
            'score_away' => $m['score_away'] ?? 0,
            'odds_1x2' => $m['odds_1x2'] ?? [],
            'odds_ou' => $m['odds_ou'] ?? [],
            'odds_ah' => $m['odds_ah'] ?? [],
            'stats' => $m['stats'] ?? [],
            'lineups' => $m['lineups'] ?? [],
            'h2h' => $m['h2h'] ?? [],
            'source' => 'source_a_json',
            'external_ids' => ['a' => $m['id'] ?? ''],
        ]);
    }
    return $out;
}

/**
 * Source B: HTML via DOMDocument (remote or cached)
 */
function wrse_fetch_source_b_html() {
    $opts = get_option('wrse_settings', []);
    $url = !empty($opts['source_b_url']) ? esc_url_raw($opts['source_b_url']) : '';
    $file = WRSE_PATH . 'cache/source-b.html';

    $html = '';
    if ($url) {
        $resp = wp_remote_get($url, ['timeout' => 6]);
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $html = wp_remote_retrieve_body($resp);
        }
    }
    if (!$html && file_exists($file)) {
        $html = file_get_contents($file);
    }
    if (!$html) return [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[contains(@class,'match')]");
    if (!$nodes || $nodes->length === 0) {
        wrse_log('HTML layout changed: no .match nodes found', 'warning', 'crawler');
        return [];
    }

    $out = [];
    foreach ($nodes as $node) {
        $league = $node->getAttribute('data-league');
        $timeUtc = $node->getAttribute('data-time-utc');
        $country = $node->getAttribute('data-country');

        $home = '';
        $away = '';
        $status = 'Scheduled';
        $minute = 0;
        $score_home = 0;
        $score_away = 0;

        $homeNode = $xpath->query(".//*[contains(@class,'home')]", $node)->item(0);
        $awayNode = $xpath->query(".//*[contains(@class,'away')]", $node)->item(0);
        $statusNode = $xpath->query(".//*[contains(@class,'status')]", $node)->item(0);
        $minuteNode = $xpath->query(".//*[contains(@class,'minute')]", $node)->item(0);
        $scoreNode = $xpath->query(".//*[contains(@class,'score')]", $node)->item(0);

        if ($homeNode) $home = $homeNode->textContent;
        if ($awayNode) $away = $awayNode->textContent;
        if ($statusNode) $status = $statusNode->textContent;
        if ($minuteNode) $minute = intval($minuteNode->textContent);

        if ($scoreNode) {
            $parts = explode('-', trim($scoreNode->textContent));
            if (count($parts) === 2) {
                $score_home = intval(trim($parts[0]));
                $score_away = intval(trim($parts[1]));
            }
        }

        $odds1x2 = ['home' => null, 'draw' => null, 'away' => null];
        $ou = ['line' => null, 'over' => null, 'under' => null];
        $ah = ['line' => null, 'home' => null, 'away' => null];

        $o1 = $xpath->query(".//*[contains(@class,'odds1x2')]", $node)->item(0);
        if ($o1) {
            $odds1x2 = [
                'home' => floatval($o1->getAttribute('data-home')),
                'draw' => floatval($o1->getAttribute('data-draw')),
                'away' => floatval($o1->getAttribute('data-away')),
            ];
        }
        $o2 = $xpath->query(".//*[contains(@class,'oddsou')]", $node)->item(0);
        if ($o2) {
            $ou = [
                'line' => floatval($o2->getAttribute('data-line')),
                'over' => floatval($o2->getAttribute('data-over')),
                'under' => floatval($o2->getAttribute('data-under')),
            ];
        }
        $o3 = $xpath->query(".//*[contains(@class,'oddsah')]", $node)->item(0);
        if ($o3) {
            $ah = [
                'line' => floatval($o3->getAttribute('data-line')),
                'home' => floatval($o3->getAttribute('data-home')),
                'away' => floatval($o3->getAttribute('data-away')),
            ];
        }

        $out[] = wrse_build_match([
            'league' => $league,
            'country' => $country,
            'match_time_utc' => $timeUtc,
            'home_team' => $home,
            'away_team' => $away,
            'status' => $status,
            'minute' => $minute,
            'score_home' => $score_home,
            'score_away' => $score_away,
            'odds_1x2' => $odds1x2,
            'odds_ou' => $ou,
            'odds_ah' => $ah,
            'source' => 'source_b_html',
            'external_ids' => ['b' => $node->getAttribute('data-id')],
        ]);
    }

    return $out;
}

function wrse_fingerprint($m) {
    return md5(($m['league'] ?? '') . '|' . ($m['match_time_utc'] ?? '') . '|' . ($m['home_team'] ?? '') . '|' . ($m['away_team'] ?? ''));
}

function wrse_merge_sources($a, $b) {
    $map = [];
    foreach (array_merge($a, $b) as $m) {
        $fp = wrse_fingerprint($m);
        if (!isset($map[$fp])) {
            $map[$fp] = $m;
        } else {
            $old = $map[$fp];
            if (($m['minute'] ?? 0) > ($old['minute'] ?? 0)) $old['minute'] = $m['minute'];
            if (($m['score_home'] ?? 0) || ($m['score_away'] ?? 0)) {
                $old['score_home'] = $m['score_home'];
                $old['score_away'] = $m['score_away'];
            }
            $old['odds_1x2'] = !empty($m['odds_1x2']) ? $m['odds_1x2'] : $old['odds_1x2'];
            $old['odds_ou'] = !empty($m['odds_ou']) ? $m['odds_ou'] : $old['odds_ou'];
            $old['odds_ah'] = !empty($m['odds_ah']) ? $m['odds_ah'] : $old['odds_ah'];
            $old['source'] = $old['source'] . ',' . ($m['source'] ?? '');
            $old['updated_at'] = current_time('mysql');
            $map[$fp] = $old;
        }
    }
    return array_values($map);
}

function wrse_store_data($matches) {
    // 1) transient
    set_transient('wrse_matches_today', $matches, 60);

    // 2) DB upsert
    foreach ($matches as $m) {
        wrse_upsert_match($m);
    }

    // 3) JSON fallback
    $file = WRSE_PATH . 'cache/matches_today.json';
    file_put_contents($file, wp_json_encode($matches));
}

function wrse_store_upcoming($matches) {
    set_transient('wrse_matches_upcoming', $matches, 600);
    $file = WRSE_PATH . 'cache/upcoming.json';
    file_put_contents($file, wp_json_encode($matches));
}

function wrse_run_crawler() {
    $tries = 0;
    while ($tries < 3) {
        $tries++;

        $a = wrse_fetch_source_a_json();
        $b = wrse_fetch_source_b_html();

        $merged = wrse_merge_sources($a, $b);
        if (!empty($merged)) {
            wrse_store_data($merged);
            wrse_log('Crawler success', 'info', 'crawler', ['count' => count($merged)]);
            return $merged;
        }
        wrse_log('Crawler attempt failed', 'warning', 'crawler', ['try' => $tries]);
    }

    wrse_log('Crawler failed after 3 retries', 'error', 'crawler');
    return [];
}

function wrse_fetch_upcoming_stub() {
    // Placeholder upcoming loader reusing crawler data for next 48h
    $data = wrse_run_crawler();
    $future = [];
    $now = time();
    foreach ($data as $m) {
        $ts = strtotime($m['match_time_utc'] ?? '');
        if ($ts && $ts > $now && $ts < ($now + 172800)) {
            $future[] = $m;
        }
    }
    wrse_store_upcoming($future);
    return $future;
}

