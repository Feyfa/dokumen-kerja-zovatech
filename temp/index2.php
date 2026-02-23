<?php

// Paste raw response dari Insomnia/Postman langsung ke sini
$rawResponseResolveIdentities = 'event: message
data: {"result":{"content":[{"type":"text","text":"{\"identities\":[{\"person_id\":220323791,\"matches\":[{\"criterion_type\":\"email_md5\",\"criterion_value\":\"d7fbcb2bc68876df8f0cc41659e7d352\",\"quality_score\":0.9}],\"overall_quality_score\":0.9,\"identifiers\":{\"emails\":[{\"email_address\":\"katieschultz708@yahoo.com\",\"opted_in\":false},{\"email_address\":\"kitkatkatie1870@gmail.com\",\"opted_in\":false}]}}],\"stats\":{\"requested\":1,\"resolved\":1,\"rate\":1},\"workflow_id\":\"9f078bd3-b1fe-4d2c-9f02-ba707aea8031\",\"tool_trace_id\":\"99aac25fb40f52399b15d56d43139ad8\"}"}]},"jsonrpc":"2.0","id":1}';

$rawResponseGetPerson = 'event: message
data: {"result":{"content":[{"type":"text","text":"{\"profiles\":[{\"person_id\":\"220323791\",\"domains\":{\"phones\":[{\"phone_number\":\"+16512463339\",\"phone_type\":\"work\",\"carrier\":\"Verizon\",\"do_not_call\":true},{\"phone_number\":\"+16514501533\",\"phone_type\":\"landline\",\"carrier\":\"Qwest Communications\",\"do_not_call\":true},{\"phone_number\":\"+16516879388\",\"phone_type\":\"landline\",\"carrier\":\"Tci Telephony Services\",\"do_not_call\":true},{\"phone_number\":\"+16517343449\",\"phone_type\":\"cell\",\"carrier\":\"Aerial Communications Inc.\",\"do_not_call\":true},{\"phone_number\":\"+16517561716\",\"phone_type\":\"landline\",\"carrier\":\"Tci Telephony Services\",\"do_not_call\":true},{\"phone_number\":\"+16519999492\",\"phone_type\":\"work\",\"carrier\":\"T-mobile\",\"do_not_call\":true}],\"emails\":[{\"email_address\":\"katieschultz708@yahoo.com\",\"opted_in\":false},{\"email_address\":\"kitkatkatie1870@gmail.com\",\"opted_in\":false}],\"names\":[{\"first_name\":\"Kathleen\",\"last_name\":\"Schultz\"}],\"addresses\":[{\"address_primary\":\"1870 Eagle Ridge Dr\",\"address_secondary\":\"Apt 10\",\"city\":\"Saint Paul\",\"state\":\"MN\",\"zip\":\"55118-4254\",\"county\":\"Dakota\",\"carrier_route\":\"C010\",\"latitude\":44.887646,\"longitude\":-93.1361,\"dma\":613,\"cbsa\":33460,\"msa\":5120,\"congressional_district\":2,\"urbanicity_code\":\"U\",\"property_type\":null}]}}],\"export\":{\"url\":\"https://watt-mcp-exports-dev.s3.us-east-1.amazonaws.com/exports/1771675329563-222e0108-39ba-497a-980c-b03822597218.csv?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Credential=ASIAXAJLZ62SUEGSTK5C%2F20260221%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260221T120209Z&X-Amz-Expires=3600&X-Amz-Security-Token=IQoJb3JpZ2luX2VjEOL%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJHMEUCID5VjsjbWXR8g6DXp7d5Kkok6Ymng1rwVfiu%2FMIjAIv0AiEAhE70MZd3bVRBGtQL%2FX3JKtOaTgrjbhikg85pND5vNocqtAQIq%2F%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FARAAGgw0ODE2NjUwODcxNDEiDIJnvp11GFrSjY3lECqIBBMzb835wOMAb%2Fn97bPig%2F7r9XudzTr7%2Fx7HdkB7Iu7p1xFQuN27FEy0lLRlm9hOhYOiiolQCvaOVN8yvUHmB3BlIKU4QnSfjq6efhUFszSr1bNdakwq9i%2BmPpr0s2qduRWkr1uGw%2FqplYhFyzB1JR44gFuZTWaALp51I0d%2BfeM%2BqzrB%2F1h7cmztXwV0xq3OWfNKBAYPjgWjEdnY1z2UGQBhQm9fKFQkUvY1uzxFLlTw29j6MNPBVrR3v7%2BZnme8g0xpFSqOTKRt1kwpQ6sqgWtPygODFB6wHP3vYqhAkYtmnXYPk6ZLXrCjtN5T2yFaO5yY%2FRi71KM3H2VwNJhvAZ0lcqGV5%2BsUbfM4O1eqqZr%2BWeGs6lmcfvScGVDx86TFkojj%2BB6YKIQnx1iAL%2FE%2FTCaIss2LUBcS29AlQx5GNhRLNv1GSrrrL9B5MRyqmqNybnvVqv8y2g3034O20OxKrF%2FvQ%2BM6DpcHeQ6owRDGGebNoeiiDvT0IyJCRFN%2B8QyX33DtDgyIwO8Ii59hO%2FivYe0Nw0fubwAcSgUOMDm9DT7DQNCrUtxsMx%2FTEefGCe39M088qFetYyjlnWwAMy4sJJ1O6K9hOQhiXu1it3SDbFOzlul7lpHcCgwINj%2BHBQ3zvXWvYZKAzhji8INDWTBi1qgQVEclhUWGSZitDKLj9ixe5S6%2B3Vm3ongwyoPmzAY6pgFbnMeiScJdDLYHU7Of%2FFNxetTBHQceGV2ewGElPGqRXT5NgigHGuSORpu2UugYB97MBNgI6NcyhpY7rT21gCuhhaxqb7a79xZtiCOfZ7u%2B4sxXyQGT2ckQgAEjPT8swIm7c6tQF%2BI2mVbPvBgstaaf2ahxhZcWsXBbHMeypJnCG3IJGKl54gAY8eShYZmxyHNtB%2B4%2BJTs2uT17WGdk9xgJcZVwB%2FL6&X-Amz-Signature=443410036e1cfc92e636d9ff6d8f44c9b86903ba9b199e729d8061688a8d8913&X-Amz-SignedHeaders=host&x-amz-checksum-mode=ENABLED&x-id=GetObject\",\"format\":\"csv\",\"rows\":1,\"size_bytes\":0,\"expires_at\":\"2026-02-21T13:02:09.642Z\"},\"workflow_id\":\"bca24f7e-889e-4504-a1a6-9b51eab82737\",\"tool_trace_id\":\"a7d7a1282500e646bfc10c7a9587641e\"}"},{"type":"resource","resource":{"uri":"https://watt-mcp-exports-dev.s3.us-east-1.amazonaws.com/exports/1771675329563-222e0108-39ba-497a-980c-b03822597218.csv?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Credential=ASIAXAJLZ62SUEGSTK5C%2F20260221%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260221T120209Z&X-Amz-Expires=3600&X-Amz-Security-Token=IQoJb3JpZ2luX2VjEOL%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJHMEUCID5VjsjbWXR8g6DXp7d5Kkok6Ymng1rwVfiu%2FMIjAIv0AiEAhE70MZd3bVRBGtQL%2FX3JKtOaTgrjbhikg85pND5vNocqtAQIq%2F%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FARAAGgw0ODE2NjUwODcxNDEiDIJnvp11GFrSjY3lECqIBBMzb835wOMAb%2Fn97bPig%2F7r9XudzTr7%2Fx7HdkB7Iu7p1xFQuN27FEy0lLRlm9hOhYOiiolQCvaOVN8yvUHmB3BlIKU4QnSfjq6efhUFszSr1bNdakwq9i%2BmPpr0s2qduRWkr1uGw%2FqplYhFyzB1JR44gFuZTWaALp51I0d%2BfeM%2BqzrB%2F1h7cmztXwV0xq3OWfNKBAYPjgWjEdnY1z2UGQBhQm9fKFQkUvY1uzxFLlTw29j6MNPBVrR3v7%2BZnme8g0xpFSqOTKRt1kwpQ6sqgWtPygODFB6wHP3vYqhAkYtmnXYPk6ZLXrCjtN5T2yFaO5yY%2FRi71KM3H2VwNJhvAZ0lcqGV5%2BsUbfM4O1eqqZr%2BWeGs6lmcfvScGVDx86TFkojj%2BB6YKIQnx1iAL%2FE%2FTCaIss2LUBcS29AlQx5GNhRLNv1GSrrrL9B5MRyqmqNybnvVqv8y2g3034O20OxKrF%2FvQ%2BM6DpcHeQ6owRDGGebNoeiiDvT0IyJCRFN%2B8QyX33DtDgyIwO8Ii59hO%2FivYe0Nw0fubwAcSgUOMDm9DT7DQNCrUtxsMx%2FTEefGCe39M088qFetYyjlnWwAMy4sJJ1O6K9hOQhiXu1it3SDbFOzlul7lpHcCgwINj%2BHBQ3zvXWvYZKAzhji8INDWTBi1qgQVEclhUWGSZitDKLj9ixe5S6%2B3Vm3ongwyoPmzAY6pgFbnMeiScJdDLYHU7Of%2FFNxetTBHQceGV2ewGElPGqRXT5NgigHGuSORpu2UugYB97MBNgI6NcyhpY7rT21gCuhhaxqb7a79xZtiCOfZ7u%2B4sxXyQGT2ckQgAEjPT8swIm7c6tQF%2BI2mVbPvBgstaaf2ahxhZcWsXBbHMeypJnCG3IJGKl54gAY8eShYZmxyHNtB%2B4%2BJTs2uT17WGdk9xgJcZVwB%2FL6&X-Amz-Signature=443410036e1cfc92e636d9ff6d8f44c9b86903ba9b199e729d8061688a8d8913&X-Amz-SignedHeaders=host&x-amz-checksum-mode=ENABLED&x-id=GetObject","mimeType":"text/csv","text":"Export file: 1 rows in CSV format. Expires at 2/21/2026, 1:02:09 PM."}}]},"jsonrpc":"2.0","id":2}';

/**
 * Mem-parse raw SSE response dari Wattdata API dan mengembalikan
 * inner JSON (isi field "text") dalam bentuk pretty-print string.
 *
 * Struktur response SSE:
 *   event: message
 *   data: {"result":{"content":[{"type":"text","text":"{...escaped json...}"},...]},...}
 *
 * @param  string $rawResponse  Raw string response (boleh termasuk prefix "event: message\ndata: ")
 * @return string               Pretty-printed JSON atau pesan error
 */
function parseWattdataResponse(string $rawResponse): string
{
    // 1. Ambil bagian setelah "data: " jika ada prefix SSE
    if (strpos($rawResponse, 'data:') !== false) {
        $parts = explode('data:', $rawResponse, 2);
        $rawResponse = trim($parts[1]);
    }

    // 2. Decode outer JSON
    $outer = json_decode($rawResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'ERROR decode outer JSON: ' . json_last_error_msg();
    }

    // 3. Ambil inner JSON string dari result.content[0].text
    $innerJsonString = $outer['result']['content'][0]['text'] ?? null;
    if ($innerJsonString === null) {
        return 'ERROR: path result.content[0].text tidak ditemukan';
    }

    // 4. Decode inner JSON
    $inner = json_decode($innerJsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'ERROR decode inner JSON: ' . json_last_error_msg();
    }

    // 5. Kembalikan sebagai pretty-print JSON
    return json_encode($inner, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Simpan result ke file JSON
function saveToJson(string $jsonString, string $filePath): void
{
    file_put_contents($filePath, $jsonString);
    echo "Tersimpan ke: " . $filePath . PHP_EOL;
}


$resultResolveIdentities = parseWattdataResponse($rawResponseResolveIdentities);
$resultGetPerson = parseWattdataResponse($rawResponseGetPerson);

$jsonDir = __DIR__ . '/../Json/';
saveToJson($resultResolveIdentities, $jsonDir . 'resolve-identities.json');
saveToJson($resultGetPerson,         $jsonDir . 'get-person.json');

