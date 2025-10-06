/**
 * Generate Cover 插件管理端JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // 检查必要的变量是否存在
        if (typeof generateCoverData === 'undefined') {
            console.error('Generate Cover: JavaScript variables not loaded');
            return;
        }
        
        // 调试信息
        console.log('Generate Cover: Variables loaded:', generateCoverData);
        
        // 检查用户登录状态
        if (!generateCoverData.isLoggedIn) {
            console.error('Generate Cover: User not logged in');
            console.log('Generate Cover: User ID:', generateCoverData.userId);
            console.log('Generate Cover: Can edit posts:', generateCoverData.canEditPosts);
            return;
        }
        
        // 检查用户权限
        if (!generateCoverData.canEditPosts) {
            console.error('Generate Cover: User cannot edit posts');
            return;
        }
        
        console.log('Generate Cover: All checks passed, binding events');
        
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
        
        // 绑定风格图片上传事件
        $('#style-image').on('change', function() {
            handleStyleImageUpload();
        });
        
        // 绑定删除风格图片事件
        $(document).on('click', '#remove-style-image', function() {
            removeStyleImage();
        });
        
        // 绑定粘贴区域事件
        $('#paste-area').on('click', function() {
            $(this).focus();
        });
        
        // 绑定粘贴事件
        $(document).on('paste', '#paste-area', function(e) {
            handlePasteImage(e);
        });
        
        // 绑定全局粘贴事件（当粘贴区域获得焦点时）
        $(document).on('paste', function(e) {
            if ($(e.target).closest('#paste-area').length > 0) {
                handlePasteImage(e);
            }
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
        
        // 获取额外提示词
        var extraPrompt = $('#extra-prompt').val();
        
        // 如果用户没有输入额外提示词，使用默认值
        if (!extraPrompt || extraPrompt.trim() === '') {
            // 从localized data中获取默认提示词（需要在PHP中传递）
            if (typeof generateCoverData !== 'undefined' && generateCoverData.defaultExtraPrompt) {
                extraPrompt = generateCoverData.defaultExtraPrompt;
            }
        }
        
        // 获取风格图片ID
        var styleImageId = $('#style-image-preview').data('attachment-id') || 0;
        
        // 发送AJAX请求
        $.ajax({
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_cover',
                post_id: generateCoverData.postId,
                nonce: generateCoverData.nonce,
                extra_prompt: extraPrompt,
                style_image_id: styleImageId
            },
            dataType: 'json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            },
            success: function(response) {
                $status.hide();
                $btn.prop('disabled', false);
                
                console.log('AJAX Success Response:', response);
                
                if (response && response.success) {
                    if (response.data.async) {
                        // 异步任务，显示处理中状态
                        showAsyncResult(response.data);
                    } else {
                        // 同步任务，显示结果
                        showSuccessResult(response.data);
                    }
                } else {
                    showError(response.data || '生成失败');
                }
            },
            error: function(xhr, status, error) {
                $status.hide();
                $btn.prop('disabled', false);
                
                console.error('AJAX Error Details:');
                console.error('Status:', xhr.status);
                console.error('Status Text:', xhr.statusText);
                console.error('Response Text:', xhr.responseText);
                console.error('Response Headers:', xhr.getAllResponseHeaders());
                
                // 检查响应是否是HTML而不是JSON
                if (xhr.responseText && xhr.responseText.trim().startsWith('<!DOCTYPE')) {
                    showError('服务器返回了HTML页面而不是JSON数据。这通常表示：1) 用户未登录 2) 会话已过期 3) 权限不足。请刷新页面重新登录。');
                } else if (xhr.status === 302) {
                    showError('会话已过期，请刷新页面后重试');
                } else if (xhr.status === 401) {
                    showError('用户未登录，请先登录WordPress管理后台');
                } else if (xhr.status === 403) {
                    showError('权限不足，请确保有编辑文章的权限');
                } else {
                    showError('网络错误：' + error + ' (状态码: ' + xhr.status + ')');
                }
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
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'regenerate_cover',
                post_id: generateCoverData.postId,
                nonce: generateCoverData.nonce
            },
            dataType: 'json',
            success: function(response) {
                $status.hide();
                $btn.prop('disabled', false);
                
                if (response && response.success) {
                    showSuccessResult(response.data);
                } else {
                    showError(response.data || '重新生成失败');
                }
            },
            error: function(xhr, status, error) {
                $status.hide();
                $btn.prop('disabled', false);
                
                console.error('AJAX Error:', xhr.responseText);
                
                if (xhr.status === 302) {
                    showError('会话已过期，请刷新页面后重试');
                } else {
                    showError('网络错误：' + error + ' (状态码: ' + xhr.status + ')');
                }
            }
        });
    }
    
    /**
     * 显示异步处理结果
     */
    function showAsyncResult(data) {
        var $result = $('#generate-cover-result');
        var $preview = $('#generated-image-preview');
        
        $preview.html(
            '<div style="margin: 20px 0; text-align: center;">' +
            '<div class="spinner is-active" style="margin: 10px auto;"></div>' +
            '<p><strong>封面正在生成中...</strong></p>' +
            '<p style="color: #666; font-size: 12px;">任务ID: ' + data.task_id + '</p>' +
            '<p style="color: #666; font-size: 12px;">生成完成后会自动更新封面，请稍候。</p>' +
            '</div>' +
            '<div style="margin: 10px 0;">' +
            '<button type="button" id="check-status-btn" class="button">检查状态</button> ' +
            '<button type="button" id="trigger-check-btn" class="button">手动检查</button> ' +
            '<button type="button" id="view-history-btn" class="button">查看历史</button>' +
            '</div>'
        );
        
        $result.show();
        
        // 显示提示消息
        showNotice('封面生成任务已提交，正在后台处理中...', 'info');
        
        // 绑定检查状态按钮
        $('#check-status-btn').on('click', function() {
            checkGenerationStatus(data.task_id);
        });
        
        // 绑定手动检查按钮
        $('#trigger-check-btn').on('click', function() {
            triggerCheckGeneration(data.task_id);
        });
    }
    
    /**
     * 检查生成状态
     */
    function checkGenerationStatus(taskId) {
        var $btn = $('#check-status-btn');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('检查中...');
        
        $.ajax({
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'check_generation_status',
                task_id: taskId,
                nonce: generateCoverData.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    if (response.data.completed) {
                        showNotice('封面生成完成！', 'success');
                        location.reload(); // 刷新页面显示新封面
                    } else {
                        showNotice('封面仍在生成中，请稍候...', 'info');
                    }
                } else {
                    showError(response.data || '检查状态失败');
                }
            },
            error: function() {
                showError('网络错误，无法检查状态');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * 手动触发检查
     */
    function triggerCheckGeneration(taskId) {
        var $btn = $('#trigger-check-btn');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('检查中...');
        
        $.ajax({
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'trigger_check_generation',
                post_id: generateCoverData.postId,
                task_id: taskId,
                nonce: generateCoverData.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    showNotice('手动检查完成，状态：' + response.data.status, 'info');
                    if (response.data.status === 'completed') {
                        location.reload(); // 刷新页面显示新封面
                    }
                } else {
                    showError(response.data || '手动检查失败');
                }
            },
            error: function() {
                showError('网络错误，无法手动检查');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
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
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_generation_history',
                post_id: generateCoverData.postId,
                nonce: generateCoverData.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    showHistoryModal(response.data);
                } else {
                    showError('获取历史记录失败');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                
                if (xhr.status === 302) {
                    showError('会话已过期，请刷新页面后重试');
                } else {
                    showError('网络错误：' + error + ' (状态码: ' + xhr.status + ')');
                }
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
    
    /**
     * 处理风格图片上传
     */
    function handleStyleImageUpload() {
        var fileInput = $('#style-image')[0];
        var file = fileInput.files[0];
        
        if (!file) {
            return;
        }
        
        // 检查文件类型
        if (!file.type.match('image.*')) {
            showError('请选择图片文件');
            return;
        }
        
        // 检查文件大小（5MB限制）
        if (file.size > 5 * 1024 * 1024) {
            showError('图片文件大小不能超过5MB');
            return;
        }
        
        // 创建FormData
        var formData = new FormData();
        formData.append('action', 'upload_style_image');
        formData.append('style_image', file);
        formData.append('nonce', generateCoverData.nonce);
        
        // 显示上传状态
        var $preview = $('#style-image-preview');
        $preview.html('<div class="spinner is-active"></div> 正在上传...').show();
        
        // 上传文件
        $.ajax({
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // 显示预览
                    showStyleImagePreview(response.data.url, response.data.attachment_id);
                    showNotice('风格图片上传成功！', 'success');
                } else {
                    showError('上传失败：' + (response.data || '未知错误'));
                    $preview.hide();
                }
            },
            error: function(xhr, status, error) {
                showError('上传失败：网络错误');
                $preview.hide();
            }
        });
    }
    
    /**
     * 显示风格图片预览
     */
    function showStyleImagePreview(url, attachmentId) {
        var $preview = $('#style-image-preview');
        
        $preview.html(
            '<img id="preview-img" src="' + url + '" style="max-width: 100%; max-height: 150px; border: 1px solid #ddd; border-radius: 4px;" />' +
            '<div style="margin-top: 5px;">' +
            '<button type="button" id="remove-style-image" class="button button-small">删除图片</button>' +
            '</div>'
        );
        
        $preview.data('attachment-id', attachmentId).show();
    }
    
    /**
     * 删除风格图片
     */
    function removeStyleImage() {
        var $preview = $('#style-image-preview');
        var attachmentId = $preview.data('attachment-id');
        
        // 清空文件输入
        $('#style-image').val('');
        
        // 隐藏预览
        $preview.hide().removeData('attachment-id');
        
        // 如果有附件ID，可以在这里调用删除附件的API（可选）
        if (attachmentId) {
            // 注意：这里不实际删除附件，只是清除预览
            // 如果需要删除附件，可以调用WordPress的删除API
            showNotice('风格图片已移除', 'info');
        }
    }
    
    /**
     * 处理粘贴的图片
     */
    function handlePasteImage(e) {
        e.preventDefault();
        
        var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
        var items = clipboardData.items;
        
        // 查找图片数据
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            
            if (item.type.indexOf('image') !== -1) {
                var file = item.getAsFile();
                
                if (file) {
                    // 显示处理状态
                    showPasteLoading();
                    
                    // 将文件转换为base64并上传
                    var reader = new FileReader();
                    reader.onload = function(event) {
                        var base64Data = event.target.result;
                        uploadPastedImage(base64Data);
                    };
                    reader.readAsDataURL(file);
                    return;
                }
            }
        }
        
        // 如果没有找到图片数据
        showError('剪贴板中没有找到图片数据');
    }
    
    /**
     * 显示粘贴加载状态
     */
    function showPasteLoading() {
        $('#paste-text').hide();
        $('#paste-loading').show();
    }
    
    /**
     * 隐藏粘贴加载状态
     */
    function hidePasteLoading() {
        $('#paste-text').show();
        $('#paste-loading').hide();
    }
    
    /**
     * 上传粘贴的图片
     */
    function uploadPastedImage(base64Data) {
        $.ajax({
            url: generateCoverData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'upload_pasted_image',
                image_data: base64Data,
                nonce: generateCoverData.nonce
            },
            dataType: 'json',
            success: function(response) {
                hidePasteLoading();
                
                if (response && response.success) {
                    // 显示预览
                    showStyleImagePreview(response.data.url, response.data.attachment_id);
                    showNotice('图片粘贴成功！', 'success');
                } else {
                    showError('粘贴失败：' + (response.data || '未知错误'));
                }
            },
            error: function(xhr, status, error) {
                hidePasteLoading();
                showError('粘贴失败：网络错误');
            }
        });
    }
    
})(jQuery);
