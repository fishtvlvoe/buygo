<?php
/**
 * BuyGo Dashboard Vue Component
 *
 * [AI Context]
 * - Dashboard component for displaying statistics
 * - Uses Vue 3 Options API
 * - Responsive design with Tailwind CSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoDashboardComponent = {
	template: `
		<div class="el-scrollbar buygo-dashboard-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							儀表板
						</h1>
					</div>
					
					<!-- First-Level Navigation Menu -->
					<div class="buygo-nav-menu-container px-4 md:px-6 pt-3 pb-2">
						<nav class="flex space-x-2 flex-nowrap overflow-x-auto" role="navigation" aria-label="主要選單">
							<!-- Main Menu Items -->
							<button
								v-for="(item, index) in visibleMenuItems"
								:key="item.path"
								@click="navigateTo(item.path)"
								:class="['px-3 py-2 rounded-md text-sm font-bold transition-colors border-0 bg-transparent flex-shrink-0 whitespace-nowrap', isActive(item.path) ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50']"
							>
								<span class="hidden md:inline">{{ item.labelDesktop }}</span>
								<span class="md:hidden">{{ item.labelMobile }}</span>
							</button>

							<!-- More Menu Button -->
							<div v-if="showMoreMenu" class="relative flex-shrink-0">
								<button
									@click="toggleMoreMenu"
									:class="['px-3 py-2 rounded-md text-sm font-bold transition-colors flex items-center border-0 bg-transparent whitespace-nowrap', showMoreDropdown ? 'bg-gray-100 text-gray-900' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50']"
								>
									更多
									<svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
									</svg>
								</button>
							</div>
						</nav>
					</div>

					<!-- More Menu Modal -->
					<div v-if="showMoreDropdown" class="fixed inset-0 z-[9999] overflow-y-auto" @click="closeMoreMenu">
						<div class="flex min-h-full items-center justify-center p-4">
							<div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all w-full max-w-xs" @click.stop>
								<!-- Header -->
								<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
									<h3 class="text-base font-semibold text-gray-900">更多選單</h3>
									<button
										@click="closeMoreMenu"
										class="text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded-md p-1"
									>
										<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
										</svg>
									</button>
								</div>

								<!-- Menu Items -->
								<div class="px-4 py-2">
									<button
										v-for="item in hiddenMenuItems"
										:key="item.path"
										@click="navigateTo(item.path)"
										:class="['w-full text-left px-4 py-3 text-sm transition-colors border-0 bg-transparent rounded-md', isActive(item.path) ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50']"
									>
										<span class="hidden md:inline">{{ item.labelDesktop }}</span>
										<span class="md:hidden">{{ item.labelMobile }}</span>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Backdrop -->
					<div v-if="showMoreDropdown" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-[9998]" @click="closeMoreMenu"></div>

					<div class="buygo-dashboard-container p-4 md:p-6">
						<!-- Loading State -->
						<div v-if="loading" class="flex items-center justify-center py-12">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
							<span class="ml-3 text-gray-600">載入中...</span>
						</div>

						<!-- Error State -->
						<div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
							<p class="text-red-800">{{ error }}</p>
						</div>

						<!-- Dashboard Content -->
						<div v-else class="space-y-6">
							<!-- Statistics Cards -->
							<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
								<!-- Today Orders Card -->
								<div class="bg-white rounded-lg shadow-sm p-6">
									<div class="flex items-center justify-between">
										<div>
											<p class="text-sm font-medium text-gray-500">今日訂單</p>
											<p class="text-3xl font-bold text-gray-900 mt-2">{{ dashboardData.today_orders || 0 }}</p>
										</div>
										<div class="text-3xl">📦</div>
									</div>
								</div>

								<!-- Today Revenue Card -->
								<div class="bg-white rounded-lg shadow-sm p-6">
									<div class="flex items-center justify-between">
										<div>
											<p class="text-sm font-medium text-gray-500">今日營收</p>
											<p class="text-3xl font-bold text-gray-900 mt-2">NT$ {{ formatNumber(dashboardData.today_revenue || 0) }}</p>
										</div>
										<div class="text-3xl">💰</div>
									</div>
								</div>

								<!-- Pending Orders Card -->
								<div class="bg-white rounded-lg shadow-sm p-6">
									<div class="flex items-center justify-between">
										<div>
											<p class="text-sm font-medium text-gray-500">待處理訂單</p>
											<p class="text-3xl font-bold text-gray-900 mt-2">{{ dashboardData.pending_orders || 0 }}</p>
										</div>
										<div class="text-3xl">⏳</div>
									</div>
								</div>

								<!-- Listed Products Card -->
								<div class="bg-white rounded-lg shadow-sm p-6">
									<div class="flex items-center justify-between">
										<div>
											<p class="text-sm font-medium text-gray-500">上架商品</p>
											<p class="text-3xl font-bold text-gray-900 mt-2">{{ dashboardData.listed_products || 0 }}</p>
										</div>
										<div class="text-3xl">🛍️</div>
									</div>
								</div>
							</div>

							<!-- Additional Content Placeholder -->
							<div class="bg-white rounded-lg shadow-sm p-6">
								<h2 class="text-base font-semibold text-gray-900 mb-4">近期活動</h2>
								<p class="text-sm text-gray-500">暫無活動紀錄</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	`,
	data() {
		// 動態計算 basePath：從目前網址找出 /buygo-portal 的前綴
		const pathname = window.location.pathname;
		const portalIndex = pathname.indexOf('/buygo-portal');
		const basePath = portalIndex !== -1 
			? pathname.substring(0, portalIndex) + '/buygo-portal'
			: '/buygo-portal';
		
		return {
			loading: true,
			error: null,
			dashboardData: {
				today_orders: 0,
				today_revenue: 0,
				pending_orders: 0,
				listed_products: 0,
			},
			basePath: basePath,
			currentPath: window.location.pathname,
			windowWidth: window.innerWidth,
			showMoreDropdown: false,
			// menuItems 只存相對路徑，完整路徑由 getFullPath() 組合
			menuItems: [
				{ path: 'dashboard', labelDesktop: '儀表板', labelMobile: '儀表板' },
				{ path: 'products', labelDesktop: '商品管理', labelMobile: '商品' },
				{ path: 'orders', labelDesktop: '訂單管理', labelMobile: '訂單' },
				{ path: 'shipping', labelDesktop: '出貨管理', labelMobile: '出貨' },
				{ path: 'members', labelDesktop: '會員管理', labelMobile: '會員' },
				{ path: 'suppliers', labelDesktop: '供應商結算', labelMobile: '供應商' },
			],
		};
	},
	computed: {
		maxVisibleItems() {
			return 3;
		},
		visibleMenuItems() {
			return this.menuItems.slice(0, this.maxVisibleItems);
		},
		hiddenMenuItems() {
			return this.menuItems.slice(this.maxVisibleItems);
		},
		showMoreMenu() {
			return this.menuItems.length > this.maxVisibleItems;
		},
	},
	mounted() {
		this.loadDashboardData();
		this.updateCurrentPath();
		this.updateWindowWidth();
		// Listen for route changes
		window.addEventListener('popstate', this.updateCurrentPath);
		// Listen for window resize
		window.addEventListener('resize', this.updateWindowWidth);
	},
	beforeUnmount() {
		window.removeEventListener('popstate', this.updateCurrentPath);
		window.removeEventListener('resize', this.updateWindowWidth);
	},
	methods: {
		updateCurrentPath() {
			this.currentPath = window.location.pathname;
		},
		updateWindowWidth() {
			this.windowWidth = window.innerWidth;
		},
		// 組合完整路徑：basePath + 相對路徑
		getFullPath(relativePath) {
			return this.basePath + '/' + relativePath;
		},
		isActive(relativePath) {
			const fullPath = this.getFullPath(relativePath);
			return this.currentPath === fullPath || this.currentPath.startsWith(fullPath + '/');
		},
		navigateTo(relativePath) {
			const fullPath = this.getFullPath(relativePath);
			if (window.FluentCommunityUtil && window.FluentCommunityUtil.router) {
				window.FluentCommunityUtil.router.push(fullPath);
			} else {
				// Fallback: use window.location
				window.location.href = fullPath;
			}
			this.updateCurrentPath();
			this.showMoreDropdown = false;
		},
		toggleMoreMenu(event) {
			if (event) {
				event.stopPropagation();
			}
			this.showMoreDropdown = !this.showMoreDropdown;
		},
		closeMoreMenu() {
			this.showMoreDropdown = false;
		},
		async loadDashboardData() {
			this.loading = true;
			this.error = null;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/dashboard`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load dashboard data' );
				}

				const result = await response.json();
				if ( result.success ) {
					this.dashboardData = result.data;
				} else {
					throw new Error( result.message || 'Failed to load dashboard data' );
				}
			} catch ( error ) {
				this.error = error.message || '載入資料時發生錯誤';
				console.error( 'Dashboard load error:', error );
			} finally {
				this.loading = false;
			}
		},
		formatNumber( num ) {
			return new Intl.NumberFormat( 'zh-TW' ).format( num );
		},
	},
};
</script>
