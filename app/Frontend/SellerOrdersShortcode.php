<?php

namespace BuyGo\Core\Frontend;

use BuyGo\Core\Services\FluentCartService;

class SellerOrdersShortcode {

    public function __construct() {
        add_shortcode('buygo_seller_orders', [$this, 'render']);
        // NOTE: Auto-inject removed. Now integrated via FluentCartIntegration Tab.
    }

    public function render() {
        if (!is_user_logged_in()) {
            return '<div class="p-4 bg-red-100 text-red-700 rounded-md">請先登入。</div>';
        }

        $user_id = get_current_user_id();
        
        // 1. Get Orders for this Seller
        // We need a way to find orders containing products owned by this user.
        // Assuming FluentCart stores product ID in order items, and products have post_author = seller_id.
        
        global $wpdb;
        
        // Query Logic:
        // Join Order Items -> Join Posts (Product) -> Where Post Author = Current User
        // This is a bit complex query. For MVP, let's try to get all orders and filter in PHP if dataset is small, 
        // OR better, write a custom SQL.
        
        // Table names (Guessing based on FluentCart standards, need to verify if possible, but will use common prefixes)
        $orders_table = $wpdb->prefix . 'fluent_cart_orders'; // Adjust if needed
        $order_items_table = $wpdb->prefix . 'fluent_cart_order_items'; // Adjust if needed
        
        // Let's rely on NotificationService or similar to help, OR just raw SQL.
        // Actually, let's use a mock-like approach if we are not 100% sure of table structure, 
        // BUT strict SQL is better.
        // Let's try to find orders where `seller_id` column exists on order (if custom added) OR via items.
        // Assuming NO distinct seller_id column on order table for multi-vendor.
        
        // Let's try a safer approach: Get all orders (limit 50) and filter.
        // NOTE: In production, this needs optimized SQL.
        $query = "SELECT * FROM {$wpdb->prefix}fcc_orders ORDER BY id DESC LIMIT 50"; // fcc_orders is default for FluentCart?
        // Actually, FluentCart Community likely uses `wp_fcc_orders` or similar. 
        // Let's start with a check or empty list if table not found to avoid crash.
        
        $orders = [];
        // REAL DATA MODE
        // -----------------------------------------------------
        $orders_table = $wpdb->prefix . 'fct_orders';
        $items_table  = $wpdb->prefix . 'fct_order_items';
        $posts_table  = $wpdb->prefix . 'posts';
        $current_user_id = get_current_user_id();

        // Pagination Setup
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        // Base Query Condition
        // We join order_items -> posts (to check post_author)
        $join_sql = "
            FROM {$orders_table} o
            INNER JOIN {$items_table} oi ON o.id = oi.order_id
            INNER JOIN {$posts_table} p ON oi.product_id = p.ID
            WHERE p.post_author = %d
            AND o.status != 'draft'
        ";

        // 1. Get Total Count
        $count_sql = $wpdb->prepare("SELECT COUNT(DISTINCT o.id) {$join_sql}", $current_user_id);
        $total_items = $wpdb->get_var($count_sql);
        $total_pages = ceil($total_items / $per_page);

        // 2. Get Paginated Results
        $sql = $wpdb->prepare("
            SELECT DISTINCT o.* 
            {$join_sql}
            ORDER BY o.id DESC
            LIMIT %d OFFSET %d
        ", $current_user_id, $per_page, $offset);

        $orders = $wpdb->get_results($sql);
        
        // Handle case where tables might not exist (e.g. plugin inactive)
        if (is_null($orders)) {
            $orders = [];
        }
        // -----------------------------------------------------
        
        // Start Buffering
        ob_start();
        ?>
        <div class="buygo-seller-portal" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            
            <!-- Header (Stack Layout) -->
            <div style="display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:24px;">
                <div>
                    <h3 style="margin:0 0 4px 0; font-size:18px; font-weight:600; color:#111827;">訂單管理</h3>
                    <p style="margin:0; font-size:14px; color:#6b7280;">查看並管理您的代購訂單，發送通知給買家。</p>
                </div>
                <div>
                     <!-- Refresh Button (Outline Style) -->
                     <button onclick="window.location.reload()" style="display:inline-flex; align-items:center; padding:8px 16px; border:1px solid #d1d5db; border-radius:6px; background:#fff; font-size:14px; font-weight:500; color:#374151; cursor:pointer;">
                        <svg style="width:16px; height:16px; margin-right:6px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        重新整理
                     </button>
                </div>
            </div>

            <!-- Orders Card (Full Width) -->
            <?php if (empty($orders)): ?>
                <div style="text-align:center; padding:48px 24px; background:#fff; border:1px dashed #d1d5db; border-radius:8px;">
                    <svg style="width:48px; height:48px; margin:0 auto 12px auto; color:#9ca3af;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <h4 style="margin:0 0 4px 0; font-size:14px; font-weight:500; color:#111827;">目前沒有訂單</h4>
                    <p style="margin:0; font-size:13px; color:#6b7280;">當有買家下單時，訂單會顯示在這裡。</p>
                </div>
            <?php else: ?>
                <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05); overflow:hidden;">
                    <?php foreach ($orders as $index => $order): ?>
                        <?php 
                            $order_id = $order->id ?? 123;
                            $status = $order->status ?? 'pending';
                            $total = $order->total_amount ?? '0';
                            $date = $order->created_at ?? date('Y-m-d H:i');
                            $customer_name = "買家 #". ($order->customer_id ?? 'Unknown');
                            
                            // Status Label Mapping (Chinese)
                            $status_labels = [
                                'pending'    => '待處理',
                                'processing' => '處理中',
                                'completed'  => '已完成',
                                'cancelled'  => '已取消',
                                'failed'     => '失敗',
                                'arrived'    => '已到貨',
                                'paid'       => '已付款',
                                'shipped'    => '已寄出',
                                'refunded'   => '已退款',
                            ];
                            $status_label = $status_labels[$status] ?? $status;
                            
                            // Status Badge Colors
                            $badge_bg = '#f3f4f6'; $badge_color = '#374151'; // Default: Gray
                            if ($status === 'completed') { $badge_bg = '#d1fae5'; $badge_color = '#065f46'; } // Green
                            if ($status === 'processing') { $badge_bg = '#dbeafe'; $badge_color = '#1e40af'; } // Blue
                            if ($status === 'arrived') { $badge_bg = '#fef3c7'; $badge_color = '#92400e'; } // Yellow
                            if ($status === 'paid') { $badge_bg = '#d1fae5'; $badge_color = '#065f46'; } // Green
                            if ($status === 'shipped') { $badge_bg = '#e0e7ff'; $badge_color = '#3730a3'; } // Indigo
                            if ($status === 'cancelled' || $status === 'failed') { $badge_bg = '#fee2e2'; $badge_color = '#991b1b'; } // Red
                            
                            $border_top = $index > 0 ? 'border-top:1px solid #e5e7eb;' : '';
                        ?>
                        <div style="padding:16px 20px; <?php echo $border_top; ?> display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
                            
                            <!-- Order Info -->
                            <div style="flex:1; min-width:200px;">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <span style="font-size:15px; font-weight:600; color:#111827;">#<?php echo esc_html($order_id); ?></span>
                                    <span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:500; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>;">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </div>
                                <div style="font-size:13px; color:#6b7280; display:flex; flex-wrap:wrap; gap:12px;">
                                    <span style="display:flex; align-items:center;">
                                        <svg style="width:14px; height:14px; margin-right:4px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                        <?php echo esc_html($customer_name); ?>
                                    </span>
                                    <span style="display:flex; align-items:center;">
                                        <svg style="width:14px; height:14px; margin-right:4px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        <?php echo esc_html($date); ?>
                                    </span>
                                    <span style="font-weight:500; color:#111827;">$<?php echo esc_html(number_format(floatval($total), 0)); ?></span>
                                </div>
                            </div>

                            <!-- Action Button (Primary = Black) -->
                            <div>
                                <button onclick="BuyGoHistory.open(<?php echo $order_id; ?>)" style="display:inline-flex; align-items:center; padding:8px 16px; border:1px solid #d1d5db; border-radius:6px; background:#fff; font-size:14px; font-weight:500; color:#374151; cursor:pointer; margin-right:8px;">
                                    紀錄
                                </button>
                                <button onclick="BuyGoNotification.open(<?php echo $order_id; ?>)" style="display:inline-flex; align-items:center; padding:8px 16px; border:none; border-radius:6px; background:#111827; font-size:14px; font-weight:500; color:#fff; cursor:pointer;">
                                    <svg style="width:16px; height:16px; margin-right:6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                    </svg>
                                    通知買家
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; margin-top: 24px; gap: 6px;">
                    <?php 
                    $base_url = remove_query_arg('paged');
                    
                    // Previous
                    if ($paged > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; color: #374151; border-radius: 6px; text-decoration: none; font-size: 14px;">上一頁</a>';
                    }

                    // Pages (Simple Range)
                    $start = max(1, $paged - 2);
                    $end = min($total_pages, $paged + 2);

                    if ($start > 1) {
                         echo '<a href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; color: #374151; border-radius: 6px; text-decoration: none; font-size: 14px;">1</a>';
                         if ($start > 2) echo '<span style="color: #9ca3af;">...</span>';
                    }

                    for ($i = $start; $i <= $end; $i++) {
                        $active_style = ($i === $paged) ? 'background: #111827; color: #fff; border-color: #111827;' : 'background: #fff; color: #374151; border-color: #e5e7eb;';
                        echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" style="padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; text-decoration: none; font-size: 14px; ' . $active_style . '">' . $i . '</a>';
                    }

                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span style="color: #9ca3af;">...</span>';
                        echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; color: #374151; border-radius: 6px; text-decoration: none; font-size: 14px;">' . $total_pages . '</a>';
                    }

                    // Next
                    if ($paged < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '" style="padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; color: #374151; border-radius: 6px; text-decoration: none; font-size: 14px;">下一頁</a>';
                    }
                    ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- History Modal -->
            <div id="buygo-history-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,0.5); backdrop-filter:blur(2px);">
                <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;">
                    <div style="position:absolute; top:0; left:0; right:0; bottom:0;" onclick="BuyGoHistory.close()"></div>
                    <div style="position:relative; background:white; border-radius:12px; max-width:500px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; max-height:80vh; display:flex; flex-direction:column;">
                        <div style="padding:16px 24px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:18px; font-weight:700; color:#111827;">📜 發送紀錄</h3>
                            <button onclick="BuyGoHistory.close()" style="background:none; border:none; font-size:20px; color:#9ca3af; cursor:pointer;">&times;</button>
                        </div>
                        <div id="history-content" style="padding:0 24px; overflow-y:auto; flex:1;">
                            <!-- List injected here -->
                        </div>
                        <div style="padding:16px 24px; border-top:1px solid #e5e7eb; text-align:right; background:#f9fafb;">
                            <button onclick="BuyGoHistory.close()" style="padding:8px 16px; border:1px solid #d1d5db; border-radius:6px; background:white; color:#374151; cursor:pointer;">關閉</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            window.BuyGoHistory = {
                open: function(orderId) {
                    document.getElementById('buygo-history-modal').style.display = 'block';
                    document.getElementById('history-content').innerHTML = '<div style="text-align:center; padding:40px; color:#6b7280;">載入中...</div>';
                    
                    fetch('/wp-json/buygo/v1/orders/' + orderId + '/notifications', {
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data || data.length === 0) {
                            document.getElementById('history-content').innerHTML = '<div style="text-align:center; padding:40px; color:#6b7280;">尚無發送紀錄</div>';
                            return;
                        }
                        
                        let html = '<ul style="list-style:none; padding:0; margin:0;">';
                        data.forEach(log => {
                            let statusColor = log.status === 'sent' ? '#059669' : '#dc2626';
                            let statusText = log.status === 'sent' ? '成功' : '失敗';
                            let icon = log.channel === 'line' ? '💬 LINE' : '📧 Email';
                            let time = log.sent_at.substring(5, 16); // mm-dd HH:MM

                            html += `<li style="border-bottom:1px solid #f3f4f6; padding:16px 0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <span style="font-weight:600; color:#111827; font-size:14px;">${icon}</span>
                                    <span style="font-size:12px; color:#6b7280;">${time}</span>
                                </div>
                                <div style="font-size:13px; color:#374151; margin-bottom:4px;">${log.title || log.type}</div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                     <span style="font-size:12px; color:#9ca3af;">${log.user_id ? 'User#'+log.user_id : ''}</span>
                                     <span style="font-size:12px; color:${statusColor}; font-weight:500;">${statusText}</span>
                                </div>
                            </li>`;
                        });
                        html += '</ul>';
                        document.getElementById('history-content').innerHTML = html;
                    })
                    .catch(e => {
                        document.getElementById('history-content').innerHTML = '<div style="text-align:center; padding:20px; color:#dc2626;">載入失敗</div>';
                        console.error(e);
                    });
                },
                close: function() {
                    document.getElementById('buygo-history-modal').style.display = 'none';
                }
            };
            </script>

            <!-- Embed Notification Modal -->
            <?php $this->render_modal(); ?>
            
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_modal() {
        // Reuse the Modal HTML and JS from our previous logic, 
        // essentially embedding it here to ensure it works even if shortcode is used alone.
        // It's safer to have it once.
        ?>
        <!-- Modal HTML with PURE INLINE STYLES (No Tailwind dependency) -->
        <div id="buygo-notification-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,0.7); overflow-y:auto;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px;">
                <!-- Close overlay on background click -->
                <div style="position:absolute; top:0; left:0; right:0; bottom:0;" onclick="BuyGoNotification.close()"></div>
                <!-- Modal Content Box -->
                <div style="position:relative; background:white; border-radius:12px; max-width:500px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
                    <!-- Header -->
                    <div style="padding:20px 24px; border-bottom:1px solid #e5e7eb;">
                        <h3 style="margin:0; font-size:18px; font-weight:700; color:#111827;" id="modal-title">📨 通知買家</h3>
                        <p style="margin:8px 0 0 0; font-size:14px; color:#6b7280;">發送 LINE 與 Email 通知給買家。</p>
                    </div>
                    <!-- Body -->
                    <div style="padding:20px 24px;">
                        <form id="buygo-notification-form">
                            <input type="hidden" id="buygo-order-id" name="order_id" value="">
                            
                            <div style="margin-bottom:16px;">
                                <label for="notification-type" style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">通知類型</label>
                                <select id="notification-type" name="type" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; background:white;">
                                    <option value="order_arrived">✨ 貨已到，請來取貨/準備出貨</option>
                                    <option value="order_paid">💰 已收到款項</option>
                                    <option value="order_shipped">🚚 已寄出</option>
                                    <option value="order_cancelled">❌ 訂單取消/缺貨</option>
                                </select>
                            </div>
                            
                            <div style="margin-bottom:16px;">
                                <label for="seller-note" style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">賣家備註 (選填)</label>
                                <textarea id="seller-note" name="note" rows="3" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; resize:vertical; box-sizing:border-box;" placeholder="例如：面交時間、寄件單號..."></textarea>
                            </div>
                            
                            <div style="background:#f9fafb; padding:12px; border-radius:6px; border:1px solid #e5e7eb;">
                                <p style="margin:0 0 4px 0; font-size:12px; font-weight:500; color:#6b7280;">預覽：</p>
                                <p id="line-preview-text" style="margin:0; font-size:12px; color:#374151; white-space:pre-wrap;"></p>
                            </div>
                        </form>
                    </div>
                    <!-- Footer -->
                    <div style="padding:16px 24px; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" onclick="BuyGoNotification.close()" style="padding:10px 20px; border:1px solid #d1d5db; border-radius:6px; background:white; font-size:14px; font-weight:500; color:#374151; cursor:pointer;">取消</button>
                        <button type="button" id="buygo-btn-send" onclick="BuyGoNotification.send()" style="padding:10px 20px; border:none; border-radius:6px; background:#111827; font-size:14px; font-weight:500; color:white; cursor:pointer;">發送</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
        // Define BuyGoNotification globally if not already defined
        if (typeof BuyGoNotification === 'undefined') {
            window.BuyGoNotification = {
                messages: {
                    'order_arrived': "✨ 您訂購的商品已到貨！\n訂單編號：{order_id}\n賣家備註：{note}\n請留意賣家後續通知或前往取貨。",
                    'order_paid': "💰 賣家已確認收到您的款項。\n訂單編號：{order_id}\n賣家備註：{note}",
                    'order_shipped': "🚚 您的訂單已寄出！\n訂單編號：{order_id}\n賣家備註：{note}\n請留意物流簡訊或通知。",
                    'order_cancelled': "❌ 您的訂單有異動/取消。\n訂單編號：{order_id}\n說明：{note}"
                },
                updatePreview: function() {
                    const type = document.getElementById('notification-type').value;
                    const note = document.getElementById('seller-note').value;
                    const orderId = document.getElementById('buygo-order-id').value;
                    let template = BuyGoNotification.messages[type] || '';
                    document.getElementById('line-preview-text').innerText = template.replace('{order_id}', orderId).replace('{note}', note || '(無)');
                },
                open: function(orderId) {
                    console.log('[BuyGo] open() called with orderId:', orderId);
                    document.getElementById('buygo-order-id').value = orderId;
                    document.getElementById('seller-note').value = ''; 
                    document.getElementById('buygo-notification-modal').style.display = 'block';
                    this.updatePreview();
                    // Attach listeners dynamically
                    document.getElementById('notification-type').onchange = this.updatePreview;
                    document.getElementById('seller-note').oninput = this.updatePreview;
                    console.log('[BuyGo] Modal should now be visible.');
                },
                close: function() {
                    document.getElementById('buygo-notification-modal').style.display = 'none';
                },
                send: function() {
                    console.log('[BuyGo] send() called!');
                    const orderId = document.getElementById('buygo-order-id').value;
                    const type = document.getElementById('notification-type').value;
                    const note = document.getElementById('seller-note').value;
                    const btn = document.getElementById('buygo-btn-send');
                    
                    console.log('[BuyGo] Sending notification for order:', orderId, 'type:', type);
                    
                    btn.innerText = '發送中...';
                    btn.disabled = true;

                    fetch('/wp-json/buygo/v1/orders/' + orderId + '/notify', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                        },
                        body: JSON.stringify({ type: type, note: note })
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message || '已發送');
                        window.location.reload();
                    })
                    .catch(e => alert('Error: ' + e))
                    .finally(() => {
                        btn.innerText = '發送';
                        btn.disabled = false;
                        BuyGoNotification.close();
                    });
                }
            };
            console.log('[BuyGo] BuyGoNotification object initialized successfully!');
        } else {
            console.log('[BuyGo] BuyGoNotification already defined, skipping.');
        }
        </script>
        <?php
    }
}
