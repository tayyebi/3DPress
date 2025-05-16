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
        add_action('wp_ajax_nopriv_threedpress_get_materials', [$this, 'ajax_get_materials']);
        add_action('wp_ajax_threedpress_get_materials', [$this, 'ajax_get_materials']);
        add_action('wp_ajax_nopriv_threedpress_get_estimate', [$this, 'ajax_get_estimate']);
        add_action('wp_ajax_threedpress_get_estimate', [$this, 'ajax_get_estimate']);
        add_action('wp_ajax_nopriv_threedpress_submit_order', [$this, 'ajax_submit_order']);
        add_action('wp_ajax_threedpress_submit_order', [$this, 'ajax_submit_order']);
        // ...more hooks for settings, etc.

        // Register the [threedpress_form] shortcode
        add_shortcode('threedpress_form', function($atts = []) {
            if (class_exists('THREEDPRESS\\Shortcode_3DPress_Form')) {
                $shortcode = new \THREEDPRESS\Shortcode_3DPress_Form();
                return $shortcode->render($atts);
            }
            return '';
        });
    }

    public static function register_order_cpt() {
        register_post_type('threedpress_order', [
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
        add_menu_page('3DPress Orders', '3DPress', 'manage_options', 'threedpress', [$this, 'admin_orders_page'], 'dashicons-format-gallery');
        add_submenu_page('threedpress', '3DPress Settings', 'Settings', 'manage_options', 'threedpress-settings', [$this, 'admin_settings_page']);
    }

    public function admin_orders_page() {
        echo '<div class="wrap"><h1>3DPress Orders</h1>';
        echo '<div class="notice notice-info"><ul>';
        echo '<li><strong>Tip:</strong> Click an order to view or edit its details and see the STL preview.</li>';
        echo '<li>STL previews require a supported browser and may take a moment to load for large files.</li>';
        echo '<li>Order status can be managed from the order edit screen.</li>';
        echo '<li>For WooCommerce integration, ensure the product ID is set in <a href="admin.php?page=threedpress-settings">Settings</a>.</li>';
        echo '<li><strong>Shortcode:</strong> Use <code>[threedpress_form]</code> to embed the order form anywhere.</li>';
        echo '<li><strong>Elementor:</strong> Drag the <b>3DPress Order Form</b> widget into your page for visual editing.</li>';
        echo '</ul></div>';
        echo '<p>Below are the submitted 3D printing orders. Click an order to view details and STL preview.</p>';
        // List orders (basic table)
        $orders = get_posts(['post_type' => 'threedpress_order', 'numberposts' => 20]);
        if ($orders) {
            echo '<table class="widefat"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>STL Preview</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                $file_url = get_post_meta($order->ID, '3dpress_file_url', true);
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_post_link($order->ID)) . '">' . esc_html($order->post_title) . '</a></td>';
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
        echo '<div class="notice notice-info"><ul>';
        echo '<li><strong>Materials:</strong> Enter a comma-separated list (e.g., PLA,ABS,Resin). Order matters for pricing and time.</li>';
        echo '<li><strong>Base Prices/Times:</strong> Enter values in the same order as materials, separated by commas.</li>';
        echo '<li><strong>WooCommerce:</strong> Enable integration and select the product to link orders for payment and shipping.</li>';
        echo '<li>Use the button below to generate a sample WooCommerce product for testing.</li>';
        echo '<li>Changes are saved when you click "Save Changes" below.</li>';
        echo '<li><strong>Shortcode:</strong> Use <code>[threedpress_form]</code> to embed the order form anywhere.</li>';
        echo '<li><strong>Elementor:</strong> Drag the <b>3DPress Order Form</b> widget into your page for visual editing.</li>';
        echo '</ul></div>';
        // Sample product button
        if (class_exists('WooCommerce')) {
            if (isset($_POST['threedpress_create_sample_product'])) {
                $product_id = wp_insert_post([
                    'post_title' => '3D Printing Order',
                    'post_type' => 'product',
                    'post_status' => 'publish',
                ]);
                if ($product_id) {
                    update_post_meta($product_id, '_price', '10');
                    update_post_meta($product_id, '_regular_price', '10');
                    echo '<div class="updated notice"><p>Sample WooCommerce product created. Product ID: ' . intval($product_id) . '</p></div>';
                }
            }
            echo '<form method="post" style="margin-bottom:1em;"><button type="submit" name="threedpress_create_sample_product" class="button">Create Sample WooCommerce Product</button></form>';
        }
        echo '<form method="post" action="options.php">';
        settings_fields('threedpress_settings_group');
        do_settings_sections('threedpress-settings');
        // Product selector
        if (class_exists('WooCommerce')) {
            $selected = get_option('threedpress_wc_product_id', '');
            $products = get_posts(['post_type'=>'product','numberposts'=>100,'post_status'=>'publish']);
            echo '<tr valign="top"><th scope="row">WooCommerce Product</th><td>';
            echo '<select name="threedpress_wc_product_id">';
            echo '<option value="">Select a product...</option>';
            foreach ($products as $p) {
                $sel = ($selected == $p->ID) ? 'selected' : '';
                echo '<option value="' . intval($p->ID) . '" ' . $sel . '>' . esc_html($p->post_title) . ' (ID: ' . intval($p->ID) . ')</option>';
            }
            echo '</select> <small>Product to use for 3D orders</small>';
            echo '</td></tr>';
        }
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function register_settings() {
        register_setting('threedpress_settings_group', 'threedpress_materials', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('threedpress_settings_group', 'threedpress_base_prices', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('threedpress_settings_group', 'threedpress_base_times', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('threedpress_settings_group', 'threedpress_woocommerce_enabled', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('threedpress_settings_group', 'threedpress_wc_product_id', ['sanitize_callback' => 'absint']);

        add_settings_section('threedpress_section_main', '3DPress Settings', null, 'threedpress-settings');

        add_settings_field('threedpress_materials', 'Materials (comma separated)', function() {
            $val = esc_attr(get_option('threedpress_materials', 'PLA,ABS,Resin'));
            echo '<input type="text" name="threedpress_materials" value="' . esc_attr($val) . '" class="regular-text" />';
        }, 'threedpress-settings', 'threedpress_section_main');

        add_settings_field('threedpress_base_prices', 'Base Prices (per material, comma separated)', function() {
            $val = esc_attr(get_option('threedpress_base_prices', '10,12,15'));
            echo '<input type="text" name="threedpress_base_prices" value="' . esc_attr($val) . '" class="regular-text" /> <small>Order matches material order above</small>';
        }, 'threedpress-settings', 'threedpress_section_main');

        add_settings_field('threedpress_base_times', 'Base Print Times (per material, comma separated, in hours)', function() {
            $val = esc_attr(get_option('threedpress_base_times', '2,2.5,3'));
            echo '<input type="text" name="threedpress_base_times" value="' . esc_attr($val) . '" class="regular-text" /> <small>Order matches material order above</small>';
        }, 'threedpress-settings', 'threedpress_section_main');

        add_settings_field('threedpress_woocommerce_enabled', 'Enable WooCommerce Integration', function() {
            $val = get_option('threedpress_woocommerce_enabled', '0');
            echo "<input type='checkbox' name='threedpress_woocommerce_enabled' value='1'" . checked($val, '1', false) . "/> Yes";
        }, 'threedpress-settings', 'threedpress_section_main');

        add_settings_field('threedpress_wc_product_id', 'WooCommerce Product ID', function() {
            $val = esc_attr(get_option('threedpress_wc_product_id', ''));
            echo '<input type="number" name="threedpress_wc_product_id" value="' . esc_attr($val) . '" class="small-text" /> <small>Product to use for 3D orders</small>';
        }, 'threedpress-settings', 'threedpress_section_main');
    }

    public function render_some_setting() {
        $value = get_option('some_setting', '');
        echo '<input type="text" name="some_setting" value="' . esc_attr($value) . '" />';
    }

    public function admin_assets() {
        wp_enqueue_style('threedpress-admin', THREEDPRESS_URL . 'assets/css/admin.css', [], '1.0.0');
        wp_enqueue_script('threedpress-admin', THREEDPRESS_URL . 'assets/js/admin.js', [], '1.0.0', true);
        wp_localize_script('threedpress-admin', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function public_assets() {
        wp_enqueue_style('threedpress-public', THREEDPRESS_URL . 'assets/css/public.css', [], '1.0.0');
        wp_enqueue_script('threedpress-public', THREEDPRESS_URL . 'assets/js/public.js', [], '1.0.0', true);
        wp_localize_script('threedpress-public', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    // AJAX handler for getting materials
    public function ajax_get_materials() {
        // Check nonce, permissions, etc.
        $materials = explode(',', get_option('threedpress_materials', 'PLA,ABS,Resin'));
        $materials = array_map('trim', $materials);
        wp_send_json(['materials' => $materials]);
    }

    // AJAX handler for getting estimate
    public function ajax_get_estimate() {
        // Check nonce, permissions, etc.
        // Nonce verification for AJAX
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'threedpress_order_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', '3dpress')]);
            exit;
        }
        $material = isset($_POST['material']) ? sanitize_text_field(wp_unslash($_POST['material'])) : '';
        $unit = isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : '';
        $scale = isset($_POST['scale']) ? floatval(wp_unslash($_POST['scale'])) : 1;
        $length = isset($_POST['length']) ? floatval(wp_unslash($_POST['length'])) : 0;
        $width = isset($_POST['width']) ? floatval(wp_unslash($_POST['width'])) : 0;
        $height = isset($_POST['height']) ? floatval(wp_unslash($_POST['height'])) : 0;
        $rotation = isset($_POST['rotation']) ? floatval(wp_unslash($_POST['rotation'])) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        $prices = explode(',', get_option('threedpress_base_prices', '10,12,15'));
        $times = explode(',', get_option('threedpress_base_times', '2,2.5,3'));
        $materials = explode(',', get_option('threedpress_materials', 'PLA,ABS,Resin'));
        $mat_index = array_search($material, array_map('trim', $materials));
        $price = isset($prices[$mat_index]) ? floatval($prices[$mat_index]) : 10;
        $time = isset($times[$mat_index]) ? floatval($times[$mat_index]) : 2;
        // Simple estimate: base price * scale * (lwh/1000)
        $volume = max($length * $width * $height, 1);
        $cost = round($price * $scale * ($volume/1000), 2);
        $est_time = round($time * $scale * ($volume/1000), 2);
        wp_send_json(['cost' => $cost, 'time' => $est_time]);
    }

    // AJAX handler for submitting order
    public function ajax_submit_order() {
        // Nonce verification for AJAX
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'threedpress_order_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', '3dpress')]);
            exit;
        }
        // Basic validation
        $file_name = isset($_FILES['model_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['model_file']['name'])) : '';
        if (empty($file_name)) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }
        $allowed = ['stl', 'obj'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            wp_send_json_error(['message' => 'Invalid file type.']);
        }
        // Upload file
        $upload = wp_handle_upload($_FILES['model_file'], ['test_form' => false]);
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }
        $file_url = $upload['url'];
        // Unslash and sanitize all POST fields
        $material = isset($_POST['material']) ? sanitize_text_field(wp_unslash($_POST['material'])) : '';
        $unit = isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : 'mm';
        $scale = isset($_POST['scale']) ? floatval(wp_unslash($_POST['scale'])) : 1;
        $length = isset($_POST['length']) ? floatval(wp_unslash($_POST['length'])) : 0;
        $width = isset($_POST['width']) ? floatval(wp_unslash($_POST['width'])) : 0;
        $height = isset($_POST['height']) ? floatval(wp_unslash($_POST['height'])) : 0;
        $rotation = isset($_POST['rotation']) ? floatval(wp_unslash($_POST['rotation'])) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        // Create order post
        $order_id = wp_insert_post([
            'post_type' => 'threedpress_order',
            'post_title' => '3D Order - ' . gmdate('Y-m-d H:i:s'),
            'post_status' => 'publish',
        ]);
        if ($order_id) {
            update_post_meta($order_id, 'threedpress_file_url', $file_url);
            update_post_meta($order_id, 'threedpress_material', $material);
            update_post_meta($order_id, 'threedpress_unit', $unit);
            update_post_meta($order_id, 'threedpress_scale', $scale);
            update_post_meta($order_id, 'threedpress_length', $length);
            update_post_meta($order_id, 'threedpress_width', $width);
            update_post_meta($order_id, 'threedpress_height', $height);
            update_post_meta($order_id, 'threedpress_rotation', $rotation);
            update_post_meta($order_id, 'threedpress_notes', $notes);
            // WooCommerce integration: add order to WooCommerce if enabled
            if (get_option('threedpress_woocommerce_enabled', '0') === '1' && class_exists('WooCommerce')) {
                $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
                $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
                $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
                $order = wc_create_order();
                $order->add_product(wc_get_product(get_option('threedpress_wc_product_id', 0)), 1, [
                    'subtotal' => floatval($_POST['cost'] ?? 0),
                    'total' => floatval($_POST['cost'] ?? 0),
                ]);
                $order->set_address([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                ], 'billing');
                $order->calculate_totals();
                update_post_meta($order_id, 'threedpress_wc_order_id', $order->get_id());
            }
            wp_send_json_success(['message' => 'Order submitted!']);
        } else {
            wp_send_json_error(['message' => 'Order could not be created.']);
        }
    }
}
