<?php

$payload = [
    'campaignCode' => '84632251',  // ganti dengan campaign_code yang ada di DB
    'pageUrl'=> 'https://chatgpt.com',
];
$label = base64_encode(json_encode($payload));
echo "label: " . $label . PHP_EOL;
echo "md5_email: " . md5('alphaomegsa@gmail.com') . PHP_EOL;