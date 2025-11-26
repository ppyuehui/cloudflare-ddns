<?php
/**
 * Cloudflare DDNS for Vercel PHP
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain; charset=utf-8');

// 错误处理
function returnError($message) {
    http_response_code(400);
    die('ERROR: ' . $message);
}

function returnSuccess($message) {
    die('SUCCESS: ' . $message);
}

// 主处理函数
function handleRequest() {
    // 获取参数
    if (!isset($_GET['str'])) {
        returnError('Missing parameters. Usage: ?str=domain,email,api_key|zone_id,ip');
    }
    
    $input = $_GET['str'];
    $params = explode(',', $input);
    
    if (count($params) < 4) {
        returnError('Invalid parameter format. Use: domain,email,api_key|zone_id,ip');
    }
    
    list($domain, $email, $auth, $ip) = $params;
    
    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        returnError('Invalid IP address: ' . $ip);
    }
    
    // 解析认证信息
    if (strpos($auth, '|') !== false) {
        list($api_key, $zone_id) = explode('|', $auth);
    } else {
        $api_key = $auth;
        $zone_id = getZoneId($domain, $email, $api_key);
    }
    
    // 获取或创建DNS记录
    $dns_record = getOrCreateDnsRecord($domain, $ip, $email, $api_key, $zone_id);
    
    // 更新DNS记录
    $result = updateDnsRecord($domain, $ip, $email, $api_key, $zone_id, $dns_record['id']);
    
    if ($result) {
        returnSuccess('DNS record ' . $domain . ' updated to ' . $ip);
    } else {
        returnError('Failed to update DNS record');
    }
}

// 获取Zone ID
function getZoneId($domain, $email, $api_key) {
    $base_domain = getBaseDomain($domain);
    $url = 'https://api.cloudflare.com/client/v4/zones?name=' . urlencode($base_domain);
    
    $response = httpRequest($url, [
        'X-Auth-Email: ' . $email,
        'X-Auth-Key: ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    if ($response['code'] !== 200) {
        returnError('Failed to get Zone ID. HTTP Code: ' . $response['code']);
    }
    
    $data = json_decode($response['body'], true);
    
    if (!$data['success'] || empty($data['result'])) {
        returnError('Zone not found for domain: ' . $base_domain);
    }
    
    return $data['result'][0]['id'];
}

// 获取或创建DNS记录
function getOrCreateDnsRecord($domain, $ip, $email, $api_key, $zone_id) {
    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records?type=A&name=' . urlencode($domain);
    
    $response = httpRequest($url, [
        'X-Auth-Email: ' . $email,
        'X-Auth-Key: ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    if ($response['code'] !== 200) {
        returnError('Failed to get DNS records. HTTP Code: ' . $response['code']);
    }
    
    $data = json_decode($response['body'], true);
    
    if (!$data['success']) {
        returnError('API error: ' . json_encode($data['errors']));
    }
    
    // 如果记录存在，返回记录信息
    if (!empty($data['result'])) {
        return [
            'id' => $data['result'][0]['id'],
            'exists' => true
        ];
    }
    
    // 记录不存在，创建新记录
    return [
        'id' => createDnsRecord($domain, $ip, $email, $api_key, $zone_id),
        'exists' => false
    ];
}

// 创建DNS记录
function createDnsRecord($domain, $ip, $email, $api_key, $zone_id) {
    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records';
    $post_data = json_encode([
        'type' => 'A',
        'name' => $domain,
        'content' => $ip,
        'ttl' => 120,
        'proxied' => false
    ]);
    
    $response = httpRequest($url, [
        'X-Auth-Email: ' . $email,
        'X-Auth-Key: ' . $api_key,
        'Content-Type: application/json'
    ], $post_data, 'POST');
    
    if ($response['code'] !== 200) {
        returnError('Failed to create DNS record. HTTP Code: ' . $response['code']);
    }
    
    $data = json_decode($response['body'], true);
    
    if (!$data['success']) {
        returnError('Failed to create DNS record: ' . json_encode($data['errors']));
    }
    
    return $data['result']['id'];
}

// 更新DNS记录
function updateDnsRecord($domain, $ip, $email, $api_key, $zone_id, $record_id) {
    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records/' . $record_id;
    $put_data = json_encode([
        'type' => 'A',
        'name' => $domain,
        'content' => $ip,
        'ttl' => 120,
        'proxied' => false
    ]);
    
    $response = httpRequest($url, [
        'X-Auth-Email: ' . $email,
        'X-Auth-Key: ' . $api_key,
        'Content-Type: 'application/json'
    ], $put_data, 'PUT');
    
    if ($response['code'] !== 200) {
        return false;
    }
    
    $data = json_decode($response['body'], true);
    return $data['success'];
}

// HTTP请求函数（使用cURL）
function httpRequest($url, $headers = [], $data = null, $method = 'GET') {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ];
    
    if ($method === 'POST' && $data) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
    } elseif ($method === 'PUT' && $data) {
        $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $options[CURLOPT_POSTFIELDS] = $data;
    }
    
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $http_code,
        'body' => $body,
        'error' => $error
    ];
}

// 提取基础域名
function getBaseDomain($domain) {
    $parts = explode('.', $domain);
    if (count($parts) >= 2) {
        return $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
    }
    return $domain;
}

// 执行主程序
handleRequest();
?>