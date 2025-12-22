<?php
/**
 * 小幫手列表表格類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 載入 WP_List_Table
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BuyGo_RP_Helper_List_Table extends WP_List_Table {
    
    /**
     * 建構函數
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'helper',
            'plural'   => 'helpers',
            'ajax'     => false,
        ) );
    }
    
    /**
     * 取得欄位
     */
    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'seller'       => '賣家',
            'helper'       => '小幫手',
            'permissions'  => '權限',
            'assigned_at'  => '指派時間',
            'assigned_by'  => '指派者',
            'actions'      => '操作',
        );
    }
    
    /**
     * 取得可排序的欄位
     */
    public function get_sortable_columns() {
        return array(
            'assigned_at' => array( 'assigned_at', true ),
            'seller'      => array( 'seller_id', false ),
        );
    }
    
    /**
     * 準備項目
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // 建立查詢
        $table = $wpdb->prefix . 'buygo_helpers';
        $where = '1=1';
        
        // 排序
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'assigned_at';
        $order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
        
        // 取得項目
        $offset = ( $current_page - 1 ) * $per_page;
        $items = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT $per_page OFFSET $offset"
        );
        
        // 取得總數
        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );
        
        // 設定項目
        $this->items = $items;
        
        // 設定分頁
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
        
        // 設定欄位標題
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }
    
    /**
     * 核取方塊欄位
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="helper[]" value="%s" />',
            $item->id
        );
    }
    
    /**
     * 賣家欄位
     */
    public function column_seller( $item ) {
        $user = get_userdata( $item->seller_id );
        return $user ? esc_html( $user->display_name ) . '<br><small>' . esc_html( $user->user_email ) . '</small>' : '未知使用者';
    }
    
    /**
     * 小幫手欄位
     */
    public function column_helper( $item ) {
        $user = get_userdata( $item->helper_id );
        return $user ? esc_html( $user->display_name ) . '<br><small>' . esc_html( $user->user_email ) . '</small>' : '未知使用者';
    }
    
    /**
     * 權限欄位
     */
    public function column_permissions( $item ) {
        $perms = array();
        if ( $item->can_view_orders ) $perms[] = '查看訂單';
        if ( $item->can_update_orders ) $perms[] = '更新訂單';
        if ( $item->can_manage_products ) $perms[] = '管理商品';
        if ( $item->can_reply_customers ) $perms[] = '回覆客戶';
        
        return empty( $perms ) ? '無權限' : implode( ', ', $perms );
    }
    
    /**
     * 指派時間欄位
     */
    public function column_assigned_at( $item ) {
        return date( 'Y-m-d H:i', strtotime( $item->assigned_at ) );
    }

    /**
     * 指派者欄位
     */
    public function column_assigned_by( $item ) {
        $user = get_userdata( $item->assigned_by );
        return $user ? esc_html( $user->display_name ) : '未知';
    }
    
    /**
     * 操作欄位
     */
    public function column_actions( $item ) {
        $delete_url = wp_nonce_url(
            add_query_arg( array(
                'page' => 'buygo-helper-management',
                'action' => 'delete',
                'id' => $item->id,
                'seller_id' => $item->seller_id,
                'helper_id' => $item->helper_id
            ) ),
            'buygo_delete_helper_' . $item->id
        );
        
        return sprintf(
            '<a href="%s" class="buygo-btn-danger" onclick="return confirm(\'確定要移除此小幫手嗎？\')">移除</a>',
            esc_url( $delete_url )
        );
    }
}
