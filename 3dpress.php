<?php
/*
Plugin Name: 3DPress
Description: Accept 3D printing orders with file upload, STL viewer, material selection, and WooCommerce integration.
Version: 1.0.0
Author: Mohammad R. Tayyebi <m@tyyi.net>, GitHub Copilot
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('THREEDPRESS_PATH', plugin_dir_path(__FILE__));
define('THREEDPRESS_URL', plugin_dir_url(__FILE__));

// Use autoloader for OOP structure
require_once THREEDPRESS_PATH . 'autoload.php';

use THREEDPRESS\ThreeDPress;
use THREEDPRESS\Shortcode_3DPress_Form;
use THREEDPRESS\Elementor_3DPress_Widget;

// Activation hook
register_activation_hook(__FILE__, [ThreeDPress::class, 'activate']);
// Deactivation hook
register_deactivation_hook(__FILE__, [ThreeDPress::class, 'deactivate']);
// Init plugin
add_action('plugins_loaded', [ThreeDPress::class, 'get_instance']);
// Register shortcode
Shortcode_3DPress_Form::register();
// Elementor widget registration
add_action('elementor/widgets/register', function($widgets_manager) {
    $widgets_manager->register(new Elementor_3DPress_Widget());
});
