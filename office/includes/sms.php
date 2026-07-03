<?php
require_once __DIR__.'/bootstrap.php';

function sms_secret_key(): string {
    $cfg = require __DIR__.'/../config/database.php';
    return hash('sha256', ($cfg['database'] ?? '').'|'.($cfg['username'] ?? '').'|'.($cfg['password'] ?? '').'|office-sms-secret', true);
}

function sms_encrypt_secret($plain): string {
    $plain = (string)$plain;
    if ($plain === '') return '';
    if (!function_exists('openssl_encrypt')) return base64_encode($plain);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', sms_secret_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv.$cipher);
}

function sms_decrypt_secret($encoded): string {
    $encoded = (string)$encoded;
    if ($encoded === '') return '';
    $raw = base64_decode($encoded, true);
    if ($raw === false) return '';
    if (!function_exists('openssl_decrypt') || strlen($raw) <= 16) return (string)$raw;
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', sms_secret_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function sms_setting_row(): array {
    if (!table_exists('sms_settings')) return [];
    $row = db()->query('SELECT * FROM sms_settings ORDER BY id LIMIT 1')->fetch();
    return $row ?: [];
}

function sms_log($recipientType, $recipientId, $mobile, $templateId, $message, $response, $status, $sentBy=null): void {
    if (!table_exists('sms_logs')) return;
    $cols = table_columns('sms_logs');
    $data = [
        'recipient_type' => $recipientType,
        'recipient_id' => $recipientId ?: null,
        'mobile_number' => $mobile,
        'sms_template_id' => $templateId ?: null,
        'message' => $message,
        'gateway_response' => is_scalar($response) ? (string)$response : json_encode($response),
        'status' => $status,
        'sent_by' => $sentBy ?: (user()['id'] ?? null),
        'sent_at' => in_array($status, ['sent','failed'], true) ? date('Y-m-d H:i:s') : null,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $data = array_intersect_key($data, array_flip($cols));
    if (!$data) return;
    $keys = array_keys($data);
    db()->prepare('INSERT INTO sms_logs (`'.implode('`,`',$keys).'`) VALUES ('.implode(',', array_fill(0, count($keys), '?')).')')
        ->execute(array_values($data));
}

function sms_clean_mobile(string $mobile): string {
    $mobile = trim($mobile);
    $mobile = preg_replace('/[^0-9+]/', '', $mobile) ?: '';
    if (str_starts_with($mobile, '+88')) $mobile = substr($mobile, 3);
    if (str_starts_with($mobile, '88') && strlen($mobile) === 13) $mobile = substr($mobile, 2);
    return $mobile;
}

function sms_normalize_url_template(string $url): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    $legacy = [
        '(APIKEY)' => '{api_key}',
        '(API KEY)' => '{api_key}',
        '(ApiKey)' => '{api_key}',
        '(Approved Sender ID)' => '{sender_id}',
        '(SENDER ID)' => '{sender_id}',
        '(Sender ID)' => '{sender_id}',
        '(CONTACT NUMBER)' => '{to}',
        '(Contact Number)' => '{to}',
        '(MOBILE)' => '{to}',
        '(MSISDN)' => '{to}',
        '(Message Content)' => '{message}',
        '(MESSAGE CONTENT)' => '{message}',
        '(MESSAGE)' => '{message}',
    ];
    return strtr($url, $legacy);
}

function sms_build_url(array $settings, string $mobile, string $message, string $action='send'): string {
    $apiKey = sms_decrypt_secret($settings['api_key_encrypted'] ?? ($settings['api_key'] ?? ''));
    $password = sms_decrypt_secret($settings['password_encrypted'] ?? '');
    $mobile = sms_clean_mobile($mobile);
    $url = (string)($action === 'balance' && !empty($settings['balance_url']) ? $settings['balance_url'] : ($settings['api_url'] ?? ''));
    $url = sms_normalize_url_template($url);

    $vars = [
        '{to}' => rawurlencode($mobile),
        '{mobile}' => rawurlencode($mobile),
        '{msisdn}' => rawurlencode($mobile),
        '{message}' => rawurlencode($message),
        '{smstext}' => rawurlencode($message),
        '{sender_id}' => rawurlencode((string)($settings['sender_id'] ?? '')),
        '{sender}' => rawurlencode((string)($settings['sender_id'] ?? '')),
        '{api_key}' => rawurlencode($apiKey),
        '{apikey}' => rawurlencode($apiKey),
        '{token}' => rawurlencode($apiKey),
        '{username}' => rawurlencode((string)($settings['username'] ?? '')),
        '{password}' => rawurlencode($password),
        '{action}' => rawurlencode($action),
    ];

    if (str_contains($url, '{')) {
        $built = strtr($url, $vars);
    } else {
        $params = [
            'to' => $mobile,
            'message' => $message,
            'sender_id' => (string)($settings['sender_id'] ?? ''),
            'api_key' => $apiKey,
            'username' => (string)($settings['username'] ?? ''),
            'password' => $password,
        ];
        if ($action === 'balance') $params['action'] = 'balance';
        $built = $url.(str_contains($url, '?') ? '&' : '?').http_build_query(array_filter($params, fn($v)=>$v !== ''));
    }

    return preg_replace('/\s+/', '%20', $built) ?: $built;
}

function sms_http_request($url): array {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return ['http_code'=>0, 'body'=>'', 'error'=>'Gateway API URL must start with http:// or https://'];
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return ['http_code'=>0, 'body'=>'', 'error'=>'Gateway API URL is malformed after placeholder replacement. Use {api_key}, {to}, {message}, {sender_id}.'];
    }

    $response = '';
    $code = 0;
    $error = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['http_code'=>0, 'body'=>'', 'error'=>'Could not initialize SMS gateway request.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ColorPolytechERP/1.0',
        ]);
        $response = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http'=>['timeout'=>30], 'ssl'=>['verify_peer'=>true, 'verify_peer_name'=>true]]);
        $response = (string)@file_get_contents($url, false, $ctx);
        $code = $response !== '' ? 200 : 0;
        if ($response === '') $error = 'No response from gateway.';
    }
    return ['http_code'=>$code, 'body'=>$response, 'error'=>$error];
}

function sms_send_message($mobile, $message, $templateId=null, $recipientType='test', $recipientId=null): array {
    $settings = sms_setting_row();
    $mobile = sms_clean_mobile((string)$mobile);
    $message = trim((string)$message);
    if ($mobile === '' || $message === '') {
        sms_log($recipientType, $recipientId, $mobile, $templateId, $message, 'Missing mobile or message.', 'failed');
        return ['ok'=>false, 'response'=>'Missing mobile or message.'];
    }
    if (empty($settings) || empty($settings['is_active'])) {
        sms_log($recipientType, $recipientId, $mobile, $templateId, $message, 'SMS disabled.', 'failed');
        return ['ok'=>false, 'response'=>'SMS is disabled.'];
    }
    if (trim((string)($settings['api_url'] ?? '')) === '') {
        sms_log($recipientType, $recipientId, $mobile, $templateId, $message, 'Gateway API URL missing.', 'failed');
        return ['ok'=>false, 'response'=>'Gateway API URL missing.'];
    }
    $result = sms_http_request(sms_build_url($settings, $mobile, $message, 'send'));
    $ok = $result['http_code'] >= 200 && $result['http_code'] < 300 && $result['error'] === '';
    sms_log($recipientType, $recipientId, $mobile, $templateId, $message, $result, $ok ? 'sent' : 'failed');
    return ['ok'=>$ok, 'response'=>($result['error'] ?: $result['body'] ?: ('HTTP '.$result['http_code']))];
}

function sms_check_balance(): array {
    $settings = sms_setting_row();
    if (empty($settings) || empty($settings['is_active'])) return ['ok'=>false, 'response'=>'SMS is disabled.'];
    if (trim((string)($settings['api_url'] ?? '')) === '' && trim((string)($settings['balance_url'] ?? '')) === '') {
        return ['ok'=>false, 'response'=>'Gateway or balance URL missing.'];
    }
    $result = sms_http_request(sms_build_url($settings, '', '', 'balance'));
    return ['ok'=>$result['http_code'] >= 200 && $result['http_code'] < 300 && $result['error'] === '', 'response'=>($result['error'] ?: $result['body'] ?: ('HTTP '.$result['http_code']))];
}
