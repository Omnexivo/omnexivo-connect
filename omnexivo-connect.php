<?php
/**
 * Plugin Name: Omnexivo Connect
 * Description: Freshchat-style omnichannel customer communication inbox for WordPress.
 * Version: 0.2.2
 * Author: Omnexivo
 * Text Domain: omnexivo-connect
 */
if (!defined('ABSPATH')) exit;
define('OMX_CONNECT_VERSION', '0.2.2');
define('OMX_CONNECT_DIR', plugin_dir_path(__FILE__));
define('OMX_CONNECT_URL', plugin_dir_url(__FILE__));
require_once OMX_CONNECT_DIR . 'includes/class-omx-connect-db.php';
require_once OMX_CONNECT_DIR . 'includes/class-omx-connect-rest.php';
require_once OMX_CONNECT_DIR . 'includes/class-omx-connect-admin.php';
require_once OMX_CONNECT_DIR . 'includes/class-omx-connect-widget.php';
register_activation_hook(__FILE__, ['OMX_Connect_DB', 'activate']);
add_action('plugins_loaded', function () {
    OMX_Connect_DB::maybe_upgrade();
    new OMX_Connect_REST();
    new OMX_Connect_Admin();
    new OMX_Connect_Widget();
});
