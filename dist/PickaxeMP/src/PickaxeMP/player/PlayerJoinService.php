<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\item\ItemTable;
use PickaxeMP\protocol\packet\packets\ItemRegistryPacket;
use PickaxeMP\protocol\packet\packets\LevelChunkPacket;
use PickaxeMP\protocol\packet\packets\SetSpawnPositionPacket;
use PickaxeMP\protocol\packet\packets\StartGamePacket;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\LevelSettings;
use PickaxeMP\protocol\packet\types\SpawnSettings;
use PickaxeMP\protocol\ProtocolVersion;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\chunk\ChunkSerializer;
use PickaxeMP\world\World;

/**
 * Kimlik doğrulanmış oturumu dünyaya bağlayan Phase 5 servisidir.
 *
 * İki aşamalı çalışır:
 *  1. `prepare()` oyuncuyu ve spawn paketlerini üretir; oturum `Initializing`
 *     durumuna geçer ancak henüz `Playing` sayılmaz.
 *  2. Taşıyıcı paketleri başarıyla gönderdikten sonra `complete()` çağrılır.
 *
 * Bu ayrım, paket gönderimi başarısız olduğu hâlde oyuncunun dünyada oynuyor
 * görünmesini engeller.
 */
final class PlayerJoinService
{
    public const SERVER_VERSION = 'PickaxeMP 0.1.0-dev';

    private readonly ChunkSerializer $chunkSerializer;

    /** İstemciye ilan edilecek eşya tablosu; `null` = gömülü tablo. */
    private readonly ?ItemTable $itemTable;

    public function __construct(
        private readonly PlayerManager $players,
        private readonly Logger $logger,
        ?ChunkSerializer $chunkSerializer = null,
        ?ItemTable $itemTable = null
    ) {
        $this->chunkSerializer = $chunkSerializer ?? new ChunkSerializer();
        $this->itemTable = $itemTable;
    }

    /**
     * Dünya giriş paketlerini hazırlar.
     *
     * @throws PlayerException oturum doğrulanmamışsa veya dünya girişi hazırlanamazsa
     */
    public function prepare(Session $session, World $world): PlayerJoin
    {
        $identity = $session->getIdentity();

        if ($identity === null || !$session->isAuthenticated()) {
            throw PlayerException::unauthenticatedSession($session->id);
        }

        if ($session->getState() !== SessionState::Authenticated) {
            throw PlayerException::invalidState(
                $session->getState()->value,
                SessionState::Authenticated->value
            );
        }

        if ($world->isClosed()) {
            throw PlayerException::invalidState('closed_world', 'open_world');
        }

        $player = $this->players->create($session, $world);

        try {
            $packets = [
                $this->startGamePacket($player, $world),
                // Phase 10.4: esya kimlik tablosu StartGame'den **hemen**
                // sonra gelmeli; istemci envanterdeki sayisal kimlikleri
                // yalnizca bu tablodan cozer.
                $this->itemRegistryPacket(),
                $this->levelChunkPacket($player, $world),
                // Phase 10.5: yeniden dogma noktasi. Paket gonderilmezse
                // istemci olum sonrasi `{0,0,0}` cevresine duser.
                $this->setSpawnPositionPacket($world),
            ];

            $session->transitionTo(SessionState::Initializing);
        } catch (\Throwable $throwable) {
            $this->players->remove($player);

            throw $throwable;
        }

        $this->logger->info('Oyuncu dunyaya hazirlandi.', [
            'player' => $identity->displayName,
            'world' => $world->getName(),
            'entity_id' => $player->entityId,
        ]);

        return new PlayerJoin($session, $player, $packets);
    }

    /**
     * Spawn paketleri gönderildikten sonra oyuncuyu oynanabilir duruma geçirir.
     */
    public function complete(PlayerJoin $join): void
    {
        if ($join->session->getState() !== SessionState::Initializing) {
            throw PlayerException::invalidState(
                $join->session->getState()->value,
                SessionState::Initializing->value
            );
        }

        if ($join->player->state() !== PlayerState::Joining) {
            throw PlayerException::invalidState(
                $join->player->state()->value,
                PlayerState::Joining->value
            );
        }

        $join->session->transitionTo(SessionState::Playing);
        $join->player->markSpawned();

        $this->logger->info('Oyuncu dunyaya girdi.', [
            'player' => $join->player->identity->displayName,
            'world' => $join->player->worldName(),
            'entity_id' => $join->player->entityId,
        ]);
    }

    private function startGamePacket(Player $player, World $world): StartGamePacket
    {
        $spawn = $world->getSpawn();
        $spawnX = (int) floor($spawn['x']);
        $spawnY = (int) floor($spawn['y']);
        $spawnZ = (int) floor($spawn['z']);

        return new StartGamePacket(
            entityId: $player->entityId,
            runtimeId: $player->runtimeId,
            gameType: 0,
            x: $spawn['x'],
            y: $spawn['y'],
            z: $spawn['z'],
            settings: new LevelSettings(
                seed: $world->getSeed(),
                spawnSettings: SpawnSettings::at($spawnX, $spawnY, $spawnZ),
                defaultSpawnX: $spawnX,
                defaultSpawnY: $spawnY,
                defaultSpawnZ: $spawnZ,
                baseGameVersion: ProtocolVersion::GAME_VERSION
            ),
            levelId: $world->getName(),
            levelName: $world->getDisplayName(),
            multiplayerCorrelationId: bin2hex(random_bytes(8)),
            serverVersion: self::SERVER_VERSION,
            // Phase 7: blok kırma **istemci tahminine** dayanır. Sunucu
            // yetkili kırma (`true`) istemciden `PredictDestroyBlock` bekler
            // ve onay gelene kadar bloğu tutar; bu akış gerçek istemciyle
            // doğrulanmadığı için kapalı tutulur.
            serverAuthBlockBreaking: false
        );
    }

    /**
     * İstemciye ilan edilen eşya kimlik tablosu.
     *
     * DÜRÜST SINIR: vanilya eşya **tanımları** (bileşen NBT'si) üretilmez;
     * girişler `componentBased=false`, `version=2` ile gönderilir ve istemci
     * kendi gömülü vanilya tanımına düşer. Ayrıntı: `ItemTable::entries()`.
     */
    private function itemRegistryPacket(): ItemRegistryPacket
    {
        return new ItemRegistryPacket(($this->itemTable ?? ItemTable::bundled())->entries());
    }

    private function levelChunkPacket(Player $player, World $world): LevelChunkPacket
    {
        $spawn = $world->getSpawn();
        $position = ChunkPosition::fromBlock(
            (int) floor($spawn['x']),
            (int) floor($spawn['z'])
        );

        return $this->chunkSerializer->packet($world->getChunk($position), $position);
    }

    /**
     * Dünya spawn'ını istemciye bildirir.
     *
     * `WorldSpawn`: pusula da bu noktayı gösterir, yeniden doğma burada olur.
     */
    private function setSpawnPositionPacket(World $world): SetSpawnPositionPacket
    {
        $spawn = $world->getSpawn();

        return SetSpawnPositionPacket::worldSpawn(
            new BlockPos(
                (int) floor($spawn['x']),
                (int) floor($spawn['y']),
                (int) floor($spawn['z'])
            )
        );
    }
}
