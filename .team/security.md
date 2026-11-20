# PickaxeMP Security Audit: ECDH/AES-CTR/Handshake Integration

## Executive Summary

The encryption integration in PickaxeMP is **fundamentally broken** at the protocol level. The deadlock described in the project facts is real: the server cannot decrypt the client's `ClientToServerHandshake` because `encryptionEstablished` is only set *after* that packet is decrypted, yet decryption requires the cipher state to already be correct. Beyond this deadlock, there are multiple authentication bypass vectors, no integrity protection (MAC-less CTR), and no replay protection. The code as written cannot reach a secure, authenticated playing state without significant redesign.

---

## 1. Authentication Bypass Risks

### 1.1 The Encryption Deadlock (Critical)

The receive path in `onPayload()` decrypts packets conditionally based on `$state->encryptionEstablished`. Because `dispatch()` sets `encryptionEstablished = true` only upon successfully processing a `ClientToServerHandshakePacket`, and that packet arrives **encrypted**, the server faces a chicken-and-egg problem:

1. Client sends encrypted `ClientToServerHandshake`.
2. Server sees `encryptionEstablished === false`, so `decryptIfNeeded()` skips decryption.
3. `engine->decode()` receives ciphertext, fails to parse a valid packet ID/body, or parses garbage.
4. `dispatch()` never reaches the `ClientToServerHandshake` branch; `encryptionEstablished` remains `false`.
5. All subsequent client packets are treated as plaintext (or garbage), and the server never establishes encryption.

**Real Bedrock behavior:** The server sends `ServerToClientHandshake` unencrypted. The client responds with `ClientToServerHandshake` encrypted using the negotiated session key. The server must decrypt that first encrypted packet using the key material derived during `initiateEncryption()`, *before* setting any "established" flag. The flag should be set based on successful cryptographic derivation and successful decryption of the handshake response, not as a precondition for decrypting it.

### 1.2 Resource Pack OR-Clause Bypass (Critical)

The condition in `dispatch()` for `ResourcePackClientResponse`:

```php
if ($state->encryptionEstablished || $state->handshake->getState()->value === 'awaiting_resource_packs')
```

This is an **authentication bypass**. It permits `ResourcePackClientResponse` packets to be processed if *either* encryption is established *or* the handshake state machine is in the `awaiting_resource_packs` phase. Since resource pack negotiation occurs *during* handshake (before encryption is established in a normal flow), the second disjunct is effectively always true during that window. An attacker can:

- Send spoofed or crafted `ResourcePackClientResponse` packets in plaintext.
- Trick the server into believing the client accepted or rejected resource packs without ever completing the cryptographic handshake.
- Potentially advance the connection state toward "playing" or trigger server-side logic that assumes resource pack validation occurred over an authenticated channel.

In PocketMine/Cloudburst, resource pack responses are bound to the encrypted session and are only accepted after the handshake cryptographic exchange is complete. The OR-clause here collapses that boundary.

### 1.3 No Global "Encryption Required" Gate

There is no evidence of a connection-level policy that rejects all non-handshake packets until `encryptionEstablished` is true. If `dispatch()` processes packet types other than handshake and resource pack response without checking encryption state, an attacker can send arbitrary plaintext packets (e.g., `PlayStatus`, `MovePlayer`, `Interact`) and have them processed as if the connection were authenticated. The absence of a default-deny rule for unencrypted traffic is a structural bypass risk.

---

## 2. Cross-Connection Isolation

### 2.1 Per-Connection Cipher State

`AesCtrCipher` appears to be instantiated per `PipelineConnection`/state object and stored on the connection state (not as a static/shared property). This is correct: CTR mode requires a unique counter stream per connection. Sharing a cipher instance across connections would cause catastrophic keystream reuse (two-time pad), allowing trivial plaintext recovery via XOR of ciphertexts.

### 2.2 SessionManager / Static State

No static properties were identified in `AesCtrCipher` that would share counter state across connections. However, if `SessionManager` or any handshake token store uses static arrays or a global registry without connection-scoped keys, there is a risk of token replay across connections. The `HandshakeToken` JWT must be bound to the specific connection (e.g., via client address, connection ID, or nonce) and validated as single-use. If tokens are validated against a global pool without binding to the originating connection, an attacker could replay a valid token from connection A on connection B.

### 2.3 State Machine Isolation

The `$state->handshake` state machine must be per-connection. If `Handshake` or its state object is shared or static, one connection's progression to `awaiting_resource_packs` or `encryption_established` could leak to another. Based on the described architecture, state appears to be instance-held on the connection pipeline, which is the correct isolation model—provided no statics were introduced elsewhere.

---

## 3. Handshake Replay / Duplicate Handshake

### 3.1 Duplicate `ClientToServerHandshake`

If the client sends a second `ClientToServerHandshake`:

- `dispatch()` sets `encryptionEstablished = true` again (idempotent flag, but semantically wrong).
- Because CTR state is continuous and never reset, decrypting a second handshake with the same cipher state will produce garbage (the cipher has already advanced past the keystream bytes used for the first handshake).
- There is no nonce, sequence number, or handshake session ID to detect and reject a duplicate handshake. The server has no way to distinguish a legitimate retransmission from a replay attack.

### 3.2 Duplicate `ServerToClientHandshake` Trigger

If a client can trigger `initiateEncryption()` a second time (e.g., by resending a login packet that the server interprets as a request to start encryption):

- A new ECDH keypair is generated.
- A new AES key and salt are derived.
- A new `AesCtrCipher` instance replaces the old one on the connection state.
- The old cipher state is discarded.

This allows an attacker to force re-keying at arbitrary points, potentially resetting encryption state or causing desynchronization if the client does not also re-key. More importantly, if the server accepts a new handshake after encryption is already established, it creates a window where the server's transmit cipher and the client's receive cipher may diverge.

### 3.3 No Handshake Nonce / Session Binding

There is no handshake nonce or cryptographic binding between the `ServerToClientHandshake` and the `ClientToServerHandshake`. An attacker who observes a legitimate `ServerToClientHandshake` could replay it, or replay a captured `ClientToServerHandshake`, and the server would have no cryptographic mechanism to detect it (beyond any application-layer state checks, which appear absent).

---

## 4. AES-CTR Misuse Analysis

### 4.1 CTR Continuity Design

The design correctly treats AES-CTR as a stream cipher where the counter advances continuously across packets within a connection. `encryptCounter`/`encryptOffset` and `decryptCounter`/`decryptOffset` track position in the keystream. This matches Bedrock's use of CTR: the length-prefixed packet payload is XORed with a continuous keystream, and the offset carries over between packets.

### 4.2 Fake-GCM IV Construction (Critical)

The project facts mention a "fake-GCM IV construction." This is a severe design smell. AES-CTR does **not** use an IV in the GCM sense; it uses a nonce/counter block. If the code constructs a 12-byte IV (as in GCM) and feeds it into a CTR mode implementation, or if it prepends a nonce to ciphertext and treats the remainder as a GCM-style authenticated ciphertext, it is conflating two incompatible modes:

- **CTR provides confidentiality only.** It provides **zero integrity**.
- **GCM provides confidentiality + authenticity** via GMAC.

If the code labels its construction "GCM" or "IV-based" but only performs CTR encryption, it is **unauthenticated encryption**. An attacker can flip bits in the ciphertext, and the server will decrypt them to flipped bits in the plaintext with no detection. This enables:
- Packet injection/modification (e.g., altering `MovePlayer` coordinates, `Interact` target entity IDs, or `Text` chat content).
- Cut-and-paste attacks (reordering or duplicating ciphertext blocks, though CTR is byte-oriented so block reordering is less relevant than bit flipping).

### 4.3 `advanceIv` and `applyKeystream` Correctness

Without seeing the exact source, the following must be verified:

- **Counter block format:** In Bedrock, the CTR counter is typically a 16-byte block where a portion is a fixed nonce and the remainder is a little-endian or big-endian counter that increments per 16-byte block. If `advanceIv` increments the wrong bytes (e.g., treating the nonce as part of the counter, or incrementing from the wrong endianness), the keystream will desynchronize from the client, causing decryption failures after the first block.
- **Partial block handling:** `applyKeystream` must correctly handle the case where a packet length is not a multiple of 16 bytes. It must XOR only the needed bytes from the current keystream block and save the offset for the next packet. If it always consumes full blocks or resets offset incorrectly, keystream desynchronization will occur.
- **Counter rollover:** If the counter is 32-bit or 64-bit and wraps after 2^32 or 2^64 blocks, the keystream repeats. With Bedrock packet sizes, this is unlikely in a single session, but if the counter is small (e.g., 16-bit), IV reuse is a real risk.

### 4.4 IV Reuse Risk

Because the cipher state is continuous and never reset per connection, IV/nonce reuse within a single connection is avoided *if* the counter truly advances monotonically. However:

- If `initiateEncryption()` is called multiple times on the same connection and a new cipher is created with the **same nonce** but a new key, that is acceptable (new key = new keystream).
- If a new cipher is created with the **same key and same nonce** (e.g., key derivation is deterministic and salt is reused), this is catastrophic keystream reuse.
- If the connection is resumed or reconnected and the cipher is reinitialized with the same key/nonce, keystream reuse occurs across connections.

---

## 5. Checksum / Integrity and Framing

### 5.1 No MAC

AES-CTR is unauthenticated. The project facts mention a "checksum design," but if this checksum is a simple CRC32, Adler32, or even an unkeyed hash appended to the packet, it **does not provide integrity** against an active attacker. An attacker can:
1. Modify ciphertext bits.
2. Recompute the unkeyed checksum over the modified plaintext (or modify the checksum field if it is also encrypted but unauthenticated).
3. Have the server accept the tampered packet.

Only a keyed MAC (HMAC) or an authenticated encryption mode (AES-GCM, ChaCha20-Poly1305) prevents this. Bedrock uses encryption with a separate integrity mechanism; if PickaxeMP omits it, the connection is vulnerable to active manipulation.

### 5.2 Length / Framing Validation

`PacketBatch::decode()` returns raw packet bytes, each including a varuint32 packet ID. If the code does not validate:
- That the packet ID is within a defined range,
- That the packet body length matches the expected length for that ID,
- That the total batch length matches the outer length prefix,

then a malformed or truncated packet can cause:
- Deserialization exceptions or undefined behavior in `engine->decode()`.
- State machine desynchronization (e.g., reading a packet ID as body bytes).
- Potential buffer over-reads or logic errors if PHP's string handling is pushed into edge cases.

There is no mention of length validation before dispatch, which is a robustness and potential exploit vector.

---

## 6. Concrete Remediation List (Ranked by Severity)

### Critical (Fix Immediately)

1. **Resolve the encryption deadlock.** Decrypt the `ClientToServerHandshake` using the cipher state derived during `initiateEncryption()` *before* checking `encryptionEstablished`. Set `encryptionEstablished = true` only after successful decryption and validation of that handshake. The receive path must not require the flag to be true in order to decrypt the handshake response.

2. **Remove the OR-clause in `ResourcePackClientResponse` dispatch.** Gate resource pack responses on `encryptionEstablished === true` alone (or on a separate, cryptographically bound handshake-complete flag). Do not allow the handshake state machine phase to bypass encryption requirements.

3. **Add a default-deny encryption gate.** In `onPayload()` or `dispatch()`, reject all packets except `ServerToClientHandshake` (server→client only) and the initial unencrypted login/handshake packets unless `encryptionEstablished` is true. Drop or disconnect on any encrypted-required packet received in plaintext.

4. **Eliminate unauthenticated CTR.** Add HMAC-SHA256 (encrypt-then-MAC) over each packet, or switch to an authenticated mode such as AES-GCM or ChaCha20-Poly1305. Verify the MAC before decryption and before any state change. Do not rely on an unkeyed checksum.

### High

5. **Add handshake replay protection.** Bind the `ServerToClientHandshake` and `ClientToServerHandshake` with a nonce or session ID. Reject duplicate handshakes. Track handshake completion as a one-time transition; ignore or disconnect on subsequent handshake attempts.

6. **Validate ECDH public keys.** Ensure the client's ephemeral public key is on the curve, not the point at infinity, and not a small-subgroup point. Failure to validate enables small-subgroup attacks that leak the server's private key.

7. **Bind `HandshakeToken` JWT to the connection.** Include connection-specific entropy (e.g., client IP/port, connection ID, or a server-generated nonce) in the token and validate it as single-use per connection.

### Medium

8. **Audit `advanceIv` / `applyKeystream`.** Verify that the CTR counter block is incremented correctly (proper endianness, correct byte offset, no nonce bytes being incremented). Confirm partial-block offset is preserved across packets.

9. **Add packet framing validation.** Validate packet ID ranges, body lengths, and batch length prefixes before decoding. Reject malformed batches with a disconnect.

10. **Prevent mid-session re-keying.** Once `encryptionEstablished` is true, reject any further `initiateEncryption()` requests or duplicate login handshakes.

### Low

11. **Remove dead code.** Delete the no-op `instanceof ClientToServerHandshakePacket` check on a string and stale comments in the batch loop to prevent confusion during future maintenance.

12. **Fix the duplicate `decryptIfNeeded()` method.** Resolve the PHP fatal error so the codebase is at least syntactically valid before security testing.

---

## Bottom Line

PickaxeMP's current encryption design cannot establish a secure session: the handshake is encrypted before the server is allowed to decrypt it, resource pack responses bypass encryption via an OR-clause, and CTR is used without any integrity protection. An active attacker can bypass authentication, inject or modify packets, and potentially force key renegotiation. The remediation above is required before the emulator can be considered safe for any network-facing deployment.
