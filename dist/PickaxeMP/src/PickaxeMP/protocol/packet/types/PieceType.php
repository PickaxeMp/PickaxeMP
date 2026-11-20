<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `persona::PieceType` - tel üzerinde `uint32`.
 *
 * Değerler Mojang `v1.26.65` şemasındaki `x-enum-binary-value` dizisinden
 * birebir alınmıştır (`persona__PieceType.json`). Sıra ile değer aynıdır
 * (0..29) ama açıkça numaralandırılır: şema yeniden sıralarsa fark edilir.
 */
enum PieceType: int
{
    case Unknown = 0;
    case Skeleton = 1;
    case Body = 2;
    case Skin = 3;
    case Bottom = 4;
    case Feet = 5;
    case Dress = 6;
    case Top = 7;
    case HighPants = 8;
    case Hands = 9;
    case Outerwear = 10;
    case FacialHair = 11;
    case Mouth = 12;
    case Eyes = 13;
    case Hair = 14;
    case Hood = 15;
    case Back = 16;
    case FaceAccessory = 17;
    case Head = 18;
    case Legs = 19;
    case LeftLeg = 20;
    case RightLeg = 21;
    case Arms = 22;
    case LeftArm = 23;
    case RightArm = 24;
    case Capes = 25;
    case ClassicSkin = 26;
    case Emote = 27;
    case CoCo = 28;
    case Unsupported = 29;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::Unsupported;
    }
}
