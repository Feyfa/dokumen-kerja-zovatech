<?php

$filedownload_url = "https://emmbetaspaces.nyc3.cdn.digitaloceanspaces.com/suppressionlist/1762510257475_leadspeek_1065.csv";

$stream = fopen($filedownload_url, 'r');
if (!$stream) {
    die("Gagal membuka file stream dari DigitalOcean Spaces");
}
var_dump($stream);

function readLines($stream, $chunkSize)
{
    $chunk = [];

    while (($line = fgets($stream)) !== false) {
        $line = trim($line);
        if ($line === '') continue;

        $chunk[] = $line;

        if (count($chunk) >= $chunkSize) {
            yield $chunk;
            $chunk = [];
        }
    }

    if (!empty($chunk)) yield $chunk;
}

foreach (readLines($stream, 10) as $chunk) {
    var_dump($chunk);
}

fclose($stream);

