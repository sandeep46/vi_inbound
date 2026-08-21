<?php

require_once __DIR__ . '/functions.php';

validateMethod();

// Enable API authentication after configuring API_KEY.
// validateApiKey();

$data = getJsonInput();

$requiredFields = [
    'Callid',
    'Dni',
    'Cli',
    'agent',
    'Status'
];
//{"Dni":"8657929461","Cli":"9326145839","agent":"8657970955","Status":"onCallFail","Callid":"393814798"}
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        sendResponse(400, "Missing required parameter: {$field}");
    }
}

if ($data['Status'] !== 'onCallFail') {
    sendResponse(400, 'Invalid Status. Expected onCallFail');
}

logRequest('on-call-fail', $data);

$bitrixResult = insertToBitrix($data);

if (!$bitrixResult['success']) {
    sendResponse(
        502,
        'Failed to save call fail data into Bitrix',
        ['error' => $bitrixResult['error']]
    );
}

sendResponse(
    200,
    'Call fail data received successfully',
    [
        'Callid' => $data['Callid'],
        'bitrix' => $bitrixResult['data']
    ]
);

function insertToBitrix(array $data)
{
    $fields = [
        'TITLE' => $data['Dni'],
        'SOURCE_ID' => 'UC_WNPZ20',
        'PHONE' => [
            ['VALUE' => $data['Cli'], 'VALUE_TYPE' => 'WORK']
        ],
        'COMMENTS' => 'Agent: ' . $data['agent'] . PHP_EOL . 'Status: ' . $data['Status'] . PHP_EOL . 'Call ID: ' . $data['Callid'],
    ];

    return upsertBitrixLeadByCallId($data, $fields);
}
