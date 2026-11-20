<?php

declare(strict_types=1);

namespace PickaxeMP\player;

use PickaxeMP\session\Session;
use PickaxeMP\session\SessionState;
use PickaxeMP\utils\Logger;
use PickaxeMP\world\World;

/**
 * Aktif oyuncuların tek sahibi.
 *
 * Sorumlulukları:
 * - Oturum → Player bağlantısını kurmak
 * - UUID, XUID ve bağlantı benzersizliğini korumak
 * - Entity/runtime kimliklerini üretmek
 * - Bağlantı kapanınca oyuncu kayıtlarını temizlemek
 */
final class PlayerManager
{
    /** @var array<string, Player> uuid => player */
    private array $byUuid = [];

    /** @var array<string, Player> xuid => player */
    private array $byXuid = [];

    /** @var array<string, Player> sessionId => player */
    private array $bySession = [];

    /** @var array<string, Player> connectionId => player */
    private array $byConnection = [];

    private int $nextEntityId = 1;

    private int $nextRuntimeId = 1;

    public function __construct(
        private readonly Logger $logger,
        private readonly ?string $inventoryRoot = null
    ) {
    }

    /**
     * Kimlik doğrulanmış oturumdan oyuncu oluşturur.
     *
     * @throws PlayerException oturum doğrulanmamışsa veya oyuncu çakışırsa
     */
    public function create(Session $session, World $world): Player
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

        $uuid = strtolower($identity->uuid);

        if (isset($this->byUuid[$uuid])) {
            throw PlayerException::duplicateIdentity($uuid, $this->byUuid[$uuid]->sessionId);
        }

        if (isset($this->byXuid[$identity->xuid])) {
            throw PlayerException::duplicateIdentity($uuid, $this->byXuid[$identity->xuid]->sessionId);
        }

        if (isset($this->byConnection[$session->connectionId])) {
            throw PlayerException::duplicateConnection($session->connectionId);
        }

        $spawn = $world->getSpawn();
        $inventory = $this->createInventory($uuid);
        $player = new Player(
            identity: $identity,
            sessionId: $session->id,
            connectionId: $session->connectionId,
            entityId: $this->nextEntityId++,
            runtimeId: $this->nextRuntimeId++,
            world: $world,
            x: $spawn['x'],
            y: $spawn['y'],
            z: $spawn['z'],
            inventory: $inventory,
            skin: $session->getSkin()
        );

        $this->byUuid[$uuid] = $player;
        $this->byXuid[$identity->xuid] = $player;
        $this->bySession[$session->id] = $player;
        $this->byConnection[$session->connectionId] = $player;

        $this->logger->info('Oyuncu olusturuldu.', [
            'player' => $identity->displayName,
            'session' => $session->id,
            'world' => $world->getName(),
        ]);

        return $player;
    }

    public function byUuid(string $uuid): ?Player
    {
        return $this->byUuid[strtolower($uuid)] ?? null;
    }    public function byXuid(string $xuid): ?Player
    {
        return $this->byXuid[$xuid] ?? null;
    }

    public function bySession(string $sessionId): ?Player
    {
        return $this->bySession[$sessionId] ?? null;
    }

    public function byConnection(string $connectionId): ?Player
    {
        return $this->byConnection[$connectionId] ?? null;
    }

    public function remove(Player $player): void
    {
        // Gecikmeli bir kapatma callback'i, aynı connection/session id'yi
        // kullanan yeni oyuncunun indekslerini silmemelidir. Yalnızca hâlâ
        // bu Player örneğine işaret eden kayıtları temizle.
        $uuid = strtolower($player->identity->uuid);

        if (($this->byUuid[$uuid] ?? null) === $player) {
            unset($this->byUuid[$uuid]);
        }

        if (($this->byXuid[$player->identity->xuid] ?? null) === $player) {
            unset($this->byXuid[$player->identity->xuid]);
        }

        if (($this->bySession[$player->sessionId] ?? null) === $player) {
            unset($this->bySession[$player->sessionId]);
        }

        if (($this->byConnection[$player->connectionId] ?? null) === $player) {
            unset($this->byConnection[$player->connectionId]);
        }

        $player->markDisconnected();

        $this->logger->debug('Oyuncu kaldirildi.', [
            'player' => $player->identity->displayName,
            'session' => $player->sessionId,
        ]);
    }

    public function removeByConnection(string $connectionId): ?Player
    {
        $player = $this->byConnection[$connectionId] ?? null;

        if ($player !== null) {
            $this->remove($player);
        }

        return $player;
    }

    public function removeBySession(string $sessionId): ?Player
    {
        $player = $this->bySession[$sessionId] ?? null;

        if ($player !== null) {
            $this->remove($player);
        }

        return $player;
    }

    /**
     * @return list<Player>
     */
    public function all(): array
    {
        return array_values($this->byUuid);
    }

    public function count(): int
    {
        return count($this->byUuid);
    }

    /**
     * Envanteri diske yazar (bağlantı kapanışı).
     *
     * Persist **başarısızlığı** oyuncu temizliğini engellemez; ancak sessizce
     * yutulmaz — `error` olarak loglanır.
     */
    public function saveInventory(Player $player): void
    {
        if ($this->inventoryRoot === null) {
            return;
        }

        try {
            $player->inventory()->flush();
        } catch (PlayerException $exception) {
            $this->logger->error('Envanter kaydedilemedi.', [
                'player' => $player->identity->displayName,
                'uuid' => $player->identity->uuid,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Oyuncunun envanterini oluşturur ve varsa diskten yükler.
     *
     * Anahtar **doğrulanmış UUID**'dir; isim değişse de envanter aynı kalır.
     */
    private function createInventory(string $uuid): PlayerInventory
    {
        $inventory = new PlayerInventory($this->inventoryRoot ?? '', $uuid, $this->logger);

        if ($this->inventoryRoot !== null) {
            $inventory->load();
        }

        return $inventory;
    }
}
