<?php

function parse_us_address(?string $address)
{
    $result = [
        'fullAddress' => $address,
        'street' => null,
        'city' => null,
        'state' => null,
        'zip' => null,
    ];

    if (empty($address)) {
        return $result;
    }

    // Harus ada minimal 2 koma → street, city, state+zip
    if (substr_count($address, ',') < 2) {
        return $result;
    }

    $parts = array_map('trim', explode(',', $address));
    $stateZip = array_pop($parts);
    $city = array_pop($parts);
    $street = implode(', ', $parts);

    if (preg_match('/^([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/', $stateZip, $m)) {
        $result['street'] = $street;
        $result['city'] = $city;
        $result['state'] = $m[1];
        $result['zip'] = $m[2];
    }

    return $result;
}

$data = parse_us_address("450 E Walnut St, Washburn, IL 61570-9406");
var_dump($data);