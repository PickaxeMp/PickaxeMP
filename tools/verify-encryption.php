<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\auth\crypto\EcPublicKey;

$configPath = __DIR__ . '/openssl.cnf';

echo "=== 1) Anahtar ciftleri ===\n";
$server = EcdhKeyExchange::generate($configPath);
$clientOptions = [
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'secp384r1',
    'private_key_bits' => 384,
    'config' => $configPath,
];
$clientPrivate = openssl_pkey_new($clientOptions);
$clientDetails = openssl_pkey_get_details($clientPrivate);
$clientPem = $clientDetails['key'];
$clientPublic = openssl_pkey_get_public($clientPem);

echo 'sunucu DER: ', strlen($server->publicKeyDer), " bayt (beklenen 120)\n";
echo 'istemci DER: ', strlen(EcdhKeyExchange::pemToDer($clientPem)), " bayt\n";

echo "\n=== 2) ECDH ortak sir ===\n";
$serverSecret = $server->computeSharedSecret($clientPublic);
$serverPublicKey = openssl_pkey_get_public($server->publicKeyPem);
$clientSecret = EcdhKeyExchange::deriveForTest($clientPrivate, $serverPublicKey);

echo 'sunucu sir: ', strlen($serverSecret), " bayt\n";
echo 'istemci sir: ', strlen($clientSecret), " bayt\n";
echo 'ESIT MI: ', hash_equals($serverSecret, $clientSecret) ? 'EVET' : 'HAYIR (KRITIK)', "\n";

echo "\n=== 3) Anahtar turetme ===\n";
$salt = EcdhKeyExchange::generateSalt();
echo 'salt: ', strlen($salt), " bayt\n";

$key = EcdhKeyExchange::deriveEncryptionKey($salt, $serverSecret);
echo 'anahtar: ', strlen($key), " bayt (beklenen 32)\n";
echo 'ilk 12 bayt (IV): ', bin2hex(substr($key, 0, 12)), "\n";

echo "\n=== 4) AES-CTR cok paketli round-trip ===\n";
$serverCipher = new AesCtrCipher($key);
$clientCipher = new AesCtrCipher($key);

$packets = [
    'kisa',
    str_repeat('A', 100),
    str_repeat('B', 1000),
    random_bytes(37),
    random_bytes(5000),
    '',
    str_repeat('C', 17),
];

$allOk = true;

foreach ($packets as $i => $plain) {
    $encrypted = $serverCipher->encrypt($plain);

    try {
        $decrypted = $clientCipher->decrypt($encrypted);
        $ok = hash_equals($plain, $decrypted);
    } catch (Throwable $e) {
        $ok = false;
        $decrypted = 'HATA: ' . $e->getMessage();
    }

    printf(
        "  paket %d: %4d bayt -> %4d sifreli, %s\n",
        $i,
        strlen($plain),
        strlen($encrypted),
        $ok ? 'OK' : 'BASARISIZ'
    );

    if (!$ok) {
        $allOk = false;
    }
}

echo $allOk ? "\nTUM PAKETLER OK\n" : "\nHATA VAR\n";

echo "\n=== 5) Ters yon (istemci -> sunucu) ===\n";
$reverseOk = true;

foreach (['bir', str_repeat('D', 300), random_bytes(64)] as $i => $plain) {
    $encrypted = $clientCipher->encrypt($plain);
    $decrypted = $serverCipher->decrypt($encrypted);

    $ok = hash_equals($plain, $decrypted);
    printf("  ters %d: %s\n", $i, $ok ? 'OK' : 'BASARISIZ');
    $reverseOk = $reverseOk && $ok;
}

echo $reverseOk ? "TERS YON OK\n" : "TERS YON HATA\n";

echo "\n=== 6) Bozulmus veri reddedilmeli ===\n";
$fresh = new AesCtrCipher($key);
$freshReceiver = new AesCtrCipher($key);
$enc = $fresh->encrypt('gizli veri');
$enc[2] = chr((ord($enc[2]) + 1) % 256);

try {
    $freshReceiver->decrypt($enc);
    echo "BOZUK VERI KABUL EDILDI (KRITIK HATA)\n";
} catch (Throwable $e) {
    echo "bozuk veri reddedildi: EVET\n";
}

echo "\n=== 7) Yanlis anahtar reddetmeli ===\n";
$wrongKey = random_bytes(32);
$wrongCipher = new AesCtrCipher($wrongKey);
$enc2 = (new AesCtrCipher($key))->encrypt('test');

try {
    $wrongCipher->decrypt($enc2);
    echo "YANLIS ANAHTAR KABUL EDILDI (KRITIK HATA)\n";
} catch (Throwable $e) {
    echo "yanlis anahtar reddedildi: EVET\n";
}

echo "\n=== 8) JWK -> DER donusumu ===\n";
$details = openssl_pkey_get_details($clientPrivate);
$jwk = [
    'kty' => 'EC',
    'crv' => 'P-384',
    'x' => rtrim(strtr(base64_encode(substr($details['ec']['x'], 0, 48)), '+/', '-_'), '='),
    'y' => rtrim(strtr(base64_encode(substr($details['ec']['y'], 0, 48)), '+/', '-_'), '='),
];

try {
    $fromJwk = EcPublicKey::fromJwk($jwk);
    $fromDer = EcPublicKey::fromDer(EcdhKeyExchange::pemToDer($clientPem));

    // Ayni anahtar mi? Ayni sirri uretmeli.
    $secretA = $server->computeSharedSecret($fromJwk);
    $secretB = $server->computeSharedSecret($fromDer);

    echo 'JWK ile ayni sir: ', hash_equals($secretA, $secretB) ? 'EVET' : 'HAYIR', "\n";
} catch (Throwable $e) {
    echo 'JWK HATASI: ', $e->getMessage(), "\n";
}

echo "\n=== 9) Gercek anahtar boyutlari ===\n";
echo 'sunucu PEM basligi: ', substr($server->publicKeyPem, 0, 27), "...\n";
