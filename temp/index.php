<?php

$payload = [
    'campaignCode' => '52369079',  // ganti dengan campaign_code yang ada di DB
    'pageUrl'=> 'https://example.com/pricing',
];
$label = base64_encode(json_encode($payload));
echo "label: " . $label . PHP_EOL;
echo "md5_email: " . md5('alphaomegsa@gmail.com') . PHP_EOL;