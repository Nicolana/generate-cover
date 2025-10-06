# GitHub Release 创建指南

## 发布步骤

### 1. 准备发布文件
确保以下文件已准备好：
- `build/generate-cover-v1.1.0.zip` - 插件包
- 更新日志内容

### 2. 创建 GitHub Release

#### 方法一：使用 GitHub Web 界面

1. 访问 [https://github.com/Nicolana/generate-cover/releases](https://github.com/Nicolana/generate-cover/releases)
2. 点击 "Create a new release" 按钮
3. 填写以下信息：

**Tag version:** `v1.1.0`
**Release title:** `Generate Cover v1.1.0`
**Description:**
```markdown
## 🎉 Generate Cover v1.1.0 发布

### 🆕 新功能
- **额外提示词功能**：支持自定义风格描述，精确控制封面生成
- **风格参考图片功能**：支持上传参考图片定义封面风格  
- **图片粘贴功能**：支持从剪贴板直接粘贴图片作为风格参考

### 🔧 改进
- **升级即梦AI接口**：适配即梦AI 4.0接口，支持更强大的图像生成能力
- **优化错误处理**：完善错误码映射，提供更友好的错误提示
- **界面优化**：改进用户界面，提升使用体验

### 🐛 修复
- 修复API调用参数和返回结果处理问题
- 修复插件升级机制，支持GitHub Releases自动更新

### 📦 安装说明
1. 下载 `generate-cover-v1.1.0.zip`
2. 解压到 WordPress 的 `/wp-content/plugins/` 目录
3. 在 WordPress 后台激活插件
4. 配置 API 密钥后即可使用

### 🔄 升级说明
如果您已经安装了旧版本，请：
1. 备份当前插件设置
2. 停用旧版本插件
3. 删除旧版本文件
4. 安装新版本
5. 重新配置设置

### 📋 系统要求
- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- 有效的 OpenRouter API 密钥
- 有效的即梦AI API 密钥
```

4. 上传文件：拖拽 `generate-cover-v1.1.0.zip` 到 "Attach binaries" 区域
5. 选择 "Set as the latest release"
6. 点击 "Publish release"

#### 方法二：使用 GitHub CLI

```bash
# 安装 GitHub CLI (如果未安装)
# Windows: winget install GitHub.cli
# macOS: brew install gh
# Linux: 参考官方文档

# 登录 GitHub
gh auth login

# 创建 Release
gh release create v1.1.0 \
  --title "Generate Cover v1.1.0" \
  --notes-file release-notes.md \
  build/generate-cover-v1.1.0.zip
```

### 3. 验证发布

发布完成后，验证以下内容：
- [ ] Release 页面显示正确
- [ ] 下载链接可访问
- [ ] 插件升级机制能检测到新版本
- [ ] 用户可以从 WordPress 后台直接更新

## 自动更新机制

插件现在支持从 GitHub Releases 自动更新：

1. **自动检测**：插件会定期检查 GitHub Releases 是否有新版本
2. **更新通知**：在 WordPress 后台显示更新通知
3. **一键更新**：用户可以直接从 WordPress 后台更新插件
4. **版本兼容**：确保升级过程不会丢失用户设置

## 发布检查清单

- [ ] 代码测试通过
- [ ] 版本号已更新
- [ ] 更新日志已准备
- [ ] 插件包已生成
- [ ] GitHub Release 已创建
- [ ] 下载链接已验证
- [ ] 自动更新机制已测试
