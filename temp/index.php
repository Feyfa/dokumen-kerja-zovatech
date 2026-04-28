<?php

$payload = [
    'campaignCode' => '74312201',  // ganti dengan campaign_code yang ada di DB
    'pageUrl'=> 'https://jakarta.com',
];
$label = base64_encode(json_encode($payload));
echo "label: " . $label . PHP_EOL;
echo "md5_email: " . md5('jude@nettheory.com') . PHP_EOL;