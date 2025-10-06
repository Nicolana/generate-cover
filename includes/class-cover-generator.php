<?php
/**
 * 封面生成器类
 * 整合OpenRouter和即梦AI，实现完整的封面生成流程
 */

namespace GenerateCover;

class Cover_Generator {
    
    private $openrouter_api;
    private $jimeng_ai;
    
    public function __construct() {
        $this->openrouter_api = new OpenRouter_API();
        $this->jimeng_ai = new Jimeng_AI();
    }
    
    /**
     * 为文章生成封面
     * 
     * @param \WP_Post $post WordPress文章对象
     * @return array
     */
    public function generate_cover($post) {
        try {
            // 1. 获取文章内容
            $content = $this->get_post_content($post);
            $title = $this->sanitize_utf8($post->post_title);
            
            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => '文章内容为空，无法生成封面'
                ];
            }
            
            // 2. 使用OpenRouter生成图片prompt
            $prompt_result = $this->openrouter_api->generate_image_prompt($content, $title);
            
            if (!$prompt_result['success']) {
                return $prompt_result;
            }
            
            $prompt = $prompt_result['content'];
            
            // 记录生成的prompt
            update_post_meta($post->ID, '_generated_prompt', $prompt);
            
            // 3. 使用即梦AI生成图片（异步）
            $image_result = $this->jimeng_ai->generate_image($prompt, [
                'size' => 4194304, // 2048*2048
                'force_single' => true
            ], true); // 异步处理
            
            if (!$image_result['success']) {
                return $image_result;
            }
            
            // 如果是异步任务，保存任务信息并返回
            if (isset($image_result['async']) && $image_result['async']) {
                // 保存任务信息到文章元数据
                update_post_meta($post->ID, '_cover_generation_task_id', $image_result['task_id']);
                update_post_meta($post->ID, '_cover_generation_prompt', $prompt);
                update_post_meta($post->ID, '_cover_generation_status', 'processing');
                update_post_meta($post->ID, '_cover_generation_start_time', current_time('mysql'));
                
                // 安排后台检查任务
                wp_schedule_single_event(time() + 30, 'check_cover_generation', [$post->ID, $image_result['task_id']]);
                
                return [
                    'success' => true,
                    'message' => '封面生成任务已提交，正在后台处理中...',
                    'task_id' => $image_result['task_id'],
                    'async' => true
                ];
            }
            
            // 4. 下载并保存图片到WordPress媒体库
            $attachment_id = $this->save_image_to_media_library($image_result['image_urls'][0], $post);
            
            if (!$attachment_id) {
                return [
                    'success' => false,
                    'message' => '保存图片到媒体库失败'
                ];
            }
            
            // 5. 设置为文章特色图片
            $featured_result = set_post_thumbnail($post->ID, $attachment_id);
            
            if (!$featured_result) {
                return [
                    'success' => false,
                    'message' => '设置特色图片失败'
                ];
            }
            
            // 6. 生成文章摘要（可选）
            $summary_result = $this->openrouter_api->generate_summary($content, $title);
            if ($summary_result['success']) {
                update_post_meta($post->ID, '_ai_summary', $summary_result['content']);
            }
            
            $result = [
                'success' => true,
                'attachment_id' => $attachment_id,
                'image_url' => wp_get_attachment_url($attachment_id),
                'prompt' => $prompt,
                'summary' => $summary_result['success'] ? $summary_result['content'] : null
            ];
            
            // 记录生成历史
            $this->record_generation_history($post->ID, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $error_result = [
                'success' => false,
                'message' => '生成过程中发生错误：' . $e->getMessage()
            ];
            
            // 记录失败历史
            $this->record_generation_history($post->ID, $error_result);
            
            return $error_result;
        }
    }
    
    /**
     * 获取文章内容
     * 
     * @param \WP_Post $post
     * @return string
     */
    private function get_post_content($post) {
        // 获取文章内容，去除HTML标签
        $content = strip_tags($post->post_content);
        
        // 清理UTF-8编码问题
        $content = $this->sanitize_utf8($content);
        
        // 限制内容长度，避免prompt过长
        if (mb_strlen($content, 'UTF-8') > 2000) {
            $content = mb_substr($content, 0, 2000, 'UTF-8') . '...';
        }
        
        return $content;
    }
    
    /**
     * 清理文本中的无效UTF-8字符
     * 
     * @param string $text
     * @return string
     */
    private function sanitize_utf8($text) {
        // 方法1: 使用mb_convert_encoding修复编码
        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        
        // 方法2: 移除无效的UTF-8字符
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // 方法3: 确保是有效的UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            // 如果仍然不是有效的UTF-8，使用iconv转换
            if (function_exists('iconv')) {
                $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            }
        }
        
        // 移除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * 保存图片到WordPress媒体库
     * 
     * @param string $image_url 图片URL
     * @param \WP_Post $post 文章对象
     * @return int|false 附件ID或false
     */
    public function save_image_to_media_library($image_url, $post) {
        // 检查URL是否有效
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 下载图片
        $response = wp_remote_get($image_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'WordPress Generate Cover Plugin'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }
        
        // 生成文件名
        $filename = sanitize_file_name($post->post_title . '_cover_' . time() . '.jpg');
        
        // 上传到WordPress
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            return false;
        }
        
        // 创建附件
        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title' => $post->post_title . ' - AI生成封面',
            'post_content' => '由Generate Cover插件自动生成的封面图片',
            'post_status' => 'inherit',
            'post_parent' => $post->ID
        ];
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (!$attachment_id) {
            return false;
        }
        
        // 生成附件元数据
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * 重新生成封面
     * 
     * @param int $post_id 文章ID
     * @return array
     */
    public function regenerate_cover($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return [
                'success' => false,
                'message' => '文章不存在'
            ];
        }
        
        // 删除现有的特色图片
        $current_thumbnail = get_post_thumbnail_id($post_id);
        if ($current_thumbnail) {
            wp_delete_attachment($current_thumbnail, true);
        }
        
        return $this->generate_cover($post);
    }
    
    /**
     * 批量生成封面
     * 
     * @param array $post_ids 文章ID数组
     * @return array
     */
    public function batch_generate_covers($post_ids) {
        $results = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            
            if (!$post) {
                $results[$post_id] = [
                    'success' => false,
                    'message' => '文章不存在'
                ];
                continue;
            }
            
            $result = $this->generate_cover($post);
            $results[$post_id] = $result;
            
            // 避免请求过于频繁
            sleep(1);
        }
        
        return $results;
    }
    
    /**
     * 获取生成历史
     * 
     * @param int $post_id 文章ID
     * @return array
     */
    public function get_generation_history($post_id) {
        $history = get_post_meta($post_id, '_cover_generation_history', true);
        
        if (!is_array($history)) {
            $history = [];
        }
        
        return $history;
    }
    
    /**
     * 记录生成历史
     * 
     * @param int $post_id 文章ID
     * @param array $result 生成结果
     * @return void
     */
    private function record_generation_history($post_id, $result) {
        $history = $this->get_generation_history($post_id);
        
        $history[] = [
            'timestamp' => current_time('mysql'),
            'success' => $result['success'],
            'prompt' => isset($result['prompt']) ? $result['prompt'] : '',
            'attachment_id' => isset($result['attachment_id']) ? $result['attachment_id'] : null,
            'message' => isset($result['message']) ? $result['message'] : ''
        ];
        
        // 只保留最近10次记录
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        update_post_meta($post_id, '_cover_generation_history', $history);
    }
}
