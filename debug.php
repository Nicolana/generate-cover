<?php
/**
 * 调试页面 - 用于排查AJAX问题
 * 访问: /wp-content/plugins/generate-cover/debug.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 检查用户权限
if (!current_user_can('manage_options')) {
    die('权限不足');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Generate Cover 调试页面</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button { padding: 10px 15px; margin: 5px; }
        #result { margin-top: 10px; padding: 10px; background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Generate Cover 调试页面</h1>
    
    <div class="section">
        <h2>插件状态检查</h2>
        <p><strong>插件版本:</strong> <?php echo defined('GENERATE_COVER_VERSION') ? GENERATE_COVER_VERSION : '未定义'; ?></p>
        <p><strong>插件目录:</strong> <?php echo defined('GENERATE_COVER_PLUGIN_DIR') ? GENERATE_COVER_PLUGIN_DIR : '未定义'; ?></p>
        <p><strong>插件URL:</strong> <?php echo defined('GENERATE_COVER_PLUGIN_URL') ? GENERATE_COVER_PLUGIN_URL : '未定义'; ?></p>
        <p><strong>当前用户:</strong> <?php echo wp_get_current_user()->user_login; ?></p>
        <p><strong>用户权限:</strong> <?php echo current_user_can('edit_posts') ? '有编辑权限' : '无编辑权限'; ?></p>
    </div>
    
    <div class="section">
        <h2>API配置检查</h2>
        <?php
        $options = get_option('generate_cover_options');
        if ($options) {
            echo '<p><strong>OpenRouter API Key:</strong> ' . (isset($options['openrouter_api_key']) && !empty($options['openrouter_api_key']) ? '已配置' : '未配置') . '</p>';
            echo '<p><strong>即梦AI Access Key:</strong> ' . (isset($options['jimeng_access_key']) && !empty($options['jimeng_access_key']) ? '已配置' : '未配置') . '</p>';
            echo '<p><strong>即梦AI Secret Key:</strong> ' . (isset($options['jimeng_secret_key']) && !empty($options['jimeng_secret_key']) ? '已配置' : '未配置') . '</p>';
            echo '<p><strong>默认模型:</strong> ' . (isset($options['default_model']) ? $options['default_model'] : '未设置') . '</p>';
        } else {
            echo '<p class="error">插件选项未找到</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>AJAX测试</h2>
        <button onclick="testSimpleAjax()">简单AJAX测试</button>
        <button onclick="testAjax()">测试AJAX连接</button>
        <button onclick="testGenerateCover()">测试生成封面</button>
        <div id="result"></div>
    </div>
    
    <div class="section">
        <h2>WordPress AJAX信息</h2>
        <p><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></p>
        <p><strong>Nonce:</strong> <span id="nonce"><?php echo wp_create_nonce('generate_cover_nonce'); ?></span></p>
        <p><strong>测试文章ID:</strong> <input type="number" id="test-post-id" value="1" /></p>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function testSimpleAjax() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'simple_test'
                },
                dataType: 'json',
                success: function(response) {
                    document.getElementById('result').innerHTML = '<div class="success">简单AJAX测试成功: ' + JSON.stringify(response) + '</div>';
                },
                error: function(xhr, status, error) {
                    document.getElementById('result').innerHTML = '<div class="error">简单AJAX测试失败: ' + error + ' (状态码: ' + xhr.status + ')</div>';
                    console.error('Simple AJAX Error:', xhr.responseText);
                }
            });
        }
        
        function testAjax() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'test_ajax_connection',
                    nonce: '<?php echo wp_create_nonce('test_ajax_connection'); ?>'
                },
                success: function(response) {
                    document.getElementById('result').innerHTML = '<div class="success">AJAX连接成功: ' + JSON.stringify(response) + '</div>';
                },
                error: function(xhr, status, error) {
                    document.getElementById('result').innerHTML = '<div class="error">AJAX连接失败: ' + error + ' (状态码: ' + xhr.status + ')</div>';
                    console.error('AJAX Error:', xhr.responseText);
                }
            });
        }
        
        function testGenerateCover() {
            var postId = document.getElementById('test-post-id').value;
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'generate_cover',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('generate_cover_nonce'); ?>'
                },
                success: function(response) {
                    document.getElementById('result').innerHTML = '<div class="success">生成封面请求成功: ' + JSON.stringify(response) + '</div>';
                },
                error: function(xhr, status, error) {
                    document.getElementById('result').innerHTML = '<div class="error">生成封面请求失败: ' + error + ' (状态码: ' + xhr.status + ')</div>';
                    console.error('AJAX Error:', xhr.responseText);
                }
            });
        }
    </script>
</body>
</html>
