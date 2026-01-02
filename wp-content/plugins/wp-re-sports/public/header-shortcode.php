<?php
if (!defined('ABSPATH')) exit;

add_shortcode('wrse_header', function () {
    ob_start(); ?>
    <header class="wrse-header">Header Test
    </header>
    <?php
    return ob_get_clean();
});
