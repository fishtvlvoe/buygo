#!/bin/bash

# BuyGo 外掛部署腳本
# 用途：打包外掛，排除開發檔案，供上傳到正式環境

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_NAME="buygo"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DEPLOY_DIR="${PLUGIN_DIR}/../${PLUGIN_NAME}-deploy-${TIMESTAMP}"
ZIP_FILE="${PLUGIN_DIR}/../${PLUGIN_NAME}-deploy-${TIMESTAMP}.zip"

echo "▋ BuyGo 外掛部署腳本"
echo "外掛目錄：${PLUGIN_DIR}"
echo ""

# 建立部署目錄
echo "建立部署目錄..."
mkdir -p "${DEPLOY_DIR}"

# 複製檔案，排除開發工具
echo "複製檔案（排除開發工具）..."
rsync -av \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='.vscode' \
  --exclude='.idea' \
  --exclude='*.log' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  --exclude='resources/admin/src' \
  --exclude='resources/admin/node_modules' \
  --exclude='resources/admin/package.json' \
  --exclude='resources/admin/package-lock.json' \
  --exclude='resources/admin/vite.config.js' \
  --exclude='resources/admin/tailwind.config.js' \
  --exclude='resources/admin/postcss.config.js' \
  --exclude='resources/admin/tsconfig.json' \
  --exclude='tests' \
  --exclude='docs' \
  --exclude='tech-docs' \
  --exclude='deploy.sh' \
  --exclude='*.zip' \
  "${PLUGIN_DIR}/" "${DEPLOY_DIR}/${PLUGIN_NAME}/"

# 建立 ZIP 檔案
echo "建立 ZIP 檔案..."
cd "${PLUGIN_DIR}/.."
zip -r "${ZIP_FILE}" "${PLUGIN_NAME}-deploy-${TIMESTAMP}" -q

# 顯示結果
echo ""
echo "▋ 部署完成"
echo "ZIP 檔案：${ZIP_FILE}"
echo "檔案大小：$(du -sh "${ZIP_FILE}" | cut -f1)"
echo ""
echo "部署目錄：${DEPLOY_DIR}"
echo "（可手動檢查後刪除）"
echo ""
echo "下一步：上傳 ${ZIP_FILE} 到正式環境"
