<?php

namespace BuyGo\Core\Frontend;

class HelperManagementShortcode {

    public function __construct() {
        add_shortcode('buygo_my_helpers', [$this, 'render']);
    }

    public function render() {
        if (!is_user_logged_in()) {
            return '<p>請先登入。</p>';
        }

        $user = wp_get_current_user();
        if (!in_array('buygo_seller', (array) $user->roles) && !in_array('buygo_admin', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            return '<div style="padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px;">您沒有權限訪問此頁面，僅限賣家使用。</div>';
        }

        // Note: buygo-smart-selector.js is no longer needed as this shortcode functionality has been migrated to Vue.js
        // Removed to prevent 404 errors

        ob_start();
        ?>
        <style>
            .buygo-helper-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
            .buygo-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .buygo-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
            .buygo-btn-primary { background: #111827; color: #fff; }
            .buygo-btn-primary:hover { background: #1f2937; }
            .buygo-btn-danger { background: #ef4444; color: #fff; }
            .buygo-btn-danger:hover { background: #dc2626; }
            .buygo-btn-outline { background: #fff; border-color: #d1d5db; color: #374151; }
            .buygo-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; }
            
            .buygo-table-wrapper { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
            .buygo-table { width: 100%; border-collapse: collapse; text-align: left; }
            .buygo-table th { background: #f9fafb; padding: 12px 16px; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
            .buygo-table td { padding: 16px; border-bottom: 1px solid #e5e7eb; background: #fff; color: #111827; font-size: 14px; }
            .buygo-table tr:last-child td { border-bottom: none; }
            .buygo-table tr:hover td { background: #f9fafb; }
            
            .buygo-badge { display: inline-flex; items-center; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; margin-right: 4px; margin-bottom: 4px; }
            
            .buygo-form-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
            .buygo-form-title { font-size: 16px; font-weight: 600; color: #0f172a; margin: 0 0 16px 0; }
            .buygo-label { display: block; font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 6px; }
            .buygo-select { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: 14px; color: #0f172a; }
            .buygo-checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
            .buygo-checkbox-label input { margin-right: 8px; width: 16px; height: 16px; border-radius: 4px; border: 1px solid #cbd5e1; text-blue-600; }
            
            /* Stack Layout (Dashboard Style) */
            .buygo-page-header { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
            @media (min-width: 768px) {
                .buygo-page-header { flex-direction: row; justify-content: space-between; align-items: flex-end; }
            }
            .buygo-page-title h3 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0; }
            .buygo-page-title p { font-size: 14px; color: #6b7280; line-height: 1.5; margin: 0; }
            
            /* Add Button Wrapper */
            .buygo-actions { display: flex; gap: 12px; }

            /* Refined Table Typography */
            .buygo-table th { background: #f9fafb; padding: 12px 20px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
            .buygo-table td { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; background: #fff; color: #111827; font-size: 14px; }

            /* Modal Styles - FluentCart Aligned */
            .buygo-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.2s; backdrop-filter: blur(2px); }
            .buygo-modal-overlay.active { opacity: 1; visibility: visible; }
            .buygo-modal-container { background: #fff; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.95); transition: transform 0.2s; overflow: hidden; display: flex; flex-direction: column; }
            .buygo-modal-overlay.active .buygo-modal-container { transform: scale(1); }
            
            /* Modal Header */
            .buygo-modal-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #fff; }
            .buygo-modal-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; line-height: 1.2; letter-spacing: -0.01em; }
            .buygo-modal-close { background: none; border: none; font-size: 24px; line-height: 1; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.15s; }
            .buygo-modal-close:hover { color: #4b5563; background: #f3f4f6; }

            /* Modal Body */
            .buygo-modal-body { padding: 24px; overflow-y: auto; max-height: 70vh; }
            .buygo-label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .buygo-select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; font-size: 15px; color: #111827; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: border-color 0.15s; outline: none; appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
            .buygo-select:focus { border-color: #4f46e5; ring: 2px solid #e0e7ff; }

            /* Checkboxes */
            .buygo-checkbox-group { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 4px; }
            .buygo-checkbox-label { display: flex; align-items: center; font-size: 15px; color: #374151; cursor: pointer; user-select: none; }
            .buygo-checkbox-label input { margin-right: 10px; width: 18px; height: 18px; border-radius: 4px; border: 1px solid #d1d5db; accent-color: #111827; cursor: pointer; }
            
            /* Modal Footer */
            .buygo-modal-footer { padding: 20px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
            .buygo-modal-footer .buygo-btn { padding: 8px 16px; font-size: 14px; border-radius: 6px; font-weight: 500; }
            /* Loading Spinner Animation */
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            .animate-spin { animation: spin 1s linear infinite; }
            .spinner-icon { width: 32px; height: 32px; color: #9ca3af; margin: 0 auto 16px auto; display: block; }
        </style>

        <div id="buygo-helpers-app">
            
            <!-- Header Section (Top) -->
            <div class="buygo-page-header">
                <div class="buygo-page-title">
                    <h3>小幫手管理</h3>
                    <p>您可以在此管理協助您處理訂單的小幫手，並設定權限。</p>
                    <p style="color: #d97706; font-size: 13px; margin-top: 8px; font-weight: 500;">
                        ⚠️ 新增你需要的小幫手後，請重新整理網頁。手機版請下拉更新，直到小幫手出現。
                    </p>
                </div>
                <div class="buygo-actions">
                    <button type="button" id="btn-batch-delete" class="buygo-btn buygo-btn-danger" style="display:none;">
                        刪除選取項目
                    </button>
                    <button type="button" onclick="document.getElementById('add-helper-modal').classList.add('active');" 
                        class="buygo-btn buygo-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        新增
                    </button>
                </div>
            </div>

            <div id="helper-list-container">
                <!-- Modal -->
                <div id="add-helper-modal" class="buygo-modal-overlay">
                    <div class="buygo-modal-container">
                        <div class="buygo-modal-header">
                            <h4 class="buygo-modal-title">新增小幫手</h4>
                            <button type="button" class="buygo-modal-close" onclick="document.getElementById('add-helper-modal').classList.remove('active');">&times;</button>
                        </div>
                        <form id="new-helper-form" onsubmit="return false;">
                            <div class="buygo-modal-body">
                                <div style="margin-bottom: 20px;">
                                    <label class="buygo-label">小幫手搜尋</label>
                                    <input type="text" id="helper-search-input" name="user_email" class="buygo-select" placeholder="搜尋姓名或 Email..." style="padding: 10px 12px;">
                                    <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">請輸入關鍵字搜尋，點擊列表選擇使用者。</p>
                                </div>
                                <div style="margin-bottom: 0;">
                                    <label class="buygo-label">權限設定</label>
                                    <div class="buygo-checkbox-group">
                                        <label class="buygo-checkbox-label"><input type="checkbox" name="permissions[can_view_orders]" value="1"> 查看訂單</label>
                                        <label class="buygo-checkbox-label"><input type="checkbox" name="permissions[can_update_orders]" value="1"> 修改訂單</label>
                                        <label class="buygo-checkbox-label"><input type="checkbox" name="permissions[can_manage_products]" value="1"> 管理商品</label>
                                        <label class="buygo-checkbox-label"><input type="checkbox" name="permissions[can_reply_customers]" value="1"> 回覆客訴</label>
                                    </div>
                                </div>
                            </div>
                            <div class="buygo-modal-footer">
                                <button type="button" onclick="document.getElementById('add-helper-modal').classList.remove('active');" 
                                    class="buygo-btn buygo-btn-outline">取消</button>
                                <button type="submit" id="btn-save-helper" class="buygo-btn buygo-btn-primary">確認新增</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="helpers-loading" style="text-align: center; color: #6b7280; padding: 40px;">
                    <svg class="animate-spin spinner-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>載入中...</span>
                </div>
                
                <div id="helpers-table-container" style="display: none;" class="buygo-table-wrapper">
                    <table class="buygo-table">
                        <thead>
                            <tr>
                                <th style="width: 48px; text-align:center;"><input type="checkbox" id="select-all-helpers"></th>
                                <th>姓名 / Email</th>
                                <th>權限</th>
                            </tr>
                        </thead>
                        <tbody id="helpers-tbody">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>
                    
                    <div id="helpers-empty" style="display: none; text-align: center; padding: 60px 20px; color: #6b7280; background: #fff; border:1px solid #e5e7eb; border-radius: 8px;">
                        <div style="margin-bottom:16px;">
                            <svg width="64" height="64" class="mx-auto text-gray-300" style="display:block; margin:0 auto; width:64px; height:64px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">沒有小幫手</h3>
                        <p class="mt-1 text-sm text-gray-500">新增小幫手來協助您管理訂單與商品。</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const apiRoot = '/wp-json/buygo/v1';
            const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

            // Note: Smart Selector initialization removed as buygo-smart-selector.js is no longer available
            // The search input will work with manual typing and API calls

            const loadHelpers = () => {
                document.getElementById('helpers-loading').style.display = 'block';
                document.getElementById('helpers-table-container').style.display = 'none';
                document.getElementById('helpers-empty').style.display = 'none';

                fetch(`${apiRoot}/helpers`, {
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('helpers-loading').style.display = 'none';
                    if (data.length > 0) {
                        renderTable(data);
                        document.getElementById('helpers-table-container').style.display = 'block';
                    } else {
                        document.getElementById('helpers-empty').style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('helpers-loading').innerText = '載入失敗';
                });
            };

            const renderTable = (helpers) => {
                const tbody = document.getElementById('helpers-tbody');
                tbody.innerHTML = '';
                helpers.forEach(helper => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid #e5e7eb';
                    
                    const permissions = [];
                    if (helper.can_view_orders) permissions.push('查看訂單');
                    if (helper.can_update_orders) permissions.push('修改訂單');
                    if (helper.can_manage_products) permissions.push('管理商品');
                    
                    const permText = permissions.length ? permissions.join(', ') : '無特殊權限';

                    row.innerHTML = `
                        <td style="padding: 12px;">
                            <input type="checkbox" class="helper-checkbox" value="${helper.id}">
                        </td>
                        <td style="padding: 12px;">
                            <div style="font-weight: 500; color: #111827;">${helper.display_name}</div>
                            <div style="font-size: 0.875em; color: #6b7280;">${helper.user_email}</div>
                        </td>
                        <td style="padding: 12px; color: #4b5563;">
                            <span style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-size: 0.85em;">${permText}</span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            };

             window.deleteHelpersBatch = () => {
                const checkboxes = document.querySelectorAll('.helper-checkbox:checked');
                if (checkboxes.length === 0) return;

                if (checkboxes.length === 0) return;

                // Removed confirmation dialog as requested
                // if (!confirm(`確定要移除這 ${checkboxes.length} 位小幫手嗎？`)) return;

                const ids = Array.from(checkboxes).map(cb => cb.value);
                
                // Execute sequentially to avoid overwhelming server or just Promise.all
                const promises = ids.map(id => {
                    return fetch(`${apiRoot}/helpers/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': nonce }
                    }).then(res => res.json());
                });

                Promise.all(promises)
                .then(results => {
                    // Success handling if needed
                })
                .catch(err => console.error(err))
                .finally(() => {
                    // Force reload with a slight delay to ensure server state is settled
                    // Using href assignment effectively forces a new GET request
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 500); 
                });
            };

            // Event Listeners for Batch Delete
            const updateBatchBtn = () => {
                const checked = document.querySelectorAll('.helper-checkbox:checked').length;
                const btn = document.getElementById('btn-batch-delete');
                if (checked > 0) {
                    btn.style.display = 'inline-block';
                    btn.innerText = `移除選取 (${checked})`;
                } else {
                    btn.style.display = 'none';
                }
            };

            document.getElementById('btn-batch-delete').addEventListener('click', deleteHelpersBatch);
            
            // Delegate event for checkboxes
            document.getElementById('helpers-tbody').addEventListener('change', (e) => {
                if (e.target.classList.contains('helper-checkbox')) {
                    updateBatchBtn();
                }
            });

            document.getElementById('select-all-helpers').addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('.helper-checkbox');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                updateBatchBtn();
            });

            // Close modal on outside click (Overlay)
            document.getElementById('add-helper-modal').addEventListener('click', (e) => {
                if (e.target.id === 'add-helper-modal') {
                    e.target.classList.remove('active');
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.getElementById('add-helper-modal').classList.remove('active');
                }
            });

            // Add Helper Form
            const form = document.getElementById('new-helper-form');
            const saveBtn = document.getElementById('btn-save-helper');

            form.addEventListener('click', (e) => {
                 if (e.target && e.target.id === 'btn-save-helper') {
                    // Collect data
                    const formData = new FormData(document.getElementById('new-helper-form'));
                    
                    // Simple Validation
                    const userEmail = formData.get('user_email');
                    if (!userEmail) { alert('請輸入 Email'); return; }

                    saveBtn.disabled = true;
                    saveBtn.innerText = '處理中...';
                    
                    const payload = {
                        user_id: userEmail, // Valid: HelperManager accepts email in this field
                        permissions: {
                            can_view_orders: formData.get('permissions[can_view_orders]') ? 1 : 0,
                            can_update_orders: formData.get('permissions[can_update_orders]') ? 1 : 0,
                            can_manage_products: formData.get('permissions[can_manage_products]') ? 1 : 0,
                            can_reply_customers: formData.get('permissions[can_reply_customers]') ? 1 : 0,
                        }
                    };

                    fetch(`${apiRoot}/helpers`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(res => res.json())
                    .then(response => {
                        saveBtn.disabled = false;
                        saveBtn.innerText = '確認新增';

                        if (response.success) {
                            // Reset form
                            document.getElementById('new-helper-form').reset();
                            // Close modal
                            document.getElementById('add-helper-modal').classList.remove('active');
                            
                            // FORCE RELOAD (Bypass Cache)
                            setTimeout(() => {
                                window.location.reload(true);
                            }, 500);
                        } else {
                            alert(response.message || '新增失敗，請確認該 Email 是否已註冊且未被指派。');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        saveBtn.disabled = false;
                        saveBtn.innerText = '確認新增';
                        alert('發生錯誤');
                    });
                 }
            });

            // Initial Load
            loadHelpers();

        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
