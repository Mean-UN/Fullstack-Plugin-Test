<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'WRSE Settings',
        'WRSE',
        'manage_options',
        'wrse-settings',
        'wrse_render_settings_page',
        'dashicons-chart-line'
    );
});

function wrse_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $opts = get_option('wrse_settings', [
        'source_a_url' => '',
        'source_b_url' => '',
        'refresh_live' => 6,
        'refresh_upcoming' => 600,
        'league_whitelist' => '',
    ]);

    if (isset($_POST['wrse_settings_submit']) && check_admin_referer('wrse_settings_nonce')) {
        $opts['source_a_url'] = esc_url_raw($_POST['source_a_url'] ?? '');
        $opts['source_b_url'] = esc_url_raw($_POST['source_b_url'] ?? '');
        $opts['refresh_live'] = max(15, intval($_POST['refresh_live'] ?? 60));
        $opts['refresh_upcoming'] = max(60, intval($_POST['refresh_upcoming'] ?? 600));
        $opts['league_whitelist'] = sanitize_text_field($_POST['league_whitelist'] ?? '');
        update_option('wrse_settings', $opts);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    if (isset($_POST['wrse_run_crawler']) && check_admin_referer('wrse_run_crawler_nonce')) {
        wrse_run_crawler();
        echo '<div class="updated"><p>Crawler executed.</p></div>';
    }

    $tables = wrse_get_tables();
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$tables['logs']} ORDER BY created_at DESC LIMIT 50", ARRAY_A);

    ?>
    <div class="wrap">
        <h1>WP Realtime Sports Engine</h1>

        <h2>General Settings</h2>
        <form method="post">
            <?php wp_nonce_field('wrse_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="source_a_url">Source A URL (JSON)</label></th>
                    <td><input type="url" name="source_a_url" id="source_a_url" class="regular-text" value="<?php echo esc_attr($opts['source_a_url']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="source_b_url">Source B URL (HTML)</label></th>
                    <td><input type="url" name="source_b_url" id="source_b_url" class="regular-text" value="<?php echo esc_attr($opts['source_b_url']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="refresh_live">Live refresh (seconds)</label></th>
                    <td><input type="number" name="refresh_live" id="refresh_live" value="<?php echo esc_attr($opts['refresh_live']); ?>" min="15" max="300"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="refresh_upcoming">Upcoming refresh (seconds)</label></th>
                    <td><input type="number" name="refresh_upcoming" id="refresh_upcoming" value="<?php echo esc_attr($opts['refresh_upcoming']); ?>" min="60" max="1800"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="league_whitelist">League whitelist (comma separated)</label></th>
                    <td><input type="text" name="league_whitelist" id="league_whitelist" class="regular-text" value="<?php echo esc_attr($opts['league_whitelist']); ?>"></td>
                </tr>
            </table>
            <p><button type="submit" name="wrse_settings_submit" class="button button-primary">Save Settings</button></p>
        </form>

        <h2>Tools</h2>
        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field('wrse_run_crawler_nonce'); ?>
            <button class="button button-secondary" name="wrse_run_crawler" value="1">Run Crawler Now</button>
        </form>

        <h2>Logs (latest 50)</h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Level</th>
                    <th>Context</th>
                    <th>Message</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo esc_html($r['id']); ?></td>
                    <td><?php echo esc_html($r['level']); ?></td>
                    <td><?php echo esc_html($r['context']); ?></td>
                    <td><?php echo esc_html($r['message']); ?></td>
                    <td><?php echo esc_html($r['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
