<?php

require_once __DIR__ . '/functions.php';

validateMethod();

// Enable API authentication after configuring API_KEY.
// validateApiKey();

$data = getJsonInput();

$requiredFields = [
    'call_id',
    'dni',
    'cli',
    'agent_number',
    'call_start_time',
   // 'Call_End_Time',
    'call_duration',
    'og_start_time',
    'og_duration',
    'og_end_time',
   // 'Recording_URL'
];
//{"cli":"9326145839","dni":"8657929461","agent_number":"8657970955","call_start_time":"12082026130012","all_end_time":"12082026130159",
//"call_duration":"","og_start_time":"","og_duration":"","og_end_time":"","call_id":"393801007"}
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        sendResponse(400, "Missing required parameter: {$field}");
    }
}

logRequest('post-call', $data);

$bitrixResult = insertToBitrix($data);

if (!$bitrixResult['success']) {
    sendResponse(
        502,
        'Failed to save post call data into Bitrix',
        ['error' => $bitrixResult['error']]
    );
}

sendResponse(
    200,
    'Post call data received successfully',
    [
        'call_id' => $data['call_id'] ?? '',
        'bitrix' => $bitrixResult['data']
    ]
);

function formatCallDateTime($datetime)
{
    if (empty($datetime)) {
        return '';
    }

    $date = DateTime::createFromFormat('dmYHis', $datetime);

    return $date ? $date->format('d M Y, h:i:s A') : $datetime;
}


function insertToBitrix(array $data)
{
    $Recording_URL=isset($data['Recording_URL'])?$data['Recording_URL']:"";
    $comments = [
    'CLI: ' . ($data['cli'] ?? ''),
    'DNI: ' . ($data['dni'] ?? ''),
    'Agent Number: ' . ($data['agent_number'] ?? ''),
    'Call Start Time: ' . formatCallDateTime($data['call_start_time'] ?? ''),
    'Call End Time: ' . formatCallDateTime($data['all_end_time'] ?? ''),
    'Call Duration: ' . ($data['call_duration'] ?? '') . ' seconds',
    'OG Start Time: ' . formatCallDateTime($data['og_start_time'] ?? ''),
    'OG Duration: ' . ($data['og_duration'] ?? '') . ' seconds',
    'OG End Time: ' . formatCallDateTime($data['og_end_time'] ?? ''),
    'Call ID: ' . ($data['call_id'] ?? ''),
    'Recording URL: ' . ($Recording_URL ?? ''),
];

$fields = [
    'TITLE' => ($data['cli'] ?? '') . '-' . ($data['call_start_time'] ?? ''),
    'SOURCE_ID' => 'UC_YU7Y4Z',
    'PHONE' => [
        [
            'VALUE' => $data['cli'],
            'VALUE_TYPE' => 'WORK'
        ]
    ],
    'COMMENTS' => implode(PHP_EOL, $comments),
];

    return upsertBitrixLeadByCallId($data, $fields);
}
