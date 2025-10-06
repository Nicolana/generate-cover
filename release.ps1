# Generate Cover Plugin Release Script (PowerShell Version)
# Usage: .\release.ps1

# --- 配置变量 ---
$ErrorActionPreference = "Stop" # 遇到错误时立即停止，相当于 Bash 的 `set -e`
$Repo = "Nicolana/generate-cover"
$Version = "1.1.1"
$Tag = "v$Version"
$PluginFile = "build\generate-cover.zip"
$ReleaseNotes = "release-notes.md"

Write-Host "🚀 开始发布 Generate Cover v$Version..." -ForegroundColor Green

# 1. 检查文件是否存在
if (-not (Test-Path $PluginFile)) {
    Write-Host "❌ 错误: 插件文件 $PluginFile 不存在" -ForegroundColor Red
    Write-Host "请先运行 php build.php 生成插件包" -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path $ReleaseNotes)) {
    Write-Host "❌ 错误: 发布说明文件 $ReleaseNotes 不存在" -ForegroundColor Red
    exit 1
}

# 2. 检查 GitHub CLI 是否安装
if (-not (Get-Command -Name gh -ErrorAction SilentlyContinue)) {
    Write-Host "❌ 错误: GitHub CLI 未安装" -ForegroundColor Red
    Write-Host "请访问 https://cli.github.com/ 安装 GitHub CLI" -ForegroundColor Yellow
    exit 1
}

# 3. 检查是否已登录
try {
    gh auth status
} catch {
    Write-Host "❌ 错误: 未登录 GitHub CLI" -ForegroundColor Red
    Write-Host "请运行: gh auth login" -ForegroundColor Yellow
    exit 1
}

# 4. 检查标签是否已存在
try {
    gh release view $Tag
    # 如果命令成功执行，说明标签已存在
    Write-Host "⚠️  警告: 标签 $Tag 已存在" -ForegroundColor Yellow

    $response = Read-Host -Prompt "是否要删除并重新创建? (y/N)"
    if ($response -match '^[Yy]$') {
        Write-Host "🗑️  删除现有标签..."
        gh release delete $Tag --yes
    } else {
        Write-Host "❌ 取消发布" -ForegroundColor Red
        exit 1
    }
} catch {
    # 如果 `gh release view` 失败并抛出异常，说明标签不存在，这是正常情况
    Write-Host "✅ 标签 $Tag 不存在，可以继续创建。"
}

# 5. 创建发布
Write-Host "📦 创建 GitHub Release..." -ForegroundColor Cyan
gh release create $Tag `
    --title "Generate Cover v$Version" `
    --notes-file $ReleaseNotes `
    --latest `
    $PluginFile

# 检查创建是否成功
if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ 发布成功!" -ForegroundColor Green
    Write-Host "🔗 发布地址: https://github.com/$Repo/releases/tag/$Tag" -ForegroundColor Cyan

    Write-Host "" # 空行
    Write-Host "📋 发布信息:" -ForegroundColor White
    Write-Host "   版本: $Version"
    Write-Host "   标签: $Tag"
    Write-Host "   文件: $PluginFile"
    Write-Host "   仓库: $Repo"
} else {
    Write-Host "❌ 发布失败，GitHub CLI 返回了错误。" -ForegroundColor Red
    exit $LASTEXITCODE
}
