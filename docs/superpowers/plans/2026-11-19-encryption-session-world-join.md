# Encryption → Session → World Join Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Make the Bedrock handshake → encrypted session → StartGame/LevelChunk path real, in the correct layer order, with honest failure semantics.

**Architecture:** Encryption is a property of the whole encoded batch payload, not of individual packets inside a batch. `PipelineConnection` carries two independent flags: `cipherAvailable` (a key exists; every inbound and outbound payload is ciphertext) and `encryptionEstablished` (the peer proved key agreement by sending a decodable `ClientToServerHandshake`). The session state enum is not extended; encryption progress is tracked per connection and the session stays `Authenticated` until `PlayerJoinService::prepare` moves it to `Initializing`.

**Tech Stack:** PHP 8.2, OpenSSL 3 (AES-256-CTR, EC P-384), PHPUnit, custom NetherNet transport.

**Spec:** `docs/DEVELOPMENT_STATE.md` (Phase 3/4/5 sections) and `.team/minimax.md` (root-cause analysis).

## Global Constraints

- Protocol target: 2225 / Minecraft 1.26.65.
- Commands run from project root: `tools/php.cmd -l <file>`, `composer lint`, `composer test`.
- Rust sources: not touched by this plan; if touched, `cargo fmt`, `cargo clippy`, `cargo test`, `cargo build`.
- No test removal and no expectation lowering. A test that encoded wrong behavior is rewritten to assert the correct behavior.
- `CLIENT_VERIFIED` cannot be claimed without a real Bedrock client; it stays `BLOCKED`.
- Encryption must never be a precondition for decoding the packet that proves encryption works.

## Review Focus

- A second `ClientToServerHandshake` after the first must close the connection, not silently re-affirm trust.
- A ciphertext frame with a bad checksum must close only the offending connection, never the server or a sibling connection.
- A `ResourcePackClientResponse` that arrives before the encrypted handshake must be rejected, not served with world data.
- A login whose `extraData` JWT signature does not verify must fail authentication instead of yielding identity or skin data.
- Movement of a client that has not spawned must not produce outbound packets.

---

### Task 1: Two-flag crypto state and payload-layer encryption

**Files:**
- Modify: `src/PickaxeMP/connection/PipelineConnection.php`
- Modify: `src/PickaxeMP/connection/ConnectionPipeline.php:169-226, 393-401, 475-579`
- Test: `tests/unit/connection/EncryptionPipelineTest.php` (create)

**Interfaces:**
- Consumes: `AesCtrCipher::encrypt(string): string`, `AesCtrCipher::decrypt(string): string`, `PacketBatch::encode(list<string>): string`, `PacketBatch::decode(string): list<string>`.
- Produces: `PipelineConnection::$cipherAvailable` (bool, default `false`), `PipelineConnection::$encryptionEstablished` (bool, default `false`).

- [x] **Step 1: Write the failing tests**

```php
public function testFirstEncryptedHandshakeIsDecodedAndEstablishesEncryption(): void
public function testDuplicateClientToServerHandshakeClosesConnection(): void
public function testInvalidCiphertextClosesOnlyThatConnection(): void
public function testTwoConnectionsUseIsolatedCipherState(): void
```

- [x] **Step 2: Run tests to verify they fail**

Run: `tools/php.cmd vendor/bin/phpunit tests/unit/connection/EncryptionPipelineTest.php`
Expected: FAIL / class not found.

- [x] **Step 3: Implement**

Add `cipherAvailable` to `PipelineConnection`. In `onPayload`, when `$state->cipherAvailable && $state->cipher !== null`, decrypt the **whole** `$payload` before `PacketBatch::decode`. Delete `decryptIfNeeded` and the dead no-op block. Catch `AuthException` in `onPayload` and route it to `failConnection`. In `sendPacket`, encrypt the encoded **batch payload** when `cipherAvailable`; in `initiateEncryption`, send `ServerToClientHandshake` first, then set `cipherAvailable = true`. Set `encryptionEstablished` only on a decoded `ClientToServerHandshake`; a second one calls `failConnection`.

- [x] **Step 4: Run tests to verify they pass**

Run: `tools/php.cmd vendor/bin/phpunit tests/unit/connection/EncryptionPipelineTest.php`
Expected: PASS.

- [x] **Step 5: Verify and update docs**

Run: `tools/php.cmd -l src/PickaxeMP/connection/ConnectionPipeline.php`, then `graphify . --update`, then update `docs/DEVELOPMENT_STATE.md`.

---

### Task 2: Enforce encryption before resource-pack handling and spawn

**Files:**
- Modify: `src/PickaxeMP/connection/ConnectionPipeline.php:403-419`
- Test: `tests/unit/connection/EncryptionPipelineTest.php`

**Interfaces:**
- Consumes: `PipelineConnection::$encryptionEstablished`.
- Produces: `ConnectionPipeline` rejects resource-pack responses from unencrypted connections.

- [x] **Step 1: Write the failing test**

```php
public function testResourcePackResponseBeforeEncryptionIsRejected(): void
```
Assert: the connection is closed, the session is terminal, and no `StartGamePacket` was sent.

- [x] **Step 2: Run test to verify it fails**

Run: `tools/php.cmd vendor/bin/phpunit --filter testResourcePackResponseBeforeEncryptionIsRejected`
Expected: FAIL (currently the `awaiting_resource_packs` clause lets it through).

- [x] **Step 3: Implement**

Replace the `encryptionEstablished || awaiting_resource_packs` condition with `encryptionEstablished` only; otherwise `failConnection('encryption_required')`. Compare handshake state with `ConnectionState::AwaitingResourcePacks` instead of the string literal.

- [x] **Step 4: Run test to verify it passes**

Run: `tools/php.cmd vendor/bin/phpunit --filter testResourcePackResponseBeforeEncryptionIsRejected`
Expected: PASS.

- [x] **Step 5: Verify and update docs**

Run `composer lint`, `graphify . --update`, update `docs/DEVELOPMENT_STATE.md`.

---

### Task 3: Route movement sync through PlayerPositionSync

**Files:**
- Modify: `src/PickaxeMP/player/MovementManager.php:29-76`
- Modify: `src/PickaxeMP/player/PlayerAuthInput.php:24-45`
- Modify: `src/PickaxeMP/connection/ConnectionPipeline.php:421-442`
- Test: `tests/unit/connection/EncryptionPipelineTest.php`

**Interfaces:**
- Produces: `MovementManager::handle(Player $player, PlayerAuthInputPacket $input, callable $send): void`, `PlayerAuthInput::process(Player $player, PlayerAuthInputPacket $packet, World $world, callable $send): void`.
- `$send` receives a `MovePlayerPacket` and must transmit it.

- [x] **Step 1: Write the failing test**

```php
public function testMovementAfterSpawnSendsExactlyOneMovePlayerPacket(): void
```
Assert one `MovePlayerPacket`, no duplicate, and that it is encrypted.

- [x] **Step 2: Run test to verify it fails**

Run: `tools/php.cmd vendor/bin/phpunit --filter testMovementAfterSpawnSendsExactlyOneMovePlayerPacket`
Expected: FAIL (two packets: callback no-op plus pipeline direct send).

- [x] **Step 3: Implement**

Thread the `callable $send` through `PlayerAuthInput::process` → `MovementManager::handle` → `PlayerPositionSync::sendUpdate`. In the pipeline, pass `fn (PacketInterface $p) => $this->sendPacket($state, $connection, $p)` and delete the direct `MovePlayerPacket` construction.

- [x] **Step 4: Run test to verify it passes**

Run: `tools/php.cmd vendor/bin/phpunit --filter testMovementAfterSpawnSendsExactlyOneMovePlayerPacket`
Expected: PASS.

- [x] **Step 5: Verify and update docs**

`tools/php.cmd -l` on both changed files, `composer lint`, update `docs/DEVELOPMENT_STATE.md`.

---

### Task 4: Validate the extraData skin JWT

**Files:**
- Create: `src/PickaxeMP/auth/SkinDataValidator.php`
- Create: `src/PickaxeMP/auth/SkinData.php`
- Modify: `src/PickaxeMP/auth/AuthenticationEngine.php:122-141`
- Test: `tests/unit/auth/SkinDataValidatorTest.php` (create)

**Interfaces:**
- Consumes: `CertificateChain::$extraData`, `JwtParser::parse(string): JwtToken`, `JwtVerifier::verify(JwtToken, TrustedPublicKey, ?int): VerificationResult`, `TrustedPublicKey::fromBase64(string, string): TrustedPublicKey`.
- Produces: `SkinDataValidator::validate(?string $extraData, JwtToken $lastChainToken, ?int $now): ?SkinData` — `null` when no extraData was sent; throws `AuthException` when present but unverifiable.
- `SkinData` exposes `skinId(): string`, `skinData(): string`, `capeData(): string`, `geometryName(): string`, `geometryData(): string`.

- [x] **Step 1: Write the failing tests**

```php
public function testValidExtraDataVerifiesAgainstPlayerKey(): void
public function testExtraDataSignedByAnotherKeyIsRejected(): void
public function testExtraDataPresentButUnsignedIsRejected(): void
public function testMissingExtraDataYieldsNullSkin(): void
public function testExtraDataCannotOverrideIdentityFields(): void
```

- [x] **Step 2: Run tests to verify they fail**

Run: `tools/php.cmd vendor/bin/phpunit tests/unit/auth/SkinDataValidatorTest.php`
Expected: FAIL / class not found.

- [x] **Step 3: Implement**

Verify the extraData JWT signature with the key that signed the last chain token (`$lastChainToken->header->x5u`); require the payload to be a JSON object; never read `xuid`, `identity`, or `displayName` from it as identity. Wire into `AuthenticationEngine::doAuthenticate` after identity creation: invalid extraData → `AuthException` → fail-closed.

- [x] **Step 4: Run tests to verify they pass**

Run: `tools/php.cmd vendor/bin/phpunit tests/unit/auth/SkinDataValidatorTest.php`
Expected: PASS.

- [x] **Step 5: Verify and update docs**

`composer lint`, `graphify . --update`, update `docs/DEVELOPMENT_STATE.md`.

---

### Task 5: Rewrite the integration test around a real encrypted exchange

**Files:**
- Modify: `tests/support/CryptoFixture.php`
- Modify: `tests/integration/ConnectionPipelineTest.php`

**Interfaces:**
- Consumes: `HandshakeToken` JWT (`{salt}` payload, `x5u` server SPKI DER), `EcdhKeyExchange::computeSharedSecret`, `EcdhKeyExchange::deriveEncryptionKey`, `AesCtrCipher`.
- Produces: `CryptoFixture::createRealisticChain(..., ?array $clientEcdhKey = null)` returning an extra `clientEcdhKey` entry and embedding `cpk` when supplied.

- [x] **Step 1: Write the failing tests**

Rewrite `testFullJoinReachesPlaying` so the client: reads the handshake JWT, derives the shared key from `cpk`'s private key, installs its own `AesCtrCipher`, sends an encrypted `ClientToServerHandshake`, then an encrypted `ResourcePackClientResponse`, and decrypts `StartGame`/`LevelChunk`. Add `testSpawnRequiresEstablishedEncryption`.

- [x] **Step 2: Run tests to verify they fail**

Run: `tools/php.cmd vendor/bin/phpunit tests/integration/ConnectionPipelineTest.php`
Expected: FAIL (frames are not encrypted / handshake never derived).

- [x] **Step 3: Implement**

Add `cpk` support to the fixture, add a client-side cipher to the test, decrypt server frames only after the handshake frame, and encrypt client payloads only after `ClientToServerHandshake` is sent.

- [x] **Step 4: Run tests to verify they pass**

Run: `tools/php.cmd vendor/bin/phpunit tests/integration/ConnectionPipelineTest.php`
Expected: PASS.

- [x] **Step 5: Verify and update docs**

Run the full suite, `graphify . --update`, update `docs/DEVELOPMENT_STATE.md`.

---

### Task 6: Full verification and honest report

**Files:**
- Modify: `docs/DEVELOPMENT_STATE.md`

- [x] **Step 1: Run the full gate**

Run: `composer lint`, then `composer test`.
Expected: zero failures.

- [x] **Step 2: Run graphify**

Run: `graphify . --update`. Expected: updated graph output.

- [x] **Step 3: Update the state document**

Record: completed layers, test counts, model usage, open issues, and `CLIENT_VERIFIED: BLOCKED (no real Bedrock client available)`.

- [x] **Step 4: Report**

State exactly what is proven by tests and what still requires a real client.
