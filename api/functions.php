<?php

require_once __DIR__ . '/config.php';

function sendResponse($statusCode, $message, $data = [])
{
    http_response_code($statusCode);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => ($statusCode >= 200 && $statusCode < 300),
        'message' => $message,
        'data'    => $data
    ]);

    exit;
}

function getJsonInput()
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') === false) {
        sendResponse(415, 'Content-Type must be application/json');
    }

    $input = file_get_contents('php://input');

    if (empty($input)) {
        sendResponse(400, 'Request body is empty');
    }

    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, 'Invalid JSON');
    }

    return $data;
}

function validateMethod()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'Only POST method is allowed');
    }
}

function validateApiKey()
{
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if ($apiKey !== API_KEY) {
        sendResponse(401, 'Invalid API key');
    }
}

function logRequest($endpoint, $requestData)
{
    $logDirectory = __DIR__ . '/logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0755, true);
    }

    $logFile = $logDirectory . '/' . date('Y-m-d') . '.log';

    $logData = [
        'datetime' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        'request'  => $requestData
    ];

    file_put_contents(
        $logFile,
        json_encode($logData) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function bitrixRequest($method, array $payload)
{
    if (BITRIX_WEBHOOK_URL === '') {
        return [
            'success' => false,
            'error' => 'BITRIX_WEBHOOK_URL is not configured'
        ];
    }

    $url = rtrim(BITRIX_WEBHOOK_URL, '/') . '/' . $method . '.json';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));

    $json = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($json === false) {
        return [
            'success' => false,
            'error' => $curlError ?: 'Unknown cURL error'
        ];
    }

    $result = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid Bitrix response',
            'response' => $json
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300 || isset($result['error'])) {
        return [
            'success' => false,
            'error' => $result['error_description'] ?? $result['error'] ?? "Bitrix HTTP {$httpCode}",
            'response' => $result
        ];
    }

    return [
        'success' => true,
        'data' => $result
    ];
}

function getCallIdFromData(array $data)
{
    return trim((string)($data['call_id'] ?? $data['Callid'] ?? ''));
}

function findBitrixLeadIdByCallId($callId)
{
    if ($callId === '') {
        return null;
    }

    $result = bitrixRequest('crm.lead.list', [
        'order' => ['ID' => 'DESC'],
        'filter' => [
            '%COMMENTS' => 'Call ID: ' . $callId
        ],
        'select' => ['ID']
    ]);

    if (!$result['success']) {
        return null;
    }

    $leads = $result['data']['result'] ?? [];
    if (!is_array($leads) || empty($leads[0]['ID'])) {
        return null;
    }

    return (string)$leads[0]['ID'];
}

function upsertBitrixLeadByCallId(array $data, array $fields)
{
    $callId = getCallIdFromData($data);

    if ($callId === '') {
        return bitrixRequest('crm.lead.add', ['fields' => $fields]);
    }

    $leadId = findBitrixLeadIdByCallId($callId);

    if ($leadId) {
        $result = bitrixRequest('crm.lead.update', [
            'id' => $leadId,
            'fields' => $fields
        ]);

        if ($result['success']) {
            $result['data']['action'] = 'updated';
            $result['data']['lead_id'] = (string)$leadId;
        }

        return $result;
    }

    $result = bitrixRequest('crm.lead.add', ['fields' => $fields]);

    if ($result['success'] && isset($result['data']['result'])) {
        $result['data']['action'] = 'inserted';
        $result['data']['lead_id'] = (string)$result['data']['result'];
    }

    return $result;
}
