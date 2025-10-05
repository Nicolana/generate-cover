<?php
/**
 * 管理设置页面类
 * 处理插件的设置页面和API测试功能
 */

namespace GenerateCover;

class Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_test_openrouter_api', [$this, 'test_openrouter_api']);
        add_action('wp_ajax_test_jimeng_api', [$this, 'test_jimeng_api']);
        add_action('wp_ajax_regenerate_cover', [$this, 'ajax_regenerate_cover']);
        add_action('wp_ajax_get_generation_history', [$this, 'ajax_get_generation_history']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
            [$this, 'openrouter_api_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'jimeng_access_key',
            '即梦AI Access Key',
            [$this, 'jimeng_access_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'jimeng_secret_key',
            '即梦AI Secret Key',
            [$this, 'jimeng_secret_key_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'default_model',
            '默认AI模型',
            [$this, 'default_model_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'image_size',
            '图片尺寸',
            [$this, 'image_size_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
        
        add_settings_field(
            'auto_generate',
            '自动生成',
            [$this, 'auto_generate_callback'],
            'generate-cover-settings',
            'generate_cover_main'
        );
    }
    
    public function openrouter_api_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['openrouter_api_key']) ? $options['openrouter_api_key'] : '';
        echo '<input type="text" name="generate_cover_options[openrouter_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">从 <a href="https://openrouter.ai/" target="_blank">OpenRouter</a> 获取API密钥</p>';
        echo '<button type="button" id="test-openrouter-api" class="button button-secondary">测试连接</button>';
        echo '<span id="openrouter-test-result"></span>';
    }
    
    public function jimeng_access_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['jimeng_access_key']) ? $options['jimeng_access_key'] : '';
        echo '<input type="text" name="generate_cover_options[jimeng_access_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">即梦AI的Access Key</p>';
    }
    
    public function jimeng_secret_key_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['jimeng_secret_key']) ? $options['jimeng_secret_key'] : '';
        echo '<input type="password" name="generate_cover_options[jimeng_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">即梦AI的Secret Key</p>';
        echo '<button type="button" id="test-jimeng-api" class="button button-secondary">测试连接</button>';
        echo '<span id="jimeng-test-result"></span>';
    }
    
    public function default_model_callback() {
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
        echo '<p class="description">用于生成图片prompt的AI模型</p>';
    }
    
    public function image_size_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['image_size']) ? $options['image_size'] : '2048x2048';
        
        $sizes = [
            '1024x1024' => '1K (1024×1024)',
            '2048x2048' => '2K (2048×2048)',
            '2304x1728' => '2K 4:3 (2304×1728)',
            '2560x1440' => '2K 16:9 (2560×1440)',
            '4096x4096' => '4K (4096×4096)'
        ];
        
        echo '<select name="generate_cover_options[image_size]">';
        foreach ($sizes as $key => $label) {
            $selected = selected($value, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">生成图片的尺寸，分辨率越高生成时间越长</p>';
    }
    
    public function auto_generate_callback() {
        $options = get_option('generate_cover_options');
        $value = isset($options['auto_generate']) ? $options['auto_generate'] : false;
        
        echo '<label>';
        echo '<input type="checkbox" name="generate_cover_options[auto_generate]" value="1" ' . checked($value, 1, false) . ' />';
        echo ' 发布文章时自动生成封面';
        echo '</label>';
        echo '<p class="description">启用后，发布新文章时会自动生成封面图片</p>';
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Generate Cover 设置</h1>
            
            <div class="card">
                <h2>插件说明</h2>
                <p>Generate Cover 是一个WordPress插件，可以自动为您的博客文章生成封面图片。</p>
                <p>插件使用以下AI服务：</p>
                <ul>
                    <li><strong>OpenRouter API</strong>：用于生成图片prompt和文章摘要</li>
                    <li><strong>即梦AI</strong>：用于生成实际的封面图片</li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('generate_cover_settings');
                do_settings_sections('generate-cover-settings');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>使用说明</h2>
                <ol>
                    <li>配置上述API密钥和参数</li>
                    <li>在文章编辑页面，点击"生成封面图片"按钮</li>
                    <li>插件会自动分析文章内容，生成合适的prompt</li>
                    <li>使用即梦AI生成封面图片并设置为文章特色图片</li>
                </ol>
                
                <h3>注意事项</h3>
                <ul>
                    <li>确保API密钥有效且有足够的额度</li>
                    <li>生成图片需要一定时间，请耐心等待</li>
                    <li>建议在文章内容完整后再生成封面</li>
                    <li>可以随时重新生成封面图片</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>批量操作</h2>
                <p>您可以为多篇文章批量生成封面：</p>
                <button type="button" id="batch-generate-btn" class="button button-primary">批量生成封面</button>
                <div id="batch-generate-result" style="display: none; margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 测试OpenRouter API
            $('#test-openrouter-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#openrouter-test-result');
                
                $btn.prop('disabled', true).text('测试中...');
                $result.html('<span class="spinner is-active"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_openrouter_api',
                        nonce: '<?php echo wp_create_nonce('test_openrouter_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                        } else {
                            $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;">✗ 网络错误</span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('测试连接');
                    }
                });
            });
            
            // 测试即梦AI API
            $('#test-jimeng-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#jimeng-test-result');
                
                $btn.prop('disabled', true).text('测试中...');
                $result.html('<span class="spinner is-active"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_jimeng_api',
                        nonce: '<?php echo wp_create_nonce('test_jimeng_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                        } else {
                            $result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color: red;">✗ 网络错误</span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('测试连接');
                    }
                });
            });
            
            // 批量生成封面
            $('#batch-generate-btn').on('click', function() {
                if (!confirm('确定要为所有没有封面图片的文章生成封面吗？这可能需要较长时间。')) {
                    return;
                }
                
                var $btn = $(this);
                var $result = $('#batch-generate-result');
                
                $btn.prop('disabled', true).text('批量生成中...');
                $result.html('<p>正在获取文章列表...</p>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'batch_generate_covers',
                        nonce: '<?php echo wp_create_nonce('batch_generate_covers'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<h4>批量生成完成</h4>';
                            html += '<p>成功：' + response.data.success_count + ' 篇</p>';
                            html += '<p>失败：' + response.data.fail_count + ' 篇</p>';
                            
                            if (response.data.failed_posts.length > 0) {
                                html += '<h5>失败的文章：</h5><ul>';
                                response.data.failed_posts.forEach(function(post) {
                                    html += '<li>' + post.title + ' (ID: ' + post.id + ') - ' + post.error + '</li>';
                                });
                                html += '</ul>';
                            }
                            
                            $result.html(html);
                        } else {
                            $result.html('<p style="color: red;">批量生成失败：' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $result.html('<p style="color: red;">网络错误</p>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('批量生成封面');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_generate-cover-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    public function test_openrouter_api() {
        check_ajax_referer('test_openrouter_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        try {
            $api = new OpenRouter_API();
            $result = $api->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('测试失败：' . $e->getMessage());
        }
    }
    
    public function test_jimeng_api() {
        check_ajax_referer('test_jimeng_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        try {
            $api = new Jimeng_AI();
            $result = $api->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('测试失败：' . $e->getMessage());
        }
    }
    
    public function ajax_regenerate_cover() {
        check_ajax_referer('generate_cover_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('权限不足');
        }
        
        $post_id = intval($_POST['post_id']);
        
        try {
            $generator = new Cover_Generator();
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
            $generator = new Cover_Generator();
            $history = $generator->get_generation_history($post_id);
            
            wp_send_json_success($history);
        } catch (Exception $e) {
            wp_send_json_error('获取历史记录失败：' . $e->getMessage());
        }
    }
}
