/**
 * Generate Cover 插件管理端JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // 绑定生成封面按钮事件
        $('#generate-cover-btn').on('click', function() {
            generateCover();
        });
        
        // 绑定重新生成按钮事件
        $(document).on('click', '#regenerate-cover-btn', function() {
            regenerateCover();
        });
        
        // 绑定查看历史按钮事件
        $(document).on('click', '#view-history-btn', function() {
            viewGenerationHistory();
        });
    });
    
    /**
     * 生成封面
     */
    function generateCover() {
        var $btn = $('#generate-cover-btn');
        var $status = $('#generate-cover-status');
        var $result = $('#generate-cover-result');
        var $error = $('#generate-cover-error');
        
        // 重置状态
        $status.hide();
        $result.hide();
        $error.hide();
        
        // 显示加载状态
        $status.show();
        $btn.prop('disabled', true);
        
        // 发送AJAX请求
        $.ajax({
            url: generateCover.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_cover',
                post_id: generateCover.postId,
                nonce: generateCover.nonce
            },
            success: function(response) {
                $status.hide();
                $btn.prop('disabled', false);
                
                if (response.success) {
                    showSuccessResult(response.data);
                } else {
                    showError(response.data || '生成失败');
                }
            },
            error: function(xhr, status, error) {
                $status.hide();
                $btn.prop('disabled', false);
                showError('网络错误：' + error);
            }
        });
    }
    
    /**
     * 重新生成封面
     */
    function regenerateCover() {
        if (!confirm('确定要重新生成封面吗？这将删除当前的封面图片。')) {
            return;
        }
        
        var $btn = $('#regenerate-cover-btn');
        var $status = $('#generate-cover-status');
        var $result = $('#generate-cover-result');
        var $error = $('#generate-cover-error');
        
        // 重置状态
        $status.hide();
        $result.hide();
        $error.hide();
        
        // 显示加载状态
        $status.show();
        $btn.prop('disabled', true);
        
        // 发送AJAX请求
        $.ajax({
            url: generateCover.ajaxUrl,
            type: 'POST',
            data: {
                action: 'regenerate_cover',
                post_id: generateCover.postId,
                nonce: generateCover.nonce
            },
            success: function(response) {
                $status.hide();
                $btn.prop('disabled', false);
                
                if (response.success) {
                    showSuccessResult(response.data);
                } else {
                    showError(response.data || '重新生成失败');
                }
            },
            error: function(xhr, status, error) {
                $status.hide();
                $btn.prop('disabled', false);
                showError('网络错误：' + error);
            }
        });
    }
    
    /**
     * 显示成功结果
     */
    function showSuccessResult(data) {
        var $result = $('#generate-cover-result');
        var $preview = $('#generated-image-preview');
        
        // 显示生成的图片
        if (data.image_url) {
            $preview.html(
                '<div style="margin: 10px 0;">' +
                '<img src="' + data.image_url + '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;" />' +
                '</div>' +
                '<div style="margin: 10px 0;">' +
                '<strong>生成的Prompt：</strong><br>' +
                '<textarea readonly style="width: 100%; height: 60px; font-size: 12px; background: #f9f9f9;">' + (data.prompt || '') + '</textarea>' +
                '</div>' +
                (data.summary ? '<div style="margin: 10px 0;"><strong>AI摘要：</strong><br><p style="font-size: 12px; color: #666;">' + data.summary + '</p></div>' : '') +
                '<div style="margin: 10px 0;">' +
                '<button type="button" id="regenerate-cover-btn" class="button">重新生成</button> ' +
                '<button type="button" id="view-history-btn" class="button">查看历史</button>' +
                '</div>'
            );
        }
        
        $result.show();
        
        // 显示成功消息
        showNotice('封面生成成功！', 'success');
    }
    
    /**
     * 显示错误信息
     */
    function showError(message) {
        var $error = $('#generate-cover-error');
        $error.find('.error').text(message);
        $error.show();
        
        // 显示错误通知
        showNotice('生成失败：' + message, 'error');
    }
    
    /**
     * 显示通知
     */
    function showNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // 移除现有通知
        $('.notice').remove();
        
        // 添加新通知
        $notice.insertAfter('.wp-header-end');
        
        // 自动隐藏
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    /**
     * 查看生成历史
     */
    function viewGenerationHistory() {
        // 发送AJAX请求获取历史记录
        $.ajax({
            url: generateCover.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_generation_history',
                post_id: generateCover.postId,
                nonce: generateCover.nonce
            },
            success: function(response) {
                if (response.success) {
                    showHistoryModal(response.data);
                } else {
                    showError('获取历史记录失败');
                }
            },
            error: function(xhr, status, error) {
                showError('网络错误：' + error);
            }
        });
    }
    
    /**
     * 显示历史记录模态框
     */
    function showHistoryModal(history) {
        var modalHtml = '<div id="history-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">' +
            '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 4px; max-width: 600px; max-height: 80%; overflow-y: auto;">' +
            '<h3>生成历史</h3>' +
            '<div id="history-content">';
        
        if (history.length === 0) {
            modalHtml += '<p>暂无生成历史</p>';
        } else {
            history.forEach(function(item, index) {
                modalHtml += '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px;">' +
                    '<div style="font-weight: bold; color: ' + (item.success ? 'green' : 'red') + ';">' +
                    '第' + (index + 1) + '次生成 - ' + (item.success ? '成功' : '失败') +
                    '</div>' +
                    '<div style="font-size: 12px; color: #666; margin: 5px 0;">' +
                    '时间：' + item.timestamp +
                    '</div>';
                
                if (item.prompt) {
                    modalHtml += '<div style="margin: 5px 0;"><strong>Prompt：</strong><br>' +
                        '<textarea readonly style="width: 100%; height: 40px; font-size: 11px; background: #f9f9f9;">' + item.prompt + '</textarea>' +
                        '</div>';
                }
                
                if (!item.success && item.message) {
                    modalHtml += '<div style="color: red; font-size: 12px;">错误：' + item.message + '</div>';
                }
                
                modalHtml += '</div>';
            });
        }
        
        modalHtml += '</div>' +
            '<div style="text-align: right; margin-top: 20px;">' +
            '<button type="button" id="close-history-modal" class="button">关闭</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(modalHtml);
        
        // 绑定关闭事件
        $('#close-history-modal').on('click', function() {
            $('#history-modal').remove();
        });
        
        // 点击背景关闭
        $('#history-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });
    }
    
})(jQuery);
