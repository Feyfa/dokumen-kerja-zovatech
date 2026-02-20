<?php

// Paste raw response dari Insomnia/Postman langsung ke sini
$rawResponseResolveIdentities = 'event: message
data: {"result":{"content":[{"type":"text","text":"{\"identities\":[{\"person_id\":220323791,\"matches\":[{\"criterion_type\":\"email_md5\",\"criterion_value\":\"d7fbcb2bc68876df8f0cc41659e7d352\",\"quality_score\":0.9}],\"overall_quality_score\":0.9,\"identifiers\":{\"emails\":[{\"email_address\":\"katieschultz708@yahoo.com\",\"opted_in\":false},{\"email_address\":\"kitkatkatie1870@gmail.com\",\"opted_in\":false}]}}],\"stats\":{\"requested\":1,\"resolved\":1,\"rate\":1},\"workflow_id\":\"9f078bd3-b1fe-4d2c-9f02-ba707aea8031\",\"tool_trace_id\":\"99aac25fb40f52399b15d56d43139ad8\"}"}]},"jsonrpc":"2.0","id":1}';

$rawResponseGetPerson = 'event: message
data: {"result":{"content":[{"type":"text","text":"{\"profiles\":[{\"person_id\":\"220323791\",\"domains\":{\"phones\":[{\"phone_number\":\"+16512463339\",\"phone_type\":\"work\",\"carrier\":\"Verizon\",\"do_not_call\":true},{\"phone_number\":\"+16514501533\",\"phone_type\":\"landline\",\"carrier\":\"Qwest Communications\",\"do_not_call\":true},{\"phone_number\":\"+16516879388\",\"phone_type\":\"landline\",\"carrier\":\"Tci Telephony Services\",\"do_not_call\":true},{\"phone_number\":\"+16517343449\",\"phone_type\":\"cell\",\"carrier\":\"Aerial Communications Inc.\",\"do_not_call\":true},{\"phone_number\":\"+16517561716\",\"phone_type\":\"landline\",\"carrier\":\"Tci Telephony Services\",\"do_not_call\":true},{\"phone_number\":\"+16519999492\",\"phone_type\":\"work\",\"carrier\":\"T-mobile\",\"do_not_call\":true}],\"emails\":[{\"email_address\":\"katieschultz708@yahoo.com\",\"opted_in\":false},{\"email_address\":\"kitkatkatie1870@gmail.com\",\"opted_in\":false}],\"names\":[{\"first_name\":\"Kathleen\",\"last_name\":\"Schultz\"}],\"addresses\":[{\"address_primary\":\"1870 Eagle Ridge Dr\",\"address_secondary\":\"Apt 10\",\"city\":\"Saint Paul\",\"state\":\"MN\",\"zip\":\"55118-4254\",\"county\":\"Dakota\",\"carrier_route\":\"C010\",\"latitude\":44.887646,\"longitude\":-93.1361,\"dma\":613,\"cbsa\":33460,\"msa\":5120,\"congressional_district\":2,\"urbanicity_code\":\"U\",\"property_type\":null}]}}],\"export\":{\"url\":\"https://watt-mcp-exports-dev.s3.us-east-1.amazonaws.com/exports/1771581959514-b3d9b33e-4330-46ac-acc0-e55fd5fcc7d1.csv?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Credential=ASIAXAJLZ62S6E3VNZLM%2F20260220%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260220T100559Z&X-Amz-Expires=3600&X-Amz-Security-Token=IQoJb3JpZ2luX2VjEMj%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJIMEYCIQDENv9dc0UUigzA1%2FZc034aGS8Ek1sZ%2FvQGTKK6xr%2Ff%2BgIhAM0Hnaq5W%2BdxEDDDg%2BsgQeQ5Et8ESENKZSr%2FX7Zxz8ztKrQECJD%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEQABoMNDgxNjY1MDg3MTQxIgyuIJUmc1v0U4C5RlkqiAS7fcpwfTefLj7Q%2BPMozp%2BUGY3dTBuZ4%2B1T4QMfxepuZ%2BlxbmNSYG1WUaijxPlMBZ3AePlolu3VNu3S1qi8PPY5952E06trtUS%2BURlt%2FK3scuTbCX6H22%2F6j5VCbpb9HqYee5ehGFwegjpCkEw8oq5Fr9JuBr%2BvehjdtFu6lYH85vcBB1wOMtnZt6Gtba9reZVqFQAHzNPBeHNnYiBzebFFu6Nh9hscG1Z2gZP%2BGPs0JEESuvvY5sMadMsFRQ%2FiWLQ6FSWcszAN0%2BYjMJEpOu23NRpSakVdx8%2FdIKIYSF8xwDDfKZTSNfMnv5bjT5FrJqeyeywzabW8buhCtoVLeRQuRKWPyjiouKfK9s3SY7BNIq6d4JqScvE5KgvaYDxnRJQMDtSyx664NXfa%2BGwSdWAqMtF53Nzy2VNxlviAgXT77s5C1eoslk20XH5W0q4Jn%2F1f1Sr7j%2BYvZH0e7yuxtrkq8TY%2BWOYgLkQ%2BuFdZRavcGsW2catn4m3p2SSIeu%2BsgbuFX2Cf6BandszwMxUUCWwPiwQ4RU736laDvWwh3ZdMSqu4Mni%2FMlU7bSaGq3psOHgSVvxjUWVQdDuLnOwG3cZXdTu56jrbhXoPMFWskYiGqRGkH9RDHRrpIM1sEUQqXNFBuZBcVaepiIpImxW9a%2FBI2df%2FiECOKmbhDPYP3UTGL7tkYTzFXAfLMIqQ4MwGOqUBrmsqQa8Ep08SemVCm7G9%2FRjOptWJrK%2BoXcQKyQlVRTACvZKmfEMI7EChfhP4yReyxErvZ39dd4RAKlthc9ElimaDiS4NXC%2FArmzr39G2vQzoSWLhQ%2BQ5zbX8GuZuoLzi5Yu4%2FvIN45Sh2r%2B31tem8M%2BJ3WJGwUf5w%2B7pBSYdU6QHLgYRjuEcdOU7mtNDI5yWWc2nx5lh59sLkQd0MEiJJWzH1IQ%2F&X-Amz-Signature=08d2419ce32ee2acb45090a5195021b9c814deff23d35c56cd5f3081734fcfac&X-Amz-SignedHeaders=host&x-amz-checksum-mode=ENABLED&x-id=GetObject\",\"format\":\"csv\",\"rows\":1,\"size_bytes\":0,\"expires_at\":\"2026-02-20T11:05:59.589Z\"},\"workflow_id\":\"63af2bbb-eb61-4358-be36-331b2282ab3b\",\"tool_trace_id\":\"f2ff455fa586599be8d62d7f7d231e60\"}"},{"type":"resource","resource":{"uri":"https://watt-mcp-exports-dev.s3.us-east-1.amazonaws.com/exports/1771581959514-b3d9b33e-4330-46ac-acc0-e55fd5fcc7d1.csv?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Credential=ASIAXAJLZ62S6E3VNZLM%2F20260220%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260220T100559Z&X-Amz-Expires=3600&X-Amz-Security-Token=IQoJb3JpZ2luX2VjEMj%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEaCXVzLWVhc3QtMSJIMEYCIQDENv9dc0UUigzA1%2FZc034aGS8Ek1sZ%2FvQGTKK6xr%2Ff%2BgIhAM0Hnaq5W%2BdxEDDDg%2BsgQeQ5Et8ESENKZSr%2FX7Zxz8ztKrQECJD%2F%2F%2F%2F%2F%2F%2F%2F%2F%2FwEQABoMNDgxNjY1MDg3MTQxIgyuIJUmc1v0U4C5RlkqiAS7fcpwfTefLj7Q%2BPMozp%2BUGY3dTBuZ4%2B1T4QMfxepuZ%2BlxbmNSYG1WUaijxPlMBZ3AePlolu3VNu3S1qi8PPY5952E06trtUS%2BURlt%2FK3scuTbCX6H22%2F6j5VCbpb9HqYee5ehGFwegjpCkEw8oq5Fr9JuBr%2BvehjdtFu6lYH85vcBB1wOMtnZt6Gtba9reZVqFQAHzNPBeHNnYiBzebFFu6Nh9hscG1Z2gZP%2BGPs0JEESuvvY5sMadMsFRQ%2FiWLQ6FSWcszAN0%2BYjMJEpOu23NRpSakVdx8%2FdIKIYSF8xwDDfKZTSNfMnv5bjT5FrJqeyeywzabW8buhCtoVLeRQuRKWPyjiouKfK9s3SY7BNIq6d4JqScvE5KgvaYDxnRJQMDtSyx664NXfa%2BGwSdWAqMtF53Nzy2VNxlviAgXT77s5C1eoslk20XH5W0q4Jn%2F1f1Sr7j%2BYvZH0e7yuxtrkq8TY%2BWOYgLkQ%2BuFdZRavcGsW2catn4m3p2SSIeu%2BsgbuFX2Cf6BandszwMxUUCWwPiwQ4RU736laDvWwh3ZdMSqu4Mni%2FMlU7bSaGq3psOHgSVvxjUWVQdDuLnOwG3cZXdTu56jrbhXoPMFWskYiGqRGkH9RDHRrpIM1sEUQqXNFBuZBcVaepiIpImxW9a%2FBI2df%2FiECOKmbhDPYP3UTGL7tkYTzFXAfLMIqQ4MwGOqUBrmsqQa8Ep08SemVCm7G9%2FRjOptWJrK%2BoXcQKyQlVRTACvZKmfEMI7EChfhP4yReyxErvZ39dd4RAKlthc9ElimaDiS4NXC%2FArmzr39G2vQzoSWLhQ%2BQ5zbX8GuZuoLzi5Yu4%2FvIN45Sh2r%2B31tem8M%2BJ3WJGwUf5w%2B7pBSYdU6QHLgYRjuEcdOU7mtNDI5yWWc2nx5lh59sLkQd0MEiJJWzH1IQ%2F&X-Amz-Signature=08d2419ce32ee2acb45090a5195021b9c814deff23d35c56cd5f3081734fcfac&X-Amz-SignedHeaders=host&x-amz-checksum-mode=ENABLED&x-id=GetObject","mimeType":"text/csv","text":"Export file: 1 rows in CSV format. Expires at 2/20/2026, 11:05:59 AM."}}]},"jsonrpc":"2.0","id":2}

';

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

