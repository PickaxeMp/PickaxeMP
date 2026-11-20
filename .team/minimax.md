# PickaxeMP — ConnectionPipeline Encryption Ordering: Root Cause Analysis

## 0. Executive summary (the root cause in one paragraph)

The deadlock is **not** in the cipher, and **not** in the batch framing. It is a **single-bit state-machine error**: the code uses one flag (`encryptionEstablished`) to mean two different things simultaneously — *"the cipher object exists and I know how to decrypt"* and *"the peer and I have agreed that traffic is encrypted."* Because the flag is only set **after** the `ClientToServerHandshake` is *decoded*, and because that handshake arrives **encrypted**, the receive path can never reach the code that would set the flag. The flag is a **consequence** of the handshake, but the handshake cannot be read without the flag. Classic chicken-and-egg.

The fix is to **split the state into two orthogonal variables**:

| Variable | Meaning | Set when |
|---|---|---|
| `encryptionReady` (cipher available) | ECDH done, AES key derived, `cipher` object installed, **CTR counters at their initial values** | Inside `initiateEncryption()`, immediately after key derivation — **before** `ServerToClientHandshake` is sent |
| `encryptionEstablished` (traffic encrypted) | The peer has completed the handshake; **all subsequent packets in both directions are encrypted** | Immediately **after** the encrypted `ClientToServerHandshake` has been successfully decrypted and dispatched |

And the receive path must decide "decrypt or not" using `encryptionReady` (cipher availability), **not** `encryptionEstablished` (agreement). The send path must decide "encrypt or not" using `encryptionEstablished` (agreement), **not** `encryptionReady`.

There is a second, subtler bug hiding behind the first: **the CTR stream position at the moment the flag flips is not the same on the two sides.** The server's *encrypt* CTR and the client's *decrypt* CTR must be in lockstep, and the client's *encrypt* CTR and the server's *decrypt* CTR must be in lockstep. If the server flips `encryptionEstablished` at the wrong instant relative to its own encrypt counter, the two sides desynchronize permanently and every subsequent packet is garbage. This is the part that most "fix the flag" patches get wrong.

---

## 1. The precise correct state machine

### 1.1 Real Bedrock (RakNet) handshake + encryption sequence

Bedrock's login sequence, as implemented in PocketMine-MP (`NetworkSession::handleEncoded()` / `enableEncryption()`) and Cloudburst (`BedrockSession::handleClientToServerHandshake`):

```
SERVER -> CLIENT   ServerToClientHandshakePacket   (UNENCRYPTED)
                    { jwt: HandshakeToken, salt: bytes }
                    (contains the client's public key, server's public key,
                     server salt, and the JWT signed by the server)

CLIENT -> SERVER   ClientToServerHandshakePacket   (ENCRYPTED)
                    { jwt: HandshakeToken }   -- client echoes a JWT
                    (the client's own HandshakeToken, signed with the
                     client's private key, proving possession)

SERVER -> CLIENT   (nothing handshake-related; the next thing the server
                    sends is normally the PlayStatus / Login sequence,
                    which IS encrypted)

CLIENT -> SERVER   (subsequent packets: LoginPacket, etc.)  (ENCRYPTED)
```

Key protocol facts:

1. **`ServerToClientHandshakePacket` is always sent in the clear.** It is the packet that *carries* the ECDH material. Encrypting it would be circular — the client does not yet have the AES key.
2. **`ClientToServerHandshakePacket` is always sent encrypted.** This is the whole point of the exchange: it proves the client derived the same shared secret. The client encrypts it with the AES key derived from the ECDH shared secret.
3. **The flip point is the *server's receipt* of the encrypted `ClientToServerHandshakePacket`.** From the server's perspective, the moment it successfully decrypts that packet, the encryption is "established" for all *subsequent* traffic in both directions.
4. **There is no server→client packet between `ServerToClientHandshake` and the flip.** The server sends `ServerToClientHandshake`, then waits. The client responds with the encrypted `ClientToServerHandshake`. The server flips the flag upon decrypting it. The server's *next* outbound packet (e.g. `PlayStatus`/`Login`) is already encrypted.

This matches PocketMine-MP's `NetworkSession`:

```php
// PocketMine: enableEncryption() is called when the server sends the handshake.
// The server's own handshake is sent unencrypted; the cipher is installed
// but the "encryption enabled" gate for *outbound* is handled by the
// handshake packet being explicitly excluded.
```

and Cloudburst's `BedrockSession`, where `handleClientToServerHandshake` is reached only because the inbound path decrypts using the already-installed cipher, and the session's `encrypted` flag is set *after* that handler returns.

### 1.2 The correct state machine for PickaxeMP

```
State: DISCONNECTED
  |
  | server accepts TCP connection, sends LoginPacket (unencrypted)
  v
State: HANDSHAKE_SENT
  | initiateEncryption():
  |   - generate ECDH keypair (client public key = our public key)
  |   - generate random salt
  |   - build HandshakeToken JWT (claims: publicKey, salt, expiry, ...)
  |   - compute shared secret = ECDH(ourPrivate, clientPublic)
  |   - derive AES-128-CTR key = KDF(sharedSecret, salt)
  |   - cipher = new AesCtrCipher(key)          <-- CTR counters at INITIAL values
  |   - state->encryptionReady = true           <-- *** cipher is usable NOW ***
  |   - sendPacket(ServerToClientHandshakePacket)  <-- sent UNENCRYPTED
  v
State: AWAITING_CLIENT_HANDSHAKE
  |   inbound: decryptIfNeeded() uses encryptionReady -> DECRYPTS
  |   inbound: decode -> ClientToServerHandshakePacket
  |   dispatch(): state->encryptionEstablished = true   <-- *** FLIP POINT ***
  v
State: ENCRYPTED
      - all inbound packets decrypted (cipher available)
      - all outbound packets encrypted (except ServerToClientHandshake,
        which is already sent and must never be re-encrypted)
```

**The exact instant `encryptionEstablished` must flip to `true`:** *after* the encrypted `ClientToServerHandshakePacket` has been **successfully decrypted and decoded**, i.e. inside `dispatch()` (or immediately after `engine->decode()` returns that packet type) — **not before**, and **not** at `initiateEncryption()` time.

**The exact instant `encryptionReady` must flip to `true`:** *inside* `initiateEncryption()`, immediately after the AES key is derived and the cipher object is installed on the state — **before** `ServerToClientHandshakePacket` is sent.

---

## 2. How the encrypted `ClientToServerHandshake` can be decrypted while the flag is false

### 2.1 The concrete solution: two flags

Introduce (conceptually) on the connection state:

```php
public bool $encryptionReady = false;        // cipher exists & key derived
public bool $encryptionEstablished = false;  // peer handshake completed
public ?AesCtrCipher $cipher = null;
```

**Receive path (`onPayload` → `decryptIfNeeded`):**

```
decryptIfNeeded($bytes):
    if (!state->encryptionReady) {
        return $bytes;              // plaintext: LoginPacket, ServerToClientHandshake echo, etc.
    }
    return state->cipher->decrypt($bytes);
```

Note: this decrypts **everything** that arrives after `initiateEncryption()` — including the `ClientToServerHandshake`. That is correct, because in real Bedrock the *only* packet the client sends after receiving `ServerToClientHandshake` and before the session is fully up is the encrypted `ClientToServerHandshake` itself. There is no plaintext packet in that window.

**Send path (`sendPacket`):**

```
sendPacket(packet):
    encrypt = state->encryptionEstablished
              && !(packet instanceof ServerToClientHandshakePacket);
    bytes = engine->encode(packet);
    if (encrypt) bytes = state->cipher->encrypt(bytes);
    batch->encode([$bytes]);
```

This is **already correct** in the current code, *provided* `encryptionEstablished` is only set after the client handshake is decrypted. The `ServerToClientHandshakePacket` exclusion is belt-and-braces (it is sent while `encryptionEstablished` is still false anyway), but it is a good defensive guard and should be kept.

### 2.2 Why the current code deadlocks

Current receive path (as described):

```
decryptIfNeeded():
    if (!state->encryptionEstablished) return $bytes;   // <-- WRONG GATE
    return cipher->decrypt($bytes);
```

At the moment the encrypted `ClientToServerHandshake` arrives:
- `encryptionEstablished === false` → `decryptIfNeeded` returns the bytes **unchanged** (still ciphertext).
- `engine->decode($ciphertext)` then tries to parse ciphertext as a packet → either throws, or mis-parses the varuint32 packet id and produces a garbage packet type.
- `dispatch()` is therefore **never reached** with a `ClientToServerHandshakePacket` instance → `encryptionEstablished` is **never set to true**.
- Every subsequent packet is likewise passed through undecrypted → permanent garbage.

This is a **hard deadlock**, not a race: no amount of retrying helps, because the state that would unlock decryption is only reachable through the decryption that is gated on that state.

### 2.3 Alternative formulations (all equivalent)

If you prefer not to add a second boolean, the equivalent formulations are:

- **Gate on cipher presence:** `if ($state->cipher === null) return $bytes;` — decrypt whenever a cipher exists. This is the cleanest single-variable form and is essentially what PocketMine does (the cipher's presence *is* the "can decrypt" signal).
- **Gate on "handshake sent":** `if (!$state->handshakeSent) return $bytes;` — equivalent, since the cipher is installed at the same moment.
- **Explicit "expect encrypted handshake" mode:** a tri-state `encryptionState ∈ {NONE, KEY_EXCHANGED, ESTABLISHED}`.

All three require the same invariant: **the inbound gate must be "do I have a key?", and the outbound gate must be "has the peer confirmed?"**

---

## 3. AES-CTR stream continuity — the part that is easy to get wrong

### 3.1 The cipher is a stream, not a per-packet cipher

`AesCtrCipher` maintains:
- `encryptCounter` / `encryptOffset` — position in the **outbound** keystream
- `decryptCounter` / `decryptOffset` — position in the **inbound** keystream

These are **independent** counters for the two directions, and they are **continuous across packets**. They are **never reset per packet**. This is correct and matches Bedrock: Bedrock's encryption is AES-128-**CTR** applied to the *concatenated plaintext of the encrypted portion of the session*, with the counter/IV continuing across packets.

### 3.2 Server → client direction

The server's **encrypt** counter must be at its **initial value (0 / initial IV)** for the very first encrypted outbound packet.

Timeline for the server's outbound keystream:

```
[ServerToClientHandshake]  -> NOT encrypted -> encryptCounter UNTOUCHED (still initial)
[... nothing ...]          -> waiting for client handshake
[ClientToServerHandshake received & decrypted]  -> flip encryptionEstablished = true
[PlayStatus / Login / etc.] -> ENCRYPTED -> uses encryptCounter at INITIAL value
```

**Critical:** the server must **not** encrypt anything between sending `ServerToClientHandshake` and flipping the flag. If it did (e.g. if it sent an encrypted `PlayStatus` before the client handshake arrived), the server's encrypt counter would advance, and the client's decrypt counter — which expects the *first* encrypted server packet to be at keystream position 0 — would desync.

In the current code, `initiateEncryption()` sends `ServerToClientHandshake` via `sendPacket()`, and `sendPacket()` correctly leaves it unencrypted because `encryptionEstablished` is still false. **Therefore the server's encrypt counter is still at its initial value when the flag flips.** Good — this part is already right, and it is exactly why the `ServerToClientHandshakePacket` exclusion in `sendPacket()` matters.

**Answer to "is there any server→client packet between `ServerToClientHandshake` and the flip?"** — **No.** The server must send nothing else until the client handshake is received. If the server needs to send something (e.g. a `PlayStatus` "logging in"), it must be sent **after** the flip, and it will be the first encrypted packet, consuming keystream positions starting at 0.

### 3.3 Client → server direction

The server's **decrypt** counter must be at its **initial value** when the encrypted `ClientToServerHandshake` arrives.

Timeline for the server's inbound keystream:

```
[LoginPacket / etc. before handshake]  -> plaintext -> decryptCounter UNTOUCHED
[ClientToServerHandshake]              -> ENCRYPTED -> decryptCounter at INITIAL value
[subsequent client packets]            -> ENCRYPTED -> decryptCounter continues
```

This works **only if** the server never decrypts anything before the handshake. Since the server's inbound gate is "cipher available", and the cipher is installed at the moment `initiateEncryption()` runs, there is a window in which the client could theoretically send a plaintext packet that the server would then try to decrypt. In practice the client sends nothing between the `ServerToClientHandshake` and its own handshake, so this is benign — but it is worth noting as a robustness concern (see §6).

### 3.4 Does the server need to encrypt the `ClientToServerHandshake` response?

**No.** There is no "response" to the `ClientToServerHandshake` in the encryption-establishment sense. The server's acknowledgement of the handshake is implicit: the next packet the server sends (typically `PlayStatus` with `LOGIN_IN`/`PLAYING`, or the `Login`/`ResourcePacksInfo` sequence) is **encrypted**, and its successful decryption by the client proves the server derived the same key.

Concretely:
- The server does **not** echo or re-send a handshake packet.
- The server does **not** send an unencrypted "ack".
- The server's next outbound packet is encrypted and is the **first** consumer of the server's outbound keystream (position 0).

### 3.5 The desync trap

A naive fix — "set `encryptionEstablished = true` inside `initiateEncryption()`" — would appear to work for the *first* client handshake (because the inbound gate would then be satisfied) but would break the **outbound** direction:

- `ServerToClientHandshake` would then be encrypted (unless the `instanceof` exclusion saves it — it does, in the current `sendPacket()`, which is why the naive fix "seems" to work).
- But more importantly, the semantics are wrong: the server would be encrypting outbound traffic before the peer has proven key agreement. If the client derived a different key (or the JWT is invalid), the server would be emitting ciphertext the client cannot read, with no way to fall back.

The correct fix keeps the two gates separate, which makes the outbound direction provably safe.

### 3.6 Summary table of keystream positions

| Direction | Packet | Encrypted? | Counter position used |
|---|---|---|---|
| S→C | `ServerToClientHandshake` | **No** | encrypt counter: **untouched** (stays at initial) |
| S→C | *(nothing until flip)* | — | — |
| C→S | `ClientToServerHandshake` | **Yes** | decrypt counter: **initial** (first use) |
| S→C | first post-handshake packet (`PlayStatus`/etc.) | **Yes** | encrypt counter: **initial** (first use) |
| C→S | subsequent client packets | **Yes** | decrypt counter: continues |
| S→C | subsequent server packets | **Yes** | encrypt counter: continues |

---

## 4. Exact fix list

### 4.1 Fix #1 — `decryptIfNeeded()` duplicate declaration (TOP PRIORITY, file does not even parse)

`src/PickaxeMP/connection/ConnectionPipeline.php` declares `decryptIfNeeded()` twice (lines ~169–179 and ~181–187). PHP fatals with `Cannot redeclare PickaxeMP\connection\ConnectionPipeline::decryptIfNeeded()`.

**Action:** delete one of the two definitions. Keep exactly one, and make the surviving one use the **correct gate** (see Fix #2). Do not keep a "deprecated alias" or a second method with a different name that forwards — a single canonical implementation is required.

Also note: because the file currently fails `php -l`, **nothing** in the project can be exercised until this is fixed. Any behavioural analysis above is of the *intended* code, not of running code.

### 4.2 Fix #2 — `decryptIfNeeded()` gate must be "cipher available", not "encryption established"

The surviving `decryptIfNeeded()` must be:

```
if (!state->encryptionReady)  return $bytes;   // or: if (state->cipher === null)
return state->cipher->decrypt($bytes);
```

**Not** `if (!state->encryptionEstablished) return $bytes;`.

### 4.3 Fix #3 — add `encryptionReady` to the connection state and set it in `initiateEncryption()`

- Add `public bool $encryptionReady = false;` (and ensure `cipher` is nullable / default-null) to the state object.
- In `initiateEncryption()`, set `state->encryptionReady = true;` **immediately after** the AES key is derived and the cipher is installed, and **before** `sendPacket(ServerToClientHandshakePacket)`.

### 4.4 Fix #4 — remove the dead/no-op `if` block in `onPayload()` (lines ~204–211)

The block:

```php
if ($packetBytes instanceof ClientToServerHandshakePacket) { ... }
```

is **dead code**: `$packetBytes` is a `string` (raw packet bytes from `PacketBatch::decode()`), so `instanceof` is **always false**. It can never execute, and it is misleading because it *looks* like the handshake-detection logic that the protocol actually requires.

**Action:** delete the entire block, including the stale comments attached to it.

**Replacement:** the handshake detection must happen on the **decoded packet object**, i.e. after `engine->decode()`:

```
foreach ($batch->decode($payload) as $packetBytes) {
    $packetBytes = $this->decryptIfNeeded($packetBytes);
    $packet = $this->engine->decode($packetBytes);
    if ($packet instanceof ClientToServerHandshakePacket) {
        // handshake verified -> flip the outbound gate
        $state->encryptionEstablished = true;
    }
    $this->dispatch($packet);
}
```

(Alternatively, keep the flip inside `dispatch()` where it currently lives — that is also correct, since `dispatch()` receives the decoded object. Either way, the detection must be on the **decoded object**, never on the raw string.)

### 4.5 Fix #5 — keep `sendPacket()`'s outbound gate as-is

`encrypt = encryptionEstablished && !(packet instanceof ServerToClientHandshakePacket)` is **correct** and should be preserved. It is what guarantees the server's outbound CTR counter is still at position 0 when the flag flips.

### 4.6 Fix #6 — do not reset CTR counters per packet

`AesCtrCipher` must continue to be used as a continuous stream. Do **not** add any per-packet `reset()`/re-init of `encryptCounter`/`encryptOffset`/`decryptCounter`/`decryptOffset`. The only reset that is legitimate is the **initial** construction of the cipher in `initiateEncryption()`.

### 4.7 Fix #7 — verify `initiateEncryption()` ordering

`initiateEncryption()` must, in this exact order:
1. Generate ECDH keypair.
2. Generate salt.
3. Build + sign the `HandshakeToken` JWT.
4. Compute shared secret; derive AES key.
5. **Construct and install the cipher; set `encryptionReady = true`.**
6. `sendPacket(ServerToClientHandshakePacket)` — unencrypted.

Step 5 before step 6 is essential: if the cipher were installed after the handshake was sent, there is a (tiny) race where the client's handshake could arrive before the cipher exists.

### 4.8 Fix #8 — `php -l` the whole tree after the edits

Run `php -l` over every file under `src/PickaxeMP/` (and the project root's autoloaded files) to confirm no other redeclaration or syntax errors exist. The duplicate-method fatal suggests the file was edited without re-linting; there may be sibling issues.

---

## 5. Does `PacketBatch` framing interact with encryption?

### 5.1 The framing structure

`PacketBatch::decode()` returns a list of **raw packet byte strings**, each of which is:

```
[varuint32 packet_id][packet body...]
```

and the **length prefix is outside** each packet string:

```
[varuint32 total_length][varuint32 packet_1_length][packet_1 bytes][varuint32 packet_2_length][packet_2 bytes]...
```

### 5.2 Is the length prefix encrypted in real Bedrock?

**No. The length prefix is NOT encrypted.** This is a structural property of Bedrock's batch format, and it is the same in PocketMine-MP and Cloudburst.

**Reasoning:**

1. **The batch header must be readable before the key exists.** The very first batch the client sends (containing `LoginPacket`) is plaintext, and the batch containing the encrypted `ClientToServerHandshake` has a plaintext header. If the length prefix were encrypted, the receiver would need the key to even know how many bytes to read — which is fine for the *handshake* batch (the key exists by then) but would make the format self-inconsistent, since the same batch format is used for the pre-key plaintext phase.

2. **Bedrock's encryption is applied to the packet payload, not to the batch container.** In PocketMine-MP, `PacketBatch::encode()` writes the batch header (lengths) in the clear, and each contained packet is individually encoded; encryption is applied at the `NetworkSession` layer to the **encoded packet bytes** before they are handed to the batch encoder. The batch encoder then treats the (possibly encrypted) packet bytes as an opaque blob and prefixes them with a plaintext length.

3. **The length prefix is a transport-layer framing concern; encryption is a session-layer concern.** In Bedrock's RakNet transport, the RakNet "encapsulated" payload carries the batch. Encryption is applied to the *game packet* bytes inside the batch, and the batch's own length fields remain plaintext so that RakNet/stream framing can operate.

4. **Empirically/structurally consistent with the current code:** `sendPacket()` does `bytes = engine->encode(packet); if (encrypt) bytes = cipher->encrypt(bytes); batch->encode([$bytes])` — i.e. encryption is applied to the **packet bytes**, and `batch->encode()` then wraps them with plaintext lengths. This matches the real protocol.

### 5.3 Consequences for the fix

- `decryptIfNeeded()` must be applied to **each individual packet byte string** returned by `PacketBatch::decode()`, **after** the batch framing has been stripped. This is exactly what the current `onPayload()` loop does — that part is correct.
- The batch's length fields are read in plaintext and are **not** passed through the cipher.
- The cipher therefore sees a **concatenation of packet payloads only** — never the length prefixes. This is what makes the "continuous CTR stream across packets" model work: the keystream is consumed by packet payloads in order, and the length prefixes are outside the keystream.

**Important corollary:** because the length prefix is plaintext and outside the keystream, the *number and sizes* of encrypted packets are observable to an attacker, but the *contents* are not. That is the actual Bedrock behaviour and should not be "fixed."

### 5.4 One subtlety worth checking

Because the length prefix is plaintext and the packet bytes are ciphertext, the **length of an encrypted packet equals the length of its plaintext** (AES-CTR is a stream mode: `ciphertext_length == plaintext_length`). This means the length prefix leaks the plaintext packet size — again, matching real Bedrock. No special handling needed.

---

## 6. Additional observations and residual risks

1. **The inbound gate is now "decrypt everything after the cipher exists."** This means any plaintext packet the client sends *after* receiving `ServerToClientHandshake` but *before* its own handshake would be misinterpreted as ciphertext and would desync the server's decrypt counter. Real clients don't do this, but a defensive implementation should either (a) assert that the first post-key inbound packet is a `ClientToServerHandshake`, or (b) keep a small explicit state enum so that "expecting handshake" is a distinct state and a non-handshake packet in that state is a protocol error (and the connection is dropped rather than silently desynced).

2. **CTR counter overflow / long-lived sessions.** `encryptCounter` is a 128-bit big-endian counter in CTR mode; `encryptOffset` is the byte offset within the current 16-byte block. If the implementation uses a 32-bit or 64-bit counter, a long-lived session could wrap. Worth confirming `AesCtrCipher` uses a full 128-bit counter (or at least 64-bit) and that `encryptOffset` wraps to 0 and increments `encryptCounter` at exactly 16 bytes.

3. **The `HandshakeToken` JWT validation.** The server should validate the client's echoed JWT (signature, expiry, and that the public key claim matches the one it sent) *before* setting `encryptionEstablished = true`. If validation fails, the connection should be closed — not silently continued with a wrong key.

4. **The `ServerToClientHandshakePacket` exclusion in `sendPacket()` is load-bearing for the keystream position.** Even though `encryptionEstablished` is false at that moment, keeping the explicit exclusion documents the invariant and protects against a future refactor that sets the flag earlier.

5. **The dead `instanceof` block is a symptom of a deeper confusion.** The author appears to have wanted to detect the handshake at the *raw-bytes* level. That is impossible without decoding (the packet id is a varuint32 at the head of the packet bytes, but the bytes are ciphertext at that point). The detection must be on the decoded object. Removing the block and putting the check after `engine->decode()` is the correct resolution.

6. **Verify `engine->decode()` failure handling.** With the current (broken) code, `engine->decode()` is being fed ciphertext. If it throws an uncaught exception, the connection dies with a confusing error. After the fix, `decode()` should only ever see plaintext. It is still worth ensuring decode failures close the connection cleanly rather than leaving it in a half-state.

---

## 7. Final corrected flow, end to end

```
SERVER accepts connection
