<?php

$payload = [
    'campaignCode' => '32886894',  // ganti dengan campaign_code yang ada di DB
    'pageUrl'=> 'https://jakarta.com',
];
$label = base64_encode(json_encode($payload));
echo "label: " . $label . PHP_EOL;

$emails = [
    'ksanfordhorses@gmail.com',
    'cdlemire@sbcglobal.net',
    '75zgert@gmail.com',
    'doakley@onebox.com',
    'jodijas30@aol.com',
    'jtabbish@yahoo.com',
    'bigdix24@gmail.com',
    'j44@access.com'
];

foreach ($emails as $email) {
    echo $email . " = " . md5($email) . PHP_EOL;
}
