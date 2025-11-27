<?php
// api/index.php - 直接处理根路径请求
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/plain; charset=utf-8');

// 获取查询参数
$query = $_SERVER['REQUEST_URI'];
if (strpos($query, '?') !== false) {
    $queryString = substr($query, strpos($query, '?') + 1);
    parse_str($queryString, $params);
} else {
    $params = $_GET;
}

// 主处理逻辑
if (!isset($params['str'])) {
    http_response_code(400);
    die('ERROR: Missing parameters. Usage: ?str=domain,email,api_key|zone_id,ip');
}

$input = $params['str'];
$paramsArray = explode(',', $input);

if (count($paramsArray) < 4) {
    http_response_code(400);
    die('ERROR: Invalid parameter format. Use: domain,email,api_key|zone_id,ip');
}

list($domain, $email, $auth, $ip) = $paramsArray;

// 这里放置之前的所有函数定义 (getZoneId, getOrCreateDnsRecord, updateDnsRecord, httpRequest等)
// ... [把之前cfddns.php中的所有函数复制到这里] ...

// 执行更新
try {
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
        die('SUCCESS: DNS record ' . $domain . ' updated to ' . $ip);
    } else {
        http_response_code(500);
        die('ERROR: Failed to update DNS record');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('ERROR: ' . $e->getMessage());
}

// 把所有辅助函数放在这里...
function getZoneId($domain, $email, $api_key) {
    // ... [复制之前的getZoneId函数] ...
}

function getOrCreateDnsRecord($domain, $ip, $email, $api_key, $zone_id) {
    // ... [复制之前的getOrCreateDnsRecord函数] ...
}

// ... [复制其他所有需要的函数] ...
?>