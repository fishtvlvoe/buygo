<?php
/**
 * BuyGo Suppliers Vue Component
 *
 * [AI Context]
 * - Supplier settlement management component
 * - Uses Vue 3 Options API
 * - Responsive design: table view (desktop) and card view (mobile)
 * - Shows supplier list with payable amounts and settlement details
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoSuppliersComponent = {
	template: `
		<div class="el-scrollbar buygo-suppliers-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header" style="position: relative; z-index: 1;">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							供應商結算
						</h1>
						<div role="region" aria-label="Actions" class="fhr_page_actions" style="position: relative; z-index: 10;">
							<!-- Date Range Picker -->
							<!-- Mobile: Calendar Icon Button -->
							<button 
								@click="showDatePickerModal = true" 
								class="md:hidden inline-flex items-center justify-center w-10 h-10 border border-gray-300 shadow-sm rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
								</svg>
								<span class="sr-only">選擇日期</span>
							</button>
							<!-- Desktop: Date Range Inputs -->
							<div class="hidden md:inline-flex items-center space-x-2 mr-3">
								<input
									type="date"
									v-model="periodStart"
									@change="loadSuppliers"
									class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
								/>
								<span class="text-gray-500">~</span>
								<input
									type="date"
									v-model="periodEnd"
									@change="loadSuppliers"
									class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
								/>
							</div>
							<button @click="refreshSuppliers" class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 shadow-sm rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
								</svg>
								<span class="sr-only">重新整理</span>
							</button>
							<!-- Mobile: Only + Icon -->
							<button @click="showAddSupplierModal = true" class="md:hidden inline-flex items-center justify-center w-10 h-10 border border-transparent rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
								</svg>
								<span class="sr-only">新增供應商</span>
							</button>
							<!-- Desktop: + Icon + Text -->
							<button @click="showAddSupplierModal = true" class="hidden md:inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
								<svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
								</svg>
								新增
							</button>
							<!-- Mobile: Calculator Icon Only -->
							<button @click="recalculateCosts" class="md:hidden inline-flex items-center justify-center w-10 h-10 border border-gray-300 shadow-sm rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
								</svg>
								<span class="sr-only">重新計算</span>
							</button>
							<!-- Desktop: Calculator Icon + Text -->
							<button @click="recalculateCosts" class="hidden md:inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
								<svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
								</svg>
								重新計算
							</button>
						</div>
					</div>
					<div class="buygo-suppliers-container p-4 md:p-6">
						<!-- Summary Cards -->
						<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
							<div class="bg-white rounded-lg shadow p-4">
								<div class="text-sm text-gray-500 mb-1">供應商總數</div>
								<div class="text-2xl font-bold text-gray-900">{{ summary.totalSuppliers }}</div>
							</div>
							<div class="bg-white rounded-lg shadow p-4">
								<div class="text-sm text-gray-500 mb-1">總銷售金額</div>
								<div class="text-2xl font-bold text-gray-900">NT$ {{ formatNumber(summary.totalSales) }}</div>
							</div>
							<div class="bg-white rounded-lg shadow p-4">
								<div class="text-sm text-gray-500 mb-1">總應付成本</div>
								<div class="text-2xl font-bold text-gray-900">NT$ {{ formatNumber(summary.totalPayable) }}</div>
							</div>
						</div>

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
									type="text"
									placeholder="搜尋供應商名稱、聯絡人..."
									class="block w-full rounded-md border-gray-300 pl-10 focus:border-gray-900 focus:ring-gray-900 text-sm py-2 placeholder-gray-400 shadow-sm"
								/>
								<button 
									v-if="searchQuery"
									@click="clearSearch"
									class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
								>
									<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
										<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
									</svg>
								</button>
							</div>
							<div class="md:w-48">
								<select
									v-model="statusFilter"
									@change="loadSuppliers"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="all">全部狀態</option>
									<option value="active">啟用</option>
									<option value="inactive">停用</option>
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

						<!-- Suppliers List - Table View (Desktop) -->
						<div v-else-if="!isMobile && suppliers.length > 0" class="bg-white rounded-lg shadow overflow-hidden">
							<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">供應商名稱</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">聯絡人</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">電話</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">產品數</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">應付金額</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									<tr v-for="supplier in suppliers" :key="supplier.id" class="hover:bg-gray-50">
										<td class="px-6 py-4 whitespace-nowrap">
											<div class="font-medium text-gray-900">{{ supplier.name }}</div>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
											{{ supplier.contact_name || '-' }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
											{{ supplier.phone || '-' }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
											{{ supplier.product_count || 0 }}
										</td>
										<td class="px-6 py-4 whitespace-nowrap">
											<div class="text-sm font-semibold text-gray-900">NT$ {{ formatNumber(supplier.payable_amount || 0) }}</div>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm">
											<button
												@click="viewSupplierDetail(supplier.id)"
												class="text-gray-900 hover:text-gray-700 font-medium"
											>
												查看詳情
											</button>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- Suppliers List - Card View (Mobile) -->
						<div v-else-if="isMobile && suppliers.length > 0" class="space-y-4">
							<div
								v-for="supplier in suppliers"
								:key="supplier.id"
								class="bg-white rounded-lg shadow p-4"
							>
								<div class="flex items-start justify-between mb-3">
									<div class="flex-1">
										<h3 class="font-semibold text-gray-900 text-lg">{{ supplier.name }}</h3>
										<p class="text-sm text-gray-500 mt-1">{{ supplier.contact_name || '無聯絡人' }}</p>
									</div>
									<span v-if="supplier.status === 'active'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
										啟用
									</span>
									<span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
										停用
									</span>
								</div>
								<div class="space-y-2 mb-4">
									<div class="flex justify-between text-sm">
										<span class="text-gray-500">產品數</span>
										<span class="text-gray-900 font-medium">{{ supplier.product_count || 0 }}</span>
									</div>
									<div class="flex justify-between text-sm">
										<span class="text-gray-500">應付金額</span>
										<span class="text-gray-900 font-bold text-lg">NT$ {{ formatNumber(supplier.payable_amount || 0) }}</span>
									</div>
								</div>
								<button
									@click="viewSupplierDetail(supplier.id)"
									class="w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
								>
									查看詳情
								</button>
							</div>
						</div>

						<!-- Empty State -->
						<div v-else-if="!loading && suppliers.length === 0" class="text-center py-12">
							<p class="text-gray-500">目前沒有供應商</p>
						</div>

						<!-- Pagination -->
						<div v-if="!loading && suppliers.length > 0" class="mt-6 flex items-center justify-between">
							<div class="text-sm text-gray-700">
								第 {{ pagination.page }} 頁，共 {{ pagination.total_pages }} 頁
							</div>
							<div class="flex space-x-2">
								<button
									@click="goToPage(pagination.page - 1)"
									:disabled="pagination.page <= 1"
									class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
								>
									上一頁
								</button>
								<button
									@click="goToPage(pagination.page + 1)"
									:disabled="pagination.page >= pagination.total_pages"
									class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
								>
									下一頁
								</button>
							</div>
						</div>
					</div>

					<!-- Supplier Detail Modal -->
					<div v-if="showDetailModal" class="relative z-[9999]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
						<!-- Background backdrop -->
						<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeDetailModal"></div>

						<!-- Full-screen scrollable container -->
						<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="closeDetailModal">
							<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
								<!-- Modal panel -->
								<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
									<!-- Header -->
									<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
										<h3 class="text-lg font-bold text-gray-900">供應商詳情: {{ detailData.supplier && detailData.supplier.name ? detailData.supplier.name : '' }}</h3>
										<button @click="closeDetailModal" class="text-gray-400 hover:text-gray-500 p-1">
											<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
											</svg>
										</button>
									</div>

									<!-- Scrollable Content -->
									<div class="px-4 py-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
										<div v-if="detailLoading" class="flex items-center justify-center py-8">
											<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
											<span class="ml-3 text-gray-600">載入中...</span>
										</div>

										<div v-else-if="detailError" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
											<p class="text-red-800 text-sm">{{ detailError }}</p>
										</div>

										<div v-else>
											<!-- Supplier Info (Editable) -->
											<div class="mb-6 p-4 bg-gray-50 rounded-lg">
												<div class="flex justify-between items-center mb-4">
													<h4 class="text-md font-semibold text-gray-900">供應商資訊</h4>
													<button 
														@click="editingSupplier = !editingSupplier" 
														class="px-4 py-2 text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none"
													>
														{{ editingSupplier ? '取消編輯' : '編輯' }}
													</button>
												</div>
												<div v-if="!editingSupplier" class="grid grid-cols-2 gap-4 text-sm">
													<div>
														<span class="text-gray-500">聯絡人：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.contact_name ? detailData.supplier.contact_name : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">電話：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.phone ? detailData.supplier.phone : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">Email：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.email ? detailData.supplier.email : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">Line ID：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.line_id ? detailData.supplier.line_id : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">銀行帳號：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.bank_account ? detailData.supplier.bank_account : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">銀行名稱：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.bank_name ? detailData.supplier.bank_name : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">分行：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.bank_branch ? detailData.supplier.bank_branch : '-' }}</span>
													</div>
													<div>
														<span class="text-gray-500">地址：</span>
														<span class="text-gray-900">{{ detailData.supplier && detailData.supplier.address ? detailData.supplier.address : '-' }}</span>
													</div>
												</div>
												<div v-else class="space-y-4">
													<div class="grid grid-cols-2 gap-4">
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">電話 <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.phone" type="tel" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.email" type="email" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">Line ID <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.line_id" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">銀行帳號 <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.bank_account" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">銀行名稱 <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.bank_name" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
														<div>
															<label class="block text-sm font-medium text-gray-700 mb-1">分行 <span class="text-red-500">*</span></label>
															<input v-model="editSupplierForm.bank_branch" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
														</div>
													</div>
													<div>
														<label class="block text-sm font-medium text-gray-700 mb-1">地址 <span class="text-red-500">*</span></label>
														<textarea v-model="editSupplierForm.address" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm"></textarea>
													</div>
												</div>
											</div>

											<!-- All Products List -->
											<div class="mb-6">
												<h4 class="text-md font-semibold text-gray-900 mb-3">產品列表 ({{ detailData.period_start }} ~ {{ detailData.period_end }})</h4>
												<div v-if="detailData.all_products && detailData.all_products.length > 0" class="overflow-x-auto">
													<table class="min-w-full divide-y divide-gray-200">
														<thead class="bg-gray-50">
															<tr>
																<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">產品</th>
																<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">規格</th>
																<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">數量</th>
																<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">成本</th>
																<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">總計</th>
															</tr>
														</thead>
														<tbody class="bg-white divide-y divide-gray-200">
															<tr 
																v-for="(product, index) in detailData.all_products" 
																:key="index" 
																:class="['cursor-pointer transition-colors', product.has_sales ? '' : 'opacity-60', 'hover:bg-gray-200', 'active:bg-gray-300']"
																@click="showProductDetail(product)"
															>
																<td class="px-4 py-3">
																	<div class="flex items-center space-x-3">
																		<img 
																			v-if="product.product_image" 
																			:src="product.product_image" 
																			:alt="product.product_name"
																			class="w-12 h-12 object-cover rounded"
																		/>
																		<div v-else class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">
																			無圖
																		</div>
																	</div>
																</td>
																<td class="px-4 py-3 text-sm text-gray-500">{{ product.variation_title }}</td>
																<td class="px-4 py-3 text-sm" :class="product.has_sales ? 'text-gray-900' : 'text-gray-400'">
																	{{ product.has_sales ? product.total_qty : '無銷售' }}
																</td>
																<td class="px-4 py-3 text-sm" :class="product.has_sales ? 'text-gray-900' : 'text-gray-400'">
																	{{ product.cost_per_unit > 0 ? 'NT$ ' + formatNumber(product.cost_per_unit) : '-' }}
																</td>
																<td class="px-4 py-3 text-sm font-semibold" :class="product.has_sales ? 'text-gray-900' : 'text-gray-400'">
																	{{ product.has_sales ? 'NT$ ' + formatNumber(product.total_cost) : '-' }}
																</td>
															</tr>
														</tbody>
													</table>
													<!-- Total Payable (Outside Table) -->
													<div class="mt-4 p-4 bg-gray-50 rounded-lg">
														<div class="flex justify-between items-center">
															<span class="text-lg font-semibold text-gray-900">總計應付金額：</span>
															<span class="text-2xl font-bold text-gray-900">NT$ {{ formatNumber(detailData.total_payable) }}</span>
														</div>
													</div>
												</div>
												<div v-else class="text-center py-8 text-gray-500">
													此供應商目前沒有產品
												</div>
											</div>

											<!-- Settlement Notes -->
											<div class="mb-6">
												<label class="block text-sm font-medium text-gray-700 mb-2">備註</label>
												<textarea
													v-model="settlementNotes"
													rows="3"
													class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm"
													placeholder="結算備註（選填）"
												></textarea>
											</div>

											<!-- Actions -->
											<div v-if="!detailLoading" class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 pt-4 border-t border-gray-200 mt-4">
												<!-- Export Buttons -->
												<div class="flex space-x-2 flex-1 sm:flex-initial">
													<button
														@click="exportSupplierDetail('csv')"
														class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 py-2.5 sm:px-4 sm:py-2 border border-gray-300 shadow-sm text-xs sm:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
													>
														<svg class="h-5 w-5 sm:h-4 sm:w-4 mr-1.5 sm:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
															<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
														</svg>
														<span class="text-xs sm:text-sm">CSV</span>
													</button>
													<button
														@click="exportSupplierDetail('pdf')"
														class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 py-2.5 sm:px-4 sm:py-2 border border-gray-300 shadow-sm text-xs sm:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
													>
														<svg class="h-5 w-5 sm:h-4 sm:w-4 mr-1.5 sm:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
															<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
														</svg>
														<span class="text-xs sm:text-sm">PDF</span>
													</button>
												</div>
												
												<!-- Action Buttons -->
												<div class="flex space-x-2 sm:space-x-3">
													<!-- Cancel Button -->
													<button
														@click="closeDetailModal"
														class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 sm:px-4 py-2.5 sm:py-2 border border-gray-300 shadow-sm text-xs sm:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
													>
														取消
													</button>
													<button
														v-if="settlementNotes && settlementNotes.trim() !== ''"
														@click="saveSettlementNotes"
														class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 sm:px-4 py-2.5 sm:py-2 border border-transparent text-xs sm:text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
													>
														儲存
													</button>
													<button
														v-if="editingSupplier"
														@click="saveSupplierChanges"
														:disabled="!hasSupplierChanges() || updatingSupplier"
														:class="['flex-1 sm:flex-initial inline-flex items-center justify-center px-4 sm:px-4 py-2.5 sm:py-2 border border-transparent text-xs sm:text-sm font-medium rounded-md shadow-sm text-white', hasSupplierChanges() && !updatingSupplier ? 'bg-gray-900 hover:bg-gray-800' : 'bg-gray-400 cursor-not-allowed']"
													>
														{{ updatingSupplier ? '處理中...' : '確認' }}
													</button>
													<button
														v-if="detailData.total_payable > 0 && !editingSupplier"
														@click="markAsSettled"
														:disabled="settling"
														class="flex-1 sm:flex-initial inline-flex items-center justify-center px-4 sm:px-4 py-2.5 sm:py-2 border border-transparent text-xs sm:text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
													>
														{{ settling ? '處理中...' : '標記為已結算' }}
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Add Supplier Modal -->
						<div v-if="showAddSupplierModal" class="relative z-[10001]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
							<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showAddSupplierModal = false"></div>
							<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="showAddSupplierModal = false">
								<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
									<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
										<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
											<h3 class="text-lg font-bold text-gray-900">新增供應商</h3>
											<button @click="showAddSupplierModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded-md p-1">
												<span class="sr-only">關閉</span>
												<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
												</svg>
											</button>
										</div>

										<div class="px-4 py-4 max-h-[70vh] overflow-y-auto">
											<!-- Step Indicator (Mobile: Show steps, Desktop: Show current step) -->
											<div class="mb-6">
												<div class="flex items-center justify-between">
													<div class="flex items-center space-x-2">
														<div :class="['w-8 h-8 rounded-full flex items-center justify-center', addSupplierStep >= 1 ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-600']">1</div>
														<div class="flex-1 h-1" :class="addSupplierStep >= 2 ? 'bg-gray-900' : 'bg-gray-200'"></div>
														<div :class="['w-8 h-8 rounded-full flex items-center justify-center', addSupplierStep >= 2 ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-600']">2</div>
														<div class="flex-1 h-1" :class="addSupplierStep >= 3 ? 'bg-gray-900' : 'bg-gray-200'"></div>
														<div :class="['w-8 h-8 rounded-full flex items-center justify-center', addSupplierStep >= 3 ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-600']">3</div>
													</div>
												</div>
												<div class="flex justify-between mt-2 text-xs text-gray-500">
													<span>基本資訊</span>
													<span>銀行資訊</span>
													<span>聯繫資訊</span>
												</div>
											</div>

											<!-- Step 1: Basic Info -->
											<div v-if="addSupplierStep === 1" class="space-y-4">
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">供應商名稱 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.name" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.name" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.name }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">聯絡人姓名 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.contact_name" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.contact_name" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.contact_name }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">統一編號 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.tax_id" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.tax_id" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.tax_id }}</p>
												</div>
											</div>

											<!-- Step 2: Bank Info -->
											<div v-if="addSupplierStep === 2" class="space-y-4">
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">銀行帳號 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.bank_account" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.bank_account" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.bank_account }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">銀行名稱 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.bank_name" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.bank_name" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.bank_name }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">分行 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.bank_branch" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.bank_branch" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.bank_branch }}</p>
												</div>
											</div>

											<!-- Step 3: Contact Info -->
											<div v-if="addSupplierStep === 3" class="space-y-4">
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">電話 <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.phone" type="tel" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.phone" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.phone }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.email" type="email" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.email" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.email }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">Line ID <span class="text-red-500">*</span></label>
													<input v-model="addSupplierForm.line_id" type="text" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm" />
													<p v-if="addSupplierErrors.line_id" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.line_id }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">地址 <span class="text-red-500">*</span></label>
													<textarea v-model="addSupplierForm.address" rows="3" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm"></textarea>
													<p v-if="addSupplierErrors.address" class="mt-1 text-sm text-red-600">{{ addSupplierErrors.address }}</p>
												</div>
												<div>
													<label class="block text-sm font-medium text-gray-700 mb-1">備註</label>
													<textarea v-model="addSupplierForm.notes" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm"></textarea>
												</div>
											</div>

											<!-- Navigation Buttons -->
											<div class="flex justify-between pt-4 border-t border-gray-200 mt-4">
												<button
													v-if="addSupplierStep > 1"
													@click="addSupplierStep--"
													class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
												>
													上一步
												</button>
												<div v-else></div>
												<div class="flex space-x-3">
													<button
														@click="showAddSupplierModal = false"
														class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
													>
														取消
													</button>
													<button
														v-if="addSupplierStep < 3"
														@click="addSupplierStep++"
														class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800"
													>
														下一步
													</button>
													<button
														v-else
														@click="submitAddSupplier"
														:disabled="addingSupplier"
														class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 disabled:opacity-50 disabled:cursor-not-allowed"
													>
														{{ addingSupplier ? '處理中...' : '確認新增' }}
													</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Date Picker Modal (Mobile) -->
						<div v-if="showDatePickerModal" class="relative z-[10002]" aria-labelledby="date-picker-title" role="dialog" aria-modal="true">
							<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showDatePickerModal = false"></div>
							<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="showDatePickerModal = false">
								<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
									<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md w-full">
										<div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
											<h3 class="text-lg font-bold text-gray-900">選擇日期範圍</h3>
										</div>
										<div class="px-4 py-4 space-y-4">
											<div>
												<label class="block text-sm font-medium text-gray-700 mb-2">開始日期</label>
												<input
													type="date"
													v-model="periodStart"
													@change="loadSuppliers"
													class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
												/>
											</div>
											<div>
												<label class="block text-sm font-medium text-gray-700 mb-2">結束日期</label>
												<input
													type="date"
													v-model="periodEnd"
													@change="loadSuppliers"
													class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900"
												/>
											</div>
											<div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
												<button
													@click="showDatePickerModal = false"
													class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
												>
													關閉
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Product Detail Modal (Second Level) -->
						<div v-if="showProductDetailModal" class="relative z-[10000]" aria-labelledby="product-modal-title" role="dialog" aria-modal="true">
							<div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeProductDetailModal"></div>
							<div class="fixed inset-0 z-10 w-screen overflow-y-auto" @click="closeProductDetailModal">
								<div class="flex min-h-full items-end justify-center p-2 sm:p-4 text-center sm:items-center">
									<div @click.stop class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all w-full max-w-4xl mx-auto my-2 sm:my-8 flex flex-col" style="max-height: calc(100vh - 4rem);">
										<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 text-center">
											<h3 class="text-lg font-bold text-gray-900">{{ currentProductDetail && currentProductDetail.product_name ? currentProductDetail.product_name : '產品詳情' }}</h3>
										</div>

										<div v-if="currentProductDetail" class="px-4 py-3 sm:px-6 sm:py-4 flex-1 overflow-y-auto" style="max-height: calc(100vh - 8rem);">
											<!-- Product Image - Centered -->
											<div class="flex justify-center mb-4">
												<img 
													v-if="currentProductDetail.product_image" 
													:src="currentProductDetail.product_image" 
													:alt="currentProductDetail.product_name"
													class="w-32 h-32 sm:w-24 sm:h-24 object-cover rounded"
												/>
												<div v-else class="w-32 h-32 sm:w-24 sm:h-24 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-sm">
													無圖
												</div>
											</div>

											<!-- Table Layout for Product Details - Centered -->
											<div class="flex justify-center">
												<div class="w-full max-w-md">
													<table class="min-w-full divide-y divide-gray-200">
														<tbody class="bg-white divide-y divide-gray-200">
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap w-1/3">規格</td>
																<td class="px-3 py-2 text-sm text-gray-900">{{ currentProductDetail.variation_title }}</td>
															</tr>
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap">原價</td>
																<td class="px-3 py-2 text-sm text-gray-900">NT$ {{ formatNumber(currentProductDetail.product_price || 0) }}</td>
															</tr>
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap">售價</td>
																<td class="px-3 py-2 text-sm text-gray-900">NT$ {{ formatNumber(currentProductDetail.product_price || 0) }}</td>
															</tr>
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap">成本</td>
																<td class="px-3 py-2 text-sm text-gray-900">NT$ {{ formatNumber(currentProductDetail.cost_per_unit || 0) }}</td>
															</tr>
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap">應付給供應商</td>
																<td class="px-3 py-2 text-sm font-semibold text-gray-900">NT$ {{ formatNumber(currentProductDetail.total_cost || 0) }}</td>
															</tr>
															<tr>
																<td class="px-3 py-2 text-sm font-medium text-gray-500 whitespace-nowrap">銷售數量</td>
																<td class="px-3 py-2 text-sm text-gray-900">{{ currentProductDetail.has_sales ? currentProductDetail.total_qty : '無銷售' }}</td>
															</tr>
														</tbody>
													</table>
												</div>
											</div>
										</div>

										<div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
											<button
												@click="closeProductDetailModal"
												class="w-full inline-flex justify-center items-center px-4 py-2.5 sm:py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
											>
												關閉
											</button>
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
		return {
			loading: true,
			error: null,
			suppliers: [],
			searchQuery: '',
			statusFilter: 'all',
			isMobile: window.innerWidth < 768,
			pagination: {
				page: 1,
				per_page: 20,
				total: 0,
				total_pages: 0,
			},
			summary: {
				totalSuppliers: 0,
				totalSales: 0,
				totalPayable: 0,
			},
			periodStart: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
			periodEnd: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
			searchTimeout: null,
			// Detail modal state
			showDetailModal: false,
			detailLoading: false,
			detailError: null,
			detailData: {
				supplier: null,
				period_start: null,
				period_end: null,
				product_summary: [],
				all_products: [],
				total_payable: 0,
			},
			currentSupplierId: null,
			settlementNotes: '',
			settling: false,
			showAddSupplierModal: false,
			showDatePickerModal: false,
			addSupplierStep: 1,
			addSupplierForm: {
				name: '',
				contact_name: '',
				phone: '',
				email: '',
				line_id: '',
				address: '',
				bank_account: '',
				bank_name: '',
				bank_branch: '',
				tax_id: '',
				notes: '',
			},
			addSupplierErrors: {},
			addingSupplier: false,
			recalculating: false,
			showProductDetailModal: false,
			currentProductDetail: null,
			editingSupplier: false,
			editSupplierForm: {
				phone: '',
				email: '',
				line_id: '',
				bank_account: '',
				bank_name: '',
				bank_branch: '',
				address: '',
			},
			editSupplierOriginal: {},
			updatingSupplier: false,
		};
	},
	mounted() {
		this.checkMobile();
		this.loadSuppliers();
		window.addEventListener( 'resize', this.handleResize );
	},
	beforeUnmount() {
		window.removeEventListener( 'resize', this.handleResize );
	},
	methods: {
		checkMobile() {
			this.isMobile = window.innerWidth < 768;
		},
		handleResize() {
			this.checkMobile();
		},
		formatNumber( num ) {
			return new Intl.NumberFormat( 'zh-TW' ).format( num || 0 );
		},
		handleSearchInput() {
			clearTimeout( this.searchTimeout );
			this.searchTimeout = setTimeout( () => {
				this.loadSuppliers();
			}, 300 );
		},
		clearSearch() {
			this.searchQuery = '';
			this.loadSuppliers();
		},
		async loadSuppliers() {
			this.loading = true;
			this.error = null;

			try {
				const params = new URLSearchParams( {
					page: this.pagination.page,
					per_page: this.pagination.per_page,
					search: this.searchQuery,
					status: this.statusFilter,
				} );

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

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '載入供應商列表失敗' );
				}

				const result = await response.json();

				console.log( 'Suppliers API response:', result );

				if ( result.success && result.data ) {
					this.suppliers = result.data.suppliers || [];
					this.pagination = {
						page: result.data.pagination?.page || 1,
						per_page: result.data.pagination?.per_page || 20,
						total: result.data.pagination?.total || 0,
						total_pages: result.data.pagination?.total_pages || 0,
					};

					// Calculate summary
					this.summary.totalSuppliers = this.suppliers.length;
					this.summary.totalPayable = this.suppliers.reduce( ( sum, s ) => sum + ( parseFloat( s.payable_amount ) || 0 ), 0 );
					
					console.log( 'Loaded suppliers:', this.suppliers.length, 'Total payable:', this.summary.totalPayable );
				} else {
					console.error( 'API returned unsuccessful response:', result );
				}
			} catch ( err ) {
				this.error = err.message || '載入供應商列表時發生錯誤';
				console.error( 'Load suppliers error:', err );
			} finally {
				this.loading = false;
			}
		},
		async refreshSuppliers() {
			this.loadSuppliers();
		},
		goToPage( page ) {
			if ( page >= 1 && page <= this.pagination.total_pages ) {
				this.pagination.page = page;
				this.loadSuppliers();
			}
		},
		async viewSupplierDetail( supplierId ) {
			this.currentSupplierId = supplierId;
			this.showDetailModal = true;
			this.detailLoading = true;
			this.detailError = null;

			try {
				const params = new URLSearchParams( {
					period_start: this.periodStart,
					period_end: this.periodEnd,
				} );

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/${supplierId}/detail?${params}`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '載入供應商詳情失敗' );
				}

				const result = await response.json();

				if ( result.success && result.data ) {
					this.detailData = result.data;
					// Initialize edit form and settlement notes
					if ( this.detailData.supplier ) {
						this.editSupplierForm = {
							phone: this.detailData.supplier.phone || '',
							email: this.detailData.supplier.email || '',
							line_id: this.detailData.supplier.line_id || '',
							bank_account: this.detailData.supplier.bank_account || '',
							bank_name: this.detailData.supplier.bank_name || '',
							bank_branch: this.detailData.supplier.bank_branch || '',
							address: this.detailData.supplier.address || '',
						};
						this.editSupplierOriginal = JSON.parse( JSON.stringify( this.editSupplierForm ) );
						
						// Load existing notes if available
						this.settlementNotes = this.detailData.supplier.notes || '';
					}
				}
			} catch ( err ) {
				this.detailError = err.message || '載入供應商詳情時發生錯誤';
				console.error( 'Load supplier detail error:', err );
			} finally {
				this.detailLoading = false;
			}
		},
		closeDetailModal() {
			this.showDetailModal = false;
			this.detailData = {
				supplier: null,
				period_start: null,
				period_end: null,
				product_summary: [],
				all_products: [],
				total_payable: 0,
			};
			this.settlementNotes = '';
			this.currentSupplierId = null;
			this.editingSupplier = false;
			this.editSupplierForm = {
				phone: '',
				email: '',
				line_id: '',
				bank_account: '',
				bank_name: '',
				bank_branch: '',
				address: '',
			};
			this.editSupplierOriginal = {};
		},
		exportSupplierDetail( format ) {
			if ( ! this.currentSupplierId ) {
				return;
			}

			const params = new URLSearchParams( {
				format: format,
				period_start: this.periodStart,
				period_end: this.periodEnd,
				_wpnonce: window.buygoFrontendPortalData?.nonce || '',
			} );

			const url = `${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/${this.currentSupplierId}/export?${params}`;

			if ( format === 'pdf' ) {
				// For PDF, open in new window for printing
				window.open( url, '_blank' );
			} else {
				// For CSV, download directly
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = '';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
			}
		},
		async recalculateCosts() {
			if ( ! confirm( '確定要重新計算所有訂單的供應商成本嗎？這可能需要一些時間。' ) ) {
				return;
			}

			this.recalculating = true;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/recalculate`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '重新計算失敗' );
				}

				const result = await response.json();

				if ( result.success ) {
					alert( result.message || '重新計算完成！' );
					this.loadSuppliers();
				}
			} catch ( err ) {
				alert( '重新計算失敗：' + err.message );
			} finally {
				this.recalculating = false;
			}
		},
		async markAsSettled() {
			if ( ! this.currentSupplierId ) {
				return;
			}

			if ( ! confirm( '確定要標記此期間為已結算嗎？' ) ) {
				return;
			}

			this.settling = true;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/${this.currentSupplierId}/settle`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( {
							period_start: this.periodStart,
							period_end: this.periodEnd,
							notes: this.settlementNotes,
						} ),
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '結算失敗' );
				}

				const result = await response.json();

				if ( result.success ) {
					alert( '結算成功！' );
					this.closeDetailModal();
					this.loadSuppliers();
				}
			} catch ( err ) {
				alert( '結算失敗：' + ( err.message || '未知錯誤' ) );
				console.error( 'Settle error:', err );
			} finally {
				this.settling = false;
			}
		},
		async recalculateCosts() {
			if ( ! confirm( '確定要重新計算所有訂單的供應商成本嗎？這可能需要一些時間。' ) ) {
				return;
			}

			this.recalculating = true;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/recalculate`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '重新計算失敗' );
				}

				const result = await response.json();

				if ( result.success ) {
					alert( result.message || '重新計算完成！' );
					this.loadSuppliers();
				}
			} catch ( err ) {
				alert( '重新計算失敗：' + err.message );
			} finally {
				this.recalculating = false;
			}
		},
		validateAddSupplierForm() {
			this.addSupplierErrors = {};

			// Step 1 validation
			if ( this.addSupplierStep === 1 ) {
				if ( ! this.addSupplierForm.name || this.addSupplierForm.name.trim() === '' ) {
					this.addSupplierErrors.name = '請輸入供應商名稱';
					return false;
				}
				if ( ! this.addSupplierForm.contact_name || this.addSupplierForm.contact_name.trim() === '' ) {
					this.addSupplierErrors.contact_name = '請輸入聯絡人姓名';
					return false;
				}
				if ( ! this.addSupplierForm.tax_id || this.addSupplierForm.tax_id.trim() === '' ) {
					this.addSupplierErrors.tax_id = '請輸入統一編號';
					return false;
				}
			}

			// Step 2 validation
			if ( this.addSupplierStep === 2 ) {
				if ( ! this.addSupplierForm.bank_account || this.addSupplierForm.bank_account.trim() === '' ) {
					this.addSupplierErrors.bank_account = '請輸入銀行帳號';
					return false;
				}
				if ( ! this.addSupplierForm.bank_name || this.addSupplierForm.bank_name.trim() === '' ) {
					this.addSupplierErrors.bank_name = '請輸入銀行名稱';
					return false;
				}
				if ( ! this.addSupplierForm.bank_branch || this.addSupplierForm.bank_branch.trim() === '' ) {
					this.addSupplierErrors.bank_branch = '請輸入分行';
					return false;
				}
			}

			// Step 3 validation
			if ( this.addSupplierStep === 3 ) {
				if ( ! this.addSupplierForm.phone || this.addSupplierForm.phone.trim() === '' ) {
					this.addSupplierErrors.phone = '請輸入電話';
					return false;
				}
				// Phone format validation (basic)
				const phoneRegex = /^[\d\s\-+()]+$/;
				if ( ! phoneRegex.test( this.addSupplierForm.phone ) ) {
					this.addSupplierErrors.phone = '電話格式不正確';
					return false;
				}

				if ( ! this.addSupplierForm.email || this.addSupplierForm.email.trim() === '' ) {
					this.addSupplierErrors.email = '請輸入 Email';
					return false;
				}
				// Email format validation
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if ( ! emailRegex.test( this.addSupplierForm.email ) ) {
					this.addSupplierErrors.email = 'Email 格式不正確';
					return false;
				}

				if ( ! this.addSupplierForm.line_id || this.addSupplierForm.line_id.trim() === '' ) {
					this.addSupplierErrors.line_id = '請輸入 Line ID';
					return false;
				}

				if ( ! this.addSupplierForm.address || this.addSupplierForm.address.trim() === '' ) {
					this.addSupplierErrors.address = '請輸入地址';
					return false;
				}
			}

			return true;
		},
		async submitAddSupplier() {
			// Final validation
			if ( ! this.validateAddSupplierForm() ) {
				return;
			}

			this.addingSupplier = true;
			this.addSupplierErrors = {};

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( this.addSupplierForm ),
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					if ( errorData.data && errorData.data.errors ) {
						this.addSupplierErrors = errorData.data.errors;
					} else {
						throw new Error( errorData.message || '新增供應商失敗' );
					}
					return;
				}

				const result = await response.json();

				if ( result.success ) {
					alert( '供應商新增成功！' );
					this.showAddSupplierModal = false;
					this.resetAddSupplierForm();
					this.loadSuppliers();
				}
			} catch ( err ) {
				alert( '新增供應商失敗：' + err.message );
			} finally {
				this.addingSupplier = false;
			}
		},
		resetAddSupplierForm() {
			this.addSupplierForm = {
				name: '',
				contact_name: '',
				phone: '',
				email: '',
				line_id: '',
				address: '',
				bank_account: '',
				bank_name: '',
				bank_branch: '',
				tax_id: '',
				notes: '',
			};
			this.addSupplierStep = 1;
			this.addSupplierErrors = {};
		},
		showProductDetail( product ) {
			// #region agent log
			try {
				fetch('http://127.0.0.1:7242/ingest/3b064778-ca19-45f5-80ca-ab47f77df6fa', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						location: 'buygo-suppliers.php:1279',
						message: 'showProductDetail called',
						data: { hasProduct: !!product, productName: product?.product_name },
						timestamp: Date.now(),
						sessionId: 'debug-session',
						runId: 'run1',
						hypothesisId: 'A'
					})
				}).catch(() => {});
			} catch(e) {}
			// #endregion
			this.currentProductDetail = product;
			this.showProductDetailModal = true;
		},
		closeProductDetailModal() {
			this.showProductDetailModal = false;
			this.currentProductDetail = null;
		},
		async saveSettlementNotes() {
			if ( ! this.currentSupplierId || ! this.settlementNotes || this.settlementNotes.trim() === '' ) {
				return;
			}

			// Save notes by updating supplier's notes field
			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/${this.currentSupplierId}`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( {
							notes: this.settlementNotes,
						} ),
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '儲存備註失敗' );
				}

				const result = await response.json();

				if ( result.success ) {
					alert( '備註已儲存！' );
					// Reload supplier detail to get updated data
					if ( this.currentSupplierId ) {
						this.viewSupplierDetail( this.currentSupplierId );
					}
				}
			} catch ( err ) {
				alert( '儲存備註失敗：' + err.message );
			}
		},
		hasSupplierChanges() {
			return JSON.stringify( this.editSupplierForm ) !== JSON.stringify( this.editSupplierOriginal );
		},
		async saveSupplierChanges() {
			if ( ! this.currentSupplierId ) {
				return;
			}

			// Validate
			if ( ! this.editSupplierForm.phone || ! this.editSupplierForm.email || ! this.editSupplierForm.line_id || 
				 ! this.editSupplierForm.bank_account || ! this.editSupplierForm.bank_name || 
				 ! this.editSupplierForm.bank_branch || ! this.editSupplierForm.address ) {
				alert( '請填寫所有必填欄位' );
				return;
			}

			// Email format validation
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if ( ! emailRegex.test( this.editSupplierForm.email ) ) {
				alert( 'Email 格式不正確' );
				return;
			}

			this.updatingSupplier = true;

			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/suppliers/${this.currentSupplierId}`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						body: JSON.stringify( this.editSupplierForm ),
					}
				);

				if ( ! response.ok ) {
					const errorData = await response.json();
					throw new Error( errorData.message || '更新失敗' );
				}

				const result = await response.json();

				if ( result.success ) {
					alert( '供應商資訊更新成功！' );
					this.editingSupplier = false;
					this.viewSupplierDetail( this.currentSupplierId ); // Reload detail
				}
			} catch ( err ) {
				alert( '更新失敗：' + err.message );
			} finally {
				this.updatingSupplier = false;
			}
		},
	},
};
</script>
