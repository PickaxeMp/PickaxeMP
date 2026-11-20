<?php

declare(strict_types=1);

namespace PickaxeMP\protocol\packet;

/**
 * Paket kimlikleri — TEK merkezi kaynak.
 *
 * Değerler Mojang'ın `v1.26.65` etiketli şemasındaki `[cereal:packet]`
 * alanlarından alınmıştır (protocol 2225). Tahmin edilmemiştir.
 *
 * Yeni paket eklerken önce şemayı indirip `[cereal:packet]` değerini
 * doğrulayın; aksi halde istemci paketi yanlış yorumlar.
 */
final class PacketId
{
    // --- Client → Server -------------------------------------------------
    public const LOGIN = 1;
    public const RESOURCE_PACK_CLIENT_RESPONSE = 8;
    public const REQUEST_NETWORK_SETTINGS = 193;

    public const INVENTORY_TRANSACTION = 30;
    public const ITEM_STACK_REQUEST = 147;
    public const INTERACT = 33;
    public const PLAYER_ACTION = 36;
    public const TAKE_ITEM_ACTOR = 17;
    public const REQUEST_CHUNK_RADIUS = 69;
    public const SET_LOCAL_PLAYER_AS_INITIALIZED = 113;

    // --- Server → Client -------------------------------------------------
    public const PLAY_STATUS = 2;
    public const ADD_PLAYER = 12;
    public const ADD_ACTOR = 13;
    public const REMOVE_ACTOR = 14;
    public const ADD_ITEM_ACTOR = 15;
    public const MOVE_ACTOR_ABSOLUTE = 18;
    public const ACTOR_EVENT = 27;
    public const UPDATE_BLOCK = 21;
    public const SET_HEALTH = 42;
    public const SET_SPAWN_POSITION = 43;
    public const RESPAWN = 45;
    public const UPDATE_ATTRIBUTES = 29;
    public const UPDATE_ABILITIES = 187;
    public const PLAYER_LIST = 63;
    public const MOVE_ACTOR_DELTA = 111;
    public const LEVEL_SOUND_EVENT = 123;
    public const MOB_EQUIPMENT = 31;
    public const CONTAINER_OPEN = 46;
    public const CONTAINER_CLOSE = 47;
    public const INVENTORY_CONTENT = 49;
    public const INVENTORY_SLOT = 50;
    public const ITEM_STACK_RESPONSE = 148;
    public const RESOURCE_PACKS_INFO = 6;
    public const RESOURCE_PACK_STACK = 7;
    public const START_GAME = 11;
    public const ITEM_REGISTRY = 162;
    public const MOVE_PLAYER = 19;
    public const SERVER_TO_CLIENT_HANDSHAKE = 3;
    public const CLIENT_TO_SERVER_HANDSHAKE = 4;
    public const LEVEL_CHUNK = 58;
    public const NETWORK_SETTINGS = 143;
    public const PLAYER_AUTH_INPUT = 144;
    public const SUB_CHUNK = 174;
    public const SET_TIME = 10;
    public const CHUNK_RADIUS_UPDATED = 70;
    public const NETWORK_CHUNK_PUBLISHER_UPDATE = 121;

    /**
     * İnsan okunabilir ad (log ve teşhis için).
     */
    public static function name(int $id): string
    {
        return self::all()[$id] ?? sprintf('unknown(0x%02X)', $id);
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::LOGIN => 'Login',
            self::ADD_PLAYER => 'AddPlayer',
            self::ADD_ACTOR => 'AddActor',
            self::REMOVE_ACTOR => 'RemoveActor',
            self::ADD_ITEM_ACTOR => 'AddItemActor',
            self::TAKE_ITEM_ACTOR => 'TakeItemActor',
            self::REQUEST_CHUNK_RADIUS => 'RequestChunkRadius',
            self::SET_LOCAL_PLAYER_AS_INITIALIZED => 'SetLocalPlayerAsInitialized',
            self::SET_TIME => 'SetTime',
            self::CHUNK_RADIUS_UPDATED => 'ChunkRadiusUpdated',
            self::NETWORK_CHUNK_PUBLISHER_UPDATE => 'NetworkChunkPublisherUpdate',
            self::MOVE_ACTOR_ABSOLUTE => 'MoveActorAbsolute',
            self::MOVE_ACTOR_DELTA => 'MoveActorDelta',
            self::ACTOR_EVENT => 'ActorEvent',
            self::LEVEL_SOUND_EVENT => 'LevelSoundEvent',
            self::PLAYER_LIST => 'PlayerList',
            self::INVENTORY_TRANSACTION => 'InventoryTransaction',
            self::INTERACT => 'Interact',
            self::PLAYER_ACTION => 'PlayerAction',
            self::UPDATE_BLOCK => 'UpdateBlock',
            self::SET_HEALTH => 'SetHealth',
            self::SET_SPAWN_POSITION => 'SetSpawnPosition',
            self::RESPAWN => 'Respawn',
            self::UPDATE_ATTRIBUTES => 'UpdateAttributes',
            self::UPDATE_ABILITIES => 'UpdateAbilities',
            self::MOB_EQUIPMENT => 'MobEquipment',
            self::CONTAINER_OPEN => 'ContainerOpen',
            self::CONTAINER_CLOSE => 'ContainerClose',
            self::INVENTORY_CONTENT => 'InventoryContent',
            self::INVENTORY_SLOT => 'InventorySlot',
            self::ITEM_STACK_REQUEST => 'ItemStackRequest',
            self::ITEM_STACK_RESPONSE => 'ItemStackResponse',
            self::PLAY_STATUS => 'PlayStatus',
            self::RESOURCE_PACKS_INFO => 'ResourcePacksInfo',
            self::RESOURCE_PACK_STACK => 'ResourcePackStack',
            self::RESOURCE_PACK_CLIENT_RESPONSE => 'ResourcePackClientResponse',
            self::NETWORK_SETTINGS => 'NetworkSettings',
            self::REQUEST_NETWORK_SETTINGS => 'RequestNetworkSettings',
            self::START_GAME => 'StartGame',
            self::ITEM_REGISTRY => 'ItemRegistry',
            self::MOVE_PLAYER => 'MovePlayer',
            self::SERVER_TO_CLIENT_HANDSHAKE => 'ServerToClientHandshake',
            self::CLIENT_TO_SERVER_HANDSHAKE => 'ClientToServerHandshake',
            self::LEVEL_CHUNK => 'LevelChunk',
            self::PLAYER_AUTH_INPUT => 'PlayerAuthInput',
            self::SUB_CHUNK => 'SubChunk',
        ];
    }
}
