<?php
/**
 * 独立的AJAX测试文件
 * 用于测试WordPress AJAX是否正常工作
 * 访问: /wp-content/plugins/generate-cover/ajax-test.php
 */

// 加载WordPress环境
require_once('../../../wp-load.php');

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');

// 检查是否是AJAX请求
if (!wp_doing_ajax()) {
    wp_send_json_error('Not an AJAX request');
}

// 检查用户登录状态
if (!is_user_logged_in()) {
    wp_send_json_error('User not logged in');
}

// 检查用户权限
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions');
}

// 返回成功响应
wp_send_json_success([
    'message' => 'AJAX test successful',
    'timestamp' => current_time('mysql'),
    'user_id' => get_current_user_id(),
    'user_login' => wp_get_current_user()->user_login,
    'is_ajax' => wp_doing_ajax(),
    'post_data' => $_POST
]);
?>
