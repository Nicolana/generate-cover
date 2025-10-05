<?php
/**
 * 即梦AI API 客户端类
 * 用于调用即梦AI图片生成服务
 */

namespace GenerateCover;

class Jimeng_AI {
    
    private $access_key;
    private $secret_key;
    private $base_url = 'https://visual.volcengineapi.com';
    private $region = 'cn-north-1';
    private $service = 'cv';
    
    public function __construct($access_key = null, $secret_key = null) {
        $this->access_key = $access_key ?: $this->get_access_key();
        $this->secret_key = $secret_key ?: $this->get_secret_key();
    }
    
    private function get_access_key() {
        $options = get_option('generate_cover_options');
        return isset($options['jimeng_access_key']) ? $options['jimeng_access_key'] : '';
    }
    
    private function get_secret_key() {
        $options = get_option('generate_cover_options');
        return isset($options['jimeng_secret_key']) ? $options['jimeng_secret_key'] : '';
    }
    
    /**
     * 生成图片
     * 
     * @param string $prompt 图片prompt
     * @param array $options 生成选项
     * @return array
     */
    public function generate_image($prompt, $options = []) {
        if (empty($this->access_key) || empty($this->secret_key)) {
            return [
                'success' => false,
                'message' => '即梦AI API密钥未配置'
            ];
        }
        
        // 默认选项
        $default_options = [
            'size' => 4194304, // 2048*2048
            'force_single' => true,
            'min_ratio' => 1/3,
            'max_ratio' => 3
        ];
        
        $options = array_merge($default_options, $options);
        
        // 提交生成任务
        $submit_result = $this->submit_task($prompt, $options);
        
        if (!$submit_result['success']) {
            return $submit_result;
        }
        
        $task_id = $submit_result['task_id'];
        
        // 轮询查询结果
        return $this->poll_result($task_id);
    }
    
    /**
     * 提交生成任务
     * 
     * @param string $prompt
     * @param array $options
     * @return array
     */
    private function submit_task($prompt, $options) {
        $url = $this->base_url . '?Action=CVSync2AsyncSubmitTask&Version=2022-08-31';
        
        $body = [
            'req_key' => 'jimeng_t2i_v40',
            'prompt' => $prompt,
            'size' => $options['size'],
            'force_single' => $options['force_single'],
            'min_ratio' => $options['min_ratio'],
            'max_ratio' => $options['max_ratio']
        ];
        
        error_log('Jimeng AI: Task body = ' . print_r($body, true));
        
        $json_body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json_body === false) {
            error_log('Jimeng AI: JSON encode failed - ' . json_last_error_msg());
            return [
                'success' => false,
                'message' => 'JSON编码失败: ' . json_last_error_msg()
            ];
        }
        
        $headers = $this->get_auth_headers('CVSync2AsyncSubmitTask', $body);
        
        error_log('Jimeng AI: Submitting task to ' . $url);
        error_log('Jimeng AI: Request body = ' . $json_body);
        error_log('Jimeng AI: Request headers = ' . print_r($headers, true));
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $json_body,
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            error_log('Jimeng AI: Submit request error - ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => '请求失败：' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Jimeng AI: Submit response status = ' . $status_code);
        error_log('Jimeng AI: Response headers = ' . print_r($response_headers, true));
        error_log('Jimeng AI: Response body = ' . $body);
        
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['message']) ? $data['message'] : '未知错误';
            return [
                'success' => false,
                'message' => 'API错误：' . $error_message
            ];
        }
        
        if (!isset($data['data']['task_id'])) {
            return [
                'success' => false,
                'message' => '响应格式错误'
            ];
        }
        
        return [
            'success' => true,
            'task_id' => $data['data']['task_id']
        ];
    }
    
    /**
     * 轮询查询结果
     * 
     * @param string $task_id
     * @return array
     */
    private function poll_result($task_id) {
        $max_attempts = 30; // 最多轮询30次
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $result = $this->get_result($task_id);
            
            if (!$result['success']) {
                return $result;
            }
            
            $status = $result['status'];
            
            if ($status === 'done') {
                return [
                    'success' => true,
                    'image_urls' => $result['image_urls'],
                    'binary_data' => $result['binary_data']
                ];
            } elseif ($status === 'not_found') {
                return [
                    'success' => false,
                    'message' => '任务未找到或已过期'
                ];
            } elseif ($status === 'expired') {
                return [
                    'success' => false,
                    'message' => '任务已过期'
                ];
            }
            
            // 等待2秒后重试
            sleep(2);
            $attempt++;
        }
        
        return [
            'success' => false,
            'message' => '生成超时，请稍后重试'
        ];
    }
    
    /**
     * 查询任务结果
     * 
     * @param string $task_id
     * @return array
     */
    private function get_result($task_id) {
        $url = $this->base_url . '?Action=CVSync2AsyncGetResult&Version=2022-08-31';
        
        $body = [
            'req_key' => 'jimeng_t2i_v40',
            'task_id' => $task_id,
            'req_json' => json_encode([
                'return_url' => true,
                'logo_info' => [
                    'add_logo' => false
                ]
            ])
        ];
        
        $json_body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json_body === false) {
            error_log('Jimeng AI: JSON encode failed - ' . json_last_error_msg());
            return [
                'success' => false,
                'message' => 'JSON编码失败: ' . json_last_error_msg()
            ];
        }
        
        $headers = $this->get_auth_headers('CVSync2AsyncGetResult', $body);
        
        error_log('Jimeng AI: Getting result from ' . $url);
        error_log('Jimeng AI: Request body = ' . $json_body);
        error_log('Jimeng AI: Request headers = ' . print_r($headers, true));
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $json_body,
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            error_log('Jimeng AI: Get result request error - ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => '请求失败：' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('Jimeng AI: Get result response status = ' . $status_code);
        error_log('Jimeng AI: Response headers = ' . print_r($response_headers, true));
        error_log('Jimeng AI: Response body = ' . $body);
        
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['message']) ? $data['message'] : '未知错误';
            return [
                'success' => false,
                'message' => 'API错误：' . $error_message
            ];
        }
        
        if (!isset($data['data'])) {
            return [
                'success' => false,
                'message' => '响应格式错误'
            ];
        }
        
        $result_data = $data['data'];
        
        return [
            'success' => true,
            'status' => $result_data['status'],
            'image_urls' => isset($result_data['image_urls']) ? $result_data['image_urls'] : [],
            'binary_data' => isset($result_data['binary_data_base64']) ? $result_data['binary_data_base64'] : []
        ];
    }
    
    /**
     * 获取认证头
     * 
     * @param string $action
     * @param array $body
     * @return array
     */
    private function get_auth_headers($action, $body) {
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        // 计算body的SHA256哈希
        $body_json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $content_sha256 = hash('sha256', $body_json);
        
        error_log('Jimeng AI: Body JSON = ' . $body_json);
        error_log('Jimeng AI: Content SHA256 = ' . $content_sha256);
        
        // 构建请求参数
        $query_params = [
            'Action' => $action,
            'Version' => '2022-08-31'
        ];
        
        // 按照官方示例的格式构建签名
        $canonical_query = http_build_query($query_params);
        
        // 按照官方示例的精确格式
        $canonical_headers = "content-type:application/json\nhost:visual.volcengineapi.com\nx-content-sha256:{$content_sha256}\nx-date:{$timestamp}";
        $signed_headers = "content-type;host;x-content-sha256;x-date";
        
        // 构建 Canonical Request - 完全按照官方示例
        $canonical_request = implode("\n", [
            'POST',
            '/',
            $canonical_query,
            $canonical_headers,
            '',  // 空行
            $signed_headers,
            $content_sha256
        ]);
        
        error_log('Jimeng AI: Canonical Request = ' . $canonical_request);
        
        // 计算签名 - 按照官方示例
        $hashed_canonical_request = hash('sha256', $canonical_request);
        $credential_scope = "{$date}/{$this->region}/{$this->service}/request";
        $string_to_sign = "HMAC-SHA256\n{$timestamp}\n{$credential_scope}\n{$hashed_canonical_request}";
        
        error_log('Jimeng AI: String to Sign = ' . $string_to_sign);
        
        // 计算签名密钥 - 按照官方示例
        $k_date = hash_hmac('sha256', $date, $this->secret_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'request', $k_service, true);
        
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        $authorization = "HMAC-SHA256 Credential={$this->access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
        
        error_log('Jimeng AI: Authorization = ' . $authorization);
        
        return [
            'Authorization' => $authorization,
            'Content-Type' => 'application/json',
            'Host' => 'visual.volcengineapi.com',
            'X-Content-Sha256' => $content_sha256,
            'X-Date' => $timestamp
        ];
    }
    
    /**
     * 测试API连接
     * 
     * @return array
     */
    public function test_connection() {
        if (empty($this->access_key) || empty($this->secret_key)) {
            return [
                'success' => false,
                'message' => 'API密钥未配置'
            ];
        }
        
        // 使用一个简单的prompt测试
        $result = $this->generate_image('a simple test image', [
            'size' => 1024 * 1024, // 1K分辨率，更快
            'force_single' => true
        ]);
        
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
