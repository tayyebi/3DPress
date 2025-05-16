<?php
namespace THREEDPRESS;

// Use OOP helpers
use THREEDPRESS\Helpers\Estimate;
use THREEDPRESS\Helpers\File;

class ThreeDPress {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        // Register CPT and flush rewrite rules
        self::register_order_cpt();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function __construct() {
        add_action('init', [__CLASS__, 'register_order_cpt']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_action('admin_init', [$this, 'register_settings']);

        // AJAX handlers
        add_action('wp_ajax_nopriv_3dpress_get_materials', [$this, 'ajax_get_materials']);
        add_action('wp_ajax_3dpress_get_materials', [$this, 'ajax_get_materials']);
        add_action('wp_ajax_nopriv_3dpress_get_estimate', [$this, 'ajax_get_estimate']);
        add_action('wp_ajax_3dpress_get_estimate', [$this, 'ajax_get_estimate']);
        add_action('wp_ajax_nopriv_3dpress_submit_order', [$this, 'ajax_submit_order']);
        add_action('wp_ajax_3dpress_submit_order', [$this, 'ajax_submit_order']);
        // ...more hooks for settings, etc.
    }

    public static function register_order_cpt() {
        register_post_type('3dpress_order', [
            'labels' => [
                'name' => '3D Orders',
                'singular_name' => '3D Order',
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-format-gallery',
        ]);
    }

    public function register_admin_menu() {
        add_menu_page('3DPress Orders', '3DPress', 'manage_options', '3dpress', [$this, 'admin_orders_page'], 'dashicons-format-gallery');
        add_submenu_page('3dpress', '3DPress Settings', 'Settings', 'manage_options', '3dpress-settings', [$this, 'admin_settings_page']);
    }

    public function admin_orders_page() {
        echo '<div class="wrap"><h1>3DPress Orders</h1>';
        echo '<p>Below are the submitted 3D printing orders. Click an order to view details and STL preview.</p>';
        // List orders (basic table)
        $orders = get_posts(['post_type' => '3dpress_order', 'numberposts' => 20]);
        if ($orders) {
            echo '<table class="widefat"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>STL Preview</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                $file_url = get_post_meta($order->ID, '3dpress_file_url', true);
                echo '<tr>';
                echo '<td><a href="' . get_edit_post_link($order->ID) . '">' . esc_html($order->post_title) . '</a></td>';
                echo '<td>' . esc_html($order->post_date) . '</td>';
                echo '<td>' . esc_html(get_post_status($order->ID)) . '</td>';
                echo '<td>';
                if ($file_url) {
                    echo '<div class="three-dpress-stl-viewer" data-stl-url="' . esc_url($file_url) . '" style="width:200px;height:150px;"></div>';
                } else {
                    echo 'No file';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No orders found.</p>';
        }
        echo '<script>if(window.ThreeDPressAdminInit) window.ThreeDPressAdminInit();</script>';
        echo '</div>';
    }

    public function admin_settings_page() {
        echo '<div class="wrap"><h1>3DPress Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('3dpress_settings_group');
        do_settings_sections('3dpress-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function register_settings() {
        register_setting('3dpress_settings_group', '3dpress_materials');
        register_setting('3dpress_settings_group', '3dpress_base_prices');
        register_setting('3dpress_settings_group', '3dpress_base_times');
        register_setting('3dpress_settings_group', '3dpress_woocommerce_enabled');
        register_setting('3dpress_settings_group', '3dpress_wc_product_id');

        add_settings_section('3dpress_section_main', '3DPress Settings', null, '3dpress-settings');

        add_settings_field('3dpress_materials', 'Materials (comma separated)', function() {
            $val = esc_attr(get_option('3dpress_materials', 'PLA,ABS,Resin'));
            echo "<input type='text' name='3dpress_materials' value='$val' class='regular-text' />";
        }, '3dpress-settings', '3dpress_section_main');

        add_settings_field('3dpress_base_prices', 'Base Prices (per material, comma separated)', function() {
            $val = esc_attr(get_option('3dpress_base_prices', '10,12,15'));
            echo "<input type='text' name='3dpress_base_prices' value='$val' class='regular-text' /> <small>Order matches material order above</small>";
        }, '3dpress-settings', '3dpress_section_main');

        add_settings_field('3dpress_base_times', 'Base Print Times (per material, comma separated, in hours)', function() {
            $val = esc_attr(get_option('3dpress_base_times', '2,2.5,3'));
            echo "<input type='text' name='3dpress_base_times' value='$val' class='regular-text' /> <small>Order matches material order above</small>";
        }, '3dpress-settings', '3dpress_section_main');

        add_settings_field('3dpress_woocommerce_enabled', 'Enable WooCommerce Integration', function() {
            $val = get_option('3dpress_woocommerce_enabled', '0');
            echo "<input type='checkbox' name='3dpress_woocommerce_enabled' value='1'" . checked($val, '1', false) . "/> Yes";
        }, '3dpress-settings', '3dpress_section_main');

        add_settings_field('3dpress_wc_product_id', 'WooCommerce Product ID', function() {
            $val = esc_attr(get_option('3dpress_wc_product_id', ''));
            echo "<input type='number' name='3dpress_wc_product_id' value='$val' class='small-text' /> <small>Product to use for 3D orders</small>";
        }, '3dpress-settings', '3dpress_section_main');
    }

    public function render_some_setting() {
        $value = get_option('some_setting', '');
        echo '<input type="text" name="some_setting" value="' . esc_attr($value) . '" />';
    }

    public function admin_assets() {
        wp_enqueue_style('3dpress-admin', THREEDPRESS_URL . 'assets/css/admin.css');
        wp_enqueue_script('3dpress-admin', THREEDPRESS_URL . 'assets/js/admin.js', [], false, true);
        // Enqueue three.js and STLLoader for STL viewing
        wp_enqueue_script('threejs', 'https://cdn.jsdelivr.net/npm/three@0.152.2/build/three.min.js', [], null, true);
        wp_enqueue_script('threejs-stlloader', 'https://cdn.jsdelivr.net/npm/three@0.152.2/examples/js/loaders/STLLoader.min.js', ['threejs'], null, true);
        // Make ajaxurl available for admin JS (if needed)
        wp_localize_script('3dpress-admin', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function public_assets() {
        wp_enqueue_style('3dpress-public', THREEDPRESS_URL . 'assets/css/public.css');
        wp_enqueue_script('3dpress-public', THREEDPRESS_URL . 'assets/js/public.js', [], false, true);
        // Enqueue three.js and STLLoader for STL viewing
        wp_enqueue_script('threejs', 'https://cdn.jsdelivr.net/npm/three@0.152.2/build/three.min.js', [], null, true);
        wp_enqueue_script('threejs-stlloader', 'https://cdn.jsdelivr.net/npm/three@0.152.2/examples/js/loaders/STLLoader.min.js', ['threejs'], null, true);
        // Make ajaxurl available for frontend JS
        wp_localize_script('3dpress-public', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    // AJAX handler for getting materials
    public function ajax_get_materials() {
        // Check nonce, permissions, etc.
        $materials = explode(',', get_option('3dpress_materials', 'PLA,ABS,Resin'));
        $materials = array_map('trim', $materials);
        wp_send_json(['materials' => $materials]);
    }

    // AJAX handler for getting estimate
    public function ajax_get_estimate() {
        // Check nonce, permissions, etc.
        $material = sanitize_text_field($_POST['material'] ?? '');
        $scale = floatval($_POST['scale'] ?? 1);
        $l = floatval($_POST['length'] ?? 0);
        $w = floatval($_POST['width'] ?? 0);
        $h = floatval($_POST['height'] ?? 0);
        $prices = explode(',', get_option('3dpress_base_prices', '10,12,15'));
        $times = explode(',', get_option('3dpress_base_times', '2,2.5,3'));
        $materials = explode(',', get_option('3dpress_materials', 'PLA,ABS,Resin'));
        $mat_index = array_search($material, array_map('trim', $materials));
        $price = isset($prices[$mat_index]) ? floatval($prices[$mat_index]) : 10;
        $time = isset($times[$mat_index]) ? floatval($times[$mat_index]) : 2;
        // Simple estimate: base price * scale * (lwh/1000)
        $volume = max($l * $w * $h, 1);
        $cost = round($price * $scale * ($volume/1000), 2);
        $est_time = round($time * $scale * ($volume/1000), 2);
        wp_send_json(['cost' => $cost, 'time' => $est_time]);
    }

    // AJAX handler for submitting order
    public function ajax_submit_order() {
        // Basic validation
        if (empty($_FILES['model_file']['name'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }
        $allowed = ['stl', 'obj'];
        $ext = strtolower(pathinfo($_FILES['model_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            wp_send_json_error(['message' => 'Invalid file type.']);
        }
        // Upload file
        $upload = wp_handle_upload($_FILES['model_file'], ['test_form' => false]);
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }
        $file_url = $upload['url'];
        // Create order post
        $order_id = wp_insert_post([
            'post_type' => '3dpress_order',
            'post_title' => '3D Order - ' . date('Y-m-d H:i:s'),
            'post_status' => 'publish',
        ]);
        if ($order_id) {
            update_post_meta($order_id, '3dpress_file_url', $file_url);
            update_post_meta($order_id, '3dpress_material', sanitize_text_field($_POST['material'] ?? ''));
            update_post_meta($order_id, '3dpress_unit', sanitize_text_field($_POST['unit'] ?? 'mm'));
            update_post_meta($order_id, '3dpress_scale', floatval($_POST['scale'] ?? 1));
            update_post_meta($order_id, '3dpress_length', floatval($_POST['length'] ?? 0));
            update_post_meta($order_id, '3dpress_width', floatval($_POST['width'] ?? 0));
            update_post_meta($order_id, '3dpress_height', floatval($_POST['height'] ?? 0));
            update_post_meta($order_id, '3dpress_rotation', floatval($_POST['rotation'] ?? 0));
            update_post_meta($order_id, '3dpress_notes', sanitize_textarea_field($_POST['notes'] ?? ''));
            // WooCommerce integration: add order to WooCommerce if enabled
            if (get_option('3dpress_woocommerce_enabled', '0') === '1' && class_exists('WooCommerce')) {
                $order = wc_create_order();
                $order->add_product(wc_get_product(get_option('3dpress_wc_product_id', 0)), 1, [
                    'subtotal' => floatval($_POST['cost'] ?? 0),
                    'total' => floatval($_POST['cost'] ?? 0),
                ]);
                $order->set_address([
                    'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                    'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                    'email' => sanitize_email($_POST['email'] ?? ''),
                ], 'billing');
                $order->calculate_totals();
                update_post_meta($order_id, '3dpress_wc_order_id', $order->get_id());
            }
            wp_send_json_success(['message' => 'Order submitted!']);
        } else {
            wp_send_json_error(['message' => 'Order could not be created.']);
        }
    }
}
