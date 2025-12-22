<?php

use BuyGo\Core\Utils\Migration;

class CreateNotificationReadsTable extends Migration {

    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_notification_reads';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            notification_id varchar(100) NOT NULL,
            notification_type varchar(50) NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            read_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_notification (user_id, notification_id),
            KEY user_id (user_id),
            KEY notification_id (notification_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_notification_reads';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
