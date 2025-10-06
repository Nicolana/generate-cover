#!/bin/bash

# Generate Cover Plugin Release Script
# 使用方法: ./release.sh

set -e

# 配置变量
REPO="Nicolana/generate-cover"
VERSION="1.1.0"
TAG="v$VERSION"
PLUGIN_FILE="build/generate-cover.zip"
RELEASE_NOTES="release-notes.md"

echo "🚀 开始发布 Generate Cover v$VERSION..."

# 检查文件是否存在
if [ ! -f "$PLUGIN_FILE" ]; then
    echo "❌ 错误: 插件文件 $PLUGIN_FILE 不存在"
    echo "请先运行 php build.php 生成插件包"
    exit 1
fi

if [ ! -f "$RELEASE_NOTES" ]; then
    echo "❌ 错误: 发布说明文件 $RELEASE_NOTES 不存在"
    exit 1
fi

# 检查 GitHub CLI 是否安装
if ! command -v gh &> /dev/null; then
    echo "❌ 错误: GitHub CLI 未安装"
    echo "请访问 https://cli.github.com/ 安装 GitHub CLI"
    exit 1
fi

# 检查是否已登录
if ! gh auth status &> /dev/null; then
    echo "❌ 错误: 未登录 GitHub CLI"
    echo "请运行: gh auth login"
    exit 1
fi

# 检查标签是否已存在
if gh release view "$TAG" &> /dev/null; then
    echo "⚠️  警告: 标签 $TAG 已存在"
    read -p "是否要删除并重新创建? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🗑️  删除现有标签..."
        gh release delete "$TAG" --yes
    else
        echo "❌ 取消发布"
        exit 1
    fi
fi

# 创建发布
echo "📦 创建 GitHub Release..."
gh release create "$TAG" \
    --title "Generate Cover v$VERSION" \
    --notes-file "$RELEASE_NOTES" \
    --latest \
    "$PLUGIN_FILE"

echo "✅ 发布成功!"
echo "🔗 发布地址: https://github.com/$REPO/releases/tag/$TAG"

# 显示发布信息
echo ""
echo "📋 发布信息:"
echo "   版本: $VERSION"
echo "   标签: $TAG"
echo "   文件: $PLUGIN_FILE"
echo "   仓库: $REPO"
