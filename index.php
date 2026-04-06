<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

ignore_user_abort(true);
set_time_limit(30);

$input = file_get_contents("php://input");
$dataWebhook = json_decode($input, true);

if (!$dataWebhook) {
    exit;
}

$apiToken = getenv("CLICKUP_API_TOKEN");

$poListId = "901522598601";
$spentFieldId = "c7370501-ee91-4fde-9de6-522119c8e974";

$url = "https://api.clickup.com/api/v2/list/$poListId/task";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $apiToken"
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['tasks'])) {
    exit;
}

$totals = [];

foreach ($data['tasks'] as $task) {

    $price = 0;
    $projectId = null;

    foreach ($task['custom_fields'] as $field) {

        if ($field['name'] === "Price") {
            $price = $field['value'] ?? 0;
        }

        if ($field['name'] === "Project Link") {
            if (!empty($field['value'][0])) {

                if (is_array($field['value'][0])) {
                    $projectId = $field['value'][0]['id'];
                } else {
                    $projectId = $field['value'][0];
                }
            }
        }
    }

    if ($projectId) {
        if (!isset($totals[$projectId])) {
            $totals[$projectId] = 0;
        }

        $totals[$projectId] += $price;
    }
}

foreach ($totals as $projectId => $totalSpent) {

    $url = "https://api.clickup.com/api/v2/task/$projectId/field/$spentFieldId";

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "value" => $totalSpent
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $apiToken",
        "Content-Type: application/json"
    ]);

    curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
echo "OK";
