<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

/**
 * `PlayerAuthInputData` bit bayrakları (Mojang şeması, 2225).
 *
 * Şemada `"Compression"` + `Enum-as-Value` işaretli; tel üzerinde
 * **varint64** olarak taşınır. Yalnızca Phase 5'te kullanılan bayraklar
 * tanımlandı; geri kalanı için `has()` kullanılabilir.
 */
final class PlayerAuthInputFlags
{
    // Yön / hareket
    public const ASCEND = 0;
    public const DESCEND = 1;
    public const NORTH_JUMP = 2;
    public const JUMP_DOWN = 3;
    public const SPRINT_DOWN = 4;
    public const CHANGE_HEIGHT = 5;
    public const JUMPING = 6;
    public const AUTO_JUMPING_IN_WATER = 7;
    public const SNEAKING = 8;
    public const SNEAK_DOWN = 9;
    public const UP = 10;
    public const DOWN = 11;
    public const LEFT = 12;
    public const RIGHT = 13;
    public const UP_LEFT = 14;
    public const UP_RIGHT = 15;
    public const DOWN_LEFT = 16;
    public const DOWN_RIGHT = 17;
    public const SPRINT_UP = 18;
    public const SPRINT_LEFT = 19;
    public const SPRINT_RIGHT = 20;
    public const SPRINT_UP_LEFT = 21;
    public const SPRINT_UP_RIGHT = 22;
    public const START_SPRINTING = 23;
    public const STOP_SPRINTING = 24;
    public const START_SNEAKING = 25;
    public const STOP_SNEAKING = 26;
    public const START_SWIMMING = 27;
    public const STOP_SWIMMING = 28;
    public const START_JUMPING = 29;
    public const START_GLIDING = 30;
    public const STOP_GLIDING = 31;

    // Koşullu alanları belirleyen bayraklar (Phase 5 için kritik)
    public const PERFORM_ITEM_INTERACTION = 32;
    public const PERFORM_BLOCK_ACTIONS = 33;
    public const PERFORM_ITEM_STACK_REQUEST = 34;
    public const IS_IN_CLIENT_PREDICTED_VEHICLE = 36;

    // Kamera
    public const CAMERA_RELATIVE_MOVEMENT_ENABLED = 39;
    public const WASD_CONTROLLED = 40;

    public static function has(int $bitset, int $flag): bool
    {
        return ($bitset & (1 << $flag)) !== 0;
    }
}
