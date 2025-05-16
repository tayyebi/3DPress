<?php
namespace THREEDPRESS;

if (!defined('ABSPATH')) exit;

class Shortcode_3DPress_Form {
    public static function register() {
        add_shortcode('threedpress_form', [self::class, 'render']);
    }

    public static function render() {
        ob_start();
        $nonce = wp_create_nonce('threedpress_order_nonce');
        echo '<div id="threedpress-form" data-nonce="' . esc_attr($nonce) . '"></div>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if(window.ThreeDPressFormInit) window.ThreeDPressFormInit();
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
