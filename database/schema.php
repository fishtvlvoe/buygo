<?php
/**
 * 資料庫結構定義
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 建立資料表
 */
function buygo_rp_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    // 賣家申請資料表
    $table_applications = $wpdb->prefix . 'buygo_seller_applications';
    $sql_applications = "CREATE TABLE $table_applications (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        real_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        line_id VARCHAR(100) NOT NULL,
        reason TEXT,
        product_types TEXT,
        submitted_at DATETIME NOT NULL,
        reviewed_at DATETIME,
        reviewed_by BIGINT(20) UNSIGNED,
        review_note TEXT,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_status (status),
        KEY idx_submitted_at (submitted_at)
    ) $charset_collate;";
    
    dbDelta( $sql_applications );
    
    // 小幫手關係資料表
    $table_helpers = $wpdb->prefix . 'buygo_helpers';
    $sql_helpers = "CREATE TABLE $table_helpers (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        seller_id BIGINT(20) UNSIGNED NOT NULL,
        helper_id BIGINT(20) UNSIGNED NOT NULL,
        can_view_orders TINYINT(1) NOT NULL DEFAULT 0,
        can_update_orders TINYINT(1) NOT NULL DEFAULT 0,
        can_manage_products TINYINT(1) NOT NULL DEFAULT 0,
        can_reply_customers TINYINT(1) NOT NULL DEFAULT 0,
        assigned_at DATETIME NOT NULL,
        assigned_by BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY unique_seller_helper (seller_id, helper_id),
        KEY idx_seller_id (seller_id),
        KEY idx_helper_id (helper_id)
    ) $charset_collate;";
    
    dbDelta( $sql_helpers );
    
    // LINE 綁定碼資料表
    $table_bindings = $wpdb->prefix . 'buygo_line_bindings';
    $sql_bindings = "CREATE TABLE $table_bindings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        binding_code VARCHAR(6) NOT NULL,
        line_uid VARCHAR(100),
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        completed_at DATETIME,
        PRIMARY KEY (id),
        UNIQUE KEY unique_binding_code (binding_code),
        KEY idx_user_id (user_id),
        KEY idx_line_uid (line_uid),
        KEY idx_status (status)
    ) $charset_collate;";
    
    dbDelta( $sql_bindings );
}
