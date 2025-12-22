<?php
/**
 * BuyGo Products Vue Component
 *
 * [AI Context]
 * - Products list component
 * - Uses Vue 3 Options API
 * - Responsive design with Tailwind CSS
 * - Supports table view (desktop) and card view (mobile)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoProductsComponent = {
	template: `
		<div class="el-scrollbar buygo-products-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							商品管理
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
							<button @click="refreshProducts" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
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
					
					<div class="buygo-products-container p-4 md:p-6">
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
									placeholder="搜尋商品名稱或 ID..."
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm pl-10 py-2 placeholder-gray-400"
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
								<div v-show="showSuggestions && (recentProducts.length > 0 || searchQuery)" 
									 class="absolute z-10 mt-1 w-full bg-white shadow-xl max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
									
									<div v-if="!searchQuery" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										最近商品
									</div>
									<div v-else class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										搜尋結果
									</div>

									<ul class="divide-y divide-gray-100">
										<li 
											v-for="product in recentProducts" 
											:key="'search-' + product.id"
											@mousedown="selectSuggestion(product)"
											class="cursor-pointer hover:bg-indigo-50 px-4 py-2 flex items-center gap-3 group"
										>
											<img v-if="product.image" :src="product.image" :alt="product.name" class="w-10 h-10 rounded object-cover flex-shrink-0">
											<div v-else class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center flex-shrink-0">
												<span class="text-xs text-gray-400">無圖片</span>
											</div>
											<div class="flex-1 min-w-0">
												<div class="font-medium text-gray-900 group-hover:text-indigo-700 truncate">{{ product.name }}</div>
												<div class="text-xs text-gray-500">ID: {{ product.id }} | {{ product.formatted_price }}</div>
											</div>
										</li>
									</ul>
								</div>
							</div>
							<div class="md:w-48">
								<select
									v-model="statusFilter"
									@change="loadProducts"
									class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 sm:text-sm"
								>
									<option value="all">全部狀態</option>
									<option value="publish">已發布</option>
									<option value="draft">草稿</option>
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

						<!-- Products List - Grid View (Mobile) -->
						<div v-if="viewMode === 'grid' || isMobile" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
							<div v-for="product in products" :key="product.id" class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow">
								<div class="p-4">
									<div class="flex items-center space-x-3 mb-3">
										<img v-if="product.image" :src="product.image" :alt="product.name" class="w-16 h-16 rounded object-cover">
										<div class="flex-1 min-w-0">
											<h3 class="text-sm font-medium text-gray-900 truncate">{{ product.name }}</h3>
											<p class="text-xs text-gray-500">ID: {{ product.id }}</p>
										</div>
									</div>
									<div class="space-y-2 text-sm">
										<div class="flex justify-between">
											<span class="text-gray-600">價格:</span>
											<span class="font-semibold text-gray-900">{{ product.formatted_price }}</span>
										</div>
										<div class="flex justify-between">
											<span class="text-gray-600">庫存:</span>
											<span class="font-semibold" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">{{ product.stock }}</span>
										</div>
										<div class="flex justify-between items-center">
											<span class="text-gray-600">狀態:</span>
											<span :class="getStatusClass(product.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
												{{ getStatusLabel(product.status) }}
											</span>
										</div>
										<div class="flex justify-between">
											<span class="text-gray-600">供應商:</span>
											<span v-if="product.supplier" class="font-semibold text-gray-900">{{ product.supplier.name }}</span>
											<span v-else class="text-gray-400 italic text-xs">未設定</span>
										</div>
									</div>
									<button @click="editProduct(product)" class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
										編輯
									</button>
								</div>
							</div>
						</div>

						<!-- Products List - Table/List View (Desktop) -->
						<div v-else-if="viewMode === 'list' && !isMobile" class="bg-white rounded-lg shadow overflow-hidden">
							<div class="overflow-x-auto">
								<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">商品</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">價格</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">庫存</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">狀態</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">供應商</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									<tr v-for="product in products" :key="product.id">
										<td class="px-6 py-4 whitespace-nowrap">
											<div class="flex items-center">
												<img v-if="product.image" :src="product.image" :alt="product.name" class="h-10 w-10 rounded object-cover mr-3">
												<div>
													<div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
													<div class="text-sm text-gray-500">ID: {{ product.id }}</div>
												</div>
											</div>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											{{ product.formatted_price }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											{{ product.stock }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap">
											<span :class="getStatusClass(product.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
												{{ getStatusLabel(product.status) }}
											</span>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
											<div v-if="product.supplier" class="flex items-center">
												<span>{{ product.supplier.name }}</span>
											</div>
											<div v-else class="text-gray-400 italic">
												未設定
											</div>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
											<button @click="editProduct(product)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
												編輯
											</button>
										</td>
									</tr>
								</tbody>
							</table>
							</div>
						</div>

						<!-- Empty State -->
						<div v-else-if="products.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
							<div class="text-4xl mb-4">📦</div>
							<h3 class="text-lg font-medium text-gray-900 mb-2">尚無商品</h3>
							<p class="text-gray-500">目前沒有任何商品</p>
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
									@change="loadProducts"
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

					<!-- Edit Product Modal -->
					<div v-if="showEditModal" class="relative z-[9999]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
						<!-- Background backdrop -->
						<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeEditModal"></div>

						<!-- Full-screen scrollable container -->
						<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="closeEditModal">
							<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
								<!-- Modal panel -->
								<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
									<!-- Header -->
									<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
										<h3 class="text-lg font-bold text-gray-900">編輯商品</h3>
										<button @click="closeEditModal" class="text-gray-400 hover:text-gray-500 p-1">
											<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
											</svg>
										</button>
									</div>

									<!-- Scrollable Content -->
									<div class="px-4 py-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
										<div v-if="editLoading" class="flex items-center justify-center py-8">
											<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
											<span class="ml-3 text-gray-600">載入中...</span>
										</div>

										<div v-else class="space-y-4">
											<div>
												<label class="block text-sm font-medium text-gray-700 mb-1">商品名稱</label>
												<input
													v-model="editForm.name"
													type="text"
													required
													class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
												/>
											</div>

											<div>
												<label class="block text-sm font-medium text-gray-700 mb-1">商品描述</label>
												<textarea
													v-model="editForm.description"
													rows="4"
													class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
												></textarea>
											</div>

											<div>
												<label class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
												<select
													v-model="editForm.status"
													class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2 bg-white appearance-none cursor-pointer"
													style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1em 1em; padding-right: 2rem;"
												>
													<option value="publish">已發布</option>
													<option value="draft">草稿</option>
													<option value="pending">審核中</option>
													<option value="private">私人</option>
												</select>
											</div>

											<!-- Supplier Selection (Smart Search) -->
											<div>
												<label class="block text-sm font-medium text-gray-700 mb-1">供應商</label>
												<div class="relative">
													<!-- Selected Supplier Display -->
													<div v-if="editForm.supplier" class="flex items-center justify-between px-4 py-2 bg-gray-50 border border-gray-300 rounded-md">
														<span class="text-sm text-gray-900">{{ editForm.supplier.name }}</span>
														<button @click="clearSupplier" type="button" class="text-gray-400 hover:text-gray-600">
															<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
																<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
															</svg>
														</button>
													</div>
													<!-- Search Input -->
													<div v-else class="relative">
														<input
															v-model="supplierSearchQuery"
															@input="searchSuppliers"
															@focus="handleSupplierFocus"
															@blur="handleSupplierBlur"
															type="text"
															placeholder="搜尋供應商名稱..."
															class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
														/>
														<!-- Dropdown -->
														<div v-show="showSupplierDropdown && suppliers.length > 0"
															class="absolute z-10 mt-1 w-full bg-white shadow-xl max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
															<div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
																{{ supplierSearchQuery ? '搜尋結果' : '最近供應商' }}
															</div>
															<ul class="divide-y divide-gray-100">
																<li
																	v-for="supplier in suppliers"
																	:key="'supplier-' + supplier.id"
																	@mousedown="selectSupplier(supplier)"
																	class="cursor-pointer hover:bg-gray-50 px-4 py-2"
																>
																	<div class="font-medium text-gray-900">{{ supplier.name }}</div>
																	<div v-if="supplier.contact_name" class="text-xs text-gray-500">{{ supplier.contact_name }}</div>
																</li>
															</ul>
														</div>
													</div>
												</div>
											</div>

											<!-- Product Variations -->
											<div v-if="editForm.variations && editForm.variations.length > 0">
												<label class="block text-sm font-medium text-gray-700 mb-2">商品</label>
												<div class="space-y-4">
													<div v-for="(variation, index) in editForm.variations" :key="variation.id" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
														<div class="flex items-start space-x-4 mb-4">
															<img v-if="variation.image" :src="variation.image" :alt="variation.variation_title" class="h-16 w-16 rounded object-cover flex-shrink-0">
															<div class="flex-1">
																<div class="mb-3">
																	<label class="block text-xs font-medium text-gray-600 mb-1">多樣式產品</label>
																	<input
																		v-model="variation.variation_title"
																		type="text"
																		class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-3 py-2"
																	/>
																</div>
																<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
																	<div>
																		<label class="block text-xs font-medium text-gray-600 mb-1">價格 (NT$)</label>
																		<input
																			v-model.number="variation.price"
																			type="number"
																			min="0"
																			step="1"
																			class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-3 py-2"
																		/>
																	</div>
																	<div>
																		<label class="block text-xs font-medium text-gray-600 mb-1">成本 (NT$)</label>
																		<input
																			v-model.number="variation.cost_price"
																			type="number"
																			min="0"
																			step="1"
																			placeholder="0"
																			class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-3 py-2"
																		/>
																	</div>
																	<div>
																		<label class="block text-xs font-medium text-gray-600 mb-1">庫存數量</label>
																		<input
																			v-model.number="variation.stock"
																			type="number"
																			min="0"
																			class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-3 py-2"
																		/>
																	</div>
																	<div>
																		<label class="block text-xs font-medium text-gray-600 mb-1">狀態</label>
																		<select
																			v-model="variation.item_status"
																			class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-3 py-2 bg-white appearance-none cursor-pointer"
																			style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1em 1em; padding-right: 2rem;"
																		>
																			<option value="active">啟用</option>
																			<option value="inactive">停用</option>
																		</select>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>

											<!-- Single Product (No Variations) -->
											<div v-else class="grid grid-cols-3 gap-4">
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">價格 (NT$)</label>
													<input
														v-model.number="editForm.price"
														type="number"
														min="0"
														step="1"
														required
														class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
													/>
												</div>

												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">成本 (NT$)</label>
													<input
														v-model.number="editForm.cost_price"
														type="number"
														min="0"
														step="1"
														placeholder="0"
														class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
													/>
												</div>

												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">庫存數量</label>
													<input
														v-model.number="editForm.stock"
														type="number"
														min="0"
														required
														class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
													/>
												</div>
											</div>

											<div v-if="editError" class="bg-red-50 border border-red-200 rounded-lg p-4">
												<p class="text-red-800 text-sm">{{ editError }}</p>
											</div>

											<!-- 按鈕列 -->
											<div v-if="!editLoading" class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-4">
												<button
													type="button"
													@click="closeEditModal"
													class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
												>
													取消
												</button>
												<button
													@click="saveProduct"
													:disabled="saving"
													class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
												>
													{{ saving ? '儲存中...' : '儲存' }}
												</button>
											</div>
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
			products: [],
			searchQuery: '',
			statusFilter: 'all',
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
			showEditModal: false,
			editLoading: false,
			editError: null,
			saving: false,
			editForm: {
				id: null,
				name: '',
				description: '',
				price: 0,
				stock: 0,
				status: 'publish',
				variations: [],
				supplier_id: null,
				cost_price: null,
			},
			// Supplier dropdown state
			suppliers: [],
			supplierSearchQuery: '',
			showSupplierDropdown: false,
			supplierSearchLoading: false,
			supplierSearchTimeout: null,
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
		this.loadProducts();
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
		// Recent products for search suggestions: Top 5 matching, or top 5 recent if empty
		recentProducts() {
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
				const savedViewMode = localStorage.getItem( 'buygo_products_view_mode' );
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
				localStorage.setItem( 'buygo_products_view_mode', mode );
			}
		},
		async loadProducts() {
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
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/products?${params}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load products' );
				}

				const result = await response.json();
				if ( result.success ) {
					this.products = result.data.products || [];
					this.pagination = result.data.pagination || this.pagination;
				} else {
					throw new Error( result.message || 'Failed to load products' );
				}
			} catch ( error ) {
				this.error = error.message || '載入商品時發生錯誤';
				console.error( 'Products load error:', error );
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
				this.loadProducts();
			}, 500 );
		},
		handleSearchFocus() {
			// Show suggestions when focused (always show recent products or search results)
			this.showSuggestions = true;
			if ( this.searchQuery.length > 0 ) {
				this.loadSuggestions();
			} else {
				// Show recent products when focused but no query
				this.loadRecentProducts();
			}
		},
		handleSearchBlur() {
			// Delay hiding suggestions to allow click events
			setTimeout( () => {
				this.showSuggestions = false;
			}, 200 );
		},
		async loadRecentProducts() {
			// Load recent products when search is empty
			this.searchLoading = true;
			try {
				const params = new URLSearchParams( {
					page: 1,
					per_page: 5, // Show latest 5 items
					status: this.statusFilter,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/products?${params}`,
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
				if ( result.success && result.data && result.data.products ) {
					this.suggestions = result.data.products.slice( 0, 5 );
				} else {
					this.suggestions = [];
				}
			} catch ( error ) {
				console.error( 'Failed to load recent products:', error );
				this.suggestions = [];
			} finally {
				this.searchLoading = false;
			}
		},
		async loadSuggestions() {
			if ( this.searchQuery.length === 0 ) {
				// If no query, load recent products instead
				this.loadRecentProducts();
				return;
			}

			this.searchLoading = true;
			this.showSuggestions = true;

			try {
				// Search in current products list (latest 5 items)
				const params = new URLSearchParams( {
					page: 1,
					per_page: 5, // Show latest 5 items
					status: this.statusFilter,
					search: this.searchQuery,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/products?${params}`,
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
				if ( result.success && result.data && result.data.products ) {
					this.suggestions = result.data.products.slice( 0, 5 );
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
		selectSuggestion( product ) {
			// Set search query and trigger full search
			this.searchQuery = product.name || product.id.toString();
			this.showSuggestions = false;
			this.pagination.page = 1;
			this.loadProducts();
		},
		clearSearch() {
			this.searchQuery = '';
			this.showSuggestions = false;
			this.suggestions = [];
			this.pagination.page = 1;
			this.loadProducts();
		},
		goToPage( page ) {
			if ( page >= 1 && page <= this.pagination.total_pages ) {
				this.pagination.page = page;
				this.loadProducts();
			}
		},
		refreshProducts() {
			this.loadProducts();
		},
		async editProduct( product ) {
			this.showEditModal = true;
			this.editError = null;
			this.editLoading = true;

			try {
				// Load full product details
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/products/${product.id}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to load product details' );
				}

				const result = await response.json();
				if ( result.success ) {
					const data = result.data;
					
					// If product has variations, use variations data
					if ( data.variations && data.variations.length > 0 ) {
						this.editForm = {
							id: data.id,
							name: data.name || '',
							description: data.description || '',
							price: data.price || 0,
							stock: data.stock || 0,
							status: data.status || 'publish',
							supplier_id: data.supplier_id || null,
							supplier: data.supplier || null,
							cost_price: data.cost_price || null,
							variations: data.variations.map( v => ( {
								id: v.id,
								variation_title: v.variation_title || '',
								price: v.price || 0,
								cost_price: v.cost_price || null,
								stock: v.stock || 0,
								item_status: v.item_status || 'active',
								image: v.image || '',
							} ) ),
						};
					} else {
						// Single product (no variations)
						this.editForm = {
							id: data.id,
							name: data.name || '',
							description: data.description || '',
							price: data.price || 0,
							cost_price: data.cost_price || null,
							stock: data.stock || 0,
							status: data.status || 'publish',
							supplier_id: data.supplier_id || null,
							supplier: data.supplier || null,
							variations: [],
						};
					}
				} else {
					throw new Error( result.message || 'Failed to load product details' );
				}
			} catch ( error ) {
				this.editError = error.message || '載入商品詳情時發生錯誤';
				console.error( 'Product load error:', error );
			} finally {
				this.editLoading = false;
			}
		},
		closeEditModal() {
			this.showEditModal = false;
			this.editError = null;
			this.editForm = {
				id: null,
				name: '',
				description: '',
				price: 0,
				cost_price: null,
				stock: 0,
				status: 'publish',
				supplier_id: null,
				supplier: null,
				variations: [],
			};
			// Reset supplier search state
			this.supplierSearchQuery = '';
			this.showSupplierDropdown = false;
			this.suppliers = [];
		},
		async saveProduct() {
			this.saving = true;
			this.editError = null;

			try {
				// Prepare update data
				const updateData = {
					name: this.editForm.name,
					description: this.editForm.description,
					status: this.editForm.status,
					supplier_id: this.editForm.supplier_id || null,
				};

				// If product has variations, send variations data with cost_price
				if ( this.editForm.variations && this.editForm.variations.length > 0 ) {
					updateData.variations = this.editForm.variations.map( v => ( {
						id: v.id,
						variation_title: v.variation_title,
						price: v.price,
						cost_price: v.cost_price || null,
						stock: v.stock,
						item_status: v.item_status,
					} ) );
				} else {
					// Single product (backward compatibility)
					updateData.price = this.editForm.price;
					updateData.cost_price = this.editForm.cost_price || null;
					updateData.stock = this.editForm.stock;
				}

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/products/${this.editForm.id}`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( updateData ),
					}
				);

				if ( ! response.ok ) {
					throw new Error( 'Failed to update product' );
				}

				const result = await response.json();
				if ( result.success ) {
					// Reload products list
					await this.loadProducts();
					// Close modal
					this.closeEditModal();
				} else {
					throw new Error( result.message || 'Failed to update product' );
				}
			} catch ( error ) {
				this.editError = error.message || '儲存商品時發生錯誤';
				console.error( 'Product save error:', error );
			} finally {
				this.saving = false;
			}
		},
		getStatusClass( status ) {
			const classes = {
				publish: 'bg-green-100 text-green-800',
				draft: 'bg-gray-100 text-gray-800',
				pending: 'bg-yellow-100 text-yellow-800',
				private: 'bg-gray-100 text-gray-800',
			};
			return classes[ status ] || 'bg-gray-100 text-gray-800';
		},
		getStatusLabel( status ) {
			const labels = {
				publish: '已發布',
				draft: '草稿',
				pending: '審核中',
				private: '私人',
			};
			return labels[ status ] || status;
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
		// Supplier search methods
		async loadSuppliers() {
			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers?per_page=10`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);
				if (response.ok) {
					const result = await response.json();
					if (result.success) {
						this.suppliers = result.data.suppliers || [];
					}
				}
			} catch (error) {
				console.error('Failed to load suppliers:', error);
			}
		},
		searchSuppliers() {
			clearTimeout(this.supplierSearchTimeout);
			this.supplierSearchTimeout = setTimeout(async () => {
				this.supplierSearchLoading = true;
				try {
					const query = this.supplierSearchQuery.trim();
					const params = new URLSearchParams({
						per_page: 10,
					});
					if (query) {
						params.append('search', query);
					}
					const response = await fetch(
						`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers?${params}`,
						{
							method: 'GET',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
							},
						}
					);
					if (response.ok) {
						const result = await response.json();
						if (result.success) {
							this.suppliers = result.data.suppliers || [];
						}
					}
				} catch (error) {
					console.error('Failed to search suppliers:', error);
				} finally {
					this.supplierSearchLoading = false;
				}
			}, 300);
		},
		handleSupplierFocus() {
			this.showSupplierDropdown = true;
			if (this.suppliers.length === 0) {
				this.loadSuppliers();
			}
		},
		handleSupplierBlur() {
			// Delay to allow click on dropdown item
			setTimeout(() => {
				this.showSupplierDropdown = false;
			}, 200);
		},
		selectSupplier(supplier) {
			this.editForm.supplier_id = supplier.id;
			this.editForm.supplier = {
				id: supplier.id,
				name: supplier.name,
			};
			this.supplierSearchQuery = '';
			this.showSupplierDropdown = false;
		},
		clearSupplier() {
			this.editForm.supplier_id = null;
			this.editForm.supplier = null;
		},
	},
};
</script>
