<?php
namespace GenerateCover;

/**
 * Plugin Name: Generate Cover
 * Plugin URI: https://example.com/generate-cover
 * Description: 自动生成博客封面图片的WordPress插件，使用AI技术生成文章封面和文本总结
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: generate-cover
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('GENERATE_COVER_VERSION', '1.0.0');
define('GENERATE_COVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GENERATE_COVER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 自动加载类文件
spl_autoload_register(function ($class) {
    $prefix = 'GenerateCover\\';
    $base_dir = GENERATE_COVER_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 插件激活和停用钩子
register_activation_hook(__FILE__, ['GenerateCover\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['GenerateCover\\Plugin', 'deactivate']);

// 初始化插件
add_action('plugins_loaded', function() {
    \GenerateCover\Plugin::get_instance();
});

class Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('wp_ajax_generate_cover', [$this, 'ajax_generate_cover']);
        add_action('wp_ajax_nopriv_generate_cover', [$this, 'ajax_generate_cover']);
        add_action('wp_ajax_regenerate_cover', [$this, 'ajax_regenerate_cover']);
        add_action('wp_ajax_get_generation_history', [$this, 'ajax_get_generation_history']);
        add_action('wp_ajax_batch_generate_covers', [$this, 'ajax_batch_generate_covers']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('publish_post', [$this, 'auto_generate_cover']);
        add_action('generate_cover_async', [$this, 'handle_async_generation']);
    }
    
    private function load_dependencies() {
        // 加载必要的类文件
        require_once GENERATE_COVER_PLUGIN_DIR . 'includes/class-openrouter-api.php';
        require_once GENERATE_COVER_PLUGIN_DIR . 'includes/class-jimeng-ai.php';
        require_once GENERATE_COVER_PLUGIN_DIR . 'includes/class-cover-generator.php';
        require_once GENERATE_COVER_PLUGIN_DIR . 'includes/class-admin-settings.php';
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Generate Cover 设置',
            'Generate Cover',
            'manage_options',
            'generate-cover-settings',
            [$this, 'admin_page']
        );
    }
    
    public function register_settings() {
        register_setting('generate_cover_settings', 'generate_cover_options');
        
        add_settings_section(
            'generate_cover_main',
            'API 配置',
            null,
            'generate-cover-settings'
        );
        
        add_settings_field(
            'openrouter_api_key',
            'OpenRouter API Key',
            [$this, 'api_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'jimeng_access_key',
            '即梦AI Access Key',
            [$this, 'access_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'jimeng_secret_key',
            '即梦AI Secret Key',
            [$this, 'secret_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'default_model',
            '默认AI模型',
            [$this, 'model_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
    }
    
    public function api_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['openrouter_api_key']) ? $options['openrouter_api_key'] : '';
        echo '<input type="text" name="generate_cover_options[openrouter_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function access_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['jimeng_access_key']) ? $options['jimeng_access_key'] : '';
        echo '<input type="text" name="generate_cover_options[jimeng_access_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function secret_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['jimeng_secret_key']) ? $options['jimeng_secret_key'] : '';
        echo '<input type="password" name="generate_cover_options[jimeng_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function model_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['default_model']) ? $options['default_model'] : 'openai/gpt-4o';
        
        $models = [
            'openai/gpt-5' => 'GPT-5',
            'openai/gpt-5-nano' => 'GPT-5 Nano',
            'openai/gpt-4o' => 'GPT-4o',
            'deepseek/deepseek-v3.2-exp' => 'DeepSeek V3.2'
        ];
        
        echo '<select name="generate_cover_options[default_model]">';
        foreach ($models as $key => $label) {
            $selected = selected($value, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Generate Cover 设置</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('generate_cover_settings');
                do_settings_sections('generate-cover-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'generate-cover-meta-box',
            'AI 封面生成',
            [$this, 'meta_box_callback'],
            'post',
            'side',
            'high'
        );
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('generate_cover_nonce', 'generate_cover_nonce');
        ?>
        <div id="generate-cover-container">
            <p>
                <button type="button" id="generate-cover-btn" class="button button-primary">
                    生成封面图片
                </button>
            </p>
            <div id="generate-cover-status" style="display: none;">
                <p><span class="spinner is-active"></span> 正在生成中...</p>
            </div>
            <div id="generate-cover-result" style="display: none;">
                <p>生成完成！</p>
                <div id="generated-image-preview"></div>
            </div>
            <div id="generate-cover-error" style="display: none;">
                <p class="error">生成失败，请检查设置或稍后重试。</p>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'generate-cover-admin',
            GENERATE_COVER_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            GENERATE_COVER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'generate-cover-admin',
            GENERATE_COVER_PLUGIN_URL . 'assets/admin.css',
            [],
            GENERATE_COVER_VERSION
        );
        
        wp_localize_script('generate-cover-admin', 'generateCover', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('generate_cover_nonce'),
            'postId' => get_the_ID()
        ]);
    }
    
    public function ajax_generate_cover() {
        check_ajax_referer('generate_cover_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('权限不足');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('文章不存在');
        }
        
        try {
            $generator = new \GenerateCover\CoverGenerator();
            $result = $generator->generate_cover($post);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('生成失败：' . $e->getMessage());
        }
    }
    
    public static function activate() {
        // 插件激活时的操作
        if (!get_option('generate_cover_options')) {
            add_option('generate_cover_options', [
                'default_model' => 'openai/gpt-4o'
            ]);
        }
    }
    
    public function ajax_regenerate_cover() {
        check_ajax_referer('generate_cover_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('权限不足');
        }
        
        $post_id = intval($_POST['post_id']);
        
        try {
            $generator = new \GenerateCover\CoverGenerator();
            $result = $generator->regenerate_cover($post_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('重新生成失败：' . $e->getMessage());
        }
    }
    
    public function ajax_get_generation_history() {
        check_ajax_referer('generate_cover_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('权限不足');
        }
        
        $post_id = intval($_POST['post_id']);
        
        try {
            $generator = new \GenerateCover\CoverGenerator();
            $history = $generator->get_generation_history($post_id);
            
            wp_send_json_success($history);
        } catch (Exception $e) {
            wp_send_json_error('获取历史记录失败：' . $e->getMessage());
        }
    }
    
    public function ajax_batch_generate_covers() {
        check_ajax_referer('batch_generate_covers', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        try {
            // 获取所有没有特色图片的文章
            $posts = get_posts([
                'numberposts' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_thumbnail_id',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            
            if (empty($posts)) {
                wp_send_json_success([
                    'success_count' => 0,
                    'fail_count' => 0,
                    'failed_posts' => []
                ]);
            }
            
            $generator = new \GenerateCover\CoverGenerator();
            $results = $generator->batch_generate_covers(wp_list_pluck($posts, 'ID'));
            
            $success_count = 0;
            $fail_count = 0;
            $failed_posts = [];
            
            foreach ($results as $post_id => $result) {
                if ($result['success']) {
                    $success_count++;
                } else {
                    $fail_count++;
                    $post = get_post($post_id);
                    $failed_posts[] = [
                        'id' => $post_id,
                        'title' => $post->post_title,
                        'error' => $result['message']
                    ];
                }
            }
            
            wp_send_json_success([
                'success_count' => $success_count,
                'fail_count' => $fail_count,
                'failed_posts' => $failed_posts
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('批量生成失败：' . $e->getMessage());
        }
    }
    
    public function auto_generate_cover($post_id) {
        $options = get_option('generate_cover_options');
        
        if (!isset($options['auto_generate']) || !$options['auto_generate']) {
            return;
        }
        
        // 检查是否已经有特色图片
        if (get_post_thumbnail_id($post_id)) {
            return;
        }
        
        // 异步生成封面
        wp_schedule_single_event(time() + 30, 'generate_cover_async', [$post_id]);
    }
    
    public function handle_async_generation($post_id) {
        try {
            $post = get_post($post_id);
            if (!$post) {
                return;
            }
            
            $generator = new \GenerateCover\CoverGenerator();
            $result = $generator->generate_cover($post);
            
            if ($result['success']) {
                $this->log_generation($post_id, 'success', '异步生成成功');
            } else {
                $this->log_generation($post_id, 'error', $result['message']);
            }
        } catch (Exception $e) {
            $this->log_generation($post_id, 'error', '异步生成异常：' . $e->getMessage());
        }
    }
    
    private function log_generation($post_id, $status, $message) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'status' => $status,
            'message' => $message,
            'post_id' => $post_id
        ];
        
        $logs = get_option('generate_cover_logs', []);
        $logs[] = $log_entry;
        
        // 只保留最近100条日志
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('generate_cover_logs', $logs);
    }
    
    public static function deactivate() {
        // 插件停用时的操作
        wp_clear_scheduled_hook('generate_cover_async');
    }
}
