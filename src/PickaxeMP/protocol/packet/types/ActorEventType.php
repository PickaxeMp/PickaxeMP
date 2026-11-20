<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `ActorEvent` - varlık durum olayı kimliği (tel üzerinde `uint8`).
 *
 * Değerler Mojang `v1.26.65` şemasındaki `x-enum-binary-value` dizisinden
 * birebir alınmıştır (`ActorEvent.json`). Sıra ile değer **aynı değildir**
 * (9, 40..56, 59, 75 atlanmıştır); bu yüzden her durum açıkça numaralandırılır.
 */
enum ActorEventType: int
{
    case None = 0;
    case Jump = 1;
    case Hurt = 2;
    case Death = 3;
    case StartAttacking = 4;
    case StopAttacking = 5;
    case TamingFailed = 6;
    case TamingSucceeded = 7;
    case ShakeWetness = 8;
    case EatGrass = 10;
    case FishhookBubble = 11;
    case FishhookFishPos = 12;
    case FishhookHookTime = 13;
    case FishhookTease = 14;
    case SquidFleeing = 15;
    case ZombieConverting = 16;
    case PlayAmbient = 17;
    case SpawnAlive = 18;
    case StartOfferFlower = 19;
    case StopOfferFlower = 20;
    case LoveHearts = 21;
    case VillagerAngry = 22;
    case VillagerHappy = 23;
    case WitchHatMagic = 24;
    case FireworksExplode = 25;
    case InLoveHearts = 26;
    case SilverfishMergeAnim = 27;
    case GuardianAttackSound = 28;
    case DrinkPotion = 29;
    case ThrowPotion = 30;
    case PrimeTNTCart = 31;
    case PrimeCreeper = 32;
    case AirSupply = 33;
    case DeprecatedAddPlayerLevels = 34;
    case GuardianMiningFatigue = 35;
    case AgentSwingArm = 36;
    case DragonStartDeathAnim = 37;
    case GroundDust = 38;
    case Shake = 39;
    case Feed = 57;
    case BabyAge = 60;
    case InstantDeath = 61;
    case NotifyTrade = 62;
    case LeashDestroyed = 63;
    case CaravanUpdated = 64;
    case TalismanActivate = 65;
    case DeprecatedUpdateStructureFeature = 66;
    case PlayerSpawnedMob = 67;
    case Puke = 68;
    case UpdateStackSize = 69;
    case StartSwimming = 70;
    case BalloonPop = 71;
    case TreasureHunt = 72;
    case SummonAgent = 73;
    case FinishedChargingItem = 74;
    case ActorGrowUp = 76;
    case VibrationDetected = 77;
    case DrinkMilk = 78;
    case ShakeWetnessStop = 79;
    case KineticDamageDealt = 80;
    case HurtWithoutReceivingDamage = 81;

    /**
     * Bu olay ek bir `Fire At Position` vektörü taşır mı?
     *
     * Şemada `Fire At Position` `required` değildir; ayrıştırma kuralı makine
     * okunur değil. Bilinen tek taşıyıcı `FIREWORKS_EXPLODE`'dur
     * (gophertunnel / pmmp davranışı). Diğer olaylar için alan yazılmaz.
     */
    public function carriesFirePosition(): bool
    {
        return $this === self::FireworksExplode;
    }

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value) ?? self::None;
    }
}
