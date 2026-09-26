<?php
if (!defined('ABSPATH')) exit;

class OMX_Connect_Widget {
    public function __construct() {
        add_action('wp_enqueue_scripts',[$this,'assets']);
        add_action('wp_footer',[$this,'render']);
    }
    public function assets() {
        if (is_admin() || !wp_parse_args(get_option('omx_connect_settings',[]),['widget_enabled'=>1])['widget_enabled']) return;
        wp_enqueue_style('omx-connect-widget',OMX_CONNECT_URL.'assets/css/widget.css',[],OMX_CONNECT_VERSION);
        wp_enqueue_script('omx-connect-widget',OMX_CONNECT_URL.'assets/js/widget.js',[],OMX_CONNECT_VERSION,true);
        $settings=wp_parse_args(get_option('omx_connect_settings',[]),['widget_title'=>'Chat with us','welcome_message'=>'Hi! How can we help you today?','accent_color'=>'#6C5CE7']);
        wp_localize_script('omx-connect-widget','OMXConnect',[
            'rest'=>esc_url_raw(rest_url('omx-connect/v1')),
            'title'=>$settings['widget_title'],
            'welcome'=>$settings['welcome_message'],
            'accent'=>$settings['accent_color']
        ]);
    }
    public function render() {
        if (is_admin() || !wp_parse_args(get_option('omx_connect_settings',[]),['widget_enabled'=>1])['widget_enabled']) return;
        ?><div id="omx-chat-root"></div><?php
    }
}
