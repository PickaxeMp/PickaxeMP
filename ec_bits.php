<?php
$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'secp384r1',
    'private_key_bits' => 384,
]);
var_dump($key);
