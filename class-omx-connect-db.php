<?php
if (!defined('ABSPATH')) exit;
class OMX_Connect_DB {
    const DB_VERSION = '0.2.0';
    public static function tables() {
        global $wpdb;
        return [
            'conversations' => $wpdb->prefix . 'omx_conversations',
            'messages' => $wpdb->prefix . 'omx_messages',
            'canned' => $wpdb->prefix . 'omx_canned_replies',
        ];
    }
    public static function activate() {
        self::install();
        if (!get_option('omx_connect_settings')) {
            add_option('omx_connect_settings', [
                'widget_title' => 'Chat with us',
                'welcome_message' => 'Hi! How can we help you today?',
                'accent_color' => '#6C5CE7',
                'departments' => 'General Support,DPL,CPL,ICL,Materials,IT,Billing',
            ]);
        }
    }
    public static function maybe_upgrade() {
        if (get_option('omx_connect_db_version') !== self::DB_VERSION) self::install();
    }
    private static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables(); $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$t['conversations']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id VARCHAR(64) NOT NULL,
            customer_name VARCHAR(190) DEFAULT '', customer_email VARCHAR(190) DEFAULT '', customer_phone VARCHAR(50) DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'open', priority VARCHAR(30) NOT NULL DEFAULT 'normal', department VARCHAR(100) DEFAULT 'General Support',
            tags TEXT NULL, assigned_user BIGINT UNSIGNED DEFAULT NULL, unread_agent INT UNSIGNED NOT NULL DEFAULT 0, unread_customer INT UNSIGNED NOT NULL DEFAULT 0,
            last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY public_id (public_id), KEY status (status), KEY assigned_user (assigned_user), KEY department (department)
        ) $charset;");
        dbDelta("CREATE TABLE {$t['messages']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, conversation_id BIGINT UNSIGNED NOT NULL,
            sender_type VARCHAR(20) NOT NULL, sender_user BIGINT UNSIGNED DEFAULT NULL, message_type VARCHAR(20) NOT NULL DEFAULT 'message',
            body LONGTEXT NOT NULL, attachment_url TEXT NULL, attachment_name VARCHAR(255) DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY conversation_id (conversation_id), KEY sender_type (sender_type)
        ) $charset;");
        dbDelta("CREATE TABLE {$t['canned']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, shortcut VARCHAR(80) DEFAULT '', body LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY shortcut (shortcut)
        ) $charset;");
        update_option('omx_connect_db_version', self::DB_VERSION);
        $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['canned']}");
        if(!$count){
            $items=[
                ['Welcome','/welcome','Hi! Thank you for contacting us. How can I help you today?'],
                ['Checking','/checking','I am checking this for you. I will update you here shortly.'],
                ['Need barcode','/barcode','Please share the barcode so I can check this for you.'],
                ['Resolved','/resolved','Your query has been resolved. Please let us know if you need any further help.']
            ];
            foreach($items as $i) $wpdb->insert($t['canned'],['title'=>$i[0],'shortcut'=>$i[1],'body'=>$i[2]]);
        }
    }
}
