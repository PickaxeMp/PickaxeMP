<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet\types;

use PickaxeMP\protocol\binary\BinaryReader;
use PickaxeMP\protocol\binary\BinaryWriter;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\UnsupportedPacketFieldException;

/**
 * `ItemStackRequest` içindeki tek bir aksiyon.
 *
 * Tel biçimi (Mojang `ItemStackRequest.json` + gophertunnel `master`, 2225):
 *
 * ```
 *  varyant  varuint32   (aksiyonun cereal varyant indeksi)
 *  tur      uint8       (ItemStackRequestActionType)
 *  govde    tur'e ozgu
 * ```
 *
 * ## Neden iki alan?
 *
 * Varyant listesi hiç gönderilmeyen `PlaceInContainer` (7) ve
 * `TakeOutContainer` (8) türlerini atlar. Bu yüzden `tur >= 9` iken
 * `varyant = tur - 2`, aksi hâlde `varyant = tur`. İki alan uyuşmazsa paket
 * reddedilir: aksi hâlde gövde yanlış yorumlanır ve akış bozulur.
 *
 * ## Kapsam
 *
 * Gövdeleri **çözülen** türler: 0-6, 9-12, 14-19. Bunlardan yalnızca
 * `Take` / `Place` / `Swap` uygulanır; kalanı sunucu tarafında
 * `ActionRequestNotAllowed` ile reddedilir (yarım uygulama yok).
 *
 * `PlaceInContainer` (7), `TakeOutContainer` (8), `CraftRecipeAuto` (13) ve
 * `CraftResults` (20) gövdeleri çözülmez (eşya tanımlayıcı listeleri taşır);
 * `UnsupportedPacketFieldException` ile **yalnızca o paket** düşürülür.
 */
final class ItemStackRequestAction
{
    public const TAKE = 0;
    public const PLACE = 1;
    public const SWAP = 2;
    public const DROP = 3;
    public const DESTROY = 4;
    public const CONSUME = 5;
    public const CREATE = 6;
    public const PLACE_IN_CONTAINER = 7;
    public const TAKE_OUT_CONTAINER = 8;
    public const LAB_TABLE_COMBINE = 9;
    public const BEACON_PAYMENT = 10;
    public const MINE_BLOCK = 11;
    public const CRAFT_RECIPE = 12;
    public const CRAFT_RECIPE_AUTO = 13;
    public const CRAFT_CREATIVE = 14;
    public const CRAFT_RECIPE_OPTIONAL = 15;
    public const CRAFT_REPAIR_AND_DISENCHANT = 16;
    public const CRAFT_LOOM = 17;
    public const CRAFT_RESERVED = 18;
    public const CRAFT_NON_IMPLEMENTED = 19;
    public const CRAFT_RESULTS = 20;

    /** Şemanın `Amount` alanı için alt/üst sınırı. */
    public const MIN_AMOUNT = 1;
    public const MAX_AMOUNT = 64;

    public function __construct(
        public readonly int $type,
        public readonly int $amount = 0,
        public readonly ?ItemStackRequestSlotInfo $source = null,
        public readonly ?ItemStackRequestSlotInfo $destination = null,
        public readonly bool $randomly = false,
        public readonly int $resultsIndex = 0,
        public readonly int $primaryEffect = 0,
        public readonly int $secondaryEffect = 0,
        public readonly int $hotbarSlot = 0,
        public readonly int $predictedDurability = 0,
        public readonly int $stackNetworkId = 0,
        public readonly int $recipeNetworkId = 0,
        public readonly int $numberOfCrafts = 0,
        public readonly int $filterStringIndex = 0,
        public readonly int $cost = 0,
        public readonly string $pattern = ''
    ) {
        if ($type < 0 || $type > 0xFF) {
            throw ProtocolException::varIntOverflow('item stack request action type (uint8)');
        }
    }

    // --- Kisayollar ---------------------------------------------------

    public static function take(
        int $amount,
        ItemStackRequestSlotInfo $source,
        ItemStackRequestSlotInfo $destination
    ): self {
        return new self(self::TAKE, self::assertAmount($amount), $source, $destination);
    }

    public static function place(
        int $amount,
        ItemStackRequestSlotInfo $source,
        ItemStackRequestSlotInfo $destination
    ): self {
        return new self(self::PLACE, self::assertAmount($amount), $source, $destination);
    }

    public static function swap(
        ItemStackRequestSlotInfo $source,
        ItemStackRequestSlotInfo $destination
    ): self {
        return new self(self::SWAP, source: $source, destination: $destination);
    }

    // --- Siniflandirma ------------------------------------------------

    /**
     * Sunucu tarafında **uygulanan** aksiyon mu?
     */
    public function isSupported(): bool
    {
        return $this->type === self::TAKE
            || $this->type === self::PLACE
            || $this->type === self::SWAP;
    }

    public function movesBetweenSlots(): bool
    {
        return $this->type === self::TAKE || $this->type === self::PLACE;
    }

    /**
     * Tel üzerindeki cereal varyant indeksi.
     */
    public function variant(): int
    {
        return $this->type >= self::LAB_TABLE_COMBINE ? $this->type - 2 : $this->type;
    }

    public static function typeForVariant(int $variant): int
    {
        return $variant >= self::PLACE_IN_CONTAINER ? $variant + 2 : $variant;
    }

    public static function name(int $type): string
    {
        return [
            self::TAKE => 'Take',
            self::PLACE => 'Place',
            self::SWAP => 'Swap',
            self::DROP => 'Drop',
            self::DESTROY => 'Destroy',
            self::CONSUME => 'Consume',
            self::CREATE => 'Create',
            self::PLACE_IN_CONTAINER => 'PlaceInContainer',
            self::TAKE_OUT_CONTAINER => 'TakeOutContainer',
            self::LAB_TABLE_COMBINE => 'LabTableCombine',
            self::BEACON_PAYMENT => 'BeaconPayment',
            self::MINE_BLOCK => 'MineBlock',
            self::CRAFT_RECIPE => 'CraftRecipe',
            self::CRAFT_RECIPE_AUTO => 'CraftRecipeAuto',
            self::CRAFT_CREATIVE => 'CraftCreative',
            self::CRAFT_RECIPE_OPTIONAL => 'CraftRecipeOptional',
            self::CRAFT_REPAIR_AND_DISENCHANT => 'CraftRepairAndDisenchant',
            self::CRAFT_LOOM => 'CraftLoom',
            self::CRAFT_RESERVED => 'CraftReserved',
            self::CRAFT_NON_IMPLEMENTED => 'CraftNonImplemented',
            self::CRAFT_RESULTS => 'CraftResults',
        ][$type] ?? sprintf('unknown(%d)', $type);
    }

    // --- Kodlama ------------------------------------------------------

    public function encode(BinaryWriter $writer): void
    {
        self::assertEncodable($this->type);

        $writer->writeVarUInt($this->variant());
        $writer->writeByte($this->type);

        switch ($this->type) {
            case self::TAKE:
            case self::PLACE:
                $writer->writeByte(self::assertAmount($this->amount));
                $this->requireSource()->encode($writer);
                $this->requireDestination()->encode($writer);

                return;

            case self::SWAP:
                $this->requireSource()->encode($writer);
                $this->requireDestination()->encode($writer);

                return;

            case self::DROP:
                $writer->writeByte(self::assertAmount($this->amount));
                $this->requireSource()->encode($writer);
                $writer->writeBool($this->randomly);

                return;

            case self::DESTROY:
            case self::CONSUME:
                $writer->writeByte(self::assertAmount($this->amount));
                $this->requireSource()->encode($writer);

                return;

            case self::CREATE:
                $writer->writeByte($this->resultsIndex);

                return;

            case self::LAB_TABLE_COMBINE:
            case self::CRAFT_NON_IMPLEMENTED:
                return;

            case self::BEACON_PAYMENT:
                $writer->writeVarInt($this->primaryEffect);
                $writer->writeVarInt($this->secondaryEffect);

                return;

            case self::MINE_BLOCK:
                $writer->writeVarInt($this->hotbarSlot);
                $writer->writeVarInt($this->predictedDurability);
                // `ItemStackNetIdVariant` = varint32 (zigzag), sabit int32 degil.
                $writer->writeVarInt($this->stackNetworkId);

                return;

            case self::CRAFT_RECIPE:
            case self::CRAFT_CREATIVE:
                $writer->writeVarUInt($this->recipeNetworkId);
                $writer->writeByte($this->numberOfCrafts);

                return;

            case self::CRAFT_RECIPE_OPTIONAL:
                $writer->writeVarUInt($this->recipeNetworkId);
                $writer->writeInt32($this->filterStringIndex);

                return;

            case self::CRAFT_REPAIR_AND_DISENCHANT:
                // Grindstone: RecipeNetworkId varuint32, RepairCost varint32,
                // Repetitions uint8 (pmmp BedrockProtocol + gophertunnel).
                $writer->writeVarUInt($this->recipeNetworkId);
                $writer->writeVarInt($this->cost);
                $writer->writeByte($this->numberOfCrafts);

                return;

            case self::CRAFT_LOOM:
            case self::CRAFT_RESERVED:
                $writer->writeString($this->pattern);
                $writer->writeByte($this->numberOfCrafts);

                return;
        }

        throw new UnsupportedPacketFieldException(
            'ItemStackRequest: aksiyon govdesi kodlanamaz (' . self::name($this->type) . ')'
        );
    }

    public static function decode(BinaryReader $reader): self
    {
        $variant = $reader->readVarUInt();
        $type = $reader->readByte();

        $expectedVariant = $type >= self::LAB_TABLE_COMBINE ? $type - 2 : $type;

        if ($variant !== $expectedVariant) {
            throw ProtocolException::invalidEnum(
                sprintf('item stack request action variant (tur %d)', $type),
                $variant
            );
        }

        return match ($type) {
            self::TAKE, self::PLACE => new self(
                type: $type,
                amount: $reader->readByte(),
                source: ItemStackRequestSlotInfo::decode($reader),
                destination: ItemStackRequestSlotInfo::decode($reader)
            ),
            self::SWAP => new self(
                type: $type,
                source: ItemStackRequestSlotInfo::decode($reader),
                destination: ItemStackRequestSlotInfo::decode($reader)
            ),
            self::DROP => self::decodeDrop($reader),
            self::DESTROY, self::CONSUME => new self(
                type: $type,
                amount: $reader->readByte(),
                source: ItemStackRequestSlotInfo::decode($reader)
            ),
            self::CREATE => new self(type: $type, resultsIndex: $reader->readByte()),
            self::LAB_TABLE_COMBINE, self::CRAFT_NON_IMPLEMENTED => new self(type: $type),
            self::BEACON_PAYMENT => new self(
                type: $type,
                primaryEffect: $reader->readVarInt(),
                secondaryEffect: $reader->readVarInt()
            ),
            self::MINE_BLOCK => new self(
                type: $type,
                hotbarSlot: $reader->readVarInt(),
                predictedDurability: $reader->readVarInt(),
                stackNetworkId: $reader->readVarInt()
            ),
            self::CRAFT_RECIPE, self::CRAFT_CREATIVE => new self(
                type: $type,
                recipeNetworkId: $reader->readVarUInt(),
                numberOfCrafts: $reader->readByte()
            ),
            self::CRAFT_RECIPE_OPTIONAL => new self(
                type: $type,
                recipeNetworkId: $reader->readVarUInt(),
                filterStringIndex: $reader->readInt32()
            ),
            self::CRAFT_REPAIR_AND_DISENCHANT => new self(
                type: $type,
                recipeNetworkId: $reader->readVarUInt(),
                cost: $reader->readVarInt(),
                numberOfCrafts: $reader->readByte()
            ),
            self::CRAFT_LOOM, self::CRAFT_RESERVED => new self(
                type: $type,
                pattern: $reader->readString(64),
                numberOfCrafts: $reader->readByte()
            ),
            default => throw new UnsupportedPacketFieldException(
                'ItemStackRequest: aksiyon govdesi cozulemez (' . self::name($type) . ')'
            ),
        };
    }

    private static function decodeDrop(BinaryReader $reader): self
    {
        $amount = $reader->readByte();
        $source = ItemStackRequestSlotInfo::decode($reader);

        return new self(
            type: self::DROP,
            amount: $amount,
            source: $source,
            randomly: $reader->readBool()
        );
    }

    private function requireSource(): ItemStackRequestSlotInfo
    {
        if ($this->source === null) {
            throw new ProtocolException(
                'ItemStackRequest: ' . self::name($this->type) . ' aksiyonu kaynak yuva tasimiyor'
            );
        }

        return $this->source;
    }

    private function requireDestination(): ItemStackRequestSlotInfo
    {
        if ($this->destination === null) {
            throw new ProtocolException(
                'ItemStackRequest: ' . self::name($this->type) . ' aksiyonu hedef yuva tasimiyor'
            );
        }

        return $this->destination;
    }

    /**
     * Varyant listesi 7 ve 8'i atladigi icin bu turler kodlanamaz.
     */
    private static function assertEncodable(int $type): void
    {
        if ($type === self::PLACE_IN_CONTAINER
            || $type === self::TAKE_OUT_CONTAINER
            || $type === self::CRAFT_RECIPE_AUTO
            || $type === self::CRAFT_RESULTS
            || $type > self::CRAFT_RESULTS
        ) {
            throw new UnsupportedPacketFieldException(
                'ItemStackRequest: aksiyon turu kodlanamaz (' . self::name($type) . ')'
            );
        }
    }

    private static function assertAmount(int $amount): int
    {
        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT) {
            throw ProtocolException::invalidEnum('item stack request action amount (1..64)', $amount);
        }

        return $amount;
    }
}
