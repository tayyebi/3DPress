<?php
namespace THREEDPRESS;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class Elementor_3DPress_Widget extends Widget_Base {
    public function get_name() {
        return '3dpress_form';
    }
    public function get_title() {
        return __('3DPress Order Form', '3dpress');
    }
    public function get_icon() {
        return 'eicon-upload-box';
    }
    public function get_categories() {
        return ['general'];
    }
    protected function _register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Content', '3dpress'),
        ]);
        $this->end_controls_section();
    }
    protected function render() {
        echo do_shortcode('[threedpress_form]');
    }
}
