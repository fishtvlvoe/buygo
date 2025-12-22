<?php

namespace BuyGo\Core\Frontend;

class LineBindingShortcode {

    public function __construct() {
        add_shortcode('buygo_line_bind', [$this, 'render']);
    }

    public function render($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to bind your LINE account.</p>';
        }

        ob_start();
        ?>
        <div id="buygo-line-binding-app" class="buygo-binding-container" style="max-width: 400px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h3 style="margin-top:0;">LINE Account Binding</h3>
            <div id="buygo-binding-status">Loading...</div>
            
            <div id="buygo-binding-actions" style="margin-top: 15px; display:none;">
                <button id="buygo-btn-generate" style="background: #00B900; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                    Generage Binding Code
                </button>
            </div>

            <div id="buygo-binding-code-display" style="margin-top: 15px; display:none; text-align: center;">
                <p>Your Binding Code:</p>
                <div id="buygo-code-value" style="font-size: 24px; font-weight: bold; letter-spacing: 2px; background: #f5f5f5; padding: 10px;"></div>
                <p style="font-size: 12px; color: #666;">Valid for 10 minutes. Please enter this code in the LINE Official Account.</p>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const apiBase = '/wp-json/buygo/v1';
            const statusEl = document.getElementById('buygo-binding-status');
            const actionsEl = document.getElementById('buygo-binding-actions');
            const generateBtn = document.getElementById('buygo-btn-generate');
            const codeDisplayEl = document.getElementById('buygo-binding-code-display');
            const codeValueEl = document.getElementById('buygo-code-value');

            // Check Status
            fetch(apiBase + '/line/bind/status', {
                headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.is_bound) {
                    statusEl.innerHTML = '<span style="color: green;">✅ Linked (UID: ' + data.line_uid.substring(0, 4) + '***)</span>';
                } else {
                    statusEl.innerHTML = '<span style="color: orange;">⚠️ Not Linked</span>';
                    actionsEl.style.display = 'block';
                }
            })
            .catch(err => {
                statusEl.innerText = 'Error checking status';
                console.error(err);
            });

            // Generate Code
            generateBtn.addEventListener('click', function() {
                generateBtn.disabled = true;
                generateBtn.innerText = 'Generating...';

                fetch(apiBase + '/line/bind/generate', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.code) {
                        actionsEl.style.display = 'none';
                        codeDisplayEl.style.display = 'block';
                        codeValueEl.innerText = data.code;
                    } else {
                        alert(data.message || 'Error');
                        generateBtn.disabled = false;
                        generateBtn.innerText = 'Generate Binding Code';
                    }
                })
                .catch(err => {
                    alert('System Error');
                    generateBtn.disabled = false;
                    generateBtn.innerText = 'Generate Binding Code';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
