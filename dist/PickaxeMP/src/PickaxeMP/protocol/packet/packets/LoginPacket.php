<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\packets;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\packet\PacketId;
use PickaxeMP\protocol\packet\PacketInterface;

/**
 * `LoginPacket` (id 1, C→S).
 *
 * Şema (Mojang v1.26.65, 2225):
 *   Client Network Version — int32, **Big Endian**
 *   Connection Request     — string (varuint32 uzunluk + ham bayt)
 *
 * `Connection Request` bir JWT zinciridir; içeriği **bu sınıfta çözülmez**.
 * Doğrulama Phase 3 Authentication Engine'in sorumluluğundadır.
 *
 * DÜRÜST SINIR: Bu sınıf yalnızca paket biçimini taşır. Doğrulanmamış bir
 * Login paketi asla "kimliği doğrulanmış oyuncu" sayılmamalıdır.
 */
final class LoginPacket implements PacketInterface
{
    public function __construct(
        public readonly int $clientNetworkVersion,
        public readonly string $connectionRequest
    ) {
    }

    public function getId(): int
    {
        return PacketId::LOGIN;
    }

    public function encode(BinaryWriter $writer): void
    {
        $writer->writeInt32BigEndian($this->clientNetworkVersion);
        $writer->writeString($this->connectionRequest);
    }

    public static function decode(BinaryReader $reader): static
    {
        return new static(
            clientNetworkVersion: $reader->readInt32BigEndian(),
            connectionRequest: $reader->readString()
        );
    }

    /**
     * Yalnızca **yapısal** kontrol: `connectionRequest` beklenen JSON
     * biçiminde mi ve bir `chain` dizisi taşıyor mu?
     *
     * DÜZELTME NOTU: Önceki sürüm "tam 2 nokta" arıyordu; bu yanlıştı.
     * Bedrock'ta `connectionRequest` tek bir JWT **değil**, şu biçimde bir
     * JSON nesnesidir:
     *
     * ```json
     * { "chain": ["<jwt>", "<jwt>"], "extraData": "..." }
     * ```
     *
     * Bu metot imza, zincir veya kimlik doğrulaması YAPMAZ — onu
     * `AuthenticationEngine` yapar. "Geçerli" anlamına gelmez.
     */
    public function hasPlausibleJwtShape(): bool
    {
        if ($this->connectionRequest === '' || strlen($this->connectionRequest) > 512 * 1024) {
            return false;
        }

        $decoded = json_decode($this->connectionRequest, true, 32);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return false;
        }

        $chain = $decoded['chain'] ?? null;

        if (!is_array($chain) || $chain === []) {
            return false;
        }

        foreach ($chain as $token) {
            if (!is_string($token) || $token === '') {
                return false;
            }
        }

        return true;
    }
}
