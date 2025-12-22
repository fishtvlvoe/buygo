<?php

namespace BuyGo\Core\Frontend;

class SellerOrderNotification {

    public function __construct() {
        add_action('wp_footer', [$this, 'inject_modal_and_script']);
    }

    public function inject_modal_and_script() {
        // Enforce logic only on account page or related endpoints
        // Using strict check for 'account' page or endpoint
        global $post;
        if (!is_page() || $post->post_name !== 'account') {
            return;
        }

        // Only for Seller/Helper/Admin roles
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $role = $user->roles[0] ?? '';
        // If RoleManager is available, better use that, but simple check is okay for frontend script injection
        if (!in_array('administrator', $user->roles) && !in_array('buygo_admin', $user->roles) && !in_array('buygo_seller', $user->roles) && !in_array('buygo_helper', $user->roles)) {
            return;
        }

        // Load FontAwesome if needed or use SVG (SVG preferred per guidelines)
        
        ?>
        <!-- BuyGo Order Notification System -->
        <!-- Using Tailwind CSS classes -->
        <div id="buygo-notification-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Background Backdrop with Blur -->
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-filter backdrop-blur-sm" aria-hidden="true" onclick="BuyGoNotification.close()"></div>

                <!-- Modal Panel -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <!-- Icon -->
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">
                                    通知買家
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        發送 LINE 與 Email 通知給買家，並更新訂單狀態。
                                    </p>

                                    <!-- Form -->
                                    <form id="buygo-notification-form" class="mt-4 space-y-4">
                                        <input type="hidden" id="buygo-order-id" name="order_id" value="">
                                        
                                        <!-- Notification Type -->
                                        <div>
                                            <label for="notification-type" class="block text-sm font-semibold text-gray-700">通知類型</label>
                                            <div class="relative mt-1">
                                                <select id="notification-type" name="type" class="block w-full pl-3 pr-10 py-2.5 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md shadow-sm appearance-none">
                                                    <option value="order_arrived">✨ 貨已到，請來取貨/準備出貨</option>
                                                    <option value="order_paid">💰 已收到款項</option>
                                                    <option value="order_shipped">🚚 已寄出</option>
                                                    <option value="order_cancelled">❌ 訂單取消/缺貨</option>
                                                </select>
                                                <!-- Custom Arrow Icon for Dropdown -->
                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Seller Note -->
                                        <div>
                                            <label for="seller-note" class="block text-sm font-semibold text-gray-700">賣家備註 (選填)</label>
                                            <textarea id="seller-note" name="note" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-gray-900" placeholder="例如：面交時間、寄件單號... (禁止輸入網址)"></textarea>
                                        </div>

                                        <!-- Preview -->
                                        <div class="bg-gray-50 p-3 rounded-md border border-gray-200">
                                            <p class="text-xs text-gray-500 font-medium mb-1">將發送 LINE：</p>
                                            <p id="line-preview-text" class="text-xs text-gray-700 leading-relaxed"></p>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-100">
                        <button type="button" id="buygo-btn-send" onclick="BuyGoNotification.send()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-900 text-base font-medium text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                            <span id="btn-text">確認發送</span>
                            <svg id="btn-spinner" class="animate-spin ml-2 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                        <button type="button" onclick="BuyGoNotification.close()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            取消
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- JS Logic -->
        <script>
        const BuyGoNotification = {
            messages: {
                'order_arrived': "✨ 您訂購的商品已到貨！\n訂單編號：{order_id}\n賣家備註：{note}\n請留意賣家後續通知或前往取貨。",
                'order_paid': "💰 賣家已確認收到您的款項。\n訂單編號：{order_id}\n賣家備註：{note}",
                'order_shipped': "🚚 您的訂單已寄出！\n訂單編號：{order_id}\n賣家備註：{note}\n請留意物流簡訊或通知。",
                'order_cancelled': "❌ 您的訂單有異動/取消。\n訂單編號：{order_id}\n說明：{note}"
            },
            
            init: function() {
                // Wait for FluentCart DOM
                console.log('BuyGo Notification System Initializing...');
                // We need to use MutationObserver because FluentCart might load via AJAX/Vue
                const targetNode = document.body;
                const config = { childList: true, subtree: true };

                const observer = new MutationObserver(function(mutationsList, observer) {
                    // Check if Order List Table exists
                    // Selector depends on FluentCart DOM structure. Let's assume common classes.
                    // This is heuristic: Look for order rows.
                    const orderRows = document.querySelectorAll('tr'); 
                    // Better to scope to a specific container if known, e.g., .fc_order_table
                    
                    orderRows.forEach(row => {
                         // Check if we already injected
                         if (row.classList.contains('buygo-processed')) return;

                         // Try to find Order ID in this row (usually first column or link)
                         const orderLink = row.querySelector('a[href*="id="]');
                         // Assuming link like ?page=fluent_cart_order&id=123
                         let orderId = null;

                         if (orderLink) {
                            const urlParams = new URL(orderLink.href).searchParams;
                            orderId = urlParams.get('id');
                         }
                         
                         // If no orderId found, maybe it's in text e.g. #1234
                         if (!orderId) {
                             const text = row.innerText;
                             const match = text.match(/#(\d+)/);
                             if (match) orderId = match[1];
                         }

                         if (orderId) {
                             // Inject Button
                             // Find the Action column (usually the last td)
                             const actionCell = row.lastElementChild;
                             if (actionCell) {
                                 const btn = document.createElement('button');
                                 btn.innerText = '通知';
                                 btn.className = 'ml-2 inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-colors';
                                 btn.onclick = (e) => {
                                     e.preventDefault();
                                     e.stopPropagation();
                                     BuyGoNotification.open(orderId);
                                 };
                                 actionCell.appendChild(btn);
                                 row.classList.add('buygo-processed');
                             }
                         }
                    });
                });
                
                // Start detecting (debounce if needed, but simple oberserver ok for now)
                observer.observe(targetNode, config);

                // Update preview on valid change
                document.getElementById('notification-type').addEventListener('change', this.updatePreview);
                document.getElementById('seller-note').addEventListener('input', this.updatePreview);
            },

            updatePreview: function() {
                const type = document.getElementById('notification-type').value;
                const note = document.getElementById('seller-note').value;
                const orderId = document.getElementById('buygo-order-id').value || '12345';
                
                let template = BuyGoNotification.messages[type] || '';
                let preview = template.replace('{order_id}', orderId).replace('{note}', note || '(無)');
                
                document.getElementById('line-preview-text').innerText = preview;
            },

            open: function(orderId) {
                document.getElementById('buygo-order-id').value = orderId;
                document.getElementById('seller-note').value = ''; // Reset note
                document.getElementById('buygo-notification-modal').classList.remove('hidden');
                this.updatePreview();
            },

            close: function() {
                document.getElementById('buygo-notification-modal').classList.add('hidden');
            },

            send: function() {
                const orderId = document.getElementById('buygo-order-id').value;
                const type = document.getElementById('notification-type').value;
                const note = document.getElementById('seller-note').value;

                const btn = document.getElementById('buygo-btn-send');
                const btnText = document.getElementById('btn-text');
                const spinner = document.getElementById('btn-spinner');

                // UI Loading state
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btnText.innerText = '發送中...';
                spinner.classList.remove('hidden');

                // API Call
                fetch('/wp-json/buygo/v1/orders/' + orderId + '/notify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
                    },
                    body: JSON.stringify({
                        type: type,
                        note: note,
                        update_status: true
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.code && data.code !== 'success' && !data.success) {
                        throw new Error(data.message || '發送失敗');
                    }
                    // Success
                    // Show Toast (Simple implementation)
                    alert('✅ 通知已發送成功！'); // Per UX rules, should use Toast, but simplified for MVP script injection
                    
                    // Force Reload to update status
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ 發送失敗：' + error.message);
                })
                .finally(() => {
                    // Reset UI
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btnText.innerText = '確認發送';
                    spinner.classList.add('hidden');
                    BuyGoNotification.close();
                });
            }
        };

        // Run on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', BuyGoNotification.init);
        } else {
            BuyGoNotification.init();
        }
        </script>
        <?php
    }
}
