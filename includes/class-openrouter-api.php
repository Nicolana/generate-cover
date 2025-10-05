<?php
/**
 * OpenRouter API 客户端类
 * 用于调用OpenRouter API生成图片prompt
 */

namespace GenerateCover;

class OpenRouter_API {
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    private $model;
    
    public function __construct($api_key = null, $model = 'openai/gpt-4o') {
        $this->api_key = $api_key ?: $this->get_api_key();
        $this->model = $model ?: $this->get_default_model();
    }
    
    private function get_api_key() {
        $options = get_option('generate_cover_options');
        return isset($options['openrouter_api_key']) ? $options['openrouter_api_key'] : '';
    }
    
    private function get_default_model() {
        $options = get_option('generate_cover_options');
        return isset($options['default_model']) ? $options['default_model'] : 'openai/gpt-4o';
    }
    
    /**
     * 生成图片prompt
     * 
     * @param string $content 文章内容
     * @param string $title 文章标题
     * @return array
     */
    public function generate_image_prompt($content, $title = '') {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'OpenRouter API密钥未配置'
            ];
        }
        
        // 构建prompt
        $system_prompt = "你是一个专业的图片prompt生成专家。请根据给定的文章内容，生成一个适合作为文章封面图片的英文prompt。

要求：
1. prompt必须是英文
2. 适合作为文章封面图片
3. 风格要现代、专业、吸引人
4. 长度控制在100-200个单词
5. 包含具体的视觉元素描述
6. 避免包含人物面孔（除非文章内容明确需要）
7. 使用高质量的摄影或插画风格描述

请直接返回prompt，不要包含其他解释文字。";

        $user_prompt = "文章标题：{$title}\n\n文章内容：{$content}";
        
        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ];
        
        return $this->make_request($messages);
    }
    
    /**
     * 生成文章摘要
     * 
     * @param string $content 文章内容
     * @param string $title 文章标题
     * @return array
     */
    public function generate_summary($content, $title = '') {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'OpenRouter API密钥未配置'
            ];
        }
        
        $system_prompt = "你是一个专业的内容编辑。请根据给定的文章内容，生成一个简洁的文章摘要。

要求：
1. 摘要长度控制在100-150字
2. 突出文章的核心观点和主要信息
3. 语言简洁明了
4. 保持原文的语气和风格
5. 不要添加个人评论或解释

请直接返回摘要内容，不要包含其他解释文字。";

        $user_prompt = "文章标题：{$title}\n\n文章内容：{$content}";
        
        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_prompt
            ]
        ];
        
        return $this->make_request($messages);
    }
    
    /**
     * 发送API请求
     * 
     * @param array $messages
     * @return array
     */
    private function make_request($messages) {
        $url = $this->base_url . '/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 0.9
        ];
        
        error_log('OpenRouter API: Preparing request');
        error_log('OpenRouter API: Model = ' . $this->model);
        error_log('OpenRouter API: Messages count = ' . count($messages));
        
        // 使用 JSON_UNESCAPED_UNICODE 和 JSON_INVALID_UTF8_SUBSTITUTE 选项
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            error_log('OpenRouter API: JSON encode failed - ' . json_last_error_msg());
            error_log('OpenRouter API: Data dump - ' . print_r($data, true));
            return [
                'success' => false,
                'message' => 'JSON编码失败: ' . json_last_error_msg()
            ];
        }
        
        error_log('OpenRouter API: JSON body length = ' . strlen($body));
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => home_url(),
            'X-Title' => 'Generate Cover WordPress Plugin'
        ];
        
        error_log('OpenRouter API: Sending POST request to ' . $url);
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('OpenRouter API: Request error - ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => '请求失败：' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('OpenRouter API: Response status = ' . $status_code);
        error_log('OpenRouter API: Response body length = ' . strlen($response_body));
        
        $data = json_decode($response_body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '未知错误';
            error_log('OpenRouter API: API error - ' . $error_message);
            error_log('OpenRouter API: Full response - ' . $response_body);
            return [
                'success' => false,
                'message' => 'API错误：' . $error_message
            ];
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('OpenRouter API: Invalid response format');
            error_log('OpenRouter API: Response data - ' . print_r($data, true));
            return [
                'success' => false,
                'message' => '响应格式错误'
            ];
        }
        
        error_log('OpenRouter API: Request successful');
        
        return [
            'success' => true,
            'content' => trim($data['choices'][0]['message']['content'])
        ];
    }
    
    /**
     * 测试API连接
     * 
     * @return array
     */
    public function test_connection() {
        $messages = [
            [
                'role' => 'user',
                'content' => 'Hello, please respond with "API connection successful"'
            ]
        ];
        
        $result = $this->make_request($messages);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'API连接正常'
            ];
        } else {
            return $result;
        }
    }
}
