<?php
$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'secp384r1',
]);
var_dump($key);
if ($key === false) {
    echo "error: " . openssl_error_string() . PHP_EOL;
}
