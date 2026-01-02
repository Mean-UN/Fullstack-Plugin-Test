<?php
if (!defined('ABSPATH')) exit;

function wrse_enqueue_frontend_assets() {
    $opts = get_option('wrse_settings', []);
    $refreshLive = isset($opts['refresh_live']) ? intval($opts['refresh_live']) : 6;
    $refreshUpcoming = isset($opts['refresh_upcoming']) ? intval($opts['refresh_upcoming']) : 600;

    wp_enqueue_style('wrse-frontend', WRSE_URL . 'assets/css/wrse-frontend.css', [], '1.0.0');
    wp_enqueue_script('wrse-frontend', WRSE_URL . 'assets/js/wrse-frontend.js', ['jquery'], '1.0.0', true);
    wp_localize_script('wrse-frontend', 'WRSE_VARS', [
        'endpoint' => esc_url_raw(rest_url('wrse/v1')),
        'refresh' => [
            'scoreboard' => 30,
            'live' => max(5, $refreshLive),
            'upcoming' => max(60, $refreshUpcoming),
        ],
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
}

add_shortcode('wrse_scoreboard', function ($atts) {
    $atts = shortcode_atts([
        'league' => 'all',
        'date' => 'today',
        'view' => 'compact'
    ], $atts);

    wrse_enqueue_frontend_assets();

    ob_start(); ?>
    <div class="wrse-scoreboard" data-league="<?php echo esc_attr($atts['league']); ?>" data-date="<?php echo esc_attr($atts['date']); ?>" data-view="<?php echo esc_attr($atts['view']); ?>">
        <div class="wrse-toolbar">
            <input type="text" class="wrse-search" placeholder="Search team..." />
            <select class="wrse-sort">
                <option value="time_asc">Time ASC</option>
                <option value="time_desc">Time DESC</option>
            </select>
            <label class="wrse-toggle"><input type="checkbox" class="wrse-live-first" /> Live first</label>
        </div>
        <div class="wrse-list skeleton"></div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('wrse_match_center', function ($atts) {
    $atts = shortcode_atts(['match_id' => '0'], $atts);
    wrse_enqueue_frontend_assets();

    ob_start(); ?>
    <div class="wrse-match-center" data-match-id="<?php echo esc_attr($atts['match_id']); ?>">
        <div class="wrse-mc-header skeleton">Loading match...</div>
        <div class="wrse-mc-grid">
            <div class="wrse-mc-card wrse-mc-summary skeleton"></div>
            <div class="wrse-mc-card wrse-mc-odds skeleton"></div>
            <div class="wrse-mc-card wrse-mc-lineups skeleton"></div>
            <div class="wrse-mc-card wrse-mc-stats skeleton"></div>
            <div class="wrse-mc-card wrse-mc-h2h skeleton"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('wrse_upcoming', function ($atts) {
    $atts = shortcode_atts(['limit' => 5], $atts);
    wrse_enqueue_frontend_assets();
    ob_start(); ?>
    <div class="wrse-upcoming" data-limit="<?php echo esc_attr($atts['limit']); ?>">
        <div class="wrse-upcoming-list skeleton"></div>
    </div>
    <?php
    return ob_get_clean();
});
