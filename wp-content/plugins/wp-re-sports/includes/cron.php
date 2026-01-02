<?php
if (!defined('ABSPATH')) exit;

add_filter('cron_schedules', function ($schedules) {
    $schedules['wrse_every_minute'] = ['interval' => 60, 'display' => 'Every Minute'];
    $schedules['wrse_every_ten_minutes'] = ['interval' => 600, 'display' => 'Every 10 Minutes'];
    return $schedules;
});

add_action('init', function () {
    if (!wp_next_scheduled('wrse_cron_1min')) {
        wp_schedule_event(time(), 'wrse_every_minute', 'wrse_cron_1min');
    }
    if (!wp_next_scheduled('wrse_cron_10min')) {
        wp_schedule_event(time(), 'wrse_every_ten_minutes', 'wrse_cron_10min');
    }
    if (!wp_next_scheduled('wrse_cron_daily')) {
        wp_schedule_event(time(), 'daily', 'wrse_cron_daily');
    }
});

add_action('wrse_cron_1min', function () {
    // Update live matches
    wrse_run_crawler();

    // Odds tracking
    if (function_exists('wrse_track_odds_movement')) {
        wrse_track_odds_movement();
    }
});

add_action('wrse_cron_10min', function () {
    wrse_fetch_upcoming_stub();
});

add_action('wrse_cron_daily', function () {
    // Cleanup logs + old odds history
    global $wpdb;
    $logs = $wpdb->prefix . 'wrse_logs';
    $odds = $wpdb->prefix . 'wrse_odds_history';

    $wpdb->query("DELETE FROM $logs WHERE created_at < (NOW() - INTERVAL 7 DAY)");
    $wpdb->query("DELETE FROM $odds WHERE created_at < (NOW() - INTERVAL 30 DAY)");
});

