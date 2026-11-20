<?php

declare(strict_types=1);

namespace PickaxeMP\auth\crypto;

use PickaxeMP\auth\AuthException;

/**
 * Bedrock oturum şifrelemesi (AES-256-CTR + sahte-GCM + checksum).
 *
 * ## Neden "sahte GCM"?
 *
 * Bedrock, 1.16.220.50 (protocol 429) sürümünden itibaren **AES-GCM**
 * kullanır — ancak **auth tag olmadan**. Bu, GCM'in güvenlik amacını
 * ortadan kaldırır; GCM zaten CTR + tag olduğu için **düz CTR** ile aynı
 * sonucu verir. Tek fark IV hazırlığıdır: GCM yalnızca ilk **12 baytı**
 * kullanır ve sonuna `00 00 00 02` ekler.
 *
 * Bu uygulama aynı baytları üretir (PocketMine'ın `fakeGCM` yöntemiyle
 * aynı yaklaşım; kod bağımsız yazılmıştır).
 *
 * ## Tel biçimi
 *
 * Her paket:
 *
 * ```
 * ciphertext = AES256CTR( payload || checksum )
 * checksum   = ilk 8 bayt( SHA256( counter_le_u64 || payload || key ) )
 * ```
 *
 * `payload` = şifrelenmemiş Bedrock baytları (paket kimliği dahil).
 *
 * ## Süreklilik (kritik)
 *
 * CTR bir **akış** şifresidir: sayaç her 16 baytlık blokta artar ve
 * paketler arasında **sıfırlanmaz**. PHP'nin `openssl_encrypt` her çağrıda
 * sayacı IV'ten başlatır. Bu yüzden sınıf, tüketilen bayt miktarını izler ve
 * her paket için IV'ü gerekli blok sayısı kadar ilerletir. Aksi halde ikinci
 * paketten itibaren şifre çözme bozulur — bu, testlerle kanıtlanmıştır.
 */
final class AesCtrCipher
{
    public const CIPHER = 'aes-256-ctr';
    public const CHECKSUM_LENGTH = 8;
    public const BLOCK_SIZE = 16;

    private string $key;

    private int $encryptOffset = 0;

    private int $decryptOffset = 0;

    private int $encryptCounter = 0;

    private int $decryptCounter = 0;

    public function __construct(string $encryptionKey)
    {
        if (strlen($encryptionKey) !== 32) {
            throw AuthException::invalidKey(
                'AES-256 anahtari 32 bayt olmali, ' . strlen($encryptionKey) . ' alindi'
            );
        }

        $this->key = $encryptionKey;
    }

    /**
     * Sahte-GCM IV'i: ilk 12 bayt + `00 00 00 02`.
     */
    public static function fakeGcmIv(string $key): string
    {
        return substr($key, 0, 12) . "\x00\x00\x00\x02";
    }

    /**
     * Gönderilecek paketi şifreler (checksum eklenmiş).
     */
    public function encrypt(string $payload): string
    {
        $checksum = $this->checksum($this->encryptCounter++, $payload);

        return $this->applyKeystream($payload . $checksum, $this->encryptOffset);
    }

    /**
     * Gelen paketi çözer ve checksum'ı doğrular.
     *
     * @throws AuthException checksum uyuşmazsa veya veri çok kısaysa
     */
    public function decrypt(string $encrypted): string
    {
        // Boş yük geçerli olabilir: o zaman yalnızca checksum taşınır (8 bayt).
        if (strlen($encrypted) < self::CHECKSUM_LENGTH) {
            throw new AuthException('Sifreli paket cok kisa: ' . strlen($encrypted) . ' bayt');
        }

        $decrypted = $this->applyKeystream($encrypted, $this->decryptOffset);

        $payload = substr($decrypted, 0, -self::CHECKSUM_LENGTH);
        $actual = substr($decrypted, -self::CHECKSUM_LENGTH);

        $expected = $this->checksum($this->decryptCounter, $payload);

        if (!hash_equals($expected, $actual)) {
            throw new AuthException(
                'Sifreli paket checksum uyusmuyor (paket #' . $this->decryptCounter . ')'
            );
        }

        $this->decryptCounter++;

        return $payload;
    }

    /**
     * Paket sayacı (teşhis).
     */
    public function encryptCounter(): int
    {
        return $this->encryptCounter;
    }

    public function decryptCounter(): int
    {
        return $this->decryptCounter;
    }

    /**
     * Checksum: `SHA256(counter_le_u64 || payload || key)[0..8]`.
     */
    private function checksum(int $counter, string $payload): string
    {
        return substr(
            hash('sha256', pack('P', $counter) . $payload . $this->key, true),
            0,
            self::CHECKSUM_LENGTH
        );
    }

    /**
     * Akış şifresini verilen konumdan uygular.
     *
     * `openssl_encrypt` sayacı her çağrıda IV'ten başlattığı için, hedef
     * konuma hizalanmak üzere başına sıfır dolgu eklenir ve sonuç kırpılır.
     * Sıfırlar şifrelenince **keystream** elde edilir; veri kısmı doğru
     * biçimde XOR'lanır.
     */
    private function applyKeystream(string $data, int &$offset): string
    {
        $iv = self::fakeGcmIv($this->key);

        $blockIndex = intdiv($offset, self::BLOCK_SIZE);
        $inBlock = $offset % self::BLOCK_SIZE;

        if ($blockIndex > 0) {
            $iv = self::advanceIv($iv, $blockIndex);
        }

        $padded = str_repeat("\x00", $inBlock) . $data;

        $result = openssl_encrypt(
            $padded,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($result === false) {
            throw AuthException::opensslFailure('AES-CTR');
        }

        $offset += strlen($data);

        return substr($result, $inBlock);
    }

    /**
     * 128-bit big-endian sayacı `$blocks` kadar ilerletir.
     */
    private static function advanceIv(string $iv, int $blocks): string
    {
        /** @var array<int, int> $bytes */
        $bytes = array_values(unpack('C*', $iv));

        $carry = $blocks;

        for ($i = 15; $i >= 0 && $carry > 0; $i--) {
            $sum = $bytes[$i] + ($carry & 0xFF);
            $bytes[$i] = $sum & 0xFF;
            $carry = ($carry >> 8) + ($sum >> 8);
        }

        return pack('C*', ...$bytes);
    }
}