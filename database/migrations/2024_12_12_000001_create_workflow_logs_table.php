<?php

use BuyGo\Core\Utils\Migration;

class CreateWorkflowLogsTable extends Migration {

    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            workflow_id varchar(100) NOT NULL,
            workflow_type varchar(50) NOT NULL,
            step_name varchar(100) NOT NULL,
            step_order int(11) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            product_id bigint(20) DEFAULT NULL,
            feed_id bigint(20) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            line_user_id varchar(100) DEFAULT NULL,
            message text DEFAULT NULL,
            error_message text DEFAULT NULL,
            metadata text DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY workflow_id (workflow_id),
            KEY workflow_type (workflow_type),
            KEY status (status),
            KEY product_id (product_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function down() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
