<?php

use BuyGo\Core\Utils\Migration;

class CreateNotificationLogsTable extends Migration {

    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_notification_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            type varchar(50) NOT NULL,
            channel varchar(20) NOT NULL,
            title varchar(255) DEFAULT '',
            message text,
            status varchar(20) DEFAULT 'sent',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            meta text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY type (type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_notification_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
