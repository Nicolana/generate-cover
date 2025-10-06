<?php
/**
 * 插件升级管理类
 * 处理插件的版本检查和升级逻辑
 */

namespace GenerateCover;

class Plugin_Updater {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $cache_key;
    private $cache_allowed;
    
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = GENERATE_COVER_VERSION;
        $this->cache_key = 'generate_cover_updater';
        $this->cache_allowed = false;
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }
    
    /**
     * 检查插件更新
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // 获取远程版本信息
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->version, $remote_version, '<') && $this->is_valid_version($remote_version)) {
            $res = new \stdClass();
            $res->slug = $this->plugin_slug;
            $res->plugin = $this->plugin_slug;
            $res->new_version = $remote_version;
            $res->tested = $this->get_remote_tested();
            $res->package = $this->get_remote_package();
            $res->url = $this->get_remote_url();
            
            $transient->response[$res->plugin] = $res;
        }
        
        return $transient;
    }
    
    /**
     * 获取插件信息
     */
    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return false;
        }
        
        $remote = $this->get_remote_info();
        
        if (!$remote) {
            return false;
        }
        
        $res = new \stdClass();
        $res->name = $remote->name;
        $res->slug = $remote->slug;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->author = $remote->author;
        $res->author_profile = $remote->author_profile;
        $res->download_link = $remote->download_link;
        $res->trunk = $remote->download_link;
        $res->requires_php = $remote->requires_php;
        $res->last_updated = $remote->last_updated;
        $res->sections = [
            'description' => $remote->sections->description,
            'installation' => $remote->sections->installation,
            'changelog' => $remote->sections->changelog
        ];
        
        if (!empty($remote->banners)) {
            $res->banners = [
                'low' => $remote->banners->low,
                'high' => $remote->banners->high
            ];
        }
        
        return $res;
    }
    
    /**
     * 安装后处理
     */
    public function post_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        
        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->plugin_slug);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;
        
        if (is_plugin_active($this->plugin_slug)) {
            activate_plugin($this->plugin_slug);
        }
        
        return $result;
    }
    
    /**
     * 获取远程版本信息
     */
    private function get_remote_version() {
        $remote = $this->get_remote_info();
        return $remote ? $remote->version : false;
    }
    
    /**
     * 获取远程测试信息
     */
    private function get_remote_tested() {
        $remote = $this->get_remote_info();
        return $remote ? $remote->tested : false;
    }
    
    /**
     * 获取远程包地址
     */
    private function get_remote_package() {
        $remote = $this->get_remote_info();
        return $remote ? $remote->download_link : false;
    }
    
    /**
     * 获取远程URL
     */
    private function get_remote_url() {
        $remote = $this->get_remote_info();
        return $remote ? $remote->url : false;
    }
    
    /**
     * 获取远程插件信息
     */
    private function get_remote_info() {
        $remote = get_transient($this->cache_key);
        
        if (false === $remote || !$this->cache_allowed) {
            $response = wp_remote_get(
                'https://api.github.com/repos/Nicolana/generate-cover/releases/latest',
                [
                    'timeout' => 15,
                    'headers' => [
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                    ]
                ]
            );
            
            if (is_wp_error($response)) {
                error_log('Generate Cover Updater: ' . $response->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log("Generate Cover Updater: HTTP {$response_code} error");
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                error_log('Generate Cover Updater: Empty response body');
                return false;
            }
            
            $release_data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Generate Cover Updater: JSON decode error - ' . json_last_error_msg());
                return false;
            }
            
            if (!isset($release_data['tag_name']) || !isset($release_data['assets'])) {
                error_log('Generate Cover Updater: Invalid release data structure');
                return false;
            }
            
            // 构建WordPress插件更新格式
            $remote = (object) [
                'name' => 'Generate Cover',
                'slug' => $this->plugin_slug,
                'version' => ltrim($release_data['tag_name'], 'v'),
                'tested' => '6.4',
                'requires' => '5.0',
                'requires_php' => '7.4',
                'author' => 'Nicolana',
                'author_profile' => 'https://github.com/Nicolana',
                'download_link' => $this->get_download_url($release_data),
                'url' => 'https://github.com/Nicolana/generate-cover',
                'last_updated' => $release_data['published_at'],
                'sections' => (object) [
                    'description' => $this->get_release_description($release_data),
                    'installation' => '1. 下载最新版本的插件包\n2. 解压到 /wp-content/plugins/ 目录\n3. 在WordPress后台激活插件',
                    'changelog' => $this->get_release_changelog($release_data)
                ]
            ];
            
            // 验证下载链接是否存在
            if (empty($remote->download_link)) {
                error_log('Generate Cover Updater: No valid download link found');
                return false;
            }
            
            set_transient($this->cache_key, $remote, 7200); // 2小时缓存
        }
        
        return $remote;
    }
    
    /**
     * 获取下载URL
     */
    private function get_download_url($release_data) {
        // 查找插件ZIP文件（不包含版本号）
        foreach ($release_data['assets'] as $asset) {
            if ($asset['name'] === 'generate-cover.zip') {
                return $asset['browser_download_url'];
            }
        }
        return null;
    }
    
    /**
     * 获取发布描述
     */
    private function get_release_description($release_data) {
        $description = "一个强大的WordPress插件，使用AI技术自动为博客文章生成封面图片。\n\n";
        $description .= "**功能特性：**\n";
        $description .= "• 🤖 AI智能分析：使用OpenRouter API分析文章内容\n";
        $description .= "• 🎨 自动生成封面：调用即梦AI 4.0生成高质量封面\n";
        $description .= "• 📝 文章摘要：自动生成文章摘要，提升SEO效果\n";
        $description .= "• 🔄 批量处理：支持为多篇文章批量生成封面\n";
        $description .= "• ✨ 额外提示词：支持自定义风格描述\n";
        $description .= "• 🖼️ 风格参考图片：支持上传参考图片定义封面风格\n";
        $description .= "• 📋 图片粘贴功能：支持从剪贴板直接粘贴图片\n\n";
        $description .= "**安装要求：**\n";
        $description .= "• WordPress 5.0 或更高版本\n";
        $description .= "• PHP 7.4 或更高版本\n";
        $description .= "• 有效的OpenRouter API密钥\n";
        $description .= "• 有效的即梦AI API密钥";
        
        return $description;
    }
    
    /**
     * 获取发布更新日志
     */
    private function get_release_changelog($release_data) {
        $changelog = "**版本 " . ltrim($release_data['tag_name'], 'v') . "**\n\n";
        $changelog .= $release_data['body'] . "\n\n";
        $changelog .= "**下载地址：**\n";
        $changelog .= "[GitHub Release](" . $release_data['html_url'] . ")";
        
        return $changelog;
    }
    
    /**
     * 手动检查更新
     */
    public function manual_check_update() {
        delete_transient($this->cache_key);
        $this->cache_allowed = true;
        
        $transient = get_site_transient('update_plugins');
        $transient = $this->check_update($transient);
        set_site_transient('update_plugins', $transient);
        
        return $transient;
    }
    
    /**
     * 验证版本号格式
     */
    private function is_valid_version($version) {
        // 检查版本号格式是否符合语义化版本规范
        return preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9\-]+)?(\+[a-zA-Z0-9\-]+)?$/', $version);
    }
    
    /**
     * 显示更新通知
     */
    public function show_update_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $update_plugins = get_site_transient('update_plugins');
        
        if (isset($update_plugins->response[$this->plugin_slug])) {
            $update = $update_plugins->response[$this->plugin_slug];
            $update_url = wp_nonce_url(
                self_admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_slug),
                'upgrade-plugin_' . $this->plugin_slug
            );
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Generate Cover</strong> 有新版本可用！';
            echo ' <a href="' . esc_url($update_url) . '">立即更新到版本 ' . esc_html($update->new_version) . '</a></p>';
            echo '</div>';
        }
    }
}
