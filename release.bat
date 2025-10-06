@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM Generate Cover Plugin Release Script for Windows
REM 使用方法: release.bat

set REPO=Nicolana/generate-cover
set VERSION=1.1.0
set TAG=v%VERSION%
set PLUGIN_FILE=build\generate-cover-v%VERSION%.zip
set RELEASE_NOTES=release-notes.md

echo 🚀 开始发布 Generate Cover v%VERSION%...

REM 检查文件是否存在
if not exist "%PLUGIN_FILE%" (
    echo ❌ 错误: 插件文件 %PLUGIN_FILE% 不存在
    echo 请先运行 php build.php 生成插件包
    pause
    exit /b 1
)

if not exist "%RELEASE_NOTES%" (
    echo ❌ 错误: 发布说明文件 %RELEASE_NOTES% 不存在
    pause
    exit /b 1
)

REM 检查 GitHub CLI 是否安装
gh --version >nul 2>&1
if errorlevel 1 (
    echo ❌ 错误: GitHub CLI 未安装
    echo 请访问 https://cli.github.com/ 安装 GitHub CLI
    pause
    exit /b 1
)

REM 检查是否已登录
gh auth status >nul 2>&1
if errorlevel 1 (
    echo ❌ 错误: 未登录 GitHub CLI
    echo 请运行: gh auth login
    pause
    exit /b 1
)

REM 检查标签是否已存在
gh release view "%TAG%" >nul 2>&1
if not errorlevel 1 (
    echo ⚠️  警告: 标签 %TAG% 已存在
    set /p choice="是否要删除并重新创建? (y/N): "
    if /i "!choice!"=="y" (
        echo 🗑️  删除现有标签...
        gh release delete "%TAG%" --yes
    ) else (
        echo ❌ 取消发布
        pause
        exit /b 1
    )
)

REM 创建发布
echo 📦 创建 GitHub Release...
gh release create "%TAG%" --title "Generate Cover v%VERSION%" --notes-file "%RELEASE_NOTES%" --latest "%PLUGIN_FILE%"

if errorlevel 1 (
    echo ❌ 发布失败
    pause
    exit /b 1
)

echo ✅ 发布成功!
echo 🔗 发布地址: https://github.com/%REPO%/releases/tag/%TAG%

echo.
echo 📋 发布信息:
echo    版本: %VERSION%
echo    标签: %TAG%
echo    文件: %PLUGIN_FILE%
echo    仓库: %REPO%

pause
