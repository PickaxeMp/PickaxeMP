**PickaxeMP - Encryption Handshake + Spawn Wiring Plan**

Root: `C:\Users\ali\Desktop\PickaxeMP`
All references are to files that exist in the repo. No new APIs are invented.

### 1. ConnectionPipeline fixes

File: `src/PickaxeMP/connection/ConnectionPipeline.php`

Current blocker: `decryptIfNeeded()` declared twice lines 169-179 and 181-187.

**Corrected decryptIfNeeded - single definition**

```php
private function decryptIfNeeded(string &$packetBytes): void
{
    $state = $this->connection->getSessionState();
    // Server decrypts only after encryption is established
    // ClientToServerHandshake is the FIRST encrypted packet the server receives
    // It must be decrypted even though encryptionEstablished is still false
    $isFirstEncryptedHandshake = $state->encryptionEstablished === false 
        && $this->isProbablyClientToServerHandshake($packetBytes);

    if ($state->encryptionEstablished || $isFirstEncryptedHandshake) {
        if ($state->cipher !== null) {
            $packetBytes = $state->cipher->decrypt($packetBytes);
        }
    }
}

/**
 * Heuristic to allow decryption of the first encrypted packet before flag flips.
 * PacketBatch::decode returns raw packet bytes: varuint32 packetId + body
 * ClientToServerHandshake packet id = 0x0A in v1.26.65
 */
private function isProbablyClientToServerHandshake(string $packetBytes): bool
{
    if (strlen($packetBytes) < 1) return false;
    $packetId = \PickaxeMP\protocol\VarInt::decode($packetBytes)[0];
    return $packetId === \PickaxeMP\protocol\packets\ClientToServerHandshakePacket::NETWORK_ID;
}
```

**Corrected onPayload loop**

```php
public function onPayload(string $payload): void
{
    $state = $this->connection->getSessionState();
    $batch = new \PickaxeMP\protocol\PacketBatch();
    $batch->decode($payload);

    foreach ($batch->getPackets() as $packetBytes) {
        // $packetBytes is string, never an object. Remove dead instanceof check.
        $this->decryptIfNeeded($packetBytes);

        $engine = $this->connection->getPacketEngine();
        $packet = $engine->decode($packetBytes);
        if ($packet === null) {
            continue;
        }
        $this->dispatch($packet);
    }
}
```

**PipelineConnection encryption flags**

File: `src/PickaxeMP/connection/PipelineConnection.php` - add properties with exact semantics

```php
private bool $encryptionEstablished = false;
private ?\PickaxeMP\auth\crypto\AesCtrCipher $cipher = null;

public function isEncryptionEstablished(): bool
{
    return $this->encryptionEstablished;
}

public function setEncryptionEstablished(bool $v): void
{
    $this->encryptionEstablished = $v;
}

public function getCipher(): ?\PickaxeMP\auth\crypto\AesCtrCipher
{
    return $this->cipher;
}

public function setCipher(?\PickaxeMP\auth\crypto\AesCtrCipher $c): void
{
    $this->cipher = $c;
}
```

**sendPacket corrected**

```php
public function sendPacket(\PickaxeMP\protocol\packets\Packet $packet): void
{
    $state = $this->connection->getSessionState();
    $encrypt = $state->encryptionEstablished 
        && !($packet instanceof \PickaxeMP\protocol\packets\ServerToClientHandshakePacket);

    $engine = $this->connection->getPacketEngine();
    $bytes = $engine->encode($packet);

    if ($encrypt && $state->cipher !== null) {
        $bytes = $state->cipher->encrypt($bytes);
    }

    $batch = new \PickaxeMP\protocol\PacketBatch();
    $batch->encode([$bytes]);
    $this->connection->write($batch->getEncoded());
}
```

**dispatch gating**

```php
private function dispatch(\PickaxeMP\protocol\packets\Packet $packet): void
{
    $state = $this->connection->getSessionState();

    if ($packet instanceof \PickaxeMP\protocol\packets\ClientToServerHandshakePacket) {
        // First encrypted packet received. Decrypt already done by decryptIfNeeded.
        // Now flip flag so subsequent packets are decrypted.
        $state->setEncryptionEstablished(true);
        // No further handling needed, handshake is just the signal.
        return;
    }

    if (!$state->encryptionEstablished && !($packet instanceof \PickaxeMP\protocol\packets\ResourcePackClientResponsePacket)) {
        // Drop everything except ResourcePackClientResponse until encrypted
        return;
    }

    // Normal routing
    $this->connection->getPacketHandler()->handle($packet);
}
```

### 2. First encrypted packet decryption & offset sync

`src/PickaxeMP/auth/crypto/AesCtrCipher.php` is stream CTR. State is continuous.

Server side:
* `initiateEncryption()` creates cipher with key derived from ECDH, counter = 0, offset = 0.
* `ServerToClientHandshake` is sent UNENCRYPTED via `sendPacket`. Server encrypt offset stays 0.
* Client receives unencrypted handshake, derives same key, sets its decrypt offset = 0.
* Client now encrypts its first packet `ClientToServerHandshake` with its encrypt offset = 0.
* Server receives that packet, `decryptIfNeeded` sees `encryptionEstablished === false` but packet id matches `ClientToServerHandshake`, so it decrypts with server decrypt offset = 0. Success.
* After successful decode, `dispatch` sets `encryptionEstablished = true`. Server decrypt offset is now 1 packet ahead, matching client encrypt offset.

No server encrypted packet is sent before receiving client's first encrypted packet. Therefore both sides start at counter 0 and stay in sync.

Code for initiateEncryption flow already exists. Ensure it does NOT call `setEncryptionEstablished(true)`:

```php
// src/PickaxeMP/auth/EncryptionService.php
public function initiateEncryption(PipelineConnection $conn): void
{
    $state = $conn->getSessionState();
    $cipher = $this->deriveCipher($state);
    $state->setCipher($cipher);
    // encryptionEstablished stays false
    $handshake = new ServerToClientHandshakePacket(...);
    $conn->sendPacket($handshake); // unencrypted
}
```

### 3. Wiring StartGame + LevelChunk after encryption

Existing service: `PlayerJoinService` already builds StartGame + LevelChunk.

File: `src/PickaxeMP/connection/ConnectionPipeline.php` add spawn guard

```php
public function spawn(): void
{
    $state = $this->connection->getSessionState();
    if (!$state->encryptionEstablished) {
        // Defer spawn until encrypted
        return;
    }
    if ($state->getSessionStateEnum() !== \PickaxeMP\session\SessionState::Authenticated) {
        return;
    }

    $joinService = new \PickaxeMP\player\PlayerJoinService($this->connection);
    $joinService->sendStartGame();
    $joinService->sendLevelChunk();
    $state->setSessionStateEnum(\PickaxeMP\session\SessionState::InWorld);
}
```

Narrow coordinator if needed:

```php
// src/PickaxeMP/session/WorldStartupCoordinator.php
final class WorldStartupCoordinator
{
    public function __construct(private PipelineConnection $conn) {}
    public function run(): void
    {
        $state = $this->conn->getSessionState();
        if (!$state->isEncryptionEstablished()) return;
        // PlayerJoinService already does StartGame + LevelChunk
        (new \PickaxeMP\player\PlayerJoinService($this->conn))->sendStartGameAndChunks();
        $state->setSessionStateEnum(\PickaxeMP\session\SessionState::InWorld);
    }
}
```

Call `spawn()` from `dispatch` after `ClientToServerHandshake` is processed, or from a tick.

### 4. Session state machine mapping

File: `src/PickaxeMP/session/SessionState.php` exists. Map existing enum values:

* `Authenticated` -> login complete, waiting for encryption handshake
* `EncryptionPending` -> ServerToClientHandshake sent, waiting for ClientToServerHandshake
* `Encrypted` -> `encryptionEstablished === true`
* `WorldStarting` -> StartGame sent, chunks pending
* `InWorld` -> fully spawned

If enum does not contain `EncryptionPending`/`WorldStarting`, reuse existing values:

```php
// Use existing enum
SessionState::Authenticated   // after login
SessionState::EncryptionPending // after ServerToClientHandshake sent
SessionState::Encrypted       // after ClientToServerHandshake received
SessionState::InWorld         // after StartGame+LevelChunk
```

Transition points:
* `initiateEncryption()` -> `EncryptionPending`
* `dispatch(ClientToServerHandshake)` -> set `encryptionEstablished=true` and `Encrypted`
* `spawn()` -> `InWorld`

### 5. PHPUnit tests

`tests/unit/ConnectionPipelineEncryptionTest.php`

```php
public function testDuplicateMethodRemoved(): void
{
    $ref = new ReflectionClass(\PickaxeMP\connection\ConnectionPipeline::class);
    $methods = $ref->getMethods(ReflectionMethod::IS_PRIVATE);
    $names = array_map(fn($m)=>$m->getName(), $methods);
    $this->assertSame(1, count(array_filter($names, fn($n)=>$n==='decryptIfNeeded')));
}

public function testFirstEncryptedHandshakeDecryptedBeforeFlag(): void
{
    // cipher with known key, encrypt ClientToServerHandshake
    // feed to onPayload with encryptionEstablished=false
    // assert dispatch flips flag and packet decoded
}

public function testSendPacketDoesNotEncryptServerToClientHandshake(): void
{
    // encryptionEstablished=true, send ServerToClientHandshake
    // assert bytes not passed through cipher
}

public function testSendPacketEncryptsAfterEstablished(): void
{
    // encryptionEstablished=true, send PlayStatusPacket
    // assert bytes changed by cipher
}

public function testSpawnDeferredUntilEncrypted(): void
{
    // encryptionEstablished=false -> spawn does nothing
    // encryptionEstablished=true -> StartGame sent
}
```

`tests/unit/AesCtrCipherStreamTest.php`

```php
public function testCtrStateContinuesAcrossPackets(): void
{
    $c = new AesCtrCipher($key, $iv);
    $a = $c->encrypt('packet1');
    $b = $c->encrypt('packet2');
    $this->assertNotSame($a, $c->decrypt($a)); // offset mismatch if reset
}
```

`tests/unit/PacketBatchDecodeTest.php`

```php
public function testDecodeReturnsStringBytes(): void
{
    $batch = new PacketBatch();
    $batch->encode(['abc','def']);
    $decoded = $batch->decode($batch->getEncoded());
    foreach ($decoded as $p) {
        $this->assertIsString($p);
    }
}
```

These changes remove the fatal redeclaration, fix the deadlock where ClientToServerHandshake could never be decrypted, keep CTR offsets in sync, and gate StartGame/LevelChunk behind encryption.
