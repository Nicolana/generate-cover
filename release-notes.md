## 🎉 Generate Cover v1.1.1 发布

### 🔧 改进
- **优化插件升级器**：增强错误处理，添加详细的错误日志记录
- **改进HTTP请求**：增加User-Agent头和更长的超时时间
- **数据验证增强**：添加JSON解析和数据结构验证
- **版本验证**：添加语义化版本号格式验证
- **下载链接验证**：确保下载链接存在才返回更新信息

### 🐛 修复
- 修复压缩包命名问题，移除版本号以符合WordPress标准
- 修复插件升级器中的下载链接查找逻辑
- 修复发布脚本中的文件路径问题

### 📦 安装说明
1. 下载 `generate-cover.zip`
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

### 🔗 相关链接
- [GitHub 仓库](https://github.com/Nicolana/generate-cover)
- [问题反馈](https://github.com/Nicolana/generate-cover/issues)
- [功能请求](https://github.com/Nicolana/generate-cover/discussions)
