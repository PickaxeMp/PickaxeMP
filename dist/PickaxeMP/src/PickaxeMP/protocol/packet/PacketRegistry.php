<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet;

use PickaxeMP\protocol\ProtocolException;

/**
 * Kayıtlı paketlerin tek kaynağı.
 *
 * Bilinmeyen paket kimliği "sessizce yut" değil, açık hata üretir: yanlış
 * yorumlanan baytlar akışı bozar. Çağıran katman (ProtocolEngine) bu hatayı
 * bağlantı bazında ele alır; sunucu çökmez.
 */
final class PacketRegistry
{
    /** @var array<int, array{class: class-string<PacketInterface>, direction: PacketDirection, maxSize: int}> */
    private array $entries = [];

    /**
     * @param class-string<PacketInterface> $class
     */
    public function register(int $id, string $class, PacketDirection $direction, int $maxSize = 8 * 1024 * 1024): void
    {
        if (isset($this->entries[$id])) {
            throw new ProtocolException("Paket kimligi zaten kayitli: {$id}");
        }

        $this->entries[$id] = [
            'class' => $class,
            'direction' => $direction,
            'maxSize' => $maxSize,
        ];
    }

    public function has(int $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * @return class-string<PacketInterface>
     */
    public function classFor(int $id): string
    {
        if (!isset($this->entries[$id])) {
            throw ProtocolException::unknownPacket($id);
        }

        return $this->entries[$id]['class'];
    }

    public function directionFor(int $id): PacketDirection
    {
        if (!isset($this->entries[$id])) {
            throw ProtocolException::unknownPacket($id);
        }

        return $this->entries[$id]['direction'];
    }

    public function maxSizeFor(int $id): int
    {
        if (!isset($this->entries[$id])) {
            throw ProtocolException::unknownPacket($id);
        }

        return $this->entries[$id]['maxSize'];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_keys($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Phase 2'de desteklenen paketlerle dolu varsayılan registry.
     */
    public static function withSupportedPackets(): self
    {
        $registry = new self();

        $registry->register(
            PacketId::LOGIN,
            packets\LoginPacket::class,
            PacketDirection::ClientToServer,
            // Login ~100 KB; üst sınırı cömert ama sınırlı tutuyoruz.
            512 * 1024
        );

        $registry->register(
            PacketId::REQUEST_NETWORK_SETTINGS,
            packets\RequestNetworkSettingsPacket::class,
            PacketDirection::ClientToServer
        );

        $registry->register(
            PacketId::NETWORK_SETTINGS,
            packets\NetworkSettingsPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::PLAY_STATUS,
            packets\PlayStatusPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::RESOURCE_PACKS_INFO,
            packets\ResourcePacksInfoPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::RESOURCE_PACK_STACK,
            packets\ResourcePackStackPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::RESOURCE_PACK_CLIENT_RESPONSE,
            packets\ResourcePackClientResponsePacket::class,
            PacketDirection::ClientToServer
        );

        $registry->register(
            PacketId::START_GAME,
            packets\StartGamePacket::class,
            PacketDirection::ServerToClient
        );

        // Phase 10.4: esya kimlik tablosu. `StartGame`'den **hemen sonra**
        // gonderilmezse istemci sayisal esya kimliklerini cozemez.
        $registry->register(
            PacketId::ITEM_REGISTRY,
            packets\ItemRegistryPacket::class,
            PacketDirection::ServerToClient,
            512 * 1024
        );

        $registry->register(
            PacketId::MOVE_PLAYER,
            packets\MovePlayerPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::LEVEL_CHUNK,
            packets\LevelChunkPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::SUB_CHUNK,
            packets\SubChunkPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::SERVER_TO_CLIENT_HANDSHAKE,
            packets\ServerToClientHandshakePacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::CLIENT_TO_SERVER_HANDSHAKE,
            packets\ClientToServerHandshakePacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        $registry->register(
            PacketId::PLAYER_AUTH_INPUT,
            packets\PlayerAuthInputPacket::class,
            PacketDirection::ClientToServer,
            64 * 1024
        );

        // --- Phase 6: envanter ve varlik paketleri -------------------------

        $registry->register(
            PacketId::INVENTORY_TRANSACTION,
            packets\InventoryTransactionPacket::class,
            PacketDirection::ClientToServer,
            64 * 1024
        );

        $registry->register(
            PacketId::MOB_EQUIPMENT,
            packets\MobEquipmentPacket::class,
            // Iki yonlu: istemci kendi hotbar secimini bildirir, sunucu
            // digerlerine yayinlar (Phase 10.5).
            PacketDirection::Both,
            4096
        );

        $registry->register(
            PacketId::CONTAINER_OPEN,
            packets\ContainerOpenPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::CONTAINER_CLOSE,
            packets\ContainerClosePacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::INVENTORY_CONTENT,
            packets\InventoryContentPacket::class,
            PacketDirection::ServerToClient,
            256 * 1024
        );

        $registry->register(
            PacketId::INVENTORY_SLOT,
            packets\InventorySlotPacket::class,
            PacketDirection::ServerToClient
        );

        // --- Phase 10.3: envanter yigin istekleri ---------------------------

        $registry->register(
            PacketId::ITEM_STACK_REQUEST,
            packets\ItemStackRequestPacket::class,
            PacketDirection::ClientToServer,
            64 * 1024
        );

        $registry->register(
            PacketId::ITEM_STACK_RESPONSE,
            packets\ItemStackResponsePacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::ADD_PLAYER,
            packets\AddPlayerPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::ADD_ACTOR,
            packets\AddActorPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::REMOVE_ACTOR,
            packets\RemoveActorPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::MOVE_ACTOR_ABSOLUTE,
            packets\MoveActorAbsolutePacket::class,
            PacketDirection::ServerToClient
        );

        // --- Phase 7: oynanis dongusu ---------------------------------------

        $registry->register(
            PacketId::INTERACT,
            packets\InteractPacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        $registry->register(
            PacketId::PLAYER_ACTION,
            packets\PlayerActionPacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        $registry->register(
            PacketId::UPDATE_BLOCK,
            packets\UpdateBlockPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::SET_HEALTH,
            packets\SetHealthPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::RESPAWN,
            packets\RespawnPacket::class,
            PacketDirection::ServerToClient
        );

        // --- Phase 7.1: oyuncu listesi, varlik olaylari, ses, delta --------

        $registry->register(
            PacketId::PLAYER_LIST,
            packets\PlayerListPacket::class,
            PacketDirection::ServerToClient,
            1024 * 1024
        );

        $registry->register(
            PacketId::ACTOR_EVENT,
            packets\ActorEventPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        $registry->register(
            PacketId::LEVEL_SOUND_EVENT,
            packets\LevelSoundEventPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        $registry->register(
            PacketId::MOVE_ACTOR_DELTA,
            packets\MoveActorDeltaPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        // --- Phase 7.2: yere dusen esya varliklari --------------------------

        $registry->register(
            PacketId::ADD_ITEM_ACTOR,
            packets\AddItemActorPacket::class,
            PacketDirection::ServerToClient
        );

        $registry->register(
            PacketId::TAKE_ITEM_ACTOR,
            packets\TakeItemActorPacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        // --- Phase 8: chunk akisi, zaman, ilk yukleme kapisi ----------------

        $registry->register(
            PacketId::SET_TIME,
            packets\SetTimePacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        $registry->register(
            PacketId::REQUEST_CHUNK_RADIUS,
            packets\RequestChunkRadiusPacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        $registry->register(
            PacketId::CHUNK_RADIUS_UPDATED,
            packets\ChunkRadiusUpdatedPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        $registry->register(
            PacketId::SET_LOCAL_PLAYER_AS_INITIALIZED,
            packets\SetLocalPlayerAsInitializedPacket::class,
            PacketDirection::ClientToServer,
            1024
        );

        $registry->register(
            PacketId::NETWORK_CHUNK_PUBLISHER_UPDATE,
            packets\NetworkChunkPublisherUpdatePacket::class,
            PacketDirection::ServerToClient,
            // 9216 ChunkPos * 8 bayt + basliklar.
            128 * 1024
        );

        $registry->register(
            PacketId::SET_SPAWN_POSITION,
            packets\SetSpawnPositionPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        // --- Phase 9: oyuncu ozellikleri ve yetenekleri ---------------------

        $registry->register(
            PacketId::UPDATE_ATTRIBUTES,
            packets\UpdateAttributesPacket::class,
            PacketDirection::ServerToClient,
            8 * 1024 * 1024
        );

        $registry->register(
            PacketId::UPDATE_ABILITIES,
            packets\UpdateAbilitiesPacket::class,
            PacketDirection::ServerToClient,
            1024
        );

        return $registry;
    }
}
