<?php
/**
 * 角色列表表格類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 載入 WP_List_Table
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BuyGo_RP_Role_List_Table extends WP_List_Table {
    
    /**
     * 建構函數
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'user',
            'plural'   => 'users',
            'ajax'     => false,
        ) );
    }
    
    /**
     * 取得欄位
     */
    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'user_login'   => '使用者名稱',
            'user_email'   => 'Email',
            'buygo_role'   => 'BuyGo 角色',
            'line_status'  => 'LINE 狀態',
            'registered'   => '註冊時間',
            'actions'      => '操作',
        );
    }
    
    /**
     * 取得可排序的欄位
     */
    public function get_sortable_columns() {
        return array(
            'user_login'  => array( 'user_login', false ),
            'user_email'  => array( 'user_email', false ),
            'registered'  => array( 'user_registered', true ),
        );
    }
    
    /**
     * 取得批次操作
     */
    public function get_bulk_actions() {
        return array(
            'set_admin'  => '設為 Admin',
            'set_seller' => '設為 Seller',
            'set_helper' => '設為 Helper',
            'set_buyer'  => '設為 Buyer',
        );
    }
    
    /**
     * 準備項目
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // 取得篩選參數
        $role_filter = isset( $_GET['role'] ) ? sanitize_text_field( $_GET['role'] ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        
        // 建立查詢參數
        $args = array(
            'number' => $per_page,
            'offset' => ( $current_page - 1 ) * $per_page,
            'orderby' => 'registered',
            'order' => 'DESC',
        );
        
        // 角色篩選
        if ( ! empty( $role_filter ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => 'buygo_role',
                    'value' => $role_filter,
                    'compare' => '=',
                ),
            );
        }
        
        // 搜尋
        if ( ! empty( $search ) ) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }
        
        // 排序
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'user_registered';
        $order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
        $args['orderby'] = $orderby;
        $args['order'] = $order;
        
        // 取得使用者
        $user_query = new WP_User_Query( $args );
        $users = $user_query->get_results();
        
        // 取得總數
        $total_args = $args;
        unset( $total_args['number'] );
        unset( $total_args['offset'] );
        $total_query = new WP_User_Query( $total_args );
        $total_items = $total_query->get_total();
        
        // 設定項目
        $this->items = $users;
        
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
        return isset( $item->$column_name ) ? $item->$column_name : '';
    }
    
    /**
     * 勾選框欄位
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="users[]" value="%d" class="buygo-checkbox" />',
            $item->ID
        );
    }
    
    /**
     * 使用者名稱欄位
     */
    public function column_user_login( $item ) {
        $edit_url = get_edit_user_link( $item->ID );
        return sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url( $edit_url ),
            esc_html( $item->user_login )
        );
    }
    
    /**
     * Email 欄位
     */
    public function column_user_email( $item ) {
        return sprintf(
            '<a href="mailto:%s">%s</a>',
            esc_attr( $item->user_email ),
            esc_html( $item->user_email )
        );
    }
    
    /**
     * BuyGo 角色欄位
     */
    public function column_buygo_role( $item ) {
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        $role = $role_manager->get_user_role( $item->ID );
        
        $role_labels = array(
            BUYGO_ROLE_ADMIN  => '管理員',
            BUYGO_ROLE_SELLER => '賣家',
            BUYGO_ROLE_HELPER => '小幫手',
            BUYGO_ROLE_BUYER  => '買家',
        );
        
        $label = $role_labels[ $role ] ?? '買家';
        
        // 建立快速變更角色的下拉選單
        $output = sprintf(
            '<span class="buygo-role-badge %s">%s</span>',
            esc_attr( $role ),
            esc_html( $label )
        );
        
        // 加入快速變更選單
        $output .= '<br><select class="buygo-quick-role-change" data-user-id="' . $item->ID . '" style="margin-top:5px;">';
        $output .= '<option value="">變更角色...</option>';
        foreach ( $role_labels as $role_value => $role_label ) {
            $selected = ( $role === $role_value ) ? ' selected' : '';
            $output .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $role_value ),
                $selected,
                esc_html( $role_label )
            );
        }
        $output .= '</select>';
        
        return $output;
    }
    
    /**
     * LINE 狀態欄位
     */
    public function column_line_status( $item ) {
        global $wpdb;
        
        $line_uid = null;
        
        // 方法 1：從 Nextend Social Login 查詢
        $social_table = $wpdb->prefix . 'social_users';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$social_table}'" ) === $social_table ) {
            $line_uid = $wpdb->get_var( $wpdb->prepare(
                "SELECT identifier FROM {$social_table} WHERE type = 'line' AND ID = %d LIMIT 1",
                $item->ID
            ) );
        }
        
        // 方法 2：從 OrderNotify 的 user_meta 查詢
        if ( empty( $line_uid ) ) {
            $line_uid = get_user_meta( $item->ID, 'buygo_line_uid', true );
        }
        
        // 方法 3：從其他可能的 meta key 查詢
        if ( empty( $line_uid ) ) {
            $possible_keys = array( '_mygo_line_uid', '_buygo_line_uid', 'line_uid' );
            foreach ( $possible_keys as $key ) {
                $line_uid = get_user_meta( $item->ID, $key, true );
                if ( ! empty( $line_uid ) ) {
                    break;
                }
            }
        }
        
        if ( ! empty( $line_uid ) ) {
            return sprintf(
                '<span style="color: #28a745;">✓ 已綁定</span><br><small style="color: #666;">%s</small>',
                esc_html( substr( $line_uid, 0, 20 ) . '...' )
            );
        } else {
            return '<span style="color: #6c757d;">未綁定</span>';
        }
    }
    
    /**
     * 註冊時間欄位
     */
    public function column_registered( $item ) {
        if ( ! empty( $item->user_registered ) ) {
            return date( 'Y-m-d H:i', strtotime( $item->user_registered ) );
        }
        return '-';
    }
    
    /**
     * 操作欄位
     */
    public function column_actions( $item ) {
        $actions = array();
        
        // 編輯
        $edit_url = get_edit_user_link( $item->ID );
        $actions[] = sprintf(
            '<a href="%s">編輯</a>',
            esc_url( $edit_url )
        );
        
        // 變更角色
        $actions[] = sprintf(
            '<a href="#" class="buygo-change-role" data-user-id="%d">變更角色</a>',
            $item->ID
        );
        
        return implode( ' | ', $actions );
    }
    
    /**
     * 顯示篩選器
     */
    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        
        $current_role = isset( $_GET['role'] ) ? sanitize_text_field( $_GET['role'] ) : '';
        ?>
        <div class="alignleft actions">
            <select name="role" id="role-filter">
                <option value="">所有角色</option>
                <option value="<?php echo BUYGO_ROLE_ADMIN; ?>" <?php selected( $current_role, BUYGO_ROLE_ADMIN ); ?>>管理員</option>
                <option value="<?php echo BUYGO_ROLE_SELLER; ?>" <?php selected( $current_role, BUYGO_ROLE_SELLER ); ?>>賣家</option>
                <option value="<?php echo BUYGO_ROLE_HELPER; ?>" <?php selected( $current_role, BUYGO_ROLE_HELPER ); ?>>小幫手</option>
                <option value="<?php echo BUYGO_ROLE_BUYER; ?>" <?php selected( $current_role, BUYGO_ROLE_BUYER ); ?>>買家</option>
            </select>
            <?php submit_button( '篩選', 'secondary', 'filter_action', false ); ?>
        </div>
        <?php
    }
}
