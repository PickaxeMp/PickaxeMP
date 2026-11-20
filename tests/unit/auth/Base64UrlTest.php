<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\jwt\Base64Url;
use PickaxeMP\auth\jwt\EcdsaSignature;
use PickaxeMP\auth\jwt\JwtAlgorithm;

final class Base64UrlTest extends TestCase
{
    public function testEncodesWithoutPadding(): void
    {
        self::assertSame('aGVsbG8', Base64Url::encode('hello'));
    }

    public function testUsesUrlSafeAlphabet(): void
    {
        // 0xFB 0xFF → standart base64'te "+/8" olur; URL-safe'te "-_8".
        $encoded = Base64Url::encode("\xFB\xFF");

        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
        self::assertStringNotContainsString('=', $encoded);
    }

    public function testRoundTrip(): void
    {
        foreach (['a', 'ab', 'abc', 'abcd', 'merhaba dünya', "\x00\x01\xFF"] as $data) {
            self::assertSame($data, Base64Url::decode(Base64Url::encode($data)), bin2hex($data));
        }
    }

    public function testEmptyEncodesToEmpty(): void
    {
        // Boş veri boş metne kodlanır; ancak boş metin decode'da reddedilir
        // (JWT parçası boş olamaz).
        self::assertSame('', Base64Url::encode(''));
    }

    public function testEmptyInputRejected(): void
    {
        $this->expectException(AuthException::class);
        Base64Url::decode('');
    }

    public function testPaddingRejected(): void
    {
        $this->expectException(AuthException::class);
        Base64Url::decode('aGVsbG8=');
    }

    public function testInvalidCharacterRejected(): void
    {
        $this->expectException(AuthException::class);
        Base64Url::decode('abc+d');
    }

    public function testInvalidLengthRejected(): void
    {
        $this->expectException(AuthException::class);
        Base64Url::decode('abcde');
    }

    public function testOversizedInputRejected(): void
    {
        $this->expectException(AuthException::class);
        Base64Url::decode(str_repeat('a', Base64Url::MAX_ENCODED_LENGTH + 1));
    }

    public function testTryDecodeReturnsNullOnFailure(): void
    {
        self::assertNull(Base64Url::tryDecode('!!!'));
        self::assertSame('hello', Base64Url::tryDecode('aGVsbG8'));
    }
}
