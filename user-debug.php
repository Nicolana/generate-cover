<?php
/**
 * 用户状态调试页面
 * 用于检查WordPress用户登录状态
 * 访问: /wp-content/plugins/generate-cover/user-debug.php
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
    <title>用户状态调试</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>WordPress 用户状态调试</h1>
    
    <div class="section">
        <h2>基本用户信息</h2>
        <?php
        $current_user = wp_get_current_user();
        echo '<p><strong>用户ID:</strong> ' . get_current_user_id() . '</p>';
        echo '<p><strong>用户名:</strong> ' . $current_user->user_login . '</p>';
        echo '<p><strong>显示名称:</strong> ' . $current_user->display_name . '</p>';
        echo '<p><strong>邮箱:</strong> ' . $current_user->user_email . '</p>';
        echo '<p><strong>角色:</strong> ' . implode(', ', $current_user->roles) . '</p>';
        ?>
    </div>
    
    <div class="section">
        <h2>登录状态检查</h2>
        <?php
        echo '<p><strong>is_user_logged_in():</strong> ' . (is_user_logged_in() ? '<span class="success">true</span>' : '<span class="error">false</span>') . '</p>';
        echo '<p><strong>get_current_user_id():</strong> ' . get_current_user_id() . '</p>';
        echo '<p><strong>wp_get_current_user() ID:</strong> ' . wp_get_current_user()->ID . '</p>';
        ?>
    </div>
    
    <div class="section">
        <h2>权限检查</h2>
        <?php
        $capabilities = [
            'edit_posts' => '编辑文章',
            'edit_pages' => '编辑页面',
            'manage_options' => '管理选项',
            'upload_files' => '上传文件',
            'edit_others_posts' => '编辑他人文章',
            'publish_posts' => '发布文章'
        ];
        
        foreach ($capabilities as $cap => $desc) {
            $has_cap = current_user_can($cap);
            echo '<p><strong>' . $desc . ' (' . $cap . '):</strong> ' . 
                 ($has_cap ? '<span class="success">有权限</span>' : '<span class="error">无权限</span>') . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>WordPress环境信息</h2>
        <?php
        echo '<p><strong>WordPress版本:</strong> ' . get_bloginfo('version') . '</p>';
        echo '<p><strong>PHP版本:</strong> ' . PHP_VERSION . '</p>';
        echo '<p><strong>当前页面:</strong> ' . $_SERVER['REQUEST_URI'] . '</p>';
        echo '<p><strong>HTTP_HOST:</strong> ' . $_SERVER['HTTP_HOST'] . '</p>';
        echo '<p><strong>REQUEST_METHOD:</strong> ' . $_SERVER['REQUEST_METHOD'] . '</p>';
        ?>
    </div>
    
    <div class="section">
        <h2>插件状态</h2>
        <?php
        echo '<p><strong>插件版本:</strong> ' . (defined('GENERATE_COVER_VERSION') ? GENERATE_COVER_VERSION : '未定义') . '</p>';
        echo '<p><strong>插件目录:</strong> ' . (defined('GENERATE_COVER_PLUGIN_DIR') ? GENERATE_COVER_PLUGIN_DIR : '未定义') . '</p>';
        echo '<p><strong>插件URL:</strong> ' . (defined('GENERATE_COVER_PLUGIN_URL') ? GENERATE_COVER_PLUGIN_URL : '未定义') . '</p>';
        ?>
    </div>
    
    <div class="section">
        <h2>JavaScript变量测试</h2>
        <button onclick="testJavaScriptVariables()">测试JavaScript变量</button>
        <div id="js-result"></div>
    </div>
    
    <div class="section">
        <h2>AJAX测试</h2>
        <button onclick="testAjax()">测试AJAX</button>
        <div id="ajax-result"></div>
    </div>
    
    <div class="section">
        <h2>完整用户对象</h2>
        <pre><?php print_r($current_user); ?></pre>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function testJavaScriptVariables() {
            var result = '';
            
            if (typeof generateCover !== 'undefined') {
                result += '<div class="success">generateCover 变量存在</div>';
                result += '<pre>' + JSON.stringify(generateCover, null, 2) + '</pre>';
            } else {
                result += '<div class="error">generateCover 变量不存在</div>';
            }
            
            document.getElementById('js-result').innerHTML = result;
        }
        
        function testAjax() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'simple_test'
                },
                dataType: 'json',
                success: function(response) {
                    document.getElementById('ajax-result').innerHTML = 
                        '<div class="success">AJAX成功: ' + JSON.stringify(response) + '</div>';
                },
                error: function(xhr, status, error) {
                    document.getElementById('ajax-result').innerHTML = 
                        '<div class="error">AJAX失败: ' + error + ' (状态码: ' + xhr.status + ')</div>' +
                        '<pre>' + xhr.responseText + '</pre>';
                }
            });
        }
        
        // 页面加载时自动测试
        jQuery(document).ready(function() {
            testJavaScriptVariables();
        });
    </script>
</body>
</html>
