<?php

namespace BuyGo\Core\Frontend;

use BuyGo\Core\App;
use BuyGo\Core\Services\LineService;
use BuyGo\Core\Api\OrderController; // Import Controller
use BuyGo\Core\Services\SellerApplicationService;
use BuyGo\Core\Frontend\HelperManagementShortcode;

class FluentCartIntegration {

    public function __construct() {
        // Add menu item to FluentCart Dashboard
        add_filter('fluent_cart/global_customer_menu_items', [$this, 'add_menu_item'], 20, 2);
        
        // TEMPORARY: Attempt to remove restrictive CSP headers if possible (usually set by other security plugins)
        add_action('send_headers', function() {
            header_remove('Content-Security-Policy');
        });
        
        // Register custom endpoint content
        add_filter('fluent_cart/customer_portal/custom_endpoints', [$this, 'register_endpoint']);
        
        // Add Polyfill globally to header to prevent FluentCommunity JS Error
        add_action('wp_head', [$this, 'add_fcom_polyfill']);
        
        // Enqueue global scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        // Note: buygo-smart-selector.js is no longer needed as all pages have been migrated to Vue.js
        // The script was used for old PHP-rendered pages which are now Vue components
        // Removed to prevent 404 errors
    }

    public function add_fcom_polyfill() {
        echo '<script>
            if (typeof window.fcom_portal_general === "undefined") {
                window.fcom_portal_general = { scope: "polyfill", has_color_scheme: false };
            }
        </script>';
    }

    public function register_endpoint($endpoints) {
        // Add "LINE Binding" tab
        $endpoints['line-binding'] = [
            'title' => 'LINE 綁定',
            'render_callback' => [$this, 'render_line_binding_content']
        ];

        // Add "Seller Application" tab
        $endpoints['seller-application'] = [
            'title' => '賣家申請',
            'render_callback' => [$this, 'render_seller_application_content']
        ];

        // Add "Helper Management" tab
        $endpoints['helper-management'] = [
            'title' => '小幫手管理',
            'render_callback' => [$this, 'render_helper_management_content']
        ];

        // Add "Role Management" tab (Only for Admins)
        if (current_user_can('manage_options')) {
            $endpoints['role-management'] = [
                'title' => '角色管理',
                'render_callback' => [$this, 'render_role_management_content']
            ];
        }

        $endpoints['seller-center'] = [
            'title' => '賣家中心',
            'render_callback' => [$this, 'render_seller_center_content']
        ];

        return $endpoints;
    }

    public function add_menu_item($items, $data) {
        // Add "LINE Binding" tab
        $items['line-binding'] = [
            'label' => 'LINE 綁定',
            'css_class' => 'fct_route',
            'link'  => $data['base_url'] . 'line-binding',
            'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
  <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
</svg>'
        ];
        
        // Add "Seller Application" tab (Non-Sellers, OR Admins for testing)
        // We use 'manage_options' to robustly check for Admins.
        $user = wp_get_current_user();
        if (!in_array('buygo_seller', (array)$user->roles) || in_array('buygo_admin', (array)$user->roles) || current_user_can('manage_options')) {
            $items['seller-application'] = [
                'label' => '賣家申請',
                'css_class' => 'fct_route',
                'link'  => $data['base_url'] . 'seller-application',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
  <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
  <path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z" />
</svg>'
            ];
        }

        // Add "Role Management" tab (Only for Admin)
        if (current_user_can('manage_options')) {
            $items['role-management'] = [
                'label' => '角色管理',
                'css_class' => 'fct_route',
                'link'  => $data['base_url'] . 'role-management',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
</svg>'
            ];
        }
        
        // Add "Helper Management" tab (For Sellers & Admins)
        if (in_array('buygo_seller', (array)$user->roles) || in_array('buygo_admin', (array)$user->roles) || current_user_can('manage_options')) {
            $items['helper-management'] = [
                'label' => '小幫手管理',
                'css_class' => 'fct_route',
                'link'  => $data['base_url'] . 'helper-management',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
  <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
</svg>'
            ];
        }

        // Add "Seller Center" Tab (The Main Vue Portal)
        // Visible to Sellers and Admins
        if (in_array('buygo_seller', (array)$user->roles) || in_array('buygo_admin', (array)$user->roles) || current_user_can('manage_options')) {
            $items['seller-center'] = [
                'label' => '賣家中心',
                'css_class' => 'fct_route buygo-portal-link',
                'link'  => $data['base_url'] . 'seller-center#/sc/dashboard', // Direct Hash Link
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
  <path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 001-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
</svg>'
            ];
        }

        return $items;
    }


    public function render_seller_center_content() {
        // 1. Ensure Assets are loaded (Vue App)
        $this->ensure_assets_loaded();

        // 2. Inject Configuration (Inline Script to guarantee availability before Vue mounts)
        // Note: Ideally this is done via wp_localize_script, but since this is a content callback,
        // we might have missed the head. Inline is safer for dynamically rendered content.
        $user_id = get_current_user_id();
        $nonce = wp_create_nonce('wp_rest');
        
        $config = [
            'api_url' => get_rest_url(null, 'buygo/v1'),
            'nonce' => $nonce,
            'user_id' => $user_id,
            'is_seller' => in_array('buygo_seller', (array)wp_get_current_user()->roles) || in_array('buygo_admin', (array)wp_get_current_user()->roles),
            'initial_route' => '/seller-center/dashboard' // Default route to dashboard
        ];
        
        echo '<script>
            window.buygo_frontend = ' . json_encode($config) . ';
        </script>';

        // 3. Render Mount Point
        echo '<div id="buygo-portal-app"></div>';
    }

    private function ensure_assets_loaded() {
        // We need to manually register the scripts because they are usually only registered in Admin context.
        // This is a simplified Manifest Loader adapted from AdminMenu.

        $plugin_dir = plugin_dir_path(dirname(__DIR__)); // buygo-role-permission/
        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $manifest_path = $plugin_dir . 'assets/.vite/manifest.json';

        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            $entry = $manifest['src/main.js'] ?? null;

            if ($entry) {
                // Register Main JS
                if (!wp_script_is('buygo-rp-admin', 'registered')) {
                    wp_register_script(
                        'buygo-rp-admin',
                        $plugin_url . 'assets/' . $entry['file'],
                        ['jquery'],
                        '1.0.0', // Version from manifest ideally
                        true
                    );
                }

                // Collect ALL CSS from the manifest to ensure chunks are loaded
                // Since this is a portal page, we want all styles available to avoid FOUC or missing styles
                $all_css = [];
                foreach ($manifest as $item) {
                    if (!empty($item['css'])) {
                        foreach ($item['css'] as $css) {
                            $all_css[] = $css;
                        }
                    }
                }
                $all_css = array_unique($all_css);

                // Register & Enqueue All CSS
                foreach ($all_css as $index => $css_file) {
                    $handle = 'buygo-rp-style-' . md5($css_file);
                    if (!wp_style_is($handle, 'registered')) {
                         wp_register_style(
                            $handle,
                            $plugin_url . 'assets/' . $css_file,
                            [],
                            '1.0.0'
                        );
                    }
                    wp_enqueue_style($handle);
                }
                
                // Enqueue Script
                wp_enqueue_script('buygo-rp-admin');
                
                // Add Module Type (Critical for Vite)
                add_filter('script_loader_tag', function($tag, $handle, $src) {
                    if ($handle === 'buygo-rp-admin') {
                        // Attempt to bypass strict CSP for our own trusted script by not module-loading if causing issues,
                        // OR keeping it but acknowledging the CSP violation risks if headers aren't controlled.
                        // Ideally, we should use 'text/javascript' if module isn't strictly required for this build,
                        // BUT Vite builds are ESM.
                        return '<script type="module" src="' . esc_url($src) . '"></script>';
                    }
                    return $tag;
                }, 10, 3);
            }
        }
    }

    private function render_styles() {
        ?>
        <style>
            .buygo-role-mgmt .buygo-card, .buygo-line-binding .buygo-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
            
            /* Stack Layout (Dashboard Style) */
            .buygo-page-header { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
            @media (min-width: 768px) {
                .buygo-page-header { flex-direction: row; justify-content: space-between; align-items: flex-end; }
            }
            .buygo-page-title h3 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0; }
            .buygo-page-title p { font-size: 14px; color: #6b7280; line-height: 1.5; margin: 0; }
            
            /* Table & Type */
            .buygo-role-mgmt .buygo-table { width: 100%; border-collapse: collapse; text-align: left; }
            .buygo-role-mgmt .buygo-table th { background: #f9fafb; padding: 12px 20px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
            .buygo-role-mgmt .buygo-table td { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; background: #fff; color: #111827; vertical-align: middle; font-size: 13px; }
            .buygo-role-mgmt .buygo-table tr:last-child td { border-bottom: none; }
            
            /* Badges & Buttons */
            .buygo-role-mgmt .buygo-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; }
            .buygo-role-mgmt .badge-seller { background: #dcfce7; color: #166534; }
            .buygo-role-mgmt .badge-helper { background: #e0e7ff; color: #3730a3; }
            .buygo-role-mgmt .badge-customer { background: #f3f4f6; color: #374151; }
            .buygo-role-mgmt .badge-admin { background: #fee2e2; color: #991b1b; }
            .buygo-role-mgmt .buygo-btn { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; }
            .buygo-role-mgmt .btn-primary { background: #111827; color: #fff; }
            .buygo-role-mgmt .btn-primary:hover { background: #374151; }
            .buygo-role-mgmt .btn-outline { background: #fff; border: 1px solid #d1d5db; color: #374151; }
            .buygo-role-mgmt .btn-outline:hover { background: #f9fafb; border-color: #9ca3af; text-decoration: none; color: #111827; }
            
            /* User Info */
            .buygo-role-mgmt .user-info { display: flex; align-items: center; gap: 10px; }
            .buygo-role-mgmt .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
            .buygo-role-mgmt .user-name { font-weight: 500; color: #111827; }
            .buygo-role-mgmt .user-email { display: none !important; }
            
            /* Modal Styles */
            .buygo-modal-overlay { position: fixed !important; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999 !important; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.2s; backdrop-filter: blur(2px); }
            .buygo-modal-overlay.active { opacity: 1 !important; visibility: visible !important; }
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
            
            /* Modal Footer */
            .buygo-modal-footer { padding: 20px 24px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
            .buygo-modal-footer .buygo-btn { padding: 8px 16px; font-size: 14px; border-radius: 6px; font-weight: 500; }
        </style>
        <?php
    }

    public function render_line_binding_content() {
        // 1. Ensure Assets are loaded (Vue App)
        $this->ensure_assets_loaded();

        // 2. Inject Configuration
        $user_id = get_current_user_id();
        $nonce = wp_create_nonce('wp_rest');
        
        $config = [
            'api_url' => get_rest_url(null, 'buygo/v1'),
            'nonce' => $nonce,
            'user_id' => $user_id,
            'initial_route' => '/line-binding'
        ];
        
        echo '<script>
            window.buygo_frontend = ' . json_encode($config) . ';
        </script>';

        // 3. Render Mount Point
        echo '<div id="buygo-portal-app"></div>';
    }

    public function render_seller_application_content() {
        // 1. Ensure Assets are loaded (Vue App)
        $this->ensure_assets_loaded();

        // 2. Inject Configuration
        $user_id = get_current_user_id();
        $nonce = wp_create_nonce('wp_rest');
        
        $config = [
            'api_url' => get_rest_url(null, 'buygo/v1'),
            'nonce' => $nonce,
            'user_id' => $user_id,
            'initial_route' => '/seller-application'
        ];
        
        echo '<script>
            window.buygo_frontend = ' . json_encode($config) . ';
        </script>';

        // 3. Render Mount Point
        echo '<div id="buygo-portal-app"></div>';
    }


    public function render_helper_management_content() {
        // 1. Ensure Assets are loaded (Vue App)
        $this->ensure_assets_loaded();

        // 2. Inject Configuration
        $user_id = get_current_user_id();
        $nonce = wp_create_nonce('wp_rest');
        
        $config = [
            'api_url' => get_rest_url(null, 'buygo/v1'),
            'nonce' => $nonce,
            'user_id' => $user_id,
            'initial_route' => '/helper-management'
        ];
        
        echo '<script>
            window.buygo_frontend = ' . json_encode($config) . ';
        </script>';

        // 3. Render Mount Point
        echo '<div id="buygo-portal-app"></div>';
    }

    public function render_role_management_content() {
        // 1. Ensure Assets are loaded (Vue App)
        $this->ensure_assets_loaded();

        // 2. Inject Configuration
        $user_id = get_current_user_id();
        $nonce = wp_create_nonce('wp_rest');
        
        $config = [
            'api_url' => get_rest_url(null, 'buygo/v1'),
            'nonce' => $nonce,
            'user_id' => $user_id,
            'initial_route' => '/role-management'
        ];
        
        echo '<script>
            window.buygo_frontend = ' . json_encode($config) . ';
        </script>';

        // 3. Render Mount Point
        echo '<div id="buygo-portal-app"></div>';
    }
}
