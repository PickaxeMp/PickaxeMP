<?php

declare(strict_types=1);

namespace PickaxeMP\Tests\unit\auth;

use PHPUnit\Framework\TestCase;
use PickaxeMP\auth\AuthException;
use PickaxeMP\auth\jwt\EcdsaSignature;
use PickaxeMP\auth\jwt\JwtAlgorithm;
final class EcdsaSignatureTest extends TestCase
{
    private JwtAlgorithm $algorithm;

    protected function setUp(): void
    {
        $this->algorithm = JwtAlgorithm::ES384;
    }

    public function testRawSignatureLengthIs96ForEs384(): void
    {
        self::assertSame(96, $this->algorithm->rawSignatureLength());
        self::assertSame(48, $this->algorithm->ecdsaCoordinateBytes());
    }

    public function testRejectsWrongRawLength(): void
    {
        $this->expectException(AuthException::class);
        EcdsaSignature::rawToDer(str_repeat("\x01", 64), $this->algorithm);
    }

    public function testRejectsZeroSignature(): void
    {
        self::assertFalse(EcdsaSignature::isValidRaw(str_repeat("\x00", 96), $this->algorithm));
    }

    public function testAcceptsNonZeroSignature(): void
    {
        self::assertTrue(
            EcdsaSignature::isValidRaw(str_repeat("\x01", 48) . str_repeat("\x02", 48), $this->algorithm)
        );
    }

    public function testDerRoundTrip(): void
    {
        // Yüksek bit set olan R ile DER'in 0x00 eklemesini sına.
        $raw = str_repeat("\x80", 48) . str_repeat("\x7F", 48);

        $der = EcdsaSignature::rawToDer($raw, $this->algorithm);
        $back = EcdsaSignature::derToRaw($der, $this->algorithm);

        self::assertSame($raw, $back);
    }

    public function testDerRoundTripWithLeadingZeros(): void
    {
        $raw = "\x00\x00\x01" . str_repeat("\x00", 45) . str_repeat("\x00", 46) . "\x00\x02";

        $der = EcdsaSignature::rawToDer($raw, $this->algorithm);
        $back = EcdsaSignature::derToRaw($der, $this->algorithm);

        self::assertSame($raw, $back);
    }

    public function testEcdsaRoundTripBothDirectionsForAllCoordinates(): void
    {
        // Her bayt değerini kapsayan deterministik örnekler.
        for ($i = 0; $i < 10; $i++) {
            $raw = '';

            for ($b = 0; $b < 96; $b++) {
                $raw .= chr(($i * 37 + $b * 11) % 256);
            }

            // R ve S sıfır olmamalı.
            if (!EcdsaSignature::isValidRaw($raw, $this->algorithm)) {
                continue;
            }

            $der = EcdsaSignature::rawToDer($raw, $this->algorithm);

            self::assertSame($raw, EcdsaSignature::derToRaw($der, $this->algorithm));
        }
    }

    public function testRejectsBrokenDer(): void
    {
        $this->expectException(AuthException::class);
        EcdsaSignature::derToRaw("\x31\x00", $this->algorithm);
    }
}
