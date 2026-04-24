<?php

$payload = [
    'campaignCode' => '84253512',  // ganti dengan campaign_code yang ada di DB
    'pageUrl'=> 'https://jidan.com',
];
$label = base64_encode(json_encode($payload));
echo "label: " . $label . PHP_EOL;
echo "md5_email: " . md5('alphaomegsa@gmail.com') . PHP_EOL;