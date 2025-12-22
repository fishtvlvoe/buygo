<?php

namespace BuyGo\Core\Admin;

class AdminMenu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('script_loader_tag', [$this, 'add_module_type_attribute'], 10, 3);
    }

    public function add_module_type_attribute($tag, $handle, $src) {
        if ('buygo-core-app' !== $handle) {
            return $tag;
        }
        return '<script type="module" src="' . esc_url($src) . '"></script>';
    }

    public function register_menu() {
        // Main Menu Item
        add_menu_page(
            'BuyGo Core',
            'BuyGo Core',
            'manage_options',
            'buygo-core',
            [$this, 'render_app'],
            'dashicons-superhero',
            2
        );

        // 1. Dashboard (Rewrite defaults)
        add_submenu_page(
            'buygo-core',
            '儀表板',
            '儀表板',
            'manage_options',
            'buygo-core',
            [$this, 'render_app']
        );

        // 2. Products & Orders
        add_submenu_page(
            'buygo-core',
            '商品',
            '商品',
            'manage_options',
            'buygo-core-products-orders',
            [$this, 'render_app']
        );

        // 3. Reports
        add_submenu_page(
            'buygo-core',
            '報告',
            '報告',
            'manage_options',
            'buygo-core-reports',
            [$this, 'render_app']
        );

        // 4. Members Management (Consolidates List, Applications, Helpers)
        add_submenu_page(
            'buygo-core',
            '會員',
            '會員',
            'manage_options',
            'buygo-core-members', 
            [$this, 'render_app']
        );

        // 5. Message Center (Consolidates Logs, Rules, Compose)
        add_submenu_page(
            'buygo-core',
            '訊息',
            '訊息',
            'manage_options',
            'buygo-core-messages',
            [$this, 'render_app']
        );

        // 6. Settings (Global Settings)
        add_submenu_page(
            'buygo-core',
            '設定',
            '設定',
            'manage_options',
            'buygo-core-settings',
            [$this, 'render_app']
        );
    }

    public function render_app() {
        echo '<div id="buygo-admin-app"></div>';
    }

    public function enqueue_assets($hook) {
        // Only load assets on BuyGo pages to prevent conflicts with other admin pages
        if (strpos($hook, 'buygo-core') === false) {
            return;
        }

        // 修正路徑計算
        $plugin_root = dirname(__DIR__, 2); // 回到外掛根目錄
        $manifest_path = $plugin_root . '/assets/.vite/manifest.json';
        $plugin_url = plugin_dir_url($plugin_root . '/buygo-role-permission.php');

        // Determine Initial Route based on Page Slug
        $current_screen = get_current_screen();
        $route = '/';
        
        if ($current_screen && strpos($current_screen->id, 'settings') !== false) {
            $route = '/settings';
        } elseif ($current_screen && strpos($current_screen->id, 'products-orders') !== false) {
            $route = '/products-orders';
        } elseif ($current_screen && strpos($current_screen->id, 'reports') !== false) {
            $route = '/reports';
        } elseif ($current_screen && strpos($current_screen->id, 'members') !== false) {
            $route = '/members';
        } elseif ($current_screen && strpos($current_screen->id, 'messages') !== false) {
            $route = '/messages';
        }

        // 除錯資訊（可以在開發完成後移除）
        // error_log('BuyGo Debug: manifest_path = ' . $manifest_path);
        // error_log('BuyGo Debug: file_exists = ' . (file_exists($manifest_path) ? 'true' : 'false'));

        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            
            // Initial Entry
            $entry = $manifest['src/main.js'] ?? null;
            
            if ($entry) {
                // Enqueue Script
                $script_url = $plugin_url . 'assets/' . $entry['file'];
                wp_enqueue_script('buygo-core-app', $script_url, [], null, true);

                // Enqueue CSS
                if (!empty($entry['css'])) {
                    foreach ($entry['css'] as $css_file) {
                        wp_enqueue_style('buygo-core-style-' . md5($css_file), $plugin_url . 'assets/' . $css_file);
                    }
                }
            }
        } else {
            // Fallback for dev mode or missing build
            echo '<div class="notice notice-error"><p>BuyGo Core: Assets not found. Please run "npm run build".</p></div>';
        }

        // Pass Data to Frontend
        wp_localize_script('buygo-core-app', 'buygo_admin', [
            'api_url' => get_rest_url(null, 'buygo/v1'), // Namespace must match BaseController
            'rest_url' => get_rest_url(null, 'buygo/v1'), // Alias for api_url
            'api_root' => get_rest_url(null, ''), // API Root for flexible construction
            'home_url' => home_url('/'), // Home URL for full webhook URLs
            'nonce' => wp_create_nonce('wp_rest'),
            'initial_route' => $route, // 傳遞路由資訊
        ]);
    }
}
