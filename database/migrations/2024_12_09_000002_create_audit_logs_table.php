<?php

class CreateAuditLogsTable {
    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_audit_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            target_type varchar(50) DEFAULT '',
            target_id varchar(50) DEFAULT '',
            detail longtext,
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY actor_id (actor_id),
            KEY action (action)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
