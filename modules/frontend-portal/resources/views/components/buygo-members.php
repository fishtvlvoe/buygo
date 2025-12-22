<?php
/**
 * BuyGo Members Vue Component
 *
 * [AI Context]
 * - Members list component
 * - Uses Vue 3 Options API
 * - Responsive design with Tailwind CSS
 * - Supports table view (desktop) and card view (mobile)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<script>
const BuyGoMembersComponent = {
	template: `
		<div class="el-scrollbar buygo-members-scrollbar" style="height: 100%;">
			<div class="el-scrollbar__wrap" style="height: calc(100vh - var(--fcom-header-height, 65px)); overflow-y: auto; overflow-x: hidden;">
				<div class="el-scrollbar__view" style="padding-bottom: 2rem; min-height: 100%;">
					<div class="fhr_content_layout_header">
						<h1 role="region" aria-label="Page Title" class="fhr_page_title">
							會員管理
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
							<button @click="loadMembers" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
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
					
					<div class="buygo-members-container p-4 md:p-6">
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
									placeholder="搜尋姓名、Email、電話..."
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
								<div v-show="showSuggestions && (recentMembers.length > 0 || searchQuery)" 
									 class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
									
									<div v-if="!searchQuery" class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										最近會員
									</div>
									<div v-else class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50">
										搜尋結果
									</div>

									<ul class="divide-y divide-gray-100">
										<li 
											v-for="user in recentMembers" 
											:key="'search-' + user.id"
											@mousedown="selectSuggestion(user)"
											class="cursor-pointer hover:bg-indigo-50 px-4 py-2 flex items-center gap-3 group"
										>
											<img :src="user.avatar_url" class="w-8 h-8 rounded-full bg-gray-200 border border-gray-100 flex-shrink-0">
											<div class="min-w-0 flex-1">
												<div class="font-medium text-gray-900 group-hover:text-indigo-700 truncate">{{ user.display_name || user.name }}</div>
												<div class="text-xs text-gray-500 truncate">{{ user.email }}</div>
											</div>
										</li>
									</ul>
								</div>
							</div>
							<div class="md:w-48">
								<select
									v-model="roleFilter"
									@change="loadMembers"
									class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 sm:text-sm px-4 py-2"
								>
									<option value="">所有角色</option>
									<option value="administrator">WP 管理員</option>
									<option value="buygo_admin">BuyGo 管理員</option>
									<option value="buygo_seller">賣家</option>
									<option value="buygo_helper">小幫手</option>
									<option value="subscriber">顧客</option>
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

						<!-- Members List - Grid View (Mobile) -->
						<div v-if="viewMode === 'grid' || isMobile" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
							<div v-for="member in members" :key="member.id" class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow">
								<div class="p-4">
									<div class="flex items-center space-x-3 mb-3">
										<img :src="member.avatar_url" :alt="member.name" class="w-12 h-12 rounded-full border border-gray-200 flex-shrink-0">
										<div class="flex-1 min-w-0">
											<div class="font-medium text-gray-900 truncate" :title="member.name">{{ member.name }}</div>
											<div class="text-xs text-gray-500 truncate" :title="member.email">{{ member.email }}</div>
										</div>
									</div>
									<div class="space-y-2">
										<div class="flex items-center justify-between">
											<span class="text-xs text-gray-500">角色</span>
											<select
												@change="updateMemberRole(member, $event.target.value)"
												:value="member.role_key || 'subscriber'"
												class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1"
												:class="{
													'bg-gray-100 text-gray-800': member.role_key === 'administrator',
													'bg-gray-100 text-gray-800': member.role_key === 'buygo_admin',
													'bg-green-100 text-green-800': member.role_key === 'buygo_seller',
													'bg-purple-100 text-purple-800': member.role_key === 'buygo_helper',
													'bg-gray-100 text-gray-800': !['administrator', 'buygo_admin', 'buygo_seller', 'buygo_helper'].includes(member.role_key)
												}"
											>
												<option value="administrator">WP 管理員</option>
												<option value="buygo_admin">BuyGo 管理員</option>
												<option value="buygo_seller">賣家</option>
												<option value="buygo_helper">小幫手</option>
												<option value="subscriber">顧客</option>
											</select>
										</div>
										<div class="flex items-center justify-between">
											<span class="text-xs text-gray-500">LINE 綁定</span>
											<span v-if="member.line_bound" class="text-xs text-green-600 flex items-center">
												<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
												</svg>
												已綁定
											</span>
											<span v-else class="text-xs text-gray-400 flex items-center">
												<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
												</svg>
												未綁定
											</span>
										</div>
										<div v-if="member.seller_status" class="flex items-center justify-between">
											<span class="text-xs text-gray-500">申請狀態</span>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
												:class="{
													'bg-yellow-100 text-yellow-800': member.seller_status === 'pending',
													'bg-green-100 text-green-800': member.seller_status === 'approved',
													'bg-red-100 text-red-800': member.seller_status === 'rejected'
												}">
												{{ getSellerStatusLabel(member.seller_status) }}
											</span>
										</div>
										<div class="text-xs text-gray-500 pt-2 border-t border-gray-100">
											註冊：{{ formatDate(member.registered_date) }}
										</div>
									</div>
									<button 
										@click="openEditModal(member)" 
										class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
									>
										編輯
									</button>
								</div>
							</div>
						</div>

						<!-- Members List - List View (Desktop) -->
						<div v-else-if="!isMobile" class="bg-white rounded-lg shadow overflow-hidden">
							<div class="overflow-x-auto">
								<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">使用者</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">角色</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">申請狀態</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LINE 綁定</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">註冊日期</th>
										<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									<tr v-for="member in members" :key="member.id" class="hover:bg-gray-50">
										<td class="px-6 py-4">
											<div class="flex items-center">
												<img :src="member.avatar_url" :alt="member.name" class="w-10 h-10 rounded-full border border-gray-200 flex-shrink-0 mr-3">
												<div class="min-w-0">
													<div class="text-sm font-medium text-gray-900 truncate" :title="member.name">{{ member.name }}</div>
													<div class="text-sm text-gray-500 truncate" :title="member.email">{{ member.email }}</div>
												</div>
											</div>
										</td>
										<td class="px-6 py-4">
											<select
												@change="updateMemberRole(member, $event.target.value)"
												:value="member.role_key || 'subscriber'"
												class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-1"
												:class="{
													'bg-gray-100 text-gray-800': member.role_key === 'administrator',
													'bg-gray-100 text-gray-800': member.role_key === 'buygo_admin',
													'bg-green-100 text-green-800': member.role_key === 'buygo_seller',
													'bg-purple-100 text-purple-800': member.role_key === 'buygo_helper',
													'bg-gray-100 text-gray-800': !['administrator', 'buygo_admin', 'buygo_seller', 'buygo_helper'].includes(member.role_key)
												}"
											>
												<option value="administrator">WP 管理員</option>
												<option value="buygo_admin">BuyGo 管理員</option>
												<option value="buygo_seller">賣家</option>
												<option value="buygo_helper">小幫手</option>
												<option value="subscriber">顧客</option>
											</select>
										</td>
										<td class="px-6 py-4">
											<span v-if="member.seller_status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
												:class="{
													'bg-yellow-100 text-yellow-800': member.seller_status === 'pending',
													'bg-green-100 text-green-800': member.seller_status === 'approved',
													'bg-red-100 text-red-800': member.seller_status === 'rejected'
												}">
												{{ getSellerStatusLabel(member.seller_status) }}
											</span>
											<span v-else class="text-gray-400 text-xs">-</span>
										</td>
										<td class="px-6 py-4">
											<span v-if="member.line_bound" class="text-green-600 text-sm flex items-center">
												<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
												</svg>
												已綁定
											</span>
											<span v-else class="text-gray-400 text-sm flex items-center">
												<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
												</svg>
												未綁定
											</span>
										</td>
										<td class="px-6 py-4 text-sm text-gray-500">
											{{ formatDate(member.registered_date) }}
										</td>
										<td class="px-6 py-4 text-right">
											<button 
												@click="openEditModal(member)" 
												class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
											>
												編輯
											</button>
										</td>
									</tr>
								</tbody>
							</table>
							</div>
						</div>

						<!-- Edit Role Modal -->
						<div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeEditModal">
							<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] flex flex-col">
								<!-- 標題列（固定在頂部） -->
								<div class="p-6 border-b border-gray-200 flex-shrink-0">
									<div class="flex items-center justify-between">
										<h2 class="text-xl font-semibold text-gray-900">編輯權限</h2>
										<button @click="closeEditModal" class="text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded-md p-1">
											<span class="sr-only">關閉</span>
											<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
											</svg>
										</button>
									</div>
								</div>
								
								<!-- 內容區域（可滾動） -->
								<div class="p-6 overflow-y-auto flex-1">

									<div v-if="editLoading" class="flex items-center justify-center py-8">
										<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
										<span class="ml-3 text-gray-600">載入中...</span>
									</div>

									<div v-else class="space-y-4">
										<div v-if="editError" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
											<p class="text-red-800 text-sm">{{ editError }}</p>
										</div>

										<div class="flex items-center gap-4 mb-6">
											<img :src="editingMember?.avatar_url" :alt="editingMember?.name" class="h-12 w-12 rounded-full border border-gray-200">
											<div>
												<h4 class="text-base font-medium text-gray-900">{{ editingMember?.name }}</h4>
												<p class="text-sm text-gray-500">{{ editingMember?.email }}</p>
											</div>
										</div>

										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1">設定角色身分</label>
											<select
												v-model="editingRole"
												@change="handleRoleChange"
												class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 text-sm px-4 py-2.5 bg-white appearance-none cursor-pointer"
												style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1em 1em; padding-right: 2rem;"
											>
												<option value="administrator">WP 管理員</option>
												<option value="buygo_admin">BuyGo 管理員</option>
												<option value="buygo_seller">賣家</option>
												<option value="buygo_helper">小幫手</option>
												<option value="subscriber">顧客</option>
											</select>
											<p class="mt-2 text-xs text-gray-500">注意：變更角色將立即影響該使用者可使用的功能權限。</p>
										</div>

										<div v-if="editingRole === 'buygo_seller' || editingRole === 'buygo_admin' || editingRole === 'administrator' || editingRole === 'buygo_helper'">
											<label class="block text-sm font-medium text-gray-700 mb-1">發文頻道</label>
											<select
												v-model="editingPostChannelId"
												class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 text-sm px-4 py-2.5 bg-white appearance-none cursor-pointer"
												style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1em 1em; padding-right: 2rem;"
											>
												<option :value="null">請選擇頻道</option>
												<option v-for="space in spaces" :key="space.id" :value="space.id">{{ space.title }}</option>
											</select>
											<p class="mt-2 text-xs text-gray-500">
												<span v-if="editingRole === 'buygo_seller'">選擇賣家發文時使用的 FluentCommunity 頻道。</span>
												<span v-else-if="editingRole === 'buygo_admin'">選擇 BuyGo 管理員發文時使用的 FluentCommunity 頻道。</span>
												<span v-else-if="editingRole === 'administrator'">選擇 WP 管理員發文時使用的 FluentCommunity 頻道。</span>
												<span v-else-if="editingRole === 'buygo_helper'">選擇小幫手發文時使用的 FluentCommunity 頻道。</span>
											</p>
										</div>
									</div>
								</div>
								
								<!-- 底部按鈕列（固定在底部） -->
								<div v-if="!editLoading" class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-200 flex-shrink-0">
									<button @click="closeEditModal" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
										取消
									</button>
									<button @click="saveRole" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
										儲存設定
									</button>
								</div>
							</div>
						</div>

						<!-- Empty State -->
						<div v-if="!loading && !error && members.length === 0" class="text-center py-12">
							<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
							</svg>
							<h3 class="mt-2 text-sm font-medium text-gray-900">沒有找到會員</h3>
							<p class="mt-1 text-sm text-gray-500">請嘗試調整搜尋條件或篩選器</p>
						</div>

						<!-- Pagination -->
						<div v-if="!loading && !error && pagination.total_pages > 0" class="px-4 py-3 bg-white border-t border-gray-200 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
							<div class="text-sm text-gray-700">
								第 {{ pagination.page }} 頁，共 {{ pagination.total_pages }} 頁
							</div>
							<div class="flex items-center gap-2">
								<span class="text-sm text-gray-700">每頁</span>
								<select
									v-model="pagination.per_page"
									@change="loadMembers"
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
									:disabled="pagination.page <= 1"
									class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400"
								>
									上一頁
								</button>
								<button
									@click="goToPage(pagination.page + 1)"
									:disabled="pagination.page >= pagination.total_pages"
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
	`,
	data() {
		// 動態計算 basePath：從目前網址找出 /buygo-portal 的前綴
		const pathname = window.location.pathname;
		const portalIndex = pathname.indexOf('/buygo-portal');
		const basePath = portalIndex !== -1 
			? pathname.substring(0, portalIndex) + '/buygo-portal'
			: '/buygo-portal';
		
		return {
			members: [],
			loading: false,
			error: null,
			searchQuery: '',
			roleFilter: '',
			isMobile: window.innerWidth < 768,
			viewMode: 'list', // 'grid' or 'list'
			pagination: {
				total: 0,
				page: 1,
				per_page: 5,
				total_pages: 0,
			},
			searchTimeout: null,
			// Smart search state
			showSuggestions: false,
			searchLoading: false,
			suggestions: [],
			// Edit role modal state
			showEditModal: false,
			editLoading: false,
			editError: null,
			editingMember: null,
			editingRole: '',
			editingPostChannelId: null,
			spaces: [],
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
		// Check initial screen size and set view mode
		this.checkMobile();
		this.updateViewMode();
		// Listen for resize events
		window.addEventListener( 'resize', this.handleResize );
		// Load members and spaces
		this.loadMembers();
		this.loadSpaces();
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
		// Recent members for search suggestions: Top 5 matching, or top 5 recent if empty
		recentMembers() {
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
		async loadMembers() {
			this.loading = true;
			this.error = null;

			try {
				const params = new URLSearchParams({
					page: this.pagination.page,
					per_page: this.pagination.per_page,
					search: this.searchQuery,
					role: this.roleFilter,
				});

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/members?${params}`,
					{
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				const result = await response.json();
				if (result.success) {
					this.members = result.data.members || [];
					this.pagination = result.data.pagination || this.pagination;
				} else {
					throw new Error(result.message || '載入會員列表失敗');
				}
			} catch (error) {
				this.error = error.message || '載入會員列表時發生錯誤';
				console.error('Members load error:', error);
			} finally {
				this.loading = false;
			}
		},
		debouncedSearch() {
			clearTimeout(this.searchTimeout);
			this.searchTimeout = setTimeout(() => {
				this.pagination.page = 1; // Reset to first page
				this.loadMembers();
			}, 500);
		},
		handleSearchInput() {
			// Show suggestions when typing
			if (this.searchQuery.length > 0) {
				this.loadSuggestions();
			} else {
				this.showSuggestions = false;
				this.suggestions = [];
			}
			// Also trigger debounced search for full list
			this.debouncedSearch();
		},
		handleSearchFocus() {
			// Show suggestions when focused (always show recent members or search results)
			this.showSuggestions = true;
			if ( this.searchQuery.length > 0 ) {
				this.loadSuggestions();
			} else {
				// Show recent members when focused but no query
				this.loadRecentMembers();
			}
		},
		handleSearchBlur() {
			// Delay hiding suggestions to allow click events
			setTimeout(() => {
				this.showSuggestions = false;
			}, 200);
		},
		async loadRecentMembers() {
			// Load recent members when search is empty
			this.searchLoading = true;
			try {
				const params = new URLSearchParams({
					page: 1,
					per_page: 5, // Show latest 5 items
					role: this.roleFilter,
				});

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/members?${params}`,
					{
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}`);
				}

				const result = await response.json();
				if (result.success && result.data && result.data.members) {
					this.suggestions = result.data.members.slice(0, 5);
				} else {
					this.suggestions = [];
				}
			} catch (error) {
				console.error('Failed to load recent members:', error);
				this.suggestions = [];
			} finally {
				this.searchLoading = false;
			}
		},
		async loadSuggestions(query = null) {
			const searchTerm = query !== null ? query : this.searchQuery;
			if (searchTerm.length === 0 && query === null) {
				// If no query, load recent members instead
				this.loadRecentMembers();
				return;
			}

			this.searchLoading = true;
			this.showSuggestions = true;

			try {
				const params = new URLSearchParams({
					q: searchTerm,
					limit: 5, // Show latest 5 items
				});

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1'}/users/search?${params}`,
					{
						headers: {
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
					}
				);

				if (!response.ok) {
					throw new Error(`HTTP ${response.status}`);
				}

				const results = await response.json();
				this.suggestions = Array.isArray(results) ? results.slice(0, 5) : [];
			} catch (error) {
				console.error('Failed to load suggestions:', error);
				this.suggestions = [];
			} finally {
				this.searchLoading = false;
			}
		},
		selectSuggestion(user) {
			// Set search query and trigger full search
			this.searchQuery = user.display_name || user.name || user.email;
			this.showSuggestions = false;
			this.pagination.page = 1;
			this.loadMembers();
		},
		clearSearch() {
			this.searchQuery = '';
			this.showSuggestions = false;
			this.suggestions = [];
			this.pagination.page = 1;
			this.loadMembers();
		},
		checkMobile() {
			this.isMobile = window.innerWidth < 768;
		},
		updateViewMode() {
			// If mobile, force grid view
			if ( this.isMobile ) {
				this.viewMode = 'grid';
			} else {
				// Desktop: use saved preference or default to list
				const savedViewMode = localStorage.getItem( 'buygo_members_view_mode' );
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
		setViewMode(mode) {
			// Only allow manual change on desktop
			if ( ! this.isMobile ) {
				this.viewMode = mode;
				localStorage.setItem('buygo_members_view_mode', mode);
			}
		},
		goToPage(page) {
			if (page >= 1 && page <= this.pagination.total_pages) {
				this.pagination.page = page;
				this.loadMembers();
			}
		},
		getSellerStatusLabel(status) {
			const labels = {
				pending: '申請中',
				approved: '已核准',
				rejected: '已拒絕',
			};
			return labels[status] || status;
		},
		formatDate(dateString) {
			if (!dateString) return '-';
			const date = new Date(dateString);
			return date.toLocaleDateString('zh-TW', {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
			});
		},
		openEditModal(member) {
			this.editingMember = member;
			// Set initial role based on member's roles
			const roles = member.roles || [];
			if (roles.includes('administrator')) {
				this.editingRole = 'administrator';
			} else if (roles.includes('buygo_admin')) {
				this.editingRole = 'buygo_admin';
			} else if (roles.includes('buygo_seller')) {
				this.editingRole = 'buygo_seller';
			} else if (roles.includes('buygo_helper')) {
				this.editingRole = 'buygo_helper';
			} else {
				this.editingRole = 'subscriber';
			}
			// 載入會員的發文頻道 ID
			this.editingPostChannelId = member.post_channel_id || null;
			this.showEditModal = true;
			this.editError = null;
		},
		closeEditModal() {
			this.showEditModal = false;
			this.editingMember = null;
			this.editingRole = '';
			this.editingPostChannelId = null;
			this.editError = null;
		},
		handleRoleChange() {
			// 如果角色不支援頻道設定，清除頻道選擇
			const rolesWithChannel = ['buygo_seller', 'buygo_admin', 'administrator', 'buygo_helper'];
			if (!rolesWithChannel.includes(this.editingRole)) {
				this.editingPostChannelId = null;
			}
		},
		async loadSpaces() {
			try {
				const response = await fetch(
					`/wp-json/buygo/v1/spaces`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
					}
				);

				if (!response.ok) {
					throw new Error('Failed to load spaces');
				}

				const result = await response.json();
				if (result.success) {
					this.spaces = result.data || [];
				}
			} catch (error) {
				console.error('Failed to load spaces:', error);
				this.spaces = [];
			}
		},
		async updateMemberRole(member, newRole) {
			try {
				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/members/${member.id}`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
						body: JSON.stringify({
							roles: [newRole],
						}),
					}
				);

				if (!response.ok) {
					throw new Error('更新角色失敗');
				}

				const result = await response.json();
				if (result.success) {
					// Update local member data
					member.role_key = newRole;
					member.role_display = this.getRoleDisplayName(newRole);
					// Reload members list
					await this.loadMembers();
				} else {
					throw new Error(result.message || '更新角色失敗');
				}
			} catch (error) {
				console.error('Update member role error:', error);
				alert('更新角色失敗: ' + (error.message || '未知錯誤'));
				// Reload to restore original role
				await this.loadMembers();
			}
		},
		getRoleDisplayName(roleKey) {
			const labels = {
				administrator: 'WP 管理員',
				buygo_admin: 'BuyGo 管理員',
				buygo_seller: '賣家',
				buygo_helper: '小幫手',
				subscriber: '顧客',
			};
			return labels[roleKey] || roleKey;
		},
		async saveRole() {
			if (!this.editingMember || !this.editingRole) {
				return;
			}

			this.editLoading = true;
			this.editError = null;

			try {
				const payload = {
					roles: [this.editingRole],
				};

				// 如果角色支援頻道設定，發送頻道 ID
				const rolesWithChannel = ['buygo_seller', 'buygo_admin', 'administrator', 'buygo_helper'];
				if (rolesWithChannel.includes(this.editingRole)) {
					payload.post_channel_id = this.editingPostChannelId || null;
				}

				const response = await fetch(
					`${window.buygoFrontendPortalData?.apiUrl || '/wp-json/buygo/v1/portal'}/members/${this.editingMember.id}`,
					{
						method: 'PUT',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': window.buygoFrontendPortalData?.nonce || '',
						},
						credentials: 'include',
						body: JSON.stringify(payload),
					}
				);

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({ message: '更新失敗' }));
					throw new Error(errorData.message || `HTTP ${response.status}`);
				}

				const result = await response.json();
				if (result.success) {
					// Reload members list
					await this.loadMembers();
					// Close modal
					this.closeEditModal();
				} else {
					throw new Error(result.message || '更新失敗');
				}
			} catch (error) {
				this.editError = error.message || '更新角色時發生錯誤';
				console.error('Save role error:', error);
			} finally {
				this.editLoading = false;
			}
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
