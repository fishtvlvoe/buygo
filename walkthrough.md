# BuyGo Role Permission Plugin - Walkthrough & Verification

## Overview
This document outlines the steps to verify the functionality of the BuyGo Role Permission plugin, including the newly implemented **Admin UI Redesign** and fixed **Frontend Pages**.

## 1. Prerequisites
- **Plugin:** `BuyGo Role Permission` must be activated.
- **Environment:** Local development (`test.buygo.me`).
- **User:** Admin user for backend; generic user for frontend testing.

## 2. Admin Panel Validation
- **Access:** Go to `WP Admin > BuyGo 角色管理` (BuyGo Role Management).
- **UI Check:** 
    - Verify the new **Fluent-like Design** (clean white cards, modern headers, "Role List" and "Usage Guide" tabs).
    - Ensure the default WordPress header is hidden and replaced by the custom one.
- **Role Management Tab:**
    - Verify the list shows users with `buygo_seller` and `buygo_helper` roles.
    - Test the "Change Role" (變更角色) dropdown. Updates should happen via AJAX.
- **Usage Guide Tab:**
    - Click the **"使用說明"** tab.
    - Verify that all shortcodes (`[buygo_seller_application_form]`, `[buygo_seller_helpers]`, etc.) are listed with descriptions.
- **Seller Applications Submenu:**
    - Go to `賣家申請` submenu.
    - Check for pending applications.
    - Verify "Approve" (核准) and "Reject" (拒絕) buttons work.
- **Helper Management Submenu:**
    - Go to `小幫手管理` submenu.
    - Verify the list of helper-seller relationships.

## 3. Frontend Shortcode Verification

### A. Seller Application
- **Page:** `/seller-app-test/` (or any page with `[buygo_seller_application_form]`)
- **Test:**
    1. As a normal user (Subscriber), verify the application form appears.
    2. Fill out and submit.
    3. Check `[buygo_seller_application_status]` on the same or result page to see "Pending" status.

### B. Helper Management
- **Page:** `/helper-test/` (or `[buygo_seller_helpers]`)
- **Test:**
    1. As a **Seller** (`buygo_seller`), verify you can see the "Add Helper" form.
    2. Add a helper (by email or ID).
    3. Set permissions (View Orders, etc.).
    4. As a non-seller, verify you see "Access Denied".

### C. LINE Binding
- **Page:** `/line-test/` (or `[buygo_line_binding]`)
- **Test:**
    1. Visit the page.
    2. Click "Generate Binding Code" (產生綁定碼).
    3. Verify a 6-digit code appears (e.g., `123456`).
    4. (Optional) Simulate LINE Webhook to complete binding if testing API.

## 4. API & Integration
- **REST API:** Check `/wp-json/buygo/v1/check-permission` (requires Auth).
- **FluentCRM:** Changes to roles should sync to FluentCRM automatically (check logs if needed).

## 5. Troubleshooting
- **Admin UI Not Updating:** Deactivate and Reactivate the plugin to clear PHP class caches.
- **Shortcodes Not Rendering:** Ensure the page content has the raw shortcode text and not pre-formatted code blocks.
