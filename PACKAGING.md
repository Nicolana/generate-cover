# WordPress插件打包指南

## 自动打包（推荐）

### 使用打包脚本

1. 在插件根目录运行打包脚本：
```bash
php build-plugin.php
```

2. 脚本会自动：
   - 创建 `build/` 目录
   - 复制必要文件到 `build/generate-cover/`
   - 创建ZIP压缩包 `generate-cover-v1.0.0.zip`

### 打包结果

打包完成后，您会得到：
- `build/generate-cover/` - 插件文件夹
- `build/generate-cover-v1.0.0.zip` - 可分发的压缩包

## 手动打包

### 方法一：使用文件管理器

1. **创建插件文件夹**
   - 创建名为 `generate-cover` 的文件夹

2. **复制必要文件**
   ```
   generate-cover/
   ├── generate-cover.php
   ├── includes/
   │   ├── class-admin-settings.php
   │   ├── class-cover-generator.php
   │   ├── class-jimeng-ai.php
   │   └── class-openrouter-api.php
   ├── assets/
   │   ├── admin.css
   │   └── admin.js
   └── README.md
   ```

3. **创建压缩包**
   - 选中 `generate-cover` 文件夹
   - 右键选择"压缩"或"添加到压缩文件"
   - 选择ZIP格式
   - 命名为 `generate-cover-v1.0.0.zip`

### 方法二：使用命令行

#### Windows (PowerShell)
```powershell
# 创建插件目录
New-Item -ItemType Directory -Path "build\generate-cover"

# 复制文件
Copy-Item "generate-cover.php" "build\generate-cover\"
Copy-Item "includes" "build\generate-cover\" -Recurse
Copy-Item "assets" "build\generate-cover\" -Recurse
Copy-Item "README.md" "build\generate-cover\"

# 创建ZIP压缩包
Compress-Archive -Path "build\generate-cover\*" -DestinationPath "build\generate-cover-v1.0.0.zip"
```

#### Linux/macOS
```bash
# 创建构建目录
mkdir -p build/generate-cover

# 复制文件
cp generate-cover.php build/generate-cover/
cp -r includes build/generate-cover/
cp -r assets build/generate-cover/
cp README.md build/generate-cover/

# 创建ZIP压缩包
cd build
zip -r generate-cover-v1.0.0.zip generate-cover/
```

## 打包前检查清单

### ✅ 必要文件检查
- [ ] `generate-cover.php` - 主插件文件
- [ ] `includes/` 目录及所有类文件
- [ ] `assets/` 目录及CSS/JS文件
- [ ] `README.md` 说明文档

### ✅ 插件头部信息检查
确保 `generate-cover.php` 包含正确的插件头部：
```php
/**
 * Plugin Name: Generate Cover
 * Plugin URI: https://example.com/generate-cover
 * Description: 自动生成博客封面图片的WordPress插件，使用AI技术生成文章封面和文本总结
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: generate-cover
 */
```

### ✅ 排除文件检查
确保不包含以下文件：
- [ ] `debug.php`
- [ ] `ajax-test.php`
- [ ] `user-debug.php`
- [ ] `build-plugin.php`
- [ ] `.git/` 目录
- [ ] 临时文件和日志文件

### ✅ 代码质量检查
- [ ] 所有PHP文件语法正确
- [ ] 没有调试代码残留
- [ ] API密钥等敏感信息已移除
- [ ] 文件权限正确（644 for files, 755 for directories）

## 分发准备

### 版本管理
1. 更新版本号：
   - `generate-cover.php` 中的 `Version`
   - `GENERATE_COVER_VERSION` 常量
   - 压缩包文件名

2. 更新变更日志：
   - 在 `README.md` 中记录新功能
   - 更新版本历史

### 测试检查
1. **功能测试**
   - [ ] 插件激活/停用正常
   - [ ] 所有功能正常工作
   - [ ] 没有PHP错误或警告

2. **兼容性测试**
   - [ ] 不同WordPress版本兼容
   - [ ] 不同PHP版本兼容
   - [ ] 不同主题兼容

3. **安全检查**
   - [ ] 所有用户输入已过滤
   - [ ] 数据库查询已准备
   - [ ] 文件上传已验证

## 分发方式

### 1. WordPress.org 官方仓库
- 需要提交到官方审核
- 遵循官方插件开发规范
- 通过SVN管理版本

### 2. 第三方市场
- CodeCanyon
- WordPress插件商店
- 自建网站分发

### 3. 直接分发
- 通过邮件发送ZIP文件
- 上传到云存储分享
- 通过网站下载页面

## 安装说明

为用户提供清晰的安装说明：

1. **下载插件**
   - 下载 `generate-cover-v1.0.0.zip`

2. **安装插件**
   - 登录WordPress管理后台
   - 进入 `插件 > 安装插件`
   - 点击"上传插件"
   - 选择ZIP文件并安装

3. **激活插件**
   - 在插件列表中找到"Generate Cover"
   - 点击"激活"

4. **配置设置**
   - 进入 `设置 > Generate Cover`
   - 配置API密钥
   - 开始使用

## 技术支持

为用户提供技术支持渠道：
- 文档链接
- 联系邮箱
- 问题反馈地址
- 更新通知方式
