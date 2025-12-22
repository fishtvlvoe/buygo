<?php
/**
 * BuyGo Frontend Portal Module Bootstrap
 *
 * [AI Context]
 * - This module provides a frontend portal integrated with FluentCommunity
 * - Similar to fca-events, it integrates seamlessly into FluentCommunity portal
 * - Only visible to sellers, WP admins, BuyGo admins, and helpers
 *
 * [Constraints]
 * - Must load after BuyGo Core (BuyGo\Core\App) so BuyGo_Core facade/services are available
 * - Must check for FluentCommunity and FluentCart dependencies
 * - Must not call register_activation_hook / register_deactivation_hook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define module constants
if ( ! defined( 'BUYGO_FRONTEND_PORTAL_VERSION' ) ) {
	define( 'BUYGO_FRONTEND_PORTAL_VERSION', '0.0.1' );
}

if ( ! defined( 'BUYGO_FRONTEND_PORTAL_PATH' ) ) {
	define( 'BUYGO_FRONTEND_PORTAL_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BUYGO_FRONTEND_PORTAL_URL' ) ) {
	define( 'BUYGO_FRONTEND_PORTAL_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Boot the Frontend Portal module
 *
 * [AI Context]
 * - We boot on plugins_loaded AFTER core (core uses priority 0)
 * - If critical dependencies are missing, we show an admin notice and skip boot
 */
function buygo_frontend_portal_module_boot() {
	// BuyGo Core must be available
	if ( ! class_exists( '\BuyGo\Core\App' ) ) {
		return;
	}

	// Check for required dependencies
	$missing = [];

	if ( ! class_exists( 'FluentCart\App\App' ) ) {
		$missing[] = 'FluentCart';
	}

	// Check for FluentCommunity
	$fluent_community_active = (
		defined( 'FLUENT_COMMUNITY_PLUGIN_VERSION' ) ||
		defined( 'FLUENT_COMMUNITY_PLUGIN_URL' ) ||
		class_exists( 'FluentCommunity\App\Services\Helper' ) ||
		class_exists( 'FluentCommunity\App\Hooks\Handlers\PortalHandler' )
	);

	if ( ! $fluent_community_active ) {
		$missing[] = 'FluentCommunity';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function() use ( $missing ) {
			$list = esc_html( implode( ', ', $missing ) );
			echo '<div class="notice notice-warning"><p>';
			echo 'BuyGo：Frontend Portal 模組需要啟用 ' . $list . ' 才能完整運作。';
			echo '</p></div>';
		} );

		// Still allow the core plugin to work; skip Frontend Portal
		return;
	}

	// Load migrations
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_01_01_create_merged_orders_table.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_01_01_create_report_cache_table.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_01_02_fix_merged_orders_timestamps.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_12_17_create_suppliers_table.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_12_17_create_supplier_settlements_table.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'database/migrations/2024_12_17_add_supplier_fields.php';

	// Run migrations on activation
	buygo_frontend_portal_create_merged_orders_table();
	buygo_frontend_portal_create_report_cache_table();
	buygo_frontend_portal_fix_merged_orders_timestamps();
	buygo_frontend_portal_create_suppliers_table();
	buygo_frontend_portal_create_supplier_settlements_table();
	buygo_frontend_portal_add_supplier_fields();

	// Load module files
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Models/MergedOrder.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Models/ReportCache.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Models/Supplier.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Models/SupplierSettlement.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/ReportCacheService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/DashboardService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/ProductsService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/OrdersService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/MergeOrderService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/MembersService.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Services/SuppliersService.php';

	// Load API Controllers
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/BaseController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/DashboardController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/ProductsController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/OrdersController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/ShippingController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/MembersController.php';
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Api/SuppliersController.php';

	// Register API routes
	add_action( 'rest_api_init', 'buygo_frontend_portal_register_api_routes' );

	// Register cache scheduler
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Hooks/CacheScheduler.php';
	BuyGo\Modules\FrontendPortal\App\Hooks\CacheScheduler::register();

	// Register order cost snapshot hook
	require_once BUYGO_FRONTEND_PORTAL_PATH . 'app/Hooks/OrderCostSnapshot.php';
	BuyGo\Modules\FrontendPortal\App\Hooks\OrderCostSnapshot::register();

	// Register FluentCommunity integration
	add_filter( 'fluent_community/app_route_paths', 'buygo_frontend_portal_register_routes' );
	add_filter( 'fluent_community/menu_groups_for_user', 'buygo_frontend_portal_register_menu' );
	add_action( 'fluent_community/portal_head', 'buygo_frontend_portal_inject_assets' );
	add_action( 'fluent_community/portal_head', 'buygo_frontend_portal_inject_components' );
}

/**
 * Register route paths with FluentCommunity
 */
function buygo_frontend_portal_register_routes( $paths ) {
	$paths[] = 'buygo-portal';
	$paths[] = 'buygo-portal/dashboard';
	$paths[] = 'buygo-portal/products';
	$paths[] = 'buygo-portal/orders';
	$paths[] = 'buygo-portal/shipping';
	$paths[] = 'buygo-portal/members';
	$paths[] = 'buygo-portal/suppliers';
	return $paths;
}

/**
 * Register menu items in FluentCommunity sidebar
 */
function buygo_frontend_portal_register_menu( $menu_groups ) {
	// Check role: only sellers, WP admins, BuyGo admins, and helpers can see
	if ( ! buygo_is_seller() && ! buygo_is_helper() && ! buygo_is_admin() && ! current_user_can( 'manage_options' ) ) {
		return $menu_groups;
	}

	// Generate URL helper
	$generate_url = function( $relative_path ) {
		if ( class_exists( '\FluentCommunity\App\Services\Helper' ) ) {
			return \FluentCommunity\App\Services\Helper::baseUrl( $relative_path );
		} else {
			$portal_slug = apply_filters( 'fluent_community/portal_slug', 'portal' );
			return home_url( "/{$portal_slug}{$relative_path}" );
		}
	};

	$menu_groups['buygo_portal_group'] = [
		'title' => 'BuyGo 管理中心',
		'id' => 'buygo_portal_group',
		'priority' => 10,
		'children' => [
			'dashboard' => [
				'id' => 'buygo_dashboard',
				'title' => '儀表板',
				'permalink' => $generate_url( '/buygo-portal/dashboard' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_dashboard' ],
			],
			'products' => [
				'id' => 'buygo_products',
				'title' => '商品管理',
				'permalink' => $generate_url( '/buygo-portal/products' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_products' ],
			],
			'orders' => [
				'id' => 'buygo_orders',
				'title' => '訂單管理',
				'permalink' => $generate_url( '/buygo-portal/orders' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_orders' ],
			],
			'shipping' => [
				'id' => 'buygo_shipping',
				'title' => '出貨管理',
				'permalink' => $generate_url( '/buygo-portal/shipping' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_shipping' ],
			],
			'members' => [
				'id' => 'buygo_members',
				'title' => '會員管理',
				'permalink' => $generate_url( '/buygo-portal/members' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_members' ],
			],
			'suppliers' => [
				'id' => 'buygo_suppliers',
				'title' => '供應商結算',
				'permalink' => $generate_url( '/buygo-portal/suppliers' ),
				'link_classes' => 'route_url',
				'link_data' => [ 'data-route' => 'buygo_suppliers' ],
			],
		]
	];

	return $menu_groups;
}

/**
 * Register API routes
 */
function buygo_frontend_portal_register_api_routes() {
	( new \BuyGo\Modules\FrontendPortal\App\Api\DashboardController() )->register_routes();
	( new \BuyGo\Modules\FrontendPortal\App\Api\ProductsController() )->register_routes();
	( new \BuyGo\Modules\FrontendPortal\App\Api\OrdersController() )->register_routes();
	( new \BuyGo\Modules\FrontendPortal\App\Api\ShippingController() )->register_routes();
	( new \BuyGo\Modules\FrontendPortal\App\Api\MembersController() )->register_routes();
	( new \BuyGo\Modules\FrontendPortal\App\Api\SuppliersController() )->register_routes();
}

/**
 * Inject CSS and JavaScript into FluentCommunity portal head
 */
function buygo_frontend_portal_inject_assets() {
	// Check role: only sellers, WP admins, BuyGo admins, and helpers can see
	if ( ! buygo_is_seller() && ! buygo_is_helper() && ! buygo_is_admin() && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Load Tailwind CSS via CDN (FluentCommunity portal doesn't include Tailwind by default)
	// Use Play CDN for Tailwind CSS
	?>
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
	// Configure Tailwind to work with FluentCommunity's existing styles
	if (typeof tailwind !== 'undefined') {
		tailwind.config = {
			important: true, // Make all utilities important to override FluentCommunity styles
			corePlugins: {
				preflight: false, // Disable Tailwind's base reset to avoid conflicts with FluentCommunity
			},
			content: [], // We're using inline classes, so no content scanning needed
		}
	}
	</script>
	<style>
	/* Force white background for BuyGo Portal to prevent dark mode */
	.buygo-dashboard-container,
	.buygo-products-container,
	.buygo-orders-container,
	.buygo-shipping-container,
	.buygo-members-container {
		background-color: #f9fafb !important; /* bg-gray-50 */
	}
	.buygo-dashboard-container *,
	.buygo-products-container *,
	.buygo-orders-container *,
	.buygo-shipping-container *,
	.buygo-members-container * {
		color-scheme: light !important;
	}
	/* Ensure all cards and containers have white background */
	.bg-white {
		background-color: #ffffff !important;
	}
	</style>
	<?php

	// Hide "+" button for "BuyGo 管理中心" group for non-WP admins
	// Only WordPress administrators should see the "+" button
	if ( ! current_user_can( 'manage_options' ) ) {
		?>
		<style>
		/* Hide the "+" button for BuyGo 管理中心 group for non-WP admins */
		.fcom_space_group_header[data-group_id="buygo_portal_group"] .fcom_space_create,
		.fcom_space_group_header:has(h4[data-group_id="buygo_portal_group"]) .fcom_space_create,
		.fcom_communities_menu:has(.fcom_space_group_header h4[data-group_id="buygo_portal_group"]) .fcom_space_create {
			display: none !important;
		}
		
		/* Alternative selector: find by group title text */
		.fcom_space_group_header:has(span:contains("BuyGo 管理中心")) .fcom_space_create {
			display: none !important;
		}
		</style>
		<script>
		// Use JavaScript as fallback for browsers that don't support :has() selector
		(function() {
			function hideBuyGoPlusButton() {
				// Find all menu group headers
				const groupHeaders = document.querySelectorAll('.fcom_space_group_header');
				groupHeaders.forEach(function(header) {
					const titleElement = header.querySelector('h4.space_section_title span');
					if (titleElement && titleElement.textContent.trim() === 'BuyGo 管理中心') {
						const createButton = header.querySelector('.fcom_space_create');
						if (createButton) {
							createButton.style.display = 'none';
						}
					}
				});
			}
			
			// Run on page load
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', hideBuyGoPlusButton);
			} else {
				hideBuyGoPlusButton();
			}
			
			// Also run after a short delay in case content loads dynamically
			setTimeout(hideBuyGoPlusButton, 500);
		})();
		</script>
		<?php
	}
}

/**
 * Inject Vue components into FluentCommunity portal
 */
function buygo_frontend_portal_inject_components() {
	// Check role: only sellers, WP admins, BuyGo admins, and helpers can see
	if ( ! buygo_is_seller() && ! buygo_is_helper() && ! buygo_is_admin() && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Inject JavaScript data
	?>
	<script>
	window.buygoFrontendPortalData = {
		apiUrl: '<?php echo esc_url( rest_url( 'buygo/v1/portal' ) ); ?>',
		nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
		currentUserId: <?php echo get_current_user_id(); ?>,
	};
	</script>
	<?php

	// Inject Vue components
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-dashboard.php';
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-products.php';
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-orders.php';
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-shipping.php';
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-members.php';
	include BUYGO_FRONTEND_PORTAL_PATH . 'resources/views/components/buygo-suppliers.php';

	// Register Vue Router routes
	?>
	<script>
	document.addEventListener( "fluentCommunityUtilReady", function () {
		if ( ! window.FluentCommunityUtil || ! window.FluentCommunityUtil.hooks ) {
			return;
		}

		window.FluentCommunityUtil.hooks.addFilter( "fluent_com_portal_routes", "buygo_portal_routes", function ( routes ) {
			if ( ! Array.isArray( routes ) ) {
				return routes;
			}

			// Dashboard route
			routes.push( {
				path: "/buygo-portal/dashboard",
				name: "buygo_dashboard",
				component: BuyGoDashboardComponent,
				meta: {
					active: "buygo_dashboard",
					parent: "buygo_portal_group"
				}
			} );

			// Products route
			routes.push( {
				path: "/buygo-portal/products",
				name: "buygo_products",
				component: BuyGoProductsComponent,
				meta: {
					active: "buygo_products",
					parent: "buygo_portal_group"
				}
			} );

			// Orders route
			routes.push( {
				path: "/buygo-portal/orders",
				name: "buygo_orders",
				component: BuyGoOrdersComponent,
				meta: {
					active: "buygo_orders",
					parent: "buygo_portal_group"
				}
			} );

			// Shipping route
			routes.push( {
				path: "/buygo-portal/shipping",
				name: "buygo_shipping",
				component: BuyGoShippingComponent,
				meta: {
					active: "buygo_shipping",
					parent: "buygo_portal_group"
				}
			} );

			// Members route
			routes.push( {
				path: "/buygo-portal/members",
				name: "buygo_members",
				component: BuyGoMembersComponent,
				meta: {
					active: "buygo_members",
					parent: "buygo_portal_group"
				}
			} );

			// Suppliers route
			routes.push( {
				path: "/buygo-portal/suppliers",
				name: "buygo_suppliers",
				component: BuyGoSuppliersComponent,
				meta: {
					active: "buygo_suppliers",
					parent: "buygo_portal_group"
				}
			} );

			return routes;
		} );
	} );
	</script>
	<?php
}

// Boot the module
add_action( 'plugins_loaded', 'buygo_frontend_portal_module_boot', 1 );
