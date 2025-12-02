<?php

/**
 * Convert any label format into legacy $data array
 *
 * Result format:
 * [
 *   0 => leadspeek_api_id,
 *   1 => keyword,
 *   2 => pixelLeadRecordID (optional),
 *   3 => customParams (optional),
 * ]
 */
function buildLegacyDataArray(string $label): array
{
    $data = [
        0 => null, // leadspeek_api_id
        1 => null, // keyword
        2 => null, // pixelLeadRecordID
        3 => null, // customParams
    ];

    if (trim($label) === '') {
        return $data;
    }

    /**
     * STEP 1
     * Pisahkan keyword → selalu ambil PIPE TERAKHIR
     */
    $pipePos = strrpos($label, '|');
    if ($pipePos === false) {
        return $data;
    }

    $head    = substr($label, 0, $pipePos);
    $keyword = substr($label, $pipePos + 1);

    /**
     * STEP 2
     * Parse bagian depan (ID, pixel, custom)
     */
    // Jika pakai format baru (local): ada "-"
    if (strpos($head, '-') !== false) {
        // Ambil maksimal 3 part: id - pixel - custom
        $parts = explode('-', $head, 3);

        $data[0] = trim($parts[0] ?? null); // leadspeek_api_id
        $data[2] = trim($parts[1] ?? null); // pixelLeadRecordID
        $data[3] = trim($parts[2] ?? null); // customParams
    } else {
        // Format lama (enhance / b2b)
        $data[0] = trim($head);
    }

    /**
     * STEP 3
     * Set keyword
     */
    $data[1] = trim($keyword);

    $data = array_filter($data, fn($v) => $v !== null && $v !== '');

    return $data;
}


// $label = "81151983-12345-test123|https://test.com";
// $label = "81151983-12345|https://test.com";

$data = buildLegacyDataArray($label);

var_dump($data);'

'