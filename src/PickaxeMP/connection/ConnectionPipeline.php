<?php

declare(strict_types=1);

namespace PickaxeMP\connection;

use PickaxeMP\auth\AuthException;
use PickaxeMP\entity\ItemActorManager;
use PickaxeMP\exception\TransportException;
use PickaxeMP\gameplay\GameplayTicker;
use PickaxeMP\gameplay\GameplayTickResult;
use PickaxeMP\item\ItemDescriptorFactory;
use PickaxeMP\item\ItemRegistry;
use PickaxeMP\item\ItemTable;
use PickaxeMP\player\FoodTable;
use PickaxeMP\player\GameMode;
use PickaxeMP\player\Player;
use PickaxeMP\player\PlayerAbilities;
use PickaxeMP\player\PlayerException;
use PickaxeMP\player\PlayerJoin;
use PickaxeMP\player\PlayerJoinService;
use PickaxeMP\player\PlayerManager;
use PickaxeMP\player\PlayerSnapshot;
use PickaxeMP\auth\crypto\AesCtrCipher;
use PickaxeMP\auth\crypto\EcdhKeyExchange;
use PickaxeMP\auth\crypto\EcPublicKey;
use PickaxeMP\auth\crypto\HandshakeToken;
use PickaxeMP\protocol\compression\CompressionAlgorithm;
use PickaxeMP\protocol\compression\CompressionManager;
use PickaxeMP\protocol\compression\CompressionSettings;
use PickaxeMP\protocol\HandshakeResult;
use PickaxeMP\protocol\PacketBatch;
use PickaxeMP\protocol\packet\PacketContext;
use PickaxeMP\protocol\packet\PacketDirection;
use PickaxeMP\protocol\packet\PacketInterface;
use PickaxeMP\protocol\packet\PacketRegistry;
use PickaxeMP\player\FoodConsumptionManager;
use PickaxeMP\player\ItemStackRequestManager;
use PickaxeMP\player\PlayerAuthInput;
use PickaxeMP\protocol\packet\packets\ActorEventPacket;
use PickaxeMP\protocol\packet\packets\ClientToServerHandshakePacket;
use PickaxeMP\protocol\packet\packets\ChunkRadiusUpdatedPacket;
use PickaxeMP\protocol\packet\packets\NetworkChunkPublisherUpdatePacket;
use PickaxeMP\protocol\packet\packets\InteractPacket;
use PickaxeMP\protocol\packet\packets\LevelSoundEventPacket;
use PickaxeMP\protocol\packet\packets\InventoryTransactionPacket;
use PickaxeMP\protocol\packet\packets\ItemStackRequestPacket;
use PickaxeMP\protocol\packet\packets\ItemStackResponsePacket;
use PickaxeMP\protocol\packet\packets\LoginPacket;
use PickaxeMP\protocol\packet\packets\MobEquipmentPacket;
use PickaxeMP\protocol\packet\packets\MovePlayerPacket;
use PickaxeMP\protocol\packet\packets\PlayerActionPacket;
use PickaxeMP\protocol\packet\packets\PlayerAuthInputPacket;
use PickaxeMP\protocol\packet\packets\PlayerListPacket;
use PickaxeMP\protocol\packet\packets\RequestChunkRadiusPacket;
use PickaxeMP\protocol\packet\packets\RequestNetworkSettingsPacket;
use PickaxeMP\protocol\packet\packets\RespawnPacket;
use PickaxeMP\protocol\packet\packets\ResourcePackClientResponsePacket;
use PickaxeMP\protocol\packet\packets\ServerToClientHandshakePacket;
use PickaxeMP\protocol\packet\packets\SetHealthPacket;
use PickaxeMP\protocol\packet\packets\SetLocalPlayerAsInitializedPacket;
use PickaxeMP\protocol\packet\packets\SetSpawnPositionPacket;
use PickaxeMP\protocol\packet\packets\SetTimePacket;
use PickaxeMP\protocol\packet\packets\TakeItemActorPacket;
use PickaxeMP\protocol\packet\packets\UpdateAbilitiesPacket;
use PickaxeMP\protocol\packet\packets\UpdateAttributesPacket;
use PickaxeMP\scheduler\Scheduler;
use PickaxeMP\protocol\packet\types\ItemStackRequest;
use PickaxeMP\protocol\packet\types\ActorEventType;
use PickaxeMP\protocol\packet\types\BlockPos;
use PickaxeMP\protocol\packet\types\PlayerActionType;
use PickaxeMP\protocol\packet\types\RespawnState;
use PickaxeMP\protocol\packet\types\Vec3;
use PickaxeMP\protocol\ProtocolEngine;
use PickaxeMP\protocol\ProtocolException;
use PickaxeMP\protocol\ProtocolHandshake;
use PickaxeMP\protocol\UnsupportedPacketFieldException;
use PickaxeMP\session\Session;
use PickaxeMP\session\SessionAuthenticator;
use PickaxeMP\session\SessionException;
use PickaxeMP\session\SessionManager;
use PickaxeMP\session\SessionState;
use PickaxeMP\transport\ConnectionInterface;
use PickaxeMP\transport\TransportInterface;
use PickaxeMP\transport\TransportListenerInterface;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\chunk\ChunkPosition;
use PickaxeMP\world\ChunkStreamer;
use PickaxeMP\world\World;

/**
 * Taşıyıcı ile oyun katmanı arasındaki **bağlantı başına** glue katmanı.
 *
 * ## Sorumluluk
 *
 * - Taşıyıcı olaylarını (`onConnectionOpen`, `onPayload`, `onConnectionClosed`)
 *   bağlantı bazlı protokol/session durumuna bağlar.
 * - Paketleri `PacketBatch` zarfıyla sarmalar/çözer, `ProtocolEngine` ile
 *   paket nesnesine çevirir.
 * - Kimlik doğrulama için `SessionAuthenticator`'a devreder.
 * - Kaynak paketleri tamamlanınca `PlayerJoinService` ile dünyaya bağlar.
 *
 * ## Değişmez kurallar
 *
 * 1. `Playing` durumuna **yalnızca** spawn paketleri başarıyla
 *    gönderildikten sonra geçilir (`PlayerJoinService::complete()`).
 * 2. Gönderim başarısızsa oyuncu kaydı geri alınır ve bağlantı kapanır;
 *    oturum `Playing` olmaz.
 * 3. Her protokol hatası **fail-closed**'tır: bağlantı kapatılır, oturum
 *    `Failed` durumuna düşer.
 * 4. `NetworkSettings` paketi **sıkıştırma zarfı taşımaz**; anlaşma
 *    tamamlandıktan sonraki tüm paketler zarf taşır.
 *
 * DÜRÜST SINIR: Bu sınış gerçek Bedrock istemcisiyle doğrulanmamıştır.
 * ECDH/`ServerToClientHandshake`/AES-CTR akışı Login sonrası başlatılır.
 */
final class ConnectionPipeline implements TransportListenerInterface, PacketBroadcaster
{
    /** @var array<string, PipelineConnection> */
    private array $connections = [];

    private readonly CompressionSettings $settings;

    private readonly ProtocolEngine $engine;

    /** Görüş mesafesindeki chunk'ları oyuncu başına bir kez gönderir. */
    private readonly ChunkStreamer $chunkStreamer;

    /** Dünya saati, eşya ömrü ve açlık zincirini işleyen tick servisi. */
    private readonly GameplayTicker $gameplayTicker;

    /** Phase 10.3: sunucu yetkili yığın istekleri ve oturum imleci. */
    private readonly ItemStackRequestManager $stackRequests;

    /** Phase 10.4: canlı bağlantının eşya kimlik defteri. */
    private readonly ItemRegistry $items;

    /** Phase 10.4: açlık/tüketim zinciri (varsayılan: gömülü tablo). */
    private readonly ?FoodConsumptionManager $food;

    /** Sunucu tick döngüsüne kayıtlıysa oyun tick sayacı. */
    private int $tickNumber = 0;

    /** Schedule kimliği; `null` = zamanlayıcıya bağlı değil. */
    private ?string $tickTaskId = null;

    /**
     * Oturum → son yayınlanan chunk penceresi (`x,z,radius`).
     *
     * Phase 10.5: `NetworkChunkPublisherUpdate` her karede gönderilmez;
     * yalnızca oyuncunun chunk'ı veya görüş yarıçapı değiştiğinde gider.
     *
     * @var array<string, string>
     */
    private array $publishedWindow = [];

    /** Sıkıştırma zarfı olmayan batch (NetworkSettings öncesi). */
    private readonly PacketBatch $plainBatch;

    /** Sıkıştırma zarfı olan batch (NetworkSettings sonrası). */
    private readonly PacketBatch $compressedBatch;

    /**
     * @param int $handshakeDeadlineSeconds `Playing` durumuna ulaşmamış
     *        bağlantılar için el sıkışma zaman aşımı (saniye).
     *        0 = kapalı (zaman aşımı uygulanmaz).
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly SessionManager $sessions,
        private readonly SessionAuthenticator $authenticator,
        private readonly PlayerJoinService $joins,
        private readonly PlayerManager $players,
        private readonly PlayerAuthInput $playerAuthInput,
        private readonly World $world,
        private readonly Logger $logger,
        ?CompressionSettings $settings = null,
        ?ProtocolEngine $engine = null,
        private readonly int $handshakeDeadlineSeconds = 30,
        ?ChunkStreamer $chunkStreamer = null,
        ?ItemActorManager $itemActors = null,
        ?GameplayTicker $gameplayTicker = null,
        ?Scheduler $scheduler = null,
        ?FoodConsumptionManager $food = null,
        ?ItemStackRequestManager $stackRequests = null,
        ?ItemRegistry $items = null
    ) {
        $this->items = $items ?? ItemRegistry::withBundledItems();
        $this->food = $food ?? new FoodConsumptionManager(
            $this->logger,
            FoodTable::withDefaultFoods(),
            $this->items
        );
        $this->stackRequests = $stackRequests ?? new ItemStackRequestManager($this->logger);
        $this->chunkStreamer = $chunkStreamer ?? new ChunkStreamer();
        $this->gameplayTicker = $gameplayTicker ?? new GameplayTicker($this->world, $itemActors);

        // Oyun döngüsü sunucu zamanlayıcısına bağlanır: aksi hâlde dünya saati
        // donar ve süresi dolan eşya varlıkları hiç silinmez. Zamanlayıcı
        // verilmezse (birim testleri) `tick()` elle çağrılır.
        if ($scheduler !== null) {
            $this->tickTaskId = $scheduler->schedule(
                fn (): GameplayTickResult => $this->tick(),
                1,
                1,
                'gameplay.pipeline-tick'
            );
        }

        $this->settings = $settings ?? new CompressionSettings(
            CompressionAlgorithm::Zlib,
            CompressionSettings::DEFAULT_THRESHOLD
        );

        $this->engine = $engine ?? new ProtocolEngine(
            PacketRegistry::withSupportedPackets(),
            new CompressionManager($this->settings)
        );

        $this->plainBatch = new PacketBatch(
            new CompressionManager($this->settings),
            PacketBatch::TRANSPORT_NETHERNET,
            false
        );

        $this->compressedBatch = new PacketBatch(
            new CompressionManager($this->settings),
            PacketBatch::TRANSPORT_NETHERNET,
            true
        );
    }

    /** Phase 10.4: canlı bağlantının eşya kimlik defteri (tanılama/komutlar için). */
    public function items(): ItemRegistry
    {
        return $this->items;
    }

    /** Phase 10.4: yemek zinciri; varsayılan olarak gömülü tabloyla kurulur. */
    public function food(): ?FoodConsumptionManager
    {
        return $this->food;
    }

    public function onConnectionOpen(ConnectionInterface $connection): void
    {
        $connectionId = $connection->getId();

        if (isset($this->connections[$connectionId])) {
            // Aynı kimlikle ikinci bağlantı: eskiyi kapat, yeniyi reddet.
            $this->logger->warning('Yinelenen baglanti kimligi reddedildi.', [
                'connection' => $connectionId,
            ]);

            $this->transport->close($connectionId, 'duplicate_connection');

            return;
        }

        try {
            $session = $this->sessions->create($connectionId, $connection->getRemoteAddress());
            $session->transitionTo(SessionState::AwaitingNetworkSettings);
        } catch (SessionException $exception) {
            $this->logger->warning('Baglanti icin oturum olusturulamadi.', [
                'connection' => $connectionId,
                'reason' => $exception->getMessage(),
            ]);

            $this->transport->close($connectionId, 'session_create_failed');

            return;
        }

        $this->connections[$connectionId] = new PipelineConnection(
            $connection,
            $session,
            new ProtocolHandshake($this->settings)
        );

        $this->logger->debug('Baglanti acildi.', [
            'connection' => $connectionId,
            'session' => $session->id,
        ]);
    }

    /**
     * Gelen yükü şifreleme durumuna göre çözer.
     *
     * ## Neden tüm yük?
     *
     * Bedrock'ta şifreleme, **batch zarfının tamamını** sarar: paket uzunluk
     * önekleri, sıkıştırma baytı ve paket kimlikleri dahil her şey
     * ciphertext'tir. Bu yüzden şifre çözme, `PacketBatch::decode`'dan
     * **önce** yapılmalıdır. Paket başına çözme denemesi yanlıştır: paket
     * sınırları ancak çözüldükten sonra görünür.
     *
     * `cipherAvailable` tek başına yeterlidir; `encryptionEstablished`'ı ön
     * koşul yapmak, o bayrağı üreten `ClientToServerHandshake`'in asla
     * çözülememesine yol açar.
     *
     * @throws AuthException ciphertext bozuksa (checksum uyuşmazlığı)
     */
    private function decryptPayload(PipelineConnection $state, string $payload): string
    {
        if (!$state->cipherAvailable) {
            return $payload;
        }

        if ($state->cipher === null) {
            throw new AuthException('Sifreleme bayragi acik ama cipher yok.');
        }

        return $state->cipher->decrypt($payload);
    }

    public function onPayload(ConnectionInterface $connection, string $payload): void
    {
        $state = $this->connections[$connection->getId()] ?? null;

        if ($state === null) {
            // Bilinmeyen bağlantıdan gelen veri yok sayılır; oturum
            // açılmadan hiçbir paket işlenmez.
            return;
        }

        try {
            $payload = $this->decryptPayload($state, $payload);

            $batch = $state->compressionNegotiated
                ? $this->compressedBatch
                : $this->plainBatch;

            foreach ($batch->decode($payload) as $packetBytes) {
                try {
                    $packet = $this->engine->decode(
                        $packetBytes,
                        new PacketContext($connection->getId(), PacketDirection::ClientToServer)
                    );
                } catch (UnsupportedPacketFieldException $exception) {
                    // Cozumlenemeyen ama protokolu bozmayan alan (or.
                    // `PlayerAuthInput` icindeki Item Stack Request). Gercek
                    // istemci bunu her envanter isleminde gonderir; paketi
                    // dusurmek yeterlidir, baglanti yasamalidir.
                    $this->logger->warning('Cozulemeyen paket alani atlandi.', [
                        'session' => $state->session->id,
                        'reason' => $exception->getMessage(),
                    ]);

                    continue;
                }

                // Yalnızca **çözülebilen** paket aktivite sayılır; bozuk veri
                // gönderen bağlantı zaman aşımını uzatamaz (zaten kapanır).
                $state->session->touch();

                try {
                    $this->dispatch($state, $connection, $packet);
                } catch (UnsupportedPacketFieldException $exception) {
                    // Yalnızca **bu paket** düşer; bağlantı yaşar. Aksi hâlde
                    // istemcinin her `PlayerAuthInput`'unda gelen çözülmemiş
                    // bir alan oyuncuyu oyundan atardı.
                    $this->logger->warning('Desteklenmeyen paket alani atlandi.', [
                        'session' => $state->session->id,
                        'packet_id' => $packet->getId(),
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
        } catch (
            ProtocolException
            | SessionException
            | PlayerException
            | TransportException
            | AuthException $exception
        ) {
            $this->failConnection($connection, $exception->getMessage());
        }
    }

    public function onConnectionClosed(ConnectionInterface $connection, string $reason): void
    {
        $connectionId = $connection->getId();
        $state = $this->connections[$connectionId] ?? null;

        if ($state === null) {
            return;
        }

        unset($this->connections[$connectionId]);

        // Envanter, oyuncu kaydı silinmeden **önce** yazılır: `removeBySession()`
        // oyuncuyu indekslerden düşürür, ardından envantere erişemeyiz.
        $player = $this->players->bySession($state->session->id);

        if ($player !== null) {
            // Phase 10.3: imleç kalıcı değildir. Envanter yazılmadan **önce**
            // imleçteki eşya envantere geri konur; aksi hâlde ayrılan oyuncu
            // taşıdığı eşyayı sessizce kaybederdi.
            $this->stackRequests->release($state->session->id, $player->inventory());

            // Ayrılma yayını **oyuncu indeksten düşmeden önce** yapılır ve
            // kapanan oturum hariç tutulur. Liste girdisi `RemoveActor`'dan
            // önce silinir; ters sırada istemci, varlığı listede olmayan bir
            // oyuncuya bağlamaya çalışır.
            $this->broadcastExcept($state->session->id, PlayerSnapshot::playerListRemove($player));
            $this->broadcastExcept($state->session->id, PlayerSnapshot::removeActor($player));
            $this->players->saveInventory($player);
        }

        // Oyuncu kaydı önce temizlenir: SessionManager'a bakarken oyuncu
        // hâlâ "oynuyor" görünmemeli.
        $this->players->removeBySession($state->session->id);
        $this->sessions->close($state->session->id, $reason);
        $this->gameplayTicker->forget($state->session->id);
        $this->food?->forget($state->session->id);
        $this->stackRequests->forget($state->session->id);
        $this->chunkStreamer->reset($state->session->id);
        unset($this->publishedWindow[$state->session->id]);

        $this->logger->debug('Baglanti kapandi.', [
            'connection' => $connectionId,
            'session' => $state->session->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Aktif bağlantı sayısı (teşhis/test).
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * Oyun tick'i: dünya saati, eşya ömrü ve açlık zinciri.
     *
     * Ağ dağıtımı burada yapılır; durum mantığı {@see GameplayTicker}
     * içindedir. `tick()` çağrısı **zorunlu değildir** — çağrılmazsa oyun
     * zamanı ilerlemez, ancak paket akışı çalışmaya devam eder.
     */
    public function tick(?int $tickNumber = null): GameplayTickResult
    {
        $tickNumber ??= ++$this->tickNumber;
        $players = $this->players->all();
        $result = $this->gameplayTicker->tick($tickNumber, $players);

        if ($result->timeToBroadcast !== null) {
            $packet = new SetTimePacket($result->timeToBroadcast);

            foreach ($players as $player) {
                if (!$player->isInitialized()) {
                    continue;
                }

                $this->sendTo($player->sessionId, $packet);
            }
        }

        // Eşya ömrü sunucu yetkilidir: süre dolunca istemcilerden de silinir.
        foreach ($result->removedItems as $packet) {
            $this->broadcastExcept(null, $packet);
        }

        // Düşen eşyaların konumu da sunucu yetkilidir; aksi hâlde toplama
        // mesafesi istemcinin gördüğü konumla uyuşmaz.
        foreach ($result->movedItems as $packet) {
            $this->broadcastExcept(null, $packet);
        }

        foreach ($result->attributeUpdates as $sessionId => $attributes) {
            $player = $this->players->bySession($sessionId);

            if ($player === null) {
                continue;
            }

            $this->sendTo($sessionId, new UpdateAttributesPacket($player->runtimeId, $attributes));
        }

        // Açlıktan hasar / doğal yenilenme: sağlık yalnızca oyuncuya bildirilir.
        foreach ($result->healthUpdates as $sessionId => $health) {
            if ($this->players->bySession($sessionId) === null) {
                continue;
            }

            $this->sendTo($sessionId, new SetHealthPacket($health));
        }

        // Phase 10.4: ölüm geri bildirimi. `SetHealth(0)` istemcide ölüm
        // animasyonunu tetiklemez; `ActorEvent(Death)` + `LevelSoundEvent`
        // gerekir. Yayın tüm oturumlara gider: diğer istemciler de ölüm
        // animasyonunu görmeli.
        foreach ($result->deaths as $sessionId => $entityId) {
            $player = $this->players->bySession($sessionId);

            if ($player === null) {
                continue;
            }

            $this->broadcastExcept(
                null,
                new ActorEventPacket($player->runtimeId, ActorEventType::Death)
            );

            $this->broadcastExcept(
                null,
                new LevelSoundEventPacket(
                    'death',
                    new Vec3($player->x(), $player->y(), $player->z()),
                    actorIdentifier: 'minecraft:player',
                    isGlobal: true,
                    actorUniqueId: $entityId
                )
            );
        }

        return $result;
    }

    /**
     * Bağlantının pipeline durumu (teşhis/test).
     */
    public function get(string $connectionId): ?PipelineConnection
    {
        return $this->connections[$connectionId] ?? null;
    }

    // --- PacketBroadcaster ------------------------------------------------

    /**
     * Yayının gidebileceği bir bağlantı mı?
     *
     * Üç koşul birlikte aranır:
     *  1. Şifreleme kanıtlanmış (`encryptionEstablished`) - aksi hâlde dünya
     *     verisi doğrulanmamış bir kanaldan servis edilir.
     *  2. Dünya girişi tamamlanmış (`spawned` + oturum `Playing`).
     *  3. Oyuncu kaydı var ve durumu etkileşime açık ya da ölü (ölü oyuncu
     *     da diğer oyuncuların hareketini görmelidir).
     */
    private function isBroadcastTarget(PipelineConnection $state): bool
    {
        if (!$state->encryptionEstablished || !$state->spawned) {
            return false;
        }

        $session = $this->sessions->get($state->session->id);

        if ($session === null || !$session->isPlaying()) {
            return false;
        }

        return true;
    }

    /**
     * Oyun modunu değiştirir ve yeni yetenek katmanını yayınlar.
     *
     * ## Neden tek yöntem?
     *
     * Oyun modu değişimi **iki** şeyi birlikte gerektirir: sunucu durumu ve
     * istemciye `UpdateAbilities`. Birini unutmak istemcide "hayatta kalmış
     * gibi görünen yaratıcı oyuncu" (veya tersi) üretir. Çağıran yalnızca bu
     * yöntemi kullanır.
     *
     * @return bool yetenek paketi istemciye ulaştı mı
     */
    public function applyGameMode(Player $player, GameMode $mode): bool
    {
        $player->setGameMode($mode);

        return $this->refreshAbilities($player);
    }

    /**
     * Uçuşu açar/kapatır ve yeni yetenek katmanını yayınlar.
     *
     * @return bool istek uygulandı ve paket gönderildi mi
     */
    public function setFlying(Player $player, bool $flying): bool
    {
        if (!$player->setFlying($flying)) {
            return false;
        }

        return $this->refreshAbilities($player);
    }

    /**
     * Oyuncunun mevcut moduna göre `UpdateAbilities` yollar.
     *
     * @return bool paket istemciye ulaştı mı
     */
    public function refreshAbilities(Player $player): bool
    {
        return $this->sendTo(
            $player->sessionId,
            new UpdateAbilitiesPacket(PlayerAbilities::forPlayer($player))
        );
    }

    public function sendTo(string $sessionId, PacketInterface $packet): bool
    {
        foreach ($this->connections as $state) {
            if ($state->session->id !== $sessionId) {
                continue;
            }

            if (!$this->isBroadcastTarget($state)) {
                return false;
            }

            try {
                $this->sendPacket($state, $state->connection, $packet);

                return true;
            } catch (\Throwable $exception) {
                $this->logger->warning('Tek oyuncuya gonderim basarisiz.', [
                    'session' => $sessionId,
                    'packet_id' => $packet->getId(),
                    'reason' => $exception->getMessage(),
                ]);

                return false;
            }
        }

        return false;
    }

    public function broadcastExcept(?string $exceptSessionId, PacketInterface $packet): int
    {
        $sent = 0;

        foreach ($this->connections as $state) {
            if ($exceptSessionId !== null && $state->session->id === $exceptSessionId) {
                continue;
            }

            if (!$this->isBroadcastTarget($state)) {
                continue;
            }

            try {
                $this->sendPacket($state, $state->connection, $packet);
                $sent++;
            } catch (\Throwable $exception) {
                // Tek bağlantının hatası yayını durdurmaz: diğer oyuncular
                // dünyayı görmeye devam eder.
                $this->logger->warning('Yayin gonderimi basarisiz.', [
                    'connection' => $state->connection->getId(),
                    'session' => $state->session->id,
                    'packet_id' => $packet->getId(),
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Zaman aşımlarını uygular.
     *
     * İki ayrı kural vardır:
     *
     * 1. **Boşta kalma zaman aşımı** (`SessionManager::reapExpired`):
     *    hiçbir aktivite görmeyen oturumlar (Playing dahil) kapatılır.
     *    Oturum yöneticisi oturumu sildiğinde ilgili **bağlantı** da
     *    kapatılır — yarım kalmış oturum bırakılmaz.
     * 2. **El sıkışma zaman aşımı** (`handshakeDeadlineSeconds`):
     *    `Playing` durumuna ulaşamayan bağlantılar fail-closed kapatılır.
     *    0 değeri bu kuralı devre dışı bırakır.
     *
     * Kural 1 `SessionManager` zaman aşımı, kural 2 pipeline zaman aşımı
     * birbirinin yerine geçmez: biri oyun içi boşta kalmayı, diğeri takılıp
     * kalan el sıkışmayı hedefler.
     *
     * @return int Kapatılan bağlantı sayısı
     */
    public function enforceDeadlines(?int $now = null): int
    {
        $now ??= time();

        // Kural 1: oturum yöneticisinin boşta kalma zaman aşımı.
        $this->sessions->reapExpired($now);

        $reaped = [];
        $timedOut = [];

        foreach ($this->connections as $connectionId => $state) {
            $session = $this->sessions->get($state->session->id);

            if ($session === null) {
                // Oturum yöneticisi zaman aşımı nedeniyle sildi.
                $reaped[] = $connectionId;

                continue;
            }

            if ($this->handshakeDeadlineSeconds <= 0) {
                continue;
            }

            if ($session->isPlaying() || $session->isTerminal()) {
                continue;
            }

            if ($now - $session->lastActivityAt() > $this->handshakeDeadlineSeconds) {
                $timedOut[] = $connectionId;
            }
        }

        $closed = 0;

        foreach ($reaped as $connectionId) {
            $this->logger->info('Oturum zaman asimina ugradi, baglanti kapatiliyor.', [
                'connection' => $connectionId,
            ]);

            $this->transport->close($connectionId, 'session_expired');
            unset($this->connections[$connectionId]);
            $closed++;
        }

        foreach ($timedOut as $connectionId) {
            $state = $this->connections[$connectionId] ?? null;

            if ($state === null) {
                continue;
            }

            $this->logger->warning('El sikisma zaman asimi doldu, baglanti kapatiliyor.', [
                'connection' => $connectionId,
                'session' => $state->session->id,
                'state' => $state->session->getState()->value,
                'deadline' => $this->handshakeDeadlineSeconds,
            ]);

            $this->failConnection($state->connection, 'handshake_deadline_exceeded');
            $closed++;
        }

        return $closed;
    }

    private function dispatch(
        PipelineConnection $state,
        ConnectionInterface $connection,
        PacketInterface $packet
    ): void {
        if ($packet instanceof RequestNetworkSettingsPacket) {
            $result = $state->handshake->handle($packet);

            // NetworkSettings sıkıştırma zarfı taşımaz: istemci henüz
            // ayarları bilmiyor. `compressionNegotiated` bu gönderimden
            // sonra true olur.
            $this->sendResponses($state, $connection, $result);

            if ($result->isClosed()) {
                return;
            }

            $this->advanceSession($state->session, SessionState::AwaitingLogin);
            $state->compressionNegotiated = true;

            return;
        }

        if ($packet instanceof LoginPacket) {
            $result = $this->authenticator->handleLogin(
                $state->handshake,
                $state->session,
                $packet
            );

            // Login başarılıysa protokol el sıkışması AwaitingResourcePacks'e
            // geçmiş olur; bu noktada ECDH başlatılır.
            if (!$result->isClosed() && $state->handshake->getState()->value === 'awaiting_resource_packs') {
                $this->initiateEncryption($state, $connection);
            }

            $this->sendResponses($state, $connection, $result);

            return;
        }

        if ($packet instanceof ClientToServerHandshakePacket) {
            // Bu paket **şifreli** gelir. Çözülebildiyse istemci doğru
            // anahtarı türetmiş demektir; bayrak bunun sonucudur.
            if (!$state->cipherAvailable) {
                $this->failConnection($connection, 'handshake_before_encryption');
                return;
            }

            if ($state->encryptionEstablished) {
                // İkinci handshake: protokol ihlali. Sessizce kabul etmek
                // saldırgana yeniden "güvenilir" görünme yolu verir.
                $this->logger->warning('Yinelenen ClientToServerHandshake reddedildi.', [
                    'session' => $state->session->id,
                ]);
                $this->failConnection($connection, 'duplicate_handshake');
                return;
            }

            $state->encryptionEstablished = true;
            $this->logger->info('ClientToServerHandshake alindi, sifreleme kanitlandi.', [
                'session' => $state->session->id,
                'state' => $state->session->getState()->value,
            ]);

            return;
        }

        if ($packet instanceof ResourcePackClientResponsePacket) {
            // Şifreleme kanıtlanmadan kaynak paketi yanıtı işlenmez: aksi
            // hâlde dünya verisi şifrelenmemiş bir kanaldan servis edilirdi.
            if (!$state->encryptionEstablished) {
                $this->logger->warning('ResourcePack paketi sifreleme kanitlanmadan geldi.', [
                    'session' => $state->session->id,
                ]);
                $this->failConnection($connection, 'encryption_required');
                return;
            }

            // Sıra ihlali (ör. `Ready` dışı beklenmeyen durum) `handle()`
            // tarafından istisnaya çevrilir ve `onPayload` fail-closed uygular.
            $result = $state->handshake->handle($packet);
            $this->sendResponses($state, $connection, $result);

            if ($state->handshake->isReady() && !$state->spawned) {
                $state->spawned = true;
                $this->spawn($state, $connection);
            }

            return;
        }

        if ($packet instanceof PlayerAuthInputPacket) {
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                // Kimlik/şifreleme kanıtlanmadan hareket işlenmez.
                return;
            }

            // Pozisyon senkronizasyonu tek yerden yürür: `PlayerPositionSync`
            // paketi üretir, bu callback taşır.
            $send = function (PacketInterface $outbound) use ($state, $connection): void {
                $this->sendPacket($state, $connection, $outbound);
            };

            // Dünya değişiklikleri **diğer** oyunculara gider; kırılan bloğu
            // gönderen istemci zaten yerel tahminle kaldırmıştır.
            $broadcast = function (PacketInterface $outbound) use ($state): void {
                $this->broadcastExcept($state->session->id, $outbound);
            };

            $this->playerAuthInput->process($player, $packet, $this->world, $send, $broadcast);

            // Phase 10.3: istemci envanterde esya tasirken istek bu bayrakla
            // gelir. Yanit **tek** paket olarak gider; mutasyon yalnizca
            // dogrulama basariliysa uygulanir.
            if ($packet->itemStackRequest !== null) {
                $this->handleItemStackRequest($player, $packet->itemStackRequest, $send);
            }

            // Hareket chunk sınırını geçtiyse yeni chunk'lar gönderilir.
            // `sync()` gönderilmişleri atladığı için her pakette çağırmak
            // güvenlidir (25 sözlük araması).
            $this->streamChunks($state, $connection, $player);

            return;
        }

        if ($packet instanceof RequestChunkRadiusPacket) {
            // İstemci görüş mesafesi ister; **kırpılmış** değer geçerlidir.
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            $granted = $this->chunkStreamer->clampRadius($packet->chunkRadius);

            $this->logger->debug('Gorus mesafesi istendi.', [
                'session' => $state->session->id,
                'requested' => $packet->chunkRadius,
                'granted' => $granted,
            ]);

            $player->grantViewDistance($granted);
            $this->sendPacket($state, $connection, new ChunkRadiusUpdatedPacket($granted));

            // Phase 9: büyüyen pencere **hemen** doldurulur. Önceden yeni
            // yarıçap bir sonraki harekete kadar boş kalıyordu; istemci
            // "yüklenmedi" bölgeye bakıyordu.
            $this->streamChunks($state, $connection, $player);

            return;
        }

        if ($packet instanceof SetLocalPlayerAsInitializedPacket) {
            // İstemci dünyayı yükledi. Artık chunk akışı ve zaman senkronu
            // anlamlı; kimlik iddiası oturumla karşılaştırılır.
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            if ($packet->playerRuntimeId !== $player->runtimeId) {
                $this->logger->warning('SetLocalPlayerAsInitialized: runtime id uyusmuyor.', [
                    'session' => $state->session->id,
                    'claimed' => $packet->playerRuntimeId,
                    'actual' => $player->runtimeId,
                ]);

                return;
            }

            if (!$player->markInitialized()) {
                // Yinelenen paket: yeniden chunk göndermek boşa bant
                // genişliği olurdu, ancak sessizce yutmak da yanlış.
                $this->logger->debug('SetLocalPlayerAsInitialized yinelendi, yok sayildi.', [
                    'session' => $state->session->id,
                ]);

                return;
            }

            $this->sendPacket($state, $connection, new SetTimePacket($this->world->getTime()));

            // Phase 9: yetenekler ve öznitelikler dünya yüklendikten sonra
            // gider. Sıra önemlidir: `UpdateAbilities` istemcinin hareket
            // modunu belirler, `UpdateAttributes` sağlık/açlık çubuklarını.
            $this->sendPacket($state, $connection, new UpdateAbilitiesPacket($player->abilityData()));
            $this->sendPacket(
                $state,
                $connection,
                new UpdateAttributesPacket($player->runtimeId, $player->attributeList())
            );

            $this->streamChunks($state, $connection, $player);

            return;
        }

        if ($packet instanceof PlayerActionPacket) {
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            // Runtime ID istemci tarafından **iddia edilir**; başka bir
            // oyuncunun kimliğiyle işlem yapılmasına izin verilmez.
            if ($packet->playerRuntimeId !== $player->runtimeId) {
                $this->logger->warning('PlayerAction: runtime id uyusmuyor, yok sayildi.', [
                    'session' => $state->session->id,
                    'claimed' => $packet->playerRuntimeId,
                    'actual' => $player->runtimeId,
                ]);

                return;
            }

            if ($packet->action === PlayerActionType::Respawn) {
                $this->respawn($state, $connection, $player);

                return;
            }

            $send = function (PacketInterface $outbound) use ($state, $connection): void {
                $this->sendPacket($state, $connection, $outbound);
            };

            $broadcast = function (PacketInterface $outbound) use ($state): void {
                $this->broadcastExcept($state->session->id, $outbound);
            };

            $this->playerAuthInput->handlePlayerAction(
                $player,
                $packet,
                $this->world,
                $send,
                $broadcast
            );

            return;
        }

        if ($packet instanceof ItemStackRequestPacket) {
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            $send = function (PacketInterface $outbound) use ($state, $connection): void {
                $this->sendPacket($state, $connection, $outbound);
            };

            $responses = [];

            foreach ($packet->requests as $request) {
                $responses[] = $this->stackRequests->handle($player, $request);
            }

            if ($responses !== []) {
                $send(new ItemStackResponsePacket($responses));
            }

            return;
        }

        if ($packet instanceof InventoryTransactionPacket) {
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            $send = function (PacketInterface $outbound) use ($state, $connection): void {
                $this->sendPacket($state, $connection, $outbound);
            };

            $broadcast = function (PacketInterface $outbound) use ($state): void {
                $this->broadcastExcept($state->session->id, $outbound);
            };

            $this->playerAuthInput->handleInventoryTransaction(
                $player,
                $packet,
                $this->world,
                $send,
                $broadcast
            );

            return;
        }

        if ($packet instanceof TakeItemActorPacket) {
            // Istemci yerdeki bir esyayi toplamak istiyor. Kimlik/mesafe/
            // envanter dogrulamasi `PlayerAuthInput` -> `ItemActorManager`
            // icinde yapilir.
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            $send = function (PacketInterface $outbound) use ($state, $connection): void {
                $this->sendPacket($state, $connection, $outbound);
            };

            $broadcast = function (PacketInterface $outbound) use ($state): void {
                $this->broadcastExcept($state->session->id, $outbound);
            };

            $this->playerAuthInput->handleTakeItemActor(
                $player,
                $packet,
                $this->world,
                $send,
                $broadcast
            );

            return;
        }

        if ($packet instanceof MobEquipmentPacket) {
            $player = $this->players->bySession($state->session->id);

            if ($player === null || !$state->encryptionEstablished) {
                return;
            }

            $this->handleMobEquipment($state, $player, $packet);

            return;
        }

        if ($packet instanceof InteractPacket) {
            // Phase 7'de etkileşim yalnızca **günlüğe** yazılır: hedef varlık
            // (mob/oyuncu) saldırısı Phase 7.2'nin konusudur. Paket sessizce
            // yutulmaz, teşhis için iz bırakır.
            $this->logger->debug('Interact paketi alindi (islenmedi).', [
                'session' => $state->session->id,
                'action' => $packet->action->name,
                'target' => $packet->targetRuntimeId,
            ]);

            return;
        }

        // Beklenmeyen paket: el sıkışma sıra ihlalini fırlatır; hata
        // `onPayload` tarafından fail-closed'a çevrilir.
        $result = $state->handshake->handle($packet);

        $this->sendResponses($state, $connection, $result);
    }

    /**
     * İstemcinin hotbar seçimini işler.
     *
     * ## Neden gerekli
     *
     * `MobEquipment` iki yönlüdür: istemci kendi seçili yuvasını bildirir,
     * sunucu diğerlerine yayınlar. Paket işlenmezse `Player` daima 0. yuvayı
     * "seçili" sanır: yemek yeme, blok yerleştirme ve `heldItem()` yanlış
     * yuvadan okur.
     *
     * ## Sözleşme
     *
     * - `Target Runtime ID` istemci iddiasıdır; oturumdaki oyuncuyla
     *   eşleşmezse paket yok sayılır (başka oyuncunun eli değiştirilemez).
     * - Yuva `[0, 9)` aralığında olmalı; 200 gibi değerler kırpılmaz,
     *   yok sayılır.
     * - Yayın **otoriter** tanımlayıcı taşır: istemcinin bildirdiği eşyaya
     *   güvenilmez. Kayıtlı olmayan eşya için yayın yapılmaz.
     * - `Container ID` doğrulanmaz: 0 (envanter) dışındaki değerler için de
     *   seçim güncellenir; yuva aralığı zaten sunucu sınırıdır.
     */
    private function handleMobEquipment(
        PipelineConnection $state,
        Player $player,
        MobEquipmentPacket $packet
    ): void {
        if ($packet->targetRuntimeId !== $player->runtimeId) {
            $this->logger->warning('MobEquipment: runtime id uyusmuyor, yok sayildi.', [
                'session' => $state->session->id,
                'claimed' => $packet->targetRuntimeId,
                'actual' => $player->runtimeId,
            ]);

            return;
        }

        $slot = $packet->selectedSlot;

        if ($slot < 0 || $slot >= Player::HOTBAR_SIZE) {
            $this->logger->warning('MobEquipment: yuva aralik disi, yok sayildi.', [
                'session' => $state->session->id,
                'slot' => $slot,
                'max' => Player::HOTBAR_SIZE - 1,
            ]);

            return;
        }

        $player->setSelectedHotbarSlot($slot);

        $descriptor = ItemDescriptorFactory::forStack(
            $player->inventory()->getSlot($slot),
            $this->items
        );

        if ($descriptor === null) {
            $this->logger->debug('MobEquipment: esya ag kimligi kayitli degil, yayin atlandi.', [
                'session' => $state->session->id,
                'slot' => $slot,
            ]);

            return;
        }

        $this->broadcastExcept($state->session->id, new MobEquipmentPacket(
            targetRuntimeId: $player->runtimeId,
            item: $descriptor,
            slot: $slot,
            selectedSlot: $slot,
            containerId: 0
        ));
    }

    /**
     * Phase 10.3 — `PlayerAuthInput` içindeki bit 34 isteğini yanıtlar.
     *
     * Yanıt **tek** `ItemStackResponsePacket`'tır; değişen yuvalar paketin
     * kendi `Containers` alanında taşınır (protokolün amacı budur).
     *
     * @param \Closure(PacketInterface): void $send
     */
    private function handleItemStackRequest(
        Player $player,
        ItemStackRequest $request,
        \Closure $send
    ): void {
        $send(new ItemStackResponsePacket([
            $this->stackRequests->handle($player, $request),
        ]));
    }

    /**
     * El sıkışma yanıtlarını gönderir.
     *
     * Sıkıştırma zarfı **görüşmeye göre** seçilir: NetworkSettings
     * gönderilmeden önceki hata yanıtları da zafsız gönderilir; aksi
     * hâlde istemci çerçeveyi çözemez.
     */
    private function sendResponses(
        PipelineConnection $state,
        ConnectionInterface $connection,
        HandshakeResult $result
    ): void {
        foreach ($result->responses as $response) {
            $this->sendPacket($state, $connection, $response);
        }

        if ($result->isClosed()) {
            $this->failConnection(
                $connection,
                $result->reason !== '' ? $result->reason : 'handshake_closed'
            );
        }
    }

    /**
     * Paketi batch zarfına koyar ve gerekiyorsa **zarfın tamamını** şifreler.
     *
     * Şifreleme sırası tel protokolüyle aynıdır:
     * `paket → batch (sıkıştırma dahil) → ciphertext`.
     *
     * `ServerToClientHandshake` bu metottan **cipher kurulmadan önce**
     * gönderildiği için özel bir istisna gerekmez; `cipherAvailable` o an
     * henüz `false`'tur.
     */
    private function sendPacket(
        PipelineConnection $state,
        ConnectionInterface $connection,
        PacketInterface $packet
    ): void {
        $bytes = $this->engine->encode($packet);

        $batch = $state->compressionNegotiated ? $this->compressedBatch : $this->plainBatch;
        $frame = $batch->encode([$bytes]);

        if ($state->cipherAvailable) {
            if ($state->cipher === null) {
                throw new AuthException('Sifreleme bayragi acik ama cipher yok.');
            }

            $frame = $state->cipher->encrypt($frame);
        }

        $this->transport->sendReliable($connection->getId(), $frame);
    }

    /**
     * Dünya girişi: paketleri gönder, **yalnızca başarıdan sonra**
     * oturumu `Playing` durumuna geçir.
     */
    private function spawn(PipelineConnection $state, ConnectionInterface $connection): void
    {
        $join = null;

        try {
            $join = $this->joins->prepare($state->session, $this->world);

            foreach ($join->packets as $packet) {
                $this->sendPacket($state, $connection, $packet);
            }
        } catch (\Throwable $exception) {
            // Geri alma: oyuncu "dünyada" görünmemeli.
            if ($join instanceof PlayerJoin) {
                $this->players->remove($join->player);
            }

            $this->logger->warning('Dunya girisi basarisiz oldu.', [
                'connection' => $connection->getId(),
                'session' => $state->session->id,
                'reason' => $exception->getMessage(),
            ]);

            $this->failConnection($connection, 'spawn_failed: ' . $exception->getMessage());

            return;
        }

        // Paketler başarıyla teslim edildi: oturum oynanabilir olur.
        $this->joins->complete($join);

        // Giriş paketleri spawn chunk'ını **zaten** gönderdi; akış servisi
        // bunu bilmezse aynı chunk'ı ikinci kez yollar.
        $spawn = $this->world->getSpawn();
        $this->chunkStreamer->markSent(
            $state->session->id,
            ChunkPosition::fromBlock((int) floor($spawn['x']), (int) floor($spawn['z']))
        );

        $this->announceSpawn($state, $join->player);
    }

    /**
     * Oyuncunun çevresindeki yeni chunk'ları gönderir.
     *
     * Yalnızca istemci dünyayı yüklediğini bildirdikten (`initialized`)
     * sonra çalışır: aksi hâlde istemci henüz hazır değilken ağır veri
     * gönderilirdi.
     */
    private function streamChunks(
        PipelineConnection $state,
        ConnectionInterface $connection,
        Player $player
    ): void {
        if (!$player->isInitialized()) {
            return;
        }

        $sessionId = $state->session->id;
        $radius = $player->grantedViewDistance() ?? $this->chunkStreamer->viewDistance();

        // Phase 10.5: pencere **chunk'lardan önce** yayınlanır. İstemci
        // kendisine hangi bölgenin tutulacağı söylenmeden gelen `LevelChunk`
        // paketlerini göstermez (gophertunnel: "If the packet is not sent at
        // all, no chunks will be shown to the player").
        $this->publishChunkWindow($state, $connection, $player, $radius);

        $packets = $this->chunkStreamer->sync(
            $this->world,
            $sessionId,
            $player->x(),
            $player->z(),
            $radius
        );

        foreach ($packets as $packet) {
            $this->sendPacket($state, $connection, $packet);
        }

        // Defteri pencere dışında sınırla; istemci tarafında boşaltma yok.
        $this->chunkStreamer->evictOutside($sessionId, $player->x(), $player->z(), $radius);
    }

    /**
     * İstemcinin bellekte tutacağı chunk penceresini yayınlar.
     *
     * ## Neden gerekli
     *
     * `LevelChunk` istemciye chunk **yükletir**, ama eskileri **boşaltmaz**.
     * Oyuncu yürüdükçe istemci belleği sınırsız büyür. Bu paket "bu merkez
     * çevresinde şu yarıçapta chunk tut" der; merkez kaydıkça istemci pencere
     * dışındakileri bırakır.
     *
     * ## Sözleşme
     *
     * - **Yalnızca değişimde gönderilir:** her karede yollamak gereksiz bant
     *   genişliğidir; oyuncu aynı chunk'ta dururken paket tekrar etmez.
     * - **Yarıçap blok cinsinden:** protokol chunk değil blok bekler, bu
     *   yüzden `radius * 16` yazılır (gophertunnel notu).
     * - **`SavedChunks` boş:** alan istemcinin önceden çözdüğü chunk'ları
     *   hatırlatmak içindir; sunucu bunu hesaplamaz (vanilla da çoğunlukla
     *   boş gönderir).
     */
    private function publishChunkWindow(
        PipelineConnection $state,
        ConnectionInterface $connection,
        Player $player,
        int $radius
    ): void {
        $sessionId = $state->session->id;
        $x = (int) floor($player->x());
        $z = (int) floor($player->z());
        $chunk = ChunkPosition::fromBlock($x, $z);
        $key = $chunk->x . ',' . $chunk->z . ',' . $radius;

        if (($this->publishedWindow[$sessionId] ?? null) === $key) {
            return;
        }

        $this->publishedWindow[$sessionId] = $key;

        $this->sendPacket(
            $state,
            $connection,
            new NetworkChunkPublisherUpdatePacket(
                new BlockPos($x, (int) floor($player->y()), $z),
                $radius * 16
            )
        );
    }

    /**
     * Giriş/çıkış görünürlüğünü iki yönlü kurar.
     *
     * Yeni oyuncu **diğerlerine**, diğerleri **yeni oyuncuya** `AddPlayer`
     * ile tanıtılır. Tek yön yapmak, bir tarafın diğerini görünmez
     * bırakması demektir.
     *
     * Yayın **en iyi çaba**dır: tek bir bağlantının hatası girişi geri
     * almaz (oyuncu zaten dünyada).
     */
    private function announceSpawn(PipelineConnection $state, Player $player): void
    {
        $sessionId = $state->session->id;

        $others = [];

        foreach ($this->players->all() as $other) {
            if ($other === $player || !$other->state()->isActive()) {
                continue;
            }

            $others[] = $other;
        }

        // 1) Yeni oyuncuya tek `PlayerList`: kendi girisi + digerleri.
        //    Kendi girisi de gonderilir; istemci sekme listesini bundan kurar.
        $entries = [PlayerSnapshot::playerListEntry($player)];

        foreach ($others as $other) {
            $entries[] = PlayerSnapshot::playerListEntry($other);
        }

        $this->sendTo($sessionId, new PlayerListPacket($entries));

        // 2) Digerlerine yeni oyuncunun liste girisi; `AddPlayer`'dan ONCE.
        foreach ($others as $other) {
            $this->sendTo($other->sessionId, PlayerSnapshot::playerListAdd($player));
        }

        // 3) Artik varliklar tanitilabilir.
        foreach ($others as $other) {
            $this->sendTo($sessionId, PlayerSnapshot::addPlayer($other));
            $this->sendTo($other->sessionId, PlayerSnapshot::addPlayer($player));
        }
    }

    /**
     * Ölü oyuncuyu dünya spawn noktasında diriltir.
     *
     * İstemci `PlayerAction(Respawn)` gönderdiğinde çalışır. Üç paket
     * birlikte gider: `Respawn` (spawn noktası + durum), `SetHealth`
     * (dolu can) ve `MovePlayer(MODE_TELEPORT)` (istemci pozisyonunu
     * sunucuya hizalar).
     */
    private function respawn(
        PipelineConnection $state,
        ConnectionInterface $connection,
        Player $player
    ): void {
        if (!$player->isDead()) {
            $this->logger->debug('Respawn istegi yok sayildi: oyuncu olmedi.', [
                'session' => $state->session->id,
                'state' => $player->state()->value,
            ]);

            return;
        }

        $spawn = $this->world->getSpawn();

        try {
            $player->markRespawned($spawn['x'], $spawn['y'], $spawn['z']);
        } catch (PlayerException $exception) {
            $this->logger->warning('Yeniden dogma basarisiz.', [
                'session' => $state->session->id,
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        // Phase 10.5: istemcinin yeniden dogma noktasi `Respawn`'dan once
        // bilinmeli; aksi halde istemci `{0,0,0}` cevresine duser.
        $this->sendPacket($state, $connection, SetSpawnPositionPacket::worldSpawn(
            new BlockPos(
                (int) floor($spawn['x']),
                (int) floor($spawn['y']),
                (int) floor($spawn['z'])
            )
        ));

        $this->sendPacket($state, $connection, new RespawnPacket(
            new Vec3($player->x(), $player->y(), $player->z()),
            RespawnState::ReadyToSpawn,
            $player->runtimeId
        ));

        $this->sendPacket($state, $connection, new SetHealthPacket($player->health()));

        $this->sendPacket($state, $connection, new MovePlayerPacket(
            runtimeId: $player->runtimeId,
            x: $player->x(),
            y: $player->y(),
            z: $player->z(),
            pitch: $player->pitch(),
            yaw: $player->yaw(),
            headYaw: $player->yaw(),
            mode: MovePlayerPacket::MODE_TELEPORT
        ));

        $this->logger->info('Oyuncu yeniden dogdu.', [
            'session' => $state->session->id,
            'player' => $player->identity->displayName,
        ]);
    }

    private function initiateEncryption(PipelineConnection $state, ConnectionInterface $connection): void
    {
        // İkinci bir anahtar/el sıkışma üretilmez: OTURUM BAŞINA TEK anahtar.
        if ($state->cipherAvailable || $state->encryptionEstablished) {
            return;
        }

        $identity = $state->session->getIdentity();
        if ($identity === null || $identity->clientPublicKey === null) {
            $this->logger->warning('ECDH başlatılamadı: clientPublicKey eksik.', [
                'session' => $state->session->id,
            ]);
            return;
        }

        try {
            // 1. Geçici sunucu anahtarı oluştur
            $ecdh = EcdhKeyExchange::generate();
            $salt = EcdhKeyExchange::generateSalt();

            // 2. Handshake JWT üret
            $jwt = HandshakeToken::create($ecdh->privateKey(), $ecdh->publicKeyDer, $salt);

            // 3. Client public key'i çöz
            $clientKey = $this->resolveClientPublicKey($identity->clientPublicKey);
            if ($clientKey === null) {
                throw new \RuntimeException('client public key çözülemedi');
            }

            // 4. Ortak sır ve şifreleme anahtarı
            $shared = $ecdh->computeSharedSecret($clientKey);
            $encKey = EcdhKeyExchange::deriveEncryptionKey($salt, $shared);
            $cipher = new AesCtrCipher($encKey);

            // 5. Anahtarı hazırla ama henüz "aktif" işaretleme: el sıkışma
            //    paketi **açık** gitmelidir.
            $state->ecdh = $ecdh;
            $state->handshakeSalt = $salt;
            $state->cipher = $cipher;

            // 6. ServerToClientHandshake'i şifreleme açılmadan gönder.
            $handshakePacket = new ServerToClientHandshakePacket($jwt);
            $this->sendPacket($state, $connection, $handshakePacket);

            // 7. Ancak gönderimden **sonra** şifrelemeyi devreye al. Bu andan
            //    itibaren gelen/giden tüm yükler ciphertext'tir.
            $state->cipherAvailable = true;

            $this->logger->info('ECDH baslatildi, ServerToClientHandshake gonderildi.', [
                'session' => $state->session->id,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ECDH başlatma hatası.', [
                'session' => $state->session->id,
                'reason' => $e->getMessage(),
            ]);
            $this->failConnection($connection, 'encryption_init_failed: ' . $e->getMessage());
        }
    }

    private function resolveClientPublicKey(string|array|null $key): ?\OpenSSLAsymmetricKey
    {
        if ($key === null) {
            return null;
        }

        // EcPublicKey yardımcı sınıfı her iki biçimi de destekler.
        try {
            return EcPublicKey::fromClientPublicKey(is_array($key) ? $key : $key);
        } catch (\Throwable $e) {
            $this->logger->warning('Client public key çözülemedi.', [
                'reason' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // derToPem kaldırıldı

    private function advanceSession(Session $session, SessionState $next): void
    {
        if ($session->getState() !== $next) {
            $session->transitionTo($next);
        }
    }

    /**
     * Fail-closed: bağlantıyı kapat, oturumu terminal duruma düşür.
     */
    private function failConnection(ConnectionInterface $connection, string $reason): void
    {
        $connectionId = $connection->getId();
        $state = $this->connections[$connectionId] ?? null;

        if ($state !== null) {
            try {
                $state->session->markAuthenticationFailed($reason);
            } catch (SessionException) {
                // Oturum zaten terminal durumda; bağlantı yine de kapanır.
            }
        }

        $this->logger->warning('Baglanti fail-closed kapatildi.', [
            'connection' => $connectionId,
            'reason' => $reason,
        ]);

        $this->transport->close($connectionId, $reason);
    }
}
