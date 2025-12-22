<?php
/**
 * 賣家申請列表表格類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 載入 WP_List_Table
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BuyGo_RP_Seller_Application_Table extends WP_List_Table {
    
    /**
     * 建構函數
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'application',
            'plural'   => 'applications',
            'ajax'     => false,
        ) );
    }
    
    /**
     * 取得欄位
     */
    public function get_columns() {
        return array(
            'id'           => '申請編號',
            'user'         => '申請人',
            'real_name'    => '真實姓名',
            'phone'        => '聯絡電話',
            'line_id'      => 'LINE ID',
            'submitted_at' => '申請時間',
            'status'       => '狀態',
            'actions'      => '操作',
        );
    }
    
    /**
     * 取得可排序的欄位
     */
    public function get_sortable_columns() {
        return array(
            'id'           => array( 'id', true ),
            'submitted_at' => array( 'submitted_at', true ),
        );
    }
    
    /**
     * 準備項目
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // 取得篩選參數
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        
        // 建立查詢
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $where = '1=1';
        
        if ( ! empty( $status_filter ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status_filter );
        }
        
        // 排序
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
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
     * 預設欄位顯示
     */
    public function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
    }
    
    /**
     * 申請編號欄位
     */
    public function column_id( $item ) {
        return sprintf( '#%d', $item->id );
    }
    
    /**
     * 申請人欄位
     */
    public function column_user( $item ) {
        $user = get_userdata( $item->user_id );
        if ( ! $user ) {
            return '使用者不存在';
        }
        
        return sprintf(
            '<strong>%s</strong><br><small>%s</small>',
            esc_html( $user->display_name ),
            esc_html( $user->user_email )
        );
    }
    
    /**
     * 狀態欄位
     */
    public function column_status( $item ) {
        $status_labels = array(
            BUYGO_APP_STATUS_PENDING   => '待審核',
            BUYGO_APP_STATUS_APPROVED  => '已核准',
            BUYGO_APP_STATUS_REJECTED  => '已拒絕',
            BUYGO_APP_STATUS_CANCELLED => '已取消',
        );
        
        $label = $status_labels[ $item->status ] ?? '未知';
        
        return sprintf(
            '<span class="buygo-status-badge %s">%s</span>',
            esc_attr( $item->status ),
            esc_html( $label )
        );
    }
    
    /**
     * 申請時間欄位
     */
    public function column_submitted_at( $item ) {
        return date( 'Y-m-d H:i', strtotime( $item->submitted_at ) );
    }
    
    /**
     * 操作欄位
     */
    public function column_actions( $item ) {
        $actions = array();
        
        // 查看詳情
        $actions[] = sprintf(
            '<a href="#" class="buygo-review-application" data-app-id="%d">查看詳情</a>',
            $item->id
        );
        
        // 如果是待審核狀態，顯示審核按鈕
        if ( $item->status === BUYGO_APP_STATUS_PENDING ) {
            $approve_url = wp_nonce_url(
                add_query_arg( array(
                    'page' => 'buygo-seller-applications',
                    'action' => 'approve',
                    'id' => $item->id,
                ) ),
                'buygo_approve_application'
            );
            
            $reject_url = wp_nonce_url(
                add_query_arg( array(
                    'page' => 'buygo-seller-applications',
                    'action' => 'reject',
                    'id' => $item->id,
                ) ),
                'buygo_approve_application'
            );
            
            $actions[] = sprintf(
                '<a href="%s" style="color: #28a745;">核准</a>',
                esc_url( $approve_url )
            );
            
            $actions[] = sprintf(
                '<a href="%s" style="color: #dc3545;">拒絕</a>',
                esc_url( $reject_url )
            );
        }
        
        return implode( ' | ', $actions );
    }
    
    /**
     * 顯示篩選器
     */
    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        ?>
        <div class="alignleft actions">
            <select name="status" id="status-filter">
                <option value="">所有狀態</option>
                <option value="<?php echo BUYGO_APP_STATUS_PENDING; ?>" <?php selected( $current_status, BUYGO_APP_STATUS_PENDING ); ?>>待審核</option>
                <option value="<?php echo BUYGO_APP_STATUS_APPROVED; ?>" <?php selected( $current_status, BUYGO_APP_STATUS_APPROVED ); ?>>已核准</option>
                <option value="<?php echo BUYGO_APP_STATUS_REJECTED; ?>" <?php selected( $current_status, BUYGO_APP_STATUS_REJECTED ); ?>>已拒絕</option>
                <option value="<?php echo BUYGO_APP_STATUS_CANCELLED; ?>" <?php selected( $current_status, BUYGO_APP_STATUS_CANCELLED ); ?>>已取消</option>
            </select>
            <?php submit_button( '篩選', 'secondary', 'filter_action', false ); ?>
        </div>
        <?php
    }
}
