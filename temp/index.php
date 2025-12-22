<?php

function get_resolve_identities(array $data, $file_id = null)
{
    // $startTime = microtime(true);
    // Simpan input data untuk error handling
    $inputData = $data;
    $id_type_features = ['email', 'phone', 'address', 'maid'];
    $hash_type_features = ['md5', 'sha1', 'sha256', 'plaintext'];
    
    /* RAKIT MULTI IDENTIFIERS */
    /*
        $data = [
            ['id_type' => 'email', 'hash_type' => 'md5', 'value' => '1231xxxxx'],
            ['id_type' => 'phone', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
            ['id_type' => 'address', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
            ['id_type' => 'maid', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
        ]
    */
    $multi_identifiers = [];
    foreach($data as $item){
        // Validasi bahwa item adalah array dan memiliki key yang diperlukan
        if(
            is_array($item) && 
            isset($item['id_type']) && 
            isset($item['hash_type']) && 
            isset($item['value']) &&
            in_array($item['id_type'], $id_type_features) &&
            in_array($item['hash_type'], $hash_type_features)
        ){
            $multi_identifiers[] = [
                'id_type' => $item['id_type'],
                'hash_type' => $item['hash_type'],
                'value' => $item['value'],
            ];
        }
    }

    // info(['multi_identifiers' => $multi_identifiers]);
    /* RAKIT MULTI IDENTIFIERS */

    // Buat struktur JSON sesuai dengan format JSON-RPC dari Postman
    $requestBody = [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => [
            'name' => 'resolve_identities',
            'arguments' => [
                'multi_identifiers' => $multi_identifiers
            ]
        ],
        'id' => 1
    ];

    var_dump(json_encode($requestBody));
}


get_resolve_identities([
    ['id_type' => 'email', 'hash_type' => 'md5', 'value' => '1231xxxxx'],
    ['id_type' => 'phone', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
    ['id_type' => 'address', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
    ['id_type' => 'maid', 'hash_type' => 'plaintext', 'value' => '1231xxxxx'],
]);