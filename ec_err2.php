<?php
$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
]);
var_dump($key);
echo "\n";
while ($e = openssl_error_string()) { echo $e."\n"; }
