<?php
/**
 * BuyGo Shipping Vue Component
 *
 * [AI Context]
 * - Shipping management component
 * - Uses Vue 3 Options API
 * - Responsive design: table view (desktop) and card view (mobile)
 * - Supports order merging functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoShippingComponent = {
	template: `
		<div class="el-scrollbar buygo-shipping-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							出貨管理
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
							<button @click="exportCSV" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
								匯出出貨單 CSV
							</button>
							<!-- Show "整合出貨" for regular orders, "分開訂單" or "刪除合併訂單" for merged orders -->
							<button v-if="selectedOrders.length >= 2 && !allSelectedAreMerged" @click="showMergeDialog = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
								整合出貨 ({{ selectedOrders.length }})
							</button>
							<button v-else-if="selectedOrders.length === 1 && selectedOrderIsMerged" @click="showUnmergeDialog = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
								分開訂單
							</button>
							<button v-else-if="selectedOrders.length > 0 && allSelectedAreMerged" @click="showDeleteMergedDialog = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
								刪除合併訂單 ({{ selectedOrders.length }})
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
					
					<div class="buygo-shipping-container p-4 md:p-6">
						<!-- Search and Filters -->
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
									placeholder="搜尋訂單編號、買家姓名、Email..."
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
											<span :class="getShippingStatusClass(order.shipping_status)" class="text-xs px-2 py-0.5 rounded-full border">
												{{ getShippingStatusLabel(order.shipping_status) }}
											</span>
										</li>
									</ul>
								</div>
							</div>
							<div class="md:w-48">
								<select
									v-model="statusFilter"
									@change="loadOrders"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="">全部狀態</option>
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
										<div class="flex-1">
											<span v-if="order.is_merged" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mr-2">
												合併
											</span>
											<h3 class="text-sm font-medium text-gray-900 inline">{{ order.order_number }}</h3>
										</div>
										<input type="checkbox" :value="order.id" v-model="selectedOrders" :disabled="order.can_select === false" class="rounded border-gray-300">
									</div>
									<div class="space-y-2 text-sm">
										<div>
											<p class="text-xs text-gray-500">買家</p>
											<p class="font-medium text-gray-900">{{ order.customer_info?.name || order.customer_name || 'Guest' }}</p>
											<p class="text-xs text-gray-500">{{ order.customer_info?.email || order.customer_email || '' }}</p>
										</div>
										<div>
											<p class="text-xs text-gray-500">運送方式</p>
											<p class="text-sm text-gray-900">{{ getShippingMethodLabel(order.shipping_method) }}</p>
										</div>
										<div>
											<p class="text-xs text-gray-500">運送狀態</p>
											<select
												@change="updateShippingStatus(order, $event.target.value)"
												:value="order.shipping_status || 'unshipped'"
												class="px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1 mt-1 bg-white border border-gray-300 appearance-none"
												:class="getShippingStatusClass(order.shipping_status || 'unshipped')"
												style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.25rem center; background-size: 0.75em 0.75em; padding-right: 1.5rem;"
											>
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
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">運送方式</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">運送狀態</th>
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
												:disabled="order.can_select === false"
												class="rounded border-gray-300"
											/>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
											<span v-if="order.is_merged" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mr-2">
												合併
											</span>
											{{ order.order_number }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											<div>
												<div>{{ order.customer_info?.name || order.customer_name || 'Guest' }}</div>
												<div class="text-xs text-gray-500">{{ order.customer_info?.email || order.customer_email || '' }}</div>
											</div>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											{{ getShippingMethodLabel(order.shipping_method) }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap">
											<select
												@change="updateShippingStatus(order, $event.target.value)"
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
							<div class="text-4xl mb-4">🚚</div>
							<h3 class="text-lg font-medium text-gray-900 mb-2">尚無出貨訂單</h3>
							<p class="text-gray-500">目前沒有任何出貨訂單</p>
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

		<!-- Merge Orders Dialog -->
		<div v-if="showMergeDialog" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
			<div class="flex min-h-screen items-center justify-center p-4">
				<div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showMergeDialog = false"></div>
				<div class="relative w-full max-w-2xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all">
					<div class="px-6 py-4 border-b border-gray-200">
						<h3 class="text-lg font-medium leading-6 text-gray-900">整合出貨</h3>
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

			<!-- View Regular Order Modal -->
			<div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeViewModal">
				<div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
					<div class="p-6">
						<div class="flex items-center justify-between mb-6">
							<h2 class="text-xl font-semibold text-gray-900">訂單詳情</h2>
							<button @click="closeViewModal" class="text-gray-400 hover:text-gray-600">
								<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
								</svg>
							</button>
						</div>

						<div v-if="viewLoading" class="flex items-center justify-center py-8">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
							<span class="ml-3 text-gray-600">載入中...</span>
						</div>

						<div v-else-if="viewError" class="bg-red-50 border border-red-200 rounded-lg p-4">
							<p class="text-red-800">{{ viewError }}</p>
						</div>

						<div v-else-if="viewOrderData" class="space-y-4">
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">訂單編號</label>
									<p class="text-sm text-gray-900">{{ viewOrderData.order_number }}</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">訂單日期</label>
									<p class="text-sm text-gray-900">{{ formatDate(viewOrderData.created_at) }}</p>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">買家姓名</label>
									<p class="text-sm text-gray-900">{{ viewOrderData.customer_name || 'Guest' }}</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">買家 Email</label>
									<p class="text-sm text-gray-900">{{ viewOrderData.customer_email || '-' }}</p>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">付款狀態</label>
									<span :class="getPaymentStatusClass(viewOrderData.payment_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
										{{ getPaymentStatusLabel(viewOrderData.payment_status) }}
									</span>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">運送狀態</label>
									<span :class="getShippingStatusClass(viewOrderData.shipping_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
										{{ getShippingStatusLabel(viewOrderData.shipping_status) }}
									</span>
								</div>
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">訂單總額</label>
								<p class="text-lg font-semibold text-gray-900">{{ viewOrderData.formatted_total }}</p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- View Merged Order Modal -->
			<div v-if="showViewMergedModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeViewMergedModal">
				<div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
					<div class="p-6">
						<div class="flex items-center justify-between mb-6">
							<h2 class="text-xl font-semibold text-gray-900">合併訂單詳情</h2>
							<button @click="closeViewMergedModal" class="text-gray-400 hover:text-gray-600">
								<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
								</svg>
							</button>
						</div>

						<div v-if="viewMergedLoading" class="flex items-center justify-center py-8">
							<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
							<span class="ml-3 text-gray-600">載入中...</span>
						</div>

						<div v-else-if="viewMergedError" class="bg-red-50 border border-red-200 rounded-lg p-4">
							<p class="text-red-800">{{ viewMergedError }}</p>
						</div>

						<div v-else-if="viewMergedData" class="space-y-4">
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">合併訂單編號</label>
									<p class="text-sm text-gray-900">{{ viewMergedData.order_number }}</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">建立日期</label>
									<p class="text-sm text-gray-900">{{ formatDate(viewMergedData.created_at) }}</p>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">買家姓名</label>
									<p class="text-sm text-gray-900">{{ viewMergedData.customer_info?.name || 'Guest' }}</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">買家 Email</label>
									<p class="text-sm text-gray-900">{{ viewMergedData.customer_info?.email || '-' }}</p>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">運送方式</label>
									<p class="text-sm text-gray-900">{{ getShippingMethodLabel(viewMergedData.shipping_method) }}</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-gray-700 mb-1">運送狀態</label>
									<span :class="getShippingStatusClass(viewMergedData.shipping_status)" class="px-2 py-1 text-xs font-semibold rounded-full">
										{{ getShippingStatusLabel(viewMergedData.shipping_status) }}
									</span>
								</div>
							</div>

							<div>
								<label class="block text-sm font-medium text-gray-700 mb-1">合併訂單總額</label>
								<p class="text-lg font-semibold text-gray-900">{{ viewMergedData.formatted_total }}</p>
							</div>

							<div v-if="viewMergedData.original_orders && viewMergedData.original_orders.length > 0">
								<label class="block text-sm font-medium text-gray-700 mb-3">包含的原始訂單 ({{ viewMergedData.original_orders.length }} 筆)</label>
								<div class="space-y-3">
									<div v-for="order in viewMergedData.original_orders" :key="order.id" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
										<div class="mb-3">
											<p class="text-sm font-medium text-gray-900">{{ order.order_number }}</p>
											<p class="text-xs text-gray-500">{{ formatDate(order.created_at) }}</p>
										</div>
										<!-- Order Items with images -->
										<div v-if="order.items && order.items.length > 0" class="space-y-2 mb-3">
											<div v-for="item in order.items" :key="item.id" class="flex items-center space-x-3 bg-white rounded p-2">
												<img v-if="item.image" :src="item.image" :alt="item.name" class="w-12 h-12 rounded object-cover flex-shrink-0">
												<div v-else class="w-12 h-12 rounded bg-gray-200 flex items-center justify-center flex-shrink-0">
													<span class="text-xs text-gray-400">無圖片</span>
												</div>
												<div class="flex-1 min-w-0">
													<p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
													<p class="text-xs text-gray-600">數量: {{ item.quantity }} × {{ item.formatted_price }}</p>
												</div>
												<p class="text-sm font-semibold text-gray-900">{{ item.formatted_total }}</p>
											</div>
										</div>
										<div class="pt-2 border-t border-gray-200">
											<p class="text-sm font-semibold text-gray-900">小計: {{ order.formatted_total }}</p>
										</div>
									</div>
								</div>
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
			orders: [],
			selectedOrders: [],
			statusFilter: '',
			searchQuery: '',
			pagination: {
				page: 1,
				per_page: 5,
				total: 0,
				total_pages: 0,
			},
			isMobile: window.innerWidth < 768,
			viewMode: 'list', // 'grid' or 'list'
			// Smart search state
			showSuggestions: false,
			searchLoading: false,
			suggestions: [],
			searchTimeout: null,
			showMergeDialog: false,
			merging: false,
			mergeError: null,
			mergeResult: null,
			mergeShippingMethod: 'standard',
			showViewModal: false,
			viewLoading: false,
			viewError: null,
			viewOrderData: null,
			showViewMergedModal: false,
			viewMergedLoading: false,
			viewMergedError: null,
			viewMergedData: null,
			showUnmergeDialog: false,
			unmerging: false,
			unmergeError: null,
			showDeleteMergedDialog: false,
			deletingMerged: false,
			deleteMergedError: null,
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
	computed: {
		// Check if all selected orders are merged orders
		allSelectedAreMerged() {
			if ( this.selectedOrders.length === 0 ) {
				return false;
			}
			return this.selectedOrders.every( orderId => {
				const order = this.orders.find( o => o.id === orderId );
				return order && order.is_merged;
			} );
		},
		// Check if single selected order is merged
		selectedOrderIsMerged() {
			if ( this.selectedOrders.length !== 1 ) {
				return false;
			}
			const order = this.orders.find( o => o.id === this.selectedOrders[0] );
			return order && order.is_merged;
		},
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
			return this.suggestions.slice( 0, 5 );
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
				const savedViewMode = localStorage.getItem( 'buygo_shipping_view_mode' );
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
				localStorage.setItem( 'buygo_shipping_view_mode', mode );
			}
		},
		async loadOrders() {
			this.loading = true;
			this.error = null;

			try {
				const params = new URLSearchParams( {
					page: this.pagination.page,
					per_page: this.pagination.per_page,
					status: this.statusFilter,
					search: this.searchQuery,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping?${params}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load shipping orders' );
				}

				const result = await response.json();
				if ( result.success ) {
					this.orders = result.data.orders || [];
					this.pagination = result.data.pagination || this.pagination;
				} else {
					throw new Error( result.message || 'Failed to load shipping orders' );
				}
			} catch ( error ) {
				this.error = error.message || '載入出貨訂單時發生錯誤';
				console.error( 'Shipping load error:', error );
			} finally {
				this.loading = false;
			}
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
				this.selectedOrders = this.orders.filter( order => order.can_merge ).map( order => order.id );
			}
		},
		async viewOrder( order ) {
			if ( order.is_merged ) {
				// View merged order
				this.showViewMergedModal = true;
				this.viewMergedLoading = true;
				this.viewMergedError = null;
				this.viewMergedData = null;

				try {
					// Get merged order details
					const response = await fetch(
						`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping/merged/${order.merged_id}`,
						{
							method: 'GET',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
							},
						}
					);

					if ( ! response.ok ) {
						throw new Error( 'Failed to load merged order details' );
					}

					const result = await response.json();
					if ( result.success ) {
						this.viewMergedData = result.data;
					} else {
						throw new Error( result.message || 'Failed to load merged order details' );
					}
				} catch ( error ) {
					this.viewMergedError = error.message || '載入合併訂單詳情時發生錯誤';
					console.error( 'Merged order view error:', error );
				} finally {
					this.viewMergedLoading = false;
				}
			} else {
				// View regular order (same as orders page)
				this.showViewModal = true;
				this.viewLoading = true;
				this.viewError = null;
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
					} else {
						throw new Error( result.message || 'Failed to load order details' );
					}
				} catch ( error ) {
					this.viewError = error.message || '載入訂單詳情時發生錯誤';
					console.error( 'Order view error:', error );
				} finally {
					this.viewLoading = false;
				}
			}
		},
		closeViewModal() {
			this.showViewModal = false;
			this.viewError = null;
			this.viewOrderData = null;
		},
		closeViewMergedModal() {
			this.showViewMergedModal = false;
			this.viewMergedError = null;
			this.viewMergedData = null;
		},
		async unmergeOrder() {
			if ( this.selectedOrders.length !== 1 ) {
				this.unmergeError = '請選擇一個合併訂單進行分開';
				return;
			}

			this.unmerging = true;
			this.unmergeError = null;

			try {
				// Get merged order ID from selected order
				const selectedOrder = this.orders.find( o => o.id === this.selectedOrders[0] );
				if ( ! selectedOrder || ! selectedOrder.is_merged ) {
					throw new Error( '請選擇一個合併訂單' );
				}

				const mergedId = selectedOrder.merged_id || selectedOrder.id.toString().replace( 'merged-', '' );
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping/unmerge/${mergedId}`,
					{
						method: 'POST',
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if ( ! response.ok ) {
					const result = await response.json();
					throw new Error( result.message || '分開訂單失敗' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Reload orders list
					await this.loadOrders();
					this.showUnmergeDialog = false;
					this.selectedOrders = [];
				} else {
					throw new Error( result.message || '分開訂單失敗' );
				}
			} catch ( error ) {
				this.unmergeError = error.message || '分開訂單時發生錯誤';
				console.error( 'Unmerge order error:', error );
			} finally {
				this.unmerging = false;
			}
		},
		async deleteMergedOrders() {
			// TODO: Implement delete merged orders API endpoint
			alert( '刪除合併訂單功能待實作' );
			this.showDeleteMergedDialog = false;
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
			};
			return labels[ status ] || status;
		},
		getShippingMethodLabel( method ) {
			if ( ! method ) {
				return '標準運送';
			}
			const labels = {
				standard: '標準運送',
				express: '快速運送',
				overnight: '隔夜送達',
				pickup: '自取',
				free: '免運',
			};
			return labels[ method.toLowerCase() ] || method;
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
					status: this.statusFilter,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping?${params}`,
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
					status: this.statusFilter,
					search: this.searchQuery,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping?${params}`,
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
		formatDateShort( dateString ) {
			if ( ! dateString ) return '-';
			const date = new Date( dateString );
			return date.toLocaleDateString( 'zh-TW', {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
			} );
		},
		clearSearch() {
			this.searchQuery = '';
			this.showSuggestions = false;
			this.suggestions = [];
			this.pagination.page = 1;
			this.loadOrders();
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
		async exportCSV() {
			// Build export URL with current filter
			const params = new URLSearchParams( {
				status: this.statusFilter || '',
			} );
			
			const exportUrl = `${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/shipping/export-csv?${params}`;
			
			try {
				// Use fetch with credentials to ensure authentication
				const response = await fetch( exportUrl, {
					method: 'GET',
					headers: {
						'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
					},
					credentials: 'include', // Include cookies for authentication
				} );

				if ( ! response.ok ) {
					const errorData = await response.json().catch( () => ( { message: '匯出失敗' } ) );
					throw new Error( errorData.message || `HTTP ${response.status}: ${response.statusText}` );
				}

				// Get the blob data
				const blob = await response.blob();
				
				// Extract filename from Content-Disposition header, or use default
				let filename = 'shipping_orders_' + new Date().toISOString().split('T')[0] + '.csv';
				const contentDisposition = response.headers.get( 'Content-Disposition' );
				if ( contentDisposition ) {
					const filenameMatch = contentDisposition.match( /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/ );
					if ( filenameMatch && filenameMatch[1] ) {
						filename = filenameMatch[1].replace( /['"]/g, '' );
					}
				}

				// Create download link
				const url = window.URL.createObjectURL( blob );
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = filename;
				link.style.display = 'none';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				window.URL.revokeObjectURL( url );
			} catch ( error ) {
				console.error( 'CSV export error:', error );
				alert( '匯出失敗: ' + ( error.message || '未知錯誤' ) );
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
		async updateShippingStatus( order, newStatus ) {
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
							shipping_status: newStatus,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to update shipping status' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Update local order data
					order.shipping_status = newStatus;
					// Reload orders list
					await this.loadOrders();
				} else {
					throw new Error( result.message || 'Failed to update shipping status' );
				}
			} catch ( error ) {
				console.error( 'Update shipping status error:', error );
				alert( '更新狀態失敗: ' + ( error.message || '未知錯誤' ) );
				// Reload to restore original status
				await this.loadOrders();
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
