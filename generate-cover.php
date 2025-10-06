<?php
namespace GenerateCover;

/**
 * Plugin Name: Generate Cover
 * Plugin URI: https://example.com/generate-cover
 * Description: 自动生成博客封面图片的WordPress插件，使用AI技术生成文章封面和文本总结
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: generate-cover
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('GENERATE_COVER_VERSION', '1.1.0');
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
        add_action('wp_ajax_regenerate_cover', [$this, 'ajax_regenerate_cover']);
        add_action('wp_ajax_get_generation_history', [$this, 'ajax_get_generation_history']);
        add_action('wp_ajax_batch_generate_covers', [$this, 'ajax_batch_generate_covers']);
        add_action('wp_ajax_test_ajax_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_simple_test', [$this, 'ajax_simple_test']);
        add_action('wp_ajax_check_generation_status', [$this, 'ajax_check_generation_status']);
        add_action('wp_ajax_trigger_check_generation', [$this, 'ajax_trigger_check_generation']);
        add_action('wp_ajax_upload_style_image', [$this, 'ajax_upload_style_image']);
        add_action('wp_ajax_upload_pasted_image', [$this, 'ajax_upload_pasted_image']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('publish_post', [$this, 'auto_generate_cover']);
        add_action('generate_cover_async', [$this, 'handle_async_generation']);
        add_action('check_cover_generation', [$this, 'handle_check_generation']);
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
        
        // 获取默认额外提示词
        $options = get_option('generate_cover_options');
        $default_extra_prompt = isset($options['default_extra_prompt']) ? $options['default_extra_prompt'] : '';
        $placeholder = $default_extra_prompt ? "默认：{$default_extra_prompt}" : "例如：现代简约风格、科技感、温暖色调等";
        ?>
        <div id="generate-cover-container">
            <p>
                <label for="extra-prompt" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    额外提示词（可选）：
                </label>
                <textarea id="extra-prompt" name="extra_prompt" rows="3" style="width: 100%; margin-bottom: 10px;" placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($default_extra_prompt); ?></textarea>
                <small style="color: #666; display: block; margin-bottom: 10px;">
                    可以添加风格描述来定制封面，如"现代简约"、"科技感"、"温暖色调"等
                </small>
            </p>
            
            <p>
                <label for="style-image" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    风格参考图片（可选）：
                </label>
                
                <!-- 粘贴区域 -->
                <div id="paste-area" style="border: 2px dashed #ccc; padding: 20px; text-align: center; margin-bottom: 10px; cursor: pointer; background: #fafafa; border-radius: 4px;" tabindex="0">
                    <div id="paste-text">
                        <span style="font-size: 16px; color: #666;">📋</span><br>
                        <span style="color: #666;">点击此处或按 Ctrl+V 粘贴图片</span>
                    </div>
                    <div id="paste-loading" style="display: none;">
                        <span class="spinner is-active"></span> 正在处理粘贴的图片...
                    </div>
                </div>
                
                <!-- 文件上传区域 -->
                <input type="file" id="style-image" name="style_image" accept="image/*" style="width: 100%; margin-bottom: 10px;" />
                <small style="color: #666; display: block; margin-bottom: 10px;">
                    上传一张参考图片来定义封面风格，支持 JPG、PNG 格式，或直接从剪贴板粘贴图片
                </small>
                
                <!-- 图片预览区域 -->
                <div id="style-image-preview" style="display: none; margin-bottom: 10px;">
                    <img id="preview-img" style="max-width: 100%; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;" />
                    <div style="margin-top: 5px;">
                        <button type="button" id="remove-style-image" class="button button-small">删除图片</button>
                    </div>
                </div>
            </p>
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
        
        // 获取默认额外提示词
        $options = get_option('generate_cover_options');
        $default_extra_prompt = isset($options['default_extra_prompt']) ? $options['default_extra_prompt'] : '';
        
        wp_localize_script('generate-cover-admin', 'generateCoverData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('generate_cover_nonce'),
            'postId' => get_the_ID(),
            'userId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'canEditPosts' => current_user_can('edit_posts'),
            'defaultExtraPrompt' => $default_extra_prompt
        ]);
    }
    
    public function ajax_generate_cover() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 调试日志
        error_log('Generate Cover: AJAX request received');
        error_log('Generate Cover: POST data: ' . print_r($_POST, true));
        error_log('Generate Cover: User logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
        error_log('Generate Cover: User ID: ' . get_current_user_id());
        
        // 检查用户是否已登录
        if (!is_user_logged_in()) {
            error_log('Generate Cover: User not logged in');
            wp_send_json_error('用户未登录，请先登录');
        }
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            error_log('Generate Cover: User permission denied');
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            error_log('Generate Cover: Nonce verification failed');
            wp_send_json_error('安全验证失败');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('无效的文章ID');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('文章不存在');
        }
        
        // 获取额外提示词
        $extra_prompt = isset($_POST['extra_prompt']) ? sanitize_textarea_field($_POST['extra_prompt']) : '';
        
        // 获取风格图片ID
        $style_image_id = isset($_POST['style_image_id']) ? intval($_POST['style_image_id']) : 0;
        
        try {
            $generator = new \GenerateCover\Cover_Generator();
            $result = $generator->generate_cover($post, $extra_prompt, $style_image_id);
            
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
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('无效的文章ID');
        }
        
        try {
            $generator = new \GenerateCover\Cover_Generator();
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
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('无效的文章ID');
        }
        
        try {
            $generator = new \GenerateCover\Cover_Generator();
            $history = $generator->get_generation_history($post_id);
            
            wp_send_json_success($history);
        } catch (Exception $e) {
            wp_send_json_error('获取历史记录失败：' . $e->getMessage());
        }
    }
    
    public function ajax_batch_generate_covers() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'batch_generate_covers')) {
            wp_send_json_error('安全验证失败');
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
            
            $generator = new \GenerateCover\Cover_Generator();
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
            
            $generator = new \GenerateCover\Cover_Generator();
            $result = $generator->generate_cover($post, '');
            
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
    
    public function ajax_test_connection() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_ajax_connection')) {
            wp_send_json_error('安全验证失败');
        }
        
        wp_send_json_success([
            'message' => 'AJAX连接正常',
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'post_data' => $_POST
        ]);
    }
    
    public function ajax_simple_test() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 简单的测试，不需要权限检查
        wp_send_json_success([
            'message' => '简单测试成功',
            'timestamp' => current_time('mysql'),
            'is_ajax' => wp_doing_ajax()
        ]);
    }
    
    public function ajax_check_generation_status() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $task_id = sanitize_text_field($_POST['task_id']);
        if (empty($task_id)) {
            wp_send_json_error('任务ID不能为空');
        }
        
        // 查找对应的文章
        $posts = get_posts([
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_cover_generation_task_id',
                    'value' => $task_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        if (empty($posts)) {
            wp_send_json_error('未找到对应的任务');
        }
        
        $post = $posts[0];
        $status = get_post_meta($post->ID, '_cover_generation_status', true);
        
        if ($status === 'completed') {
            wp_send_json_success([
                'completed' => true,
                'message' => '封面生成完成',
                'attachment_id' => get_post_meta($post->ID, '_cover_generation_attachment_id', true)
            ]);
        } elseif ($status === 'failed') {
            $error = get_post_meta($post->ID, '_cover_generation_error', true);
            wp_send_json_success([
                'completed' => false,
                'failed' => true,
                'message' => '生成失败：' . $error
            ]);
        } else {
            wp_send_json_success([
                'completed' => false,
                'failed' => false,
                'message' => '正在生成中...'
            ]);
        }
    }
    
    /**
     * 检查封面生成任务
     * 
     * @param int $post_id
     * @param string $task_id
     */
    public function handle_check_generation($post_id = null, $task_id = null) {
        // 处理 WordPress Cron 调用时可能缺少参数的情况
        if ($post_id === null || $task_id === null) {
            error_log("Generate Cover: Missing parameters for handle_check_generation");
            error_log("Generate Cover: post_id = " . var_export($post_id, true));
            error_log("Generate Cover: task_id = " . var_export($task_id, true));
            
            // 尝试从全局变量或数据库获取参数
            global $wpdb;
            
            // 查找所有正在处理的任务
            $processing_tasks = $wpdb->get_results("
                SELECT post_id, meta_value as task_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_cover_generation_task_id' 
                AND post_id IN (
                    SELECT post_id 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_cover_generation_status' 
                    AND meta_value = 'processing'
                )
            ");
            
            if (!empty($processing_tasks)) {
                foreach ($processing_tasks as $task) {
                    error_log("Generate Cover: Found processing task - post_id: {$task->post_id}, task_id: {$task->task_id}");
                    $this->check_single_generation($task->post_id, $task->task_id);
                }
            } else {
                error_log("Generate Cover: No processing tasks found");
            }
            
            return;
        }
        
        // 调用实际检查函数
        $this->check_single_generation($post_id, $task_id);
    }
    
    /**
     * 检查单个生成任务
     * 
     * @param int $post_id
     * @param string $task_id
     */
    private function check_single_generation($post_id, $task_id) {
        error_log("Generate Cover: Checking generation for post {$post_id}, task {$task_id}");
        
        try {
            $jimeng_ai = new \GenerateCover\Jimeng_AI();
            $result = $jimeng_ai->check_task_result($task_id);
            
            if (!$result['success']) {
                error_log("Generate Cover: Task check failed - " . $result['message']);
                // 如果是网络错误，重新安排检查
                if (strpos($result['message'], 'timeout') !== false || strpos($result['message'], 'DNS') !== false) {
                    wp_schedule_single_event(time() + 60, 'check_cover_generation', [$post_id, $task_id]);
                    return;
                }
                
                // 其他错误，标记为失败
                update_post_meta($post_id, '_cover_generation_status', 'failed');
                update_post_meta($post_id, '_cover_generation_error', $result['message']);
                $this->log_generation($post_id, 'error', '任务检查失败：' . $result['message']);
                return;
            }
            
            $status = $result['status'];
            error_log("Generate Cover: Task status = {$status}");
            
            if ($status === 'done') {
                // 任务完成，下载并设置封面
                $this->complete_cover_generation($post_id, $result);
            } elseif ($status === 'not_found' || $status === 'expired') {
                // 任务失败
                update_post_meta($post_id, '_cover_generation_status', 'failed');
                update_post_meta($post_id, '_cover_generation_error', '任务未找到或已过期');
                $this->log_generation($post_id, 'error', '任务未找到或已过期');
            } else {
                // 任务仍在处理中，重新安排检查
                wp_schedule_single_event(time() + 30, 'check_cover_generation', [$post_id, $task_id]);
            }
            
        } catch (Exception $e) {
            error_log("Generate Cover: Exception in check_single_generation - " . $e->getMessage());
            update_post_meta($post_id, '_cover_generation_status', 'failed');
            update_post_meta($post_id, '_cover_generation_error', $e->getMessage());
            $this->log_generation($post_id, 'error', '检查任务异常：' . $e->getMessage());
        }
    }
    
    public function ajax_trigger_check_generation() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $post_id = intval($_POST['post_id']);
        $task_id = sanitize_text_field($_POST['task_id']);
        
        if (!$post_id || !$task_id) {
            wp_send_json_error('参数错误');
        }
        
        // 手动触发检查
        $this->check_single_generation($post_id, $task_id);
        
        // 获取最新状态
        $status = get_post_meta($post_id, '_cover_generation_status', true);
        
        wp_send_json_success([
            'status' => $status,
            'message' => '检查完成'
        ]);
    }
    
    /**
     * 处理风格图片上传
     */
    public function ajax_upload_style_image() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 检查是否有文件上传
        if (!isset($_FILES['style_image']) || $_FILES['style_image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('没有上传文件或上传失败');
        }
        
        $file = $_FILES['style_image'];
        
        // 检查文件类型
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('不支持的文件格式，请上传 JPG 或 PNG 图片');
        }
        
        // 检查文件大小（限制为5MB）
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('文件大小不能超过5MB');
        }
        
        // 使用WordPress的媒体上传功能
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // 设置上传目录
        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name('style_image_' . time() . '_' . $file['name']);
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error('文件保存失败');
        }
        
        // 创建附件
        $attachment = [
            'post_mime_type' => $file['type'],
            'post_title' => '风格参考图片',
            'post_content' => '用于封面生成的风格参考图片',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (!$attachment_id) {
            wp_send_json_error('创建附件失败');
        }
        
        // 生成附件元数据
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // 返回成功结果
        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'filename' => $filename
        ]);
    }
    
    /**
     * 处理粘贴的图片上传
     */
    public function ajax_upload_pasted_image() {
        // 确保这是AJAX请求
        if (!wp_doing_ajax()) {
            wp_die('Invalid request');
        }
        
        // 确保输出是JSON格式
        header('Content-Type: application/json; charset=utf-8');
        
        // 检查用户权限
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('权限不足');
        }
        
        // 检查nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generate_cover_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 获取base64图片数据
        $image_data = isset($_POST['image_data']) ? $_POST['image_data'] : '';
        
        if (empty($image_data)) {
            wp_send_json_error('没有接收到图片数据');
        }
        
        // 解析base64数据
        if (preg_match('/data:image\/(\w+);base64,(.+)/', $image_data, $matches)) {
            $image_type = $matches[1];
            $base64_data = $matches[2];
        } else {
            wp_send_json_error('无效的图片数据格式');
        }
        
        // 检查图片类型
        if (!in_array($image_type, ['jpeg', 'jpg', 'png'])) {
            wp_send_json_error('不支持的图片格式，请使用 JPG 或 PNG 格式');
        }
        
        // 解码base64数据
        $image_binary = base64_decode($base64_data);
        
        if ($image_binary === false) {
            wp_send_json_error('图片数据解码失败');
        }
        
        // 检查文件大小（5MB限制）
        if (strlen($image_binary) > 5 * 1024 * 1024) {
            wp_send_json_error('图片文件大小不能超过5MB');
        }
        
        // 使用WordPress的媒体上传功能
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // 设置上传目录
        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name('pasted_image_' . time() . '.' . $image_type);
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // 保存文件
        if (file_put_contents($file_path, $image_binary) === false) {
            wp_send_json_error('文件保存失败');
        }
        
        // 创建附件
        $attachment = [
            'post_mime_type' => 'image/' . $image_type,
            'post_title' => '粘贴的风格参考图片',
            'post_content' => '通过剪贴板粘贴的风格参考图片',
            'post_status' => 'inherit'
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (!$attachment_id) {
            wp_send_json_error('创建附件失败');
        }
        
        // 生成附件元数据
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // 返回成功结果
        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'filename' => $filename
        ]);
    }
    
    /**
     * 完成封面生成
     * 
     * @param int $post_id
     * @param array $result
     */
    private function complete_cover_generation($post_id, $result) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        try {
            $generator = new \GenerateCover\Cover_Generator();
            
            // 下载并保存图片
            $attachment_id = $generator->save_image_to_media_library($result['image_urls'][0], $post);
            
            if (!$attachment_id) {
                update_post_meta($post_id, '_cover_generation_status', 'failed');
                update_post_meta($post_id, '_cover_generation_error', '保存图片到媒体库失败');
                $this->log_generation($post_id, 'error', '保存图片到媒体库失败');
                return;
            }
            
            // 设置为文章特色图片
            $featured_result = set_post_thumbnail($post_id, $attachment_id);
            
            if (!$featured_result) {
                update_post_meta($post_id, '_cover_generation_status', 'failed');
                update_post_meta($post_id, '_cover_generation_error', '设置特色图片失败');
                $this->log_generation($post_id, 'error', '设置特色图片失败');
                return;
            }
            
            // 更新状态为成功
            update_post_meta($post_id, '_cover_generation_status', 'completed');
            update_post_meta($post_id, '_cover_generation_attachment_id', $attachment_id);
            update_post_meta($post_id, '_cover_generation_completed_time', current_time('mysql'));
            
            // 生成摘要（如果需要）
            $prompt = get_post_meta($post_id, '_cover_generation_prompt', true);
            if ($prompt) {
                $openrouter_api = new \GenerateCover\OpenRouter_API();
                $summary_result = $openrouter_api->generate_summary($post->post_content, $post->post_title);
                if ($summary_result['success']) {
                    update_post_meta($post_id, '_ai_summary', $summary_result['content']);
                }
            }
            
            $this->log_generation($post_id, 'success', '异步生成成功');
            error_log("Generate Cover: Cover generation completed for post {$post_id}");
            
        } catch (Exception $e) {
            update_post_meta($post_id, '_cover_generation_status', 'failed');
            update_post_meta($post_id, '_cover_generation_error', $e->getMessage());
            $this->log_generation($post_id, 'error', '完成生成异常：' . $e->getMessage());
        }
    }
    
    public static function deactivate() {
        // 插件停用时的操作
        wp_clear_scheduled_hook('generate_cover_async');
        wp_clear_scheduled_hook('check_cover_generation');
    }
}
