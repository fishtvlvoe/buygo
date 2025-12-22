<?php
/**
 * BuyGo Orders Vue Component
 *
 * [AI Context]
 * - Orders list component
 * - Uses Vue 3 Options API
 * - Responsive design: table view (desktop) and card view (mobile)
 * - Shows payment status and shipping status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoOrdersComponent = {
	template: `
		<div class="el-scrollbar buygo-orders-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							訂單管理
						</h1>
						<div role="region" aria-label="Actions" class="fhr_page_actions">
							<!-- View Switcher (hidden on mobile) -->
							<div v-if="!isMobile" class="inline-flex items-center space-x-1 mr-3 border border-gray-300 rounded">
								<button 
									type="button"
									@click="setViewMode('grid')"
									:class="['px-2 py-1', viewMode === 'grid' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50']"
									title="網格視圖"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none">
										<path d="M12.4059 1.59412C13.3334 2.52162 13.3334 4.0144 13.3334 6.99996C13.3334 9.98552 13.3334 11.4783 12.4059 12.4058C11.4784 13.3333 9.98564 13.3333 7.00008 13.3333C4.01452 13.3333 2.52174 13.3333 1.59424 12.4058C0.666748 11.4783 0.666748 9.98552 0.666748 6.99996C0.666748 4.0144 0.666748 2.52162 1.59424 1.59412C2.52174 0.666626 4.01452 0.666626 7.00008 0.666626C9.98564 0.666626 11.4784 0.666626 12.4059 1.59412Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path>
										<path d="M13.3335 7L0.66683 7" stroke="currentColor" stroke-linecap="round"></path>
										<path d="M7 0.666626L7 13.3333" stroke="currentColor" stroke-linecap="round"></path>
									</svg>
								</button>
								<button 
									type="button"
									@click="setViewMode('list')"
									:class="['px-2 py-1', viewMode === 'list' ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50']"
									title="列表視圖"
								>
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
										<path d="M1.33325 7.60008C1.33325 6.8279 1.49441 6.66675 2.26659 6.66675H13.7333C14.5054 6.66675 14.6666 6.8279 14.6666 7.60008V8.40008C14.6666 9.17226 14.5054 9.33341 13.7333 9.33341H2.26659C1.49441 9.33341 1.33325 9.17226 1.33325 8.40008V7.60008Z" fill="currentColor"></path>
										<path d="M1.33325 2.26675C1.33325 1.49457 1.49441 1.33341 2.26659 1.33341H13.7333C14.5054 1.33341 14.6666 1.49457 14.6666 2.26675V3.06675C14.6666 3.83892 14.5054 4.00008 13.7333 4.00008H2.26659C1.49441 4.00008 1.33325 3.83892 1.33325 3.06675V2.26675Z" fill="currentColor"></path>
										<path d="M1.33325 12.9334C1.33325 12.1612 1.49441 12.0001 2.26659 12.0001H13.7333C14.5054 12.0001 14.6666 12.1612 14.6666 12.9334V13.7334C14.6666 14.5056 14.5054 14.6667 13.7333 14.6667H2.26659C1.49441 14.6667 1.33325 14.5056 1.33325 13.7334V12.9334Z" fill="currentColor"></path>
									</svg>
								</button>
							</div>
							<button v-if="selectedOrders.length === 1" @click="showDeleteDialog = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
								刪除訂單
							</button>
							<button v-else-if="selectedOrders.length >= 2" @click="showMergeDialog = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
								整合出貨 ({{ selectedOrders.length }})
							</button>
							<button @click="refreshOrders" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
								重新整理
							</button>
						</div>
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
					
					<div class="buygo-orders-container p-4 md:p-6">
						<!-- Filters -->
						<div class="mb-4 space-y-4 md:flex md:space-y-0 md:space-x-4">
							<div class="flex-1 relative">
								<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
									<svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
									</svg>
								</div>
								<input
									v-model="searchQuery"
									@input="handleSearchInput"
									@focus="handleSearchFocus"
									@blur="handleSearchBlur"
									type="text"
									placeholder="搜尋訂單編號、顧客姓名..."
									class="block w-full rounded-md border-gray-300 pl-10 focus:border-gray-900 focus:ring-gray-900 text-sm py-2 placeholder-gray-400 shadow-sm"
								/>
								<!-- Clear Button -->
								<button 
									v-if="searchQuery"
									@click="clearSearch"
									class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
								>
									<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
										<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
									</svg>
								</button>

								<!-- Search Suggestions Dropdown -->
								<div v-show="showSuggestions && (recentOrders.length > 0 || searchQuery)" 
									 class="absolute z-10 mt-1 w-full bg-white shadow-xl max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
									
									<div v-if="!searchQuery" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										最近訂單
									</div>
									<div v-else class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										搜尋結果
									</div>

									<ul class="divide-y divide-gray-100">
										<li 
											v-for="order in recentOrders" 
											:key="'search-' + order.id"
											@mousedown="selectSuggestion(order)"
											class="cursor-pointer hover:bg-indigo-50 px-4 py-2 flex justify-between items-center group"
										>
											<div>
												<div class="font-medium text-gray-900 group-hover:text-indigo-700">{{ order.customer_name || order.customer_info?.name || 'Guest' }}</div>
												<div class="text-xs text-gray-500">{{ order.order_number || order.id }} • {{ formatDateShort(order.created_at) }}</div>
											</div>
											<span :class="getPaymentStatusClass(order.payment_status)" class="text-xs px-2 py-0.5 rounded-full border">
												{{ getPaymentStatusLabel(order.payment_status) }}
											</span>
										</li>
									</ul>
								</div>
							</div>
							<div class="md:w-48">
								<select
									v-model="paymentStatusFilter"
									@change="loadOrders"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="">全部付款狀態</option>
									<option value="paid">已付款</option>
									<option value="pending">待付款</option>
								</select>
							</div>
							<div class="md:w-48">
								<select
									v-model="shippingStatusFilter"
									@change="loadOrders"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="">全部運送狀態</option>
									<option value="unshipped">未出貨</option>
									<option value="shipped">已出貨</option>
									<option value="delivered">已送達</option>
								</select>
							</div>
						</div>

						<!-- Loading State -->
						<div v-if="loading" class="flex items-center justify-center py-12">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
							<span class="ml-3 text-gray-600">載入中...</span>
						</div>

						<!-- Error State -->
						<div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
							<p class="text-red-800">{{ error }}</p>
						</div>

						<!-- Orders List - Grid View (Mobile) -->
						<div v-if="viewMode === 'grid' || isMobile" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
							<div v-for="order in orders" :key="order.id" class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow">
								<div class="p-4">
									<div class="flex items-center justify-between mb-3">
										<h3 class="text-sm font-medium text-gray-900">{{ order.order_number }}</h3>
										<input type="checkbox" :value="order.id" v-model="selectedOrders" class="rounded border-gray-300">
									</div>
									<div class="space-y-2 text-sm">
										<div>
											<p class="text-xs text-gray-500">買家</p>
											<p class="font-medium text-gray-900">{{ order.customer_name || 'Guest' }}</p>
											<p class="text-xs text-gray-500">{{ order.customer_email || '' }}</p>
										</div>
										<div class="flex justify-between items-center pt-2 border-t border-gray-200">
											<span class="text-gray-600">付款狀態:</span>
											<select @change="updateOrderStatus(order, 'payment_status', $event.target.value)" :value="order.payment_status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1" :class="getPaymentStatusClass(order.payment_status)">
												<option value="paid">已付款</option>
												<option value="pending">待付款</option>
												<option value="failed">付款失敗</option>
											</select>
										</div>
										<div class="flex justify-between items-center">
											<span class="text-gray-600">運送狀態:</span>
											<select @change="updateOrderStatus(order, 'shipping_status', $event.target.value)" :value="order.shipping_status || 'unshipped'" class="px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1 bg-white border border-gray-300 appearance-none" :class="getShippingStatusClass(order.shipping_status || 'unshipped')" style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.25rem center; background-size: 0.75em 0.75em; padding-right: 1.5rem;">
												<option value="unshipped">未出貨</option>
												<option value="shipped">已出貨</option>
												<option value="delivered">已送達</option>
											</select>
										</div>
										<div class="flex justify-between pt-2 border-t border-gray-200">
											<span class="text-gray-600">總額:</span>
											<span class="font-semibold text-gray-900">{{ order.formatted_total }}</span>
										</div>
									</div>
									<button @click="viewOrder(order)" class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
										查看詳情
									</button>
								</div>
							</div>
						</div>

						<!-- Orders List - Table/List View (Desktop) -->
						<div v-else-if="viewMode === 'list' && !isMobile" class="bg-white rounded-lg shadow overflow-hidden">
							<div class="overflow-x-auto">
								<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-6 py-3 text-left">
											<input
												type="checkbox"
												@change="toggleSelectAll"
												:checked="selectedOrders.length === orders.length && orders.length > 0"
												class="rounded border-gray-300"
											/>
										</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">訂單編號</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">買家</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">付款狀態</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">運送狀態</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">金額</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									<tr v-for="order in orders" :key="order.id">
										<td class="px-6 py-4 whitespace-nowrap">
											<input
												type="checkbox"
												:value="order.id"
												v-model="selectedOrders"
												class="rounded border-gray-300"
											/>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
											{{ order.order_number }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											{{ order.customer_name || 'Guest' }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap">
											<select
												@change="updateOrderStatus(order, 'payment_status', $event.target.value)"
												:value="order.payment_status"
												class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1"
												:class="getPaymentStatusClass(order.payment_status)"
											>
												<option value="pending">待付款</option>
												<option value="paid">已付款</option>
												<option value="failed">付款失敗</option>
												<option value="refunded">已退款</option>
											</select>
										</td>
										<td class="px-6 py-4 whitespace-nowrap">
											<select
												@change="updateOrderStatus(order, 'shipping_status', $event.target.value)"
												:value="order.shipping_status || 'unshipped'"
												class="px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1 bg-white border border-gray-300 appearance-none"
												:class="getShippingStatusClass(order.shipping_status || 'unshipped')"
												style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.25rem center; background-size: 0.75em 0.75em; padding-right: 1.5rem;"
											>
												<option value="unshipped">未出貨</option>
												<option value="shipped">已出貨</option>
												<option value="delivered">已送達</option>
											</select>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											{{ order.formatted_total }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
											<button @click="viewOrder(order)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
												查看
											</button>
										</td>
									</tr>
								</tbody>
							</table>
							</div>
						</div>

						<!-- Empty State -->
						<div v-else-if="orders.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
							<div class="text-4xl mb-4">📦</div>
							<h3 class="text-lg font-medium text-gray-900 mb-2">尚無訂單</h3>
							<p class="text-gray-500">目前沒有任何訂單</p>
						</div>

						<!-- Empty State -->
						<div v-if="!loading && !error && orders.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
							<div class="text-4xl mb-4">📋</div>
							<h3 class="text-lg font-medium text-gray-900 mb-2">尚無訂單</h3>
							<p class="text-gray-500">目前沒有任何訂單</p>
						</div>

						<!-- Pagination -->
						<div v-if="pagination.total_pages > 0" class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
							<div class="text-sm text-gray-700">
								第 {{ pagination.page }} 頁，共 {{ pagination.total_pages }} 頁
							</div>
							<div class="flex items-center gap-2">
								<span class="text-sm text-gray-700">每頁</span>
								<select
									v-model="pagination.per_page"
									@change="loadOrders"
									class="px-2 py-1 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 appearance-none cursor-pointer"
									style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1em 1em; padding-right: 2rem;"
								>
									<option value="5">5</option>
									<option value="10">10</option>
									<option value="20">20</option>
									<option value="50">50</option>
									<option value="100">100</option>
								</select>
								<span class="text-sm text-gray-700">筆</span>
							</div>
							<div class="text-sm text-gray-700">
								總計 {{ pagination.total }} 筆
							</div>
							<div class="flex space-x-2">
								<button
									@click="goToPage(pagination.page - 1)"
									:disabled="pagination.page === 1"
									class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400"
								>
									上一頁
								</button>
								<button
									@click="goToPage(pagination.page + 1)"
									:disabled="pagination.page === pagination.total_pages"
									class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400"
								>
									下一頁
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- View Order Modal -->
		<div v-if="showViewModal" class="relative z-[9999]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<!-- Background backdrop -->
			<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeViewModal"></div>

			<!-- Full-screen scrollable container -->
			<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="closeViewModal">
				<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<!-- Modal panel -->
					<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl">
						
						<!-- Loading State -->
						<div v-if="viewLoading" class="p-12 flex justify-center items-center">
							<svg class="animate-spin h-8 w-8 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
								<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
								<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
							</svg>
						</div>

						<!-- Error State -->
						<div v-else-if="viewError" class="p-6 text-center">
							<div class="text-red-600 mb-2 font-bold">無法載入訂單</div>
							<p class="text-sm text-gray-500 mb-4">{{ viewError }}</p>
							<button @click="closeViewModal" class="px-4 py-2 bg-black text-white rounded-md text-sm">關閉</button>
						</div>

						<!-- Content -->
						<div v-else-if="viewOrderData" class="bg-white">
							<!-- Header -->
							<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
								<h3 class="text-lg font-bold text-gray-900">
									訂單 {{ viewOrderData.order_number }}
								</h3>
								<button @click="closeViewModal" class="text-gray-400 hover:text-gray-500 p-1">
									<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
									</svg>
								</button>
							</div>

							<!-- Scrollable Content -->
							<div class="px-4 py-4 max-h-[70vh] overflow-y-auto custom-scrollbar space-y-6">
								
								<!-- Actions Section (Moved to top) -->
								<div class="bg-gray-50 p-3 rounded-lg border border-gray-200 flex items-center gap-3">
									<label class="text-xs text-gray-500 whitespace-nowrap">訂單狀態</label>
									<select 
										v-model="viewOrderData.payment_status" 
										@change="updateOrderStatusFromModal(viewOrderData)"
										class="flex-1 max-w-[140px] rounded-md border-gray-300 text-sm focus:border-black focus:ring-black py-1.5 bg-white"
									>
										<option value="pending">待處理</option>
										<option value="paid">已付款</option>
										<option value="processing">處理中</option>
										<option value="on-hold">保留</option>
										<option value="completed">已完成</option>
										<option value="cancelled">已取消</option>
										<option value="refunded">已退款</option>
										<option value="failed">失敗</option>
									</select>
									<label class="text-xs text-gray-500 whitespace-nowrap ml-2">物流狀態</label>
									<select 
										v-model="viewOrderData.shipping_status" 
										@change="updateOrderStatusFromModal(viewOrderData)"
										class="flex-1 max-w-[140px] rounded-md border-gray-300 text-sm focus:border-black focus:ring-black py-1.5 bg-white"
									>
										<option value="unshipped">未出貨</option>
										<option value="shipped">已出貨</option>
										<option value="delivered">已送達</option>
									</select>
									<button 
										@click="updateOrderStatusFromModal(viewOrderData)" 
										class="text-xs font-medium px-3 py-1.5 rounded-md transition-all whitespace-nowrap ml-auto bg-black text-white hover:bg-gray-800 shadow-sm">
										變更
									</button>
								</div>
								
								<!-- Customer Info (Label-Value Table) -->
								<div>
									<h4 class="text-sm font-bold text-gray-900 mb-3 border-l-4 border-black pl-3">顧客資訊</h4>
									
									<div v-if="!isEditingCustomer" class="grid grid-cols-[80px_1fr] gap-y-3 text-sm border-t border-gray-100 pt-3">
										<div class="text-gray-500 font-medium">姓名</div>
										<div class="font-bold text-gray-900">{{ viewOrderData.customer_name || 'Guest' }}</div>
										
										<div class="text-gray-500 font-medium">電話</div>
										<div class="text-gray-900">{{ viewOrderData.customer_phone || '-' }}</div>
										
										<div class="text-gray-500 font-medium">Email</div>
										<div class="text-gray-900 break-all">{{ viewOrderData.customer_email || '-' }}</div>
										
										<div class="text-gray-500 font-medium">配送地址</div>
										<div class="text-gray-900 leading-relaxed">{{ viewOrderData.customer_address || '-' }}</div>
										
										<div class="text-gray-500 font-medium">付款方式</div>
										<div class="text-gray-900">{{ viewOrderData.payment_method || '-' }}</div>
									</div>
									
									<div v-else class="grid grid-cols-[80px_1fr] gap-y-3 text-sm border-t border-gray-100 pt-3">
										<div class="text-gray-500 font-medium">姓名</div>
										<div>
											<input 
												v-model="editCustomerData.name"
												type="text"
												class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black">
										</div>
										
										<div class="text-gray-500 font-medium">電話</div>
										<div>
											<input 
												v-model="editCustomerData.phone"
												type="tel"
												class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black">
										</div>
										
										<div class="text-gray-500 font-medium">Email</div>
										<div>
											<input 
												v-model="editCustomerData.email"
												type="email"
												class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black">
										</div>
										
										<div class="text-gray-500 font-medium">配送地址</div>
										<div>
											<textarea 
												v-model="editCustomerData.address"
												rows="2"
												class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-black focus:border-black resize-none"></textarea>
										</div>
										
										<div class="text-gray-500 font-medium">付款方式</div>
										<div class="text-gray-900">{{ viewOrderData.payment_method || '-' }}</div>
									</div>
								</div>

								<!-- Items Section (Table Style) -->
								<div>
									<h4 class="text-sm font-bold text-gray-900 mb-3 border-l-4 border-black pl-3">訂單明細</h4>
									
									<div class="space-y-0 divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
										<div v-for="item in (viewOrderData.items || [])" :key="item.id" class="p-3 flex gap-3 bg-white hover:bg-gray-50">
											<!-- Image -->
											<div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-gray-200">
												<img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full object-cover object-center">
												<div v-else class="h-full w-full bg-gray-100 flex items-center justify-center text-gray-400">
													<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
												</div>
											</div>
											<!-- Info -->
											<div class="flex-1 min-w-0 flex flex-col justify-center">
												<h3 class="text-sm font-bold text-gray-900 line-clamp-2 leading-snug">{{ item.name }}</h3>
												<p v-if="item.variation_title && item.variation_title !== item.name" class="text-xs text-gray-500 mt-1">{{ item.variation_title }}</p>
												<div class="mt-1 flex justify-between items-end">
													<span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">x {{ item.quantity }}</span>
													<span class="text-sm font-bold text-gray-900">{{ item.formatted_line_total }}</span>
												</div>
											</div>
										</div>
										
										<!-- Edit Button (Moved below product list) -->
										<div class="p-3 bg-gray-50 border-t border-gray-200">
											<button 
												v-if="!isEditingCustomer"
												@click="startEditCustomer"
												class="w-full text-xs font-medium bg-black text-white hover:bg-gray-800 px-3 py-1.5 rounded-md transition-all shadow-sm">
												編輯
											</button>
											<div v-else class="flex gap-2">
												<button 
													@click="cancelEditCustomer"
													class="flex-1 text-xs text-gray-700 bg-white border border-gray-300 px-3 py-2 rounded hover:bg-gray-50 transition-colors">
													取消
												</button>
												<button 
													@click="saveCustomerInfo"
													:disabled="savingCustomer"
													class="flex-1 text-xs bg-black text-white px-3 py-2 rounded hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
													{{ savingCustomer ? '儲存中...' : '儲存' }}
												</button>
											</div>
										</div>
									</div>
								</div>

							</div>

							<!-- Footer Actions -->
							<div class="bg-gray-50 px-6 py-4 flex justify-end items-center rounded-b-lg border-t border-gray-200">
								<button @click="closeViewModal" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
									關閉
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Merge Orders Dialog -->
		<div v-if="showMergeDialog" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
			<div class="flex min-h-screen items-center justify-center p-4">
				<div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showMergeDialog = false"></div>
				<div class="relative w-full max-w-2xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all">
					<div class="px-6 py-4 border-b border-gray-200">
						<h3 class="text-lg font-medium text-gray-900">整合出貨</h3>
					</div>
					<div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
						<div v-if="merging" class="flex items-center justify-center py-8">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
							<span class="ml-3 text-gray-600">處理中...</span>
						</div>
						<div v-else-if="mergeError" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
							<p class="text-red-800">{{ mergeError }}</p>
						</div>
						<div v-else>
							<p class="text-sm text-gray-600 mb-4">已選擇 {{ selectedOrders.length }} 筆訂單進行整合</p>
							
							<div class="mb-4">
								<label class="block text-sm font-medium text-gray-700 mb-2">運送方式</label>
								<select
									v-model="mergeShippingMethod"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="standard">標準運送</option>
									<option value="express">快速運送</option>
									<option value="pickup">自取</option>
								</select>
							</div>

							<div v-if="mergeResult" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
								<p class="text-green-800 font-semibold mb-2">整合成功！</p>
								<p class="text-sm text-green-700">合併訂單 ID: {{ mergeResult.merged_order_id }}</p>
								<p class="text-sm text-green-700">總金額: NT$ {{ formatNumber(mergeResult.total_amount) }}</p>
							</div>
						</div>
					</div>
					<div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
						<button
							@click="showMergeDialog = false; mergeResult = null; mergeError = null"
							class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
						>
							{{ mergeResult ? '關閉' : '取消' }}
						</button>
						<button
							v-if="!mergeResult"
							@click="confirmMerge"
							:disabled="merging || selectedOrders.length < 2"
							class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							確認整合
						</button>
					</div>
				</div>
			</div>

			<!-- Delete Order Dialog -->
			<div v-if="showDeleteDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showDeleteDialog = false">
				<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
					<div class="p-6">
						<h3 class="text-lg font-semibold text-gray-900 mb-4">確認刪除訂單</h3>
						<p class="text-sm text-gray-600 mb-6">確定要刪除選中的訂單嗎？此操作無法復原。</p>
						
						<div v-if="deleteError" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
							<p class="text-red-800 text-sm">{{ deleteError }}</p>
						</div>

						<div class="flex justify-end space-x-3">
							<button
								@click="showDeleteDialog = false; deleteError = null"
								class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
							>
								取消
							</button>
							<button
								@click="confirmDelete"
								:disabled="deleting"
								class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
							>
								{{ deleting ? '刪除中...' : '確認刪除' }}
							</button>
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
			orders: [],
			selectedOrders: [],
			searchQuery: '',
			paymentStatusFilter: '',
			shippingStatusFilter: '',
			pagination: {
				page: 1,
				per_page: 5,
				total: 0,
				total_pages: 0,
			},
			searchTimeout: null,
			// Smart search state
			showSuggestions: false,
			searchLoading: false,
			suggestions: [],
			isMobile: window.innerWidth < 768,
			viewMode: 'list', // 'grid' or 'list'
			showMergeDialog: false,
			merging: false,
			mergeError: null,
			mergeResult: null,
			mergeShippingMethod: 'standard',
			showViewModal: false,
			viewLoading: false,
			viewError: null,
			viewOrderData: null,
			// Customer edit state
			isEditingCustomer: false,
			savingCustomer: false,
			editCustomerData: {
				name: '',
				phone: '',
				email: '',
				address: ''
			},
			showDeleteDialog: false,
			deleting: false,
			deleteError: null,
			// Navigation menu state
			basePath: basePath,
			currentPath: window.location.pathname,
			windowWidth: window.innerWidth,
			showMoreDropdown: false,
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
	mounted() {
		this.loadOrders();
		// Check initial screen size and set view mode
		this.checkMobile();
		this.updateViewMode();
		// Listen for resize events
		window.addEventListener( 'resize', this.handleResize );
		// Navigation menu event listeners
		this.updateCurrentPath();
		this.updateWindowWidth();
		window.addEventListener('popstate', this.updateCurrentPath);
		window.addEventListener('resize', this.updateWindowWidth);
	},
	beforeUnmount() {
		window.removeEventListener( 'resize', this.handleResize );
		// Navigation menu event listeners
		window.removeEventListener('popstate', this.updateCurrentPath);
		window.removeEventListener('resize', this.updateWindowWidth);
	},
	computed: {
		// Recent orders for search suggestions: Top 5 matching, or top 5 recent if empty
		recentOrders() {
			if ( this.searchQuery ) {
				// Return first 5 matches from suggestions
				return this.suggestions.slice( 0, 5 );
			} else {
				// Return recent orders from suggestions
				return this.suggestions.slice( 0, 5 );
			}
		},
		// Navigation menu computed properties
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
	methods: {
		checkMobile() {
			this.isMobile = window.innerWidth < 768;
		},
		updateViewMode() {
			// If mobile, force grid view
			if ( this.isMobile ) {
				this.viewMode = 'grid';
			} else {
				// Desktop: use saved preference or default to list
				const savedViewMode = localStorage.getItem( 'buygo_orders_view_mode' );
				this.viewMode = ( savedViewMode === 'grid' || savedViewMode === 'list' ) ? savedViewMode : 'list';
			}
		},
		handleResize() {
			const wasMobile = this.isMobile;
			this.checkMobile();
			// If mobile state changed, update view mode
			if ( wasMobile !== this.isMobile ) {
				this.updateViewMode();
			}
		},
		setViewMode( mode ) {
			// Only allow manual change on desktop
			if ( ! this.isMobile ) {
				this.viewMode = mode;
				// Save to localStorage
				localStorage.setItem( 'buygo_orders_view_mode', mode );
			}
		},
		async loadOrders() {
			this.loading = true;
			this.error = null;

			try {
				const params = new URLSearchParams( {
					page: this.pagination.page,
					per_page: this.pagination.per_page,
					payment_status: this.paymentStatusFilter,
					shipping_status: this.shippingStatusFilter,
					search: this.searchQuery,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders?${params}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load orders' );
				}

				const result = await response.json();
				if ( result.success ) {
					this.orders = result.data.orders || [];
					this.pagination = result.data.pagination || this.pagination;
				} else {
					throw new Error( result.message || 'Failed to load orders' );
				}
			} catch ( error ) {
				this.error = error.message || '載入訂單時發生錯誤';
				console.error( 'Orders load error:', error );
			} finally {
				this.loading = false;
			}
		},
		handleSearchInput() {
			// Show suggestions when typing
			if ( this.searchQuery.length > 0 ) {
				this.loadSuggestions();
			} else {
				this.showSuggestions = false;
				this.suggestions = [];
			}
			// Also trigger debounced search for full list
			clearTimeout( this.searchTimeout );
			this.searchTimeout = setTimeout( () => {
				this.pagination.page = 1; // Reset to first page
				this.loadOrders();
			}, 500 );
		},
		handleSearchFocus() {
			// Show suggestions when focused (always show recent orders or search results)
			this.showSuggestions = true;
			if ( this.searchQuery.length > 0 ) {
				this.loadSuggestions();
			} else {
				// Show recent orders when focused but no query
				this.loadRecentOrders();
			}
		},
		handleSearchBlur() {
			// Delay hiding suggestions to allow click events
			setTimeout( () => {
				this.showSuggestions = false;
			}, 200 );
		},
		async loadRecentOrders() {
			// Load recent orders when search is empty
			this.searchLoading = true;
			try {
				const params = new URLSearchParams( {
					page: 1,
					per_page: 5, // Show latest 5 items
					payment_status: this.paymentStatusFilter,
					shipping_status: this.shippingStatusFilter,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders?${params}`,
					{
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if ( ! response.ok ) {
					throw new Error( `HTTP ${response.status}` );
				}

				const result = await response.json();
				if ( result.success && result.data && result.data.orders ) {
					this.suggestions = result.data.orders.slice( 0, 5 );
				} else {
					this.suggestions = [];
				}
			} catch ( error ) {
				console.error( 'Failed to load recent orders:', error );
				this.suggestions = [];
			} finally {
				this.searchLoading = false;
			}
		},
		async loadSuggestions() {
			if ( this.searchQuery.length === 0 ) {
				// If no query, load recent orders instead
				this.loadRecentOrders();
				return;
			}

			this.searchLoading = true;
			this.showSuggestions = true;

			try {
				// Search in current orders list (latest 5 items)
				const params = new URLSearchParams( {
					page: 1,
					per_page: 5, // Show latest 5 items
					payment_status: this.paymentStatusFilter,
					shipping_status: this.shippingStatusFilter,
					search: this.searchQuery,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders?${params}`,
					{
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if ( ! response.ok ) {
					throw new Error( `HTTP ${response.status}` );
				}

				const result = await response.json();
				if ( result.success && result.data && result.data.orders ) {
					this.suggestions = result.data.orders.slice( 0, 5 );
				} else {
					this.suggestions = [];
				}
			} catch ( error ) {
				console.error( 'Failed to load suggestions:', error );
				this.suggestions = [];
			} finally {
				this.searchLoading = false;
			}
		},
		selectSuggestion( order ) {
			// Open order detail view instead of just setting search query
			this.showSuggestions = false;
			this.viewOrder( order );
		},
		clearSearch() {
			this.searchQuery = '';
			this.showSuggestions = false;
			this.suggestions = [];
			this.pagination.page = 1;
			this.loadOrders();
		},
		goToPage( page ) {
			if ( page >= 1 && page <= this.pagination.total_pages ) {
				this.pagination.page = page;
				this.loadOrders();
			}
		},
		refreshOrders() {
			this.loadOrders();
		},
		toggleSelectAll() {
			if ( this.selectedOrders.length === this.orders.length ) {
				this.selectedOrders = [];
			} else {
				this.selectedOrders = this.orders.map( order => order.id );
			}
		},
		async viewOrder( order ) {
			this.showViewModal = true;
			this.viewError = null;
			this.viewLoading = true;
			this.viewOrderData = null;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/${order.id}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load order details' );
				}

					const result = await response.json();
					if ( result.success ) {
						this.viewOrderData = result.data;
						// Reset edit state when loading new order
						this.isEditingCustomer = false;
						this.editCustomerData = {
							name: '',
							phone: '',
							email: '',
							address: ''
						};
					} else {
						throw new Error( result.message || 'Failed to load order details' );
					}
			} catch ( error ) {
				this.viewError = error.message || '載入訂單詳情時發生錯誤';
				console.error( 'Order view error:', error );
			} finally {
				this.viewLoading = false;
			}
		},
		closeViewModal() {
			this.showViewModal = false;
			this.viewError = null;
			this.viewOrderData = null;
			this.isEditingCustomer = false;
			this.editCustomerData = {
				name: '',
				phone: '',
				email: '',
				address: ''
			};
		},
		startEditCustomer() {
			if ( ! this.viewOrderData ) {
				return;
			}
			this.editCustomerData = {
				name: this.viewOrderData.customer_name || '',
				phone: this.viewOrderData.customer_phone || '',
				email: this.viewOrderData.customer_email || '',
				address: this.viewOrderData.customer_address || ''
			};
			this.isEditingCustomer = true;
		},
		cancelEditCustomer() {
			this.isEditingCustomer = false;
			this.editCustomerData = {
				name: '',
				phone: '',
				email: '',
				address: ''
			};
		},
		async saveCustomerInfo() {
			if ( ! this.viewOrderData ) {
				return;
			}

			this.savingCustomer = true;
			this.viewError = null;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/${this.viewOrderData.id}/customer`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
						body: JSON.stringify( {
							name: this.editCustomerData.name,
							phone: this.editCustomerData.phone,
							email: this.editCustomerData.email,
							address: this.editCustomerData.address,
						} ),
					}
				);

				if ( ! response.ok ) {
					const result = await response.json();
					throw new Error( result.message || '更新顧客資訊失敗' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Reload order details
					await this.viewOrder( { id: this.viewOrderData.id } );
					this.isEditingCustomer = false;
				} else {
					throw new Error( result.message || '更新顧客資訊失敗' );
				}
			} catch ( error ) {
				this.viewError = error.message || '更新顧客資訊時發生錯誤';
				console.error( 'Save customer info error:', error );
			} finally {
				this.savingCustomer = false;
			}
		},
		formatDate( dateString ) {
			if ( ! dateString ) return '-';
			const date = new Date( dateString );
			return date.toLocaleDateString( 'zh-TW', {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
			} );
		},
		formatDateShort( dateString ) {
			if ( ! dateString ) return '-';
			const date = new Date( dateString );
			return date.toLocaleDateString( 'zh-TW', {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
			} );
		},
		getPaymentStatusClass( status ) {
			const classes = {
				paid: 'bg-green-100 text-green-800',
				pending: 'bg-yellow-100 text-yellow-800',
				failed: 'bg-red-100 text-red-800',
			};
			return classes[ status ] || 'bg-gray-100 text-gray-800';
		},
		getPaymentStatusLabel( status ) {
			const labels = {
				paid: '已付款',
				pending: '待付款',
				failed: '付款失敗',
			};
			return labels[ status ] || status;
		},
		getShippingStatusClass( status ) {
			const classes = {
				unshipped: 'bg-yellow-100 text-yellow-800',
				shipped: 'bg-gray-100 text-gray-800',
				delivered: 'bg-green-100 text-green-800',
			};
			return classes[ status ] || 'bg-gray-100 text-gray-800';
		},
		getShippingStatusLabel( status ) {
			const labels = {
				unshipped: '未出貨',
				shipped: '已出貨',
				delivered: '已送達',
				cancelled: '已取消',
			};
			return labels[ status ] || status;
		},
		async updateOrderStatus( order, statusType, newStatus ) {
			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/${order.id}/status`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( {
							[statusType]: newStatus,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to update order status' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Update local order data
					order[statusType] = newStatus;
					// Reload orders list
					await this.loadOrders();
				} else {
					throw new Error( result.message || 'Failed to update order status' );
				}
			} catch ( error ) {
				console.error( 'Update order status error:', error );
				alert( '更新狀態失敗: ' + ( error.message || '未知錯誤' ) );
				// Reload to restore original status
				await this.loadOrders();
			}
		},
		async updateOrderStatusFromModal( order ) {
			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/${order.id}/status`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( {
							payment_status: order.payment_status,
							shipping_status: order.shipping_status,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to update order status' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Reload orders list
					await this.loadOrders();
				} else {
					throw new Error( result.message || 'Failed to update order status' );
				}
			} catch ( error ) {
				console.error( 'Update order status error:', error );
				alert( '更新狀態失敗: ' + ( error.message || '未知錯誤' ) );
				// Reload order details to restore original status
				await this.viewOrder( order );
			}
		},
		async confirmDelete() {
			if ( this.selectedOrders.length !== 1 ) {
				this.deleteError = '請選擇一個訂單進行刪除';
				return;
			}

			this.deleting = true;
			this.deleteError = null;

			try {
				const orderId = this.selectedOrders[0];
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/${orderId}`,
					{
						method: 'DELETE',
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if ( ! response.ok ) {
					const result = await response.json();
					throw new Error( result.message || '刪除訂單失敗' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Reload orders list
					await this.loadOrders();
					this.showDeleteDialog = false;
					this.selectedOrders = [];
				} else {
					throw new Error( result.message || '刪除訂單失敗' );
				}
			} catch ( error ) {
				this.deleteError = error.message || '刪除訂單時發生錯誤';
				console.error( 'Delete order error:', error );
			} finally {
				this.deleting = false;
			}
		},
		async confirmMerge() {
			if ( this.selectedOrders.length < 2 ) {
				this.mergeError = '至少需要選擇 2 筆訂單才能整合';
				return;
			}

			this.merging = true;
			this.mergeError = null;
			this.mergeResult = null;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/orders/merge`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( {
							order_ids: this.selectedOrders,
							shipping_method: this.mergeShippingMethod,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to merge orders' );
				}

				const result = await response.json();
				if ( result.success ) {
					this.mergeResult = result.data;
					this.selectedOrders = [];
					// Reload orders after a short delay
					setTimeout( () => {
						this.loadOrders();
					}, 1500 );
				} else {
					throw new Error( result.message || 'Failed to merge orders' );
				}
			} catch ( error ) {
				this.mergeError = error.message || '整合訂單時發生錯誤';
				console.error( 'Merge orders error:', error );
			} finally {
				this.merging = false;
			}
		},
		formatNumber( num ) {
			return new Intl.NumberFormat( 'zh-TW' ).format( num );
		},
		// Navigation menu methods
		updateCurrentPath() {
			this.currentPath = window.location.pathname;
		},
		updateWindowWidth() {
			this.windowWidth = window.innerWidth;
		},
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
	},
};
</script>
