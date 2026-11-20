# Community 4

> 68 nodes · cohesion 0.06

## Key Concepts

- **EncryptionTest** (26 connections) — `tests/unit/auth/EncryptionTest.php`
- **AesCtrCipher** (21 connections) — `src/PickaxeMP/auth/crypto/AesCtrCipher.php`
- **EcdhKeyExchange** (13 connections) — `src/PickaxeMP/auth/crypto/EcdhKeyExchange.php`
- **.generate()** (13 connections) — `src/PickaxeMP/auth/crypto/EcdhKeyExchange.php`
- **EcPublicKey** (11 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **.fromJwk()** (10 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **.create()** (9 connections) — `src/PickaxeMP/auth/crypto/HandshakeToken.php`
- **.decrypt()** (8 connections) — `src/PickaxeMP/auth/crypto/AesCtrCipher.php`
- **.encrypt()** (8 connections) — `src/PickaxeMP/auth/crypto/AesCtrCipher.php`
- **.opensslFailure()** (7 connections) — `src/PickaxeMP/auth/AuthException.php`
- **.parse()** (7 connections) — `src/PickaxeMP/auth/crypto/HandshakeToken.php`
- **.applyKeystream()** (6 connections) — `src/PickaxeMP/auth/crypto/AesCtrCipher.php`
- **.assertEcP384()** (6 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **.fromBase64Der()** (6 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **.fromDer()** (6 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **HandshakeToken** (6 connections) — `src/PickaxeMP/auth/crypto/HandshakeToken.php`
- **.testHandshakeTokenRoundTrip()** (6 connections) — `tests/unit/auth/EncryptionTest.php`
- **.deriveEncryptionKey()** (5 connections) — `src/PickaxeMP/auth/crypto/EcdhKeyExchange.php`
- **OpenSSLAsymmetricKey** (5 connections)
- **.fromClientPublicKey()** (5 connections) — `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- **OpenSSLAsymmetricKey** (5 connections)
- **.clientKeyPair()** (5 connections) — `tests/unit/auth/EncryptionTest.php`
- **.testHandshakeTokenHeaderUsesEs384()** (5 connections) — `tests/unit/auth/EncryptionTest.php`
- **.testJwkConvertsToSameKeyAsDer()** (5 connections) — `tests/unit/auth/EncryptionTest.php`
- **.computeSharedSecret()** (4 connections) — `src/PickaxeMP/auth/crypto/EcdhKeyExchange.php`
- *... and 43 more nodes in this community*

## Relationships

- [Community 25](Community_25.md) (13 shared connections)
- [Community 7](Community_7.md) (13 shared connections)
- [Community 16](Community_16.md) (5 shared connections)
- [Community 65](Community_65.md) (1 shared connections)

## Source Files

- `src/PickaxeMP/auth/AuthException.php`
- `src/PickaxeMP/auth/crypto/AesCtrCipher.php`
- `src/PickaxeMP/auth/crypto/EcPublicKey.php`
- `src/PickaxeMP/auth/crypto/EcdhKeyExchange.php`
- `src/PickaxeMP/auth/crypto/HandshakeToken.php`
- `tests/unit/auth/EncryptionTest.php`
- `tools/verify-encryption.php`

## Audit Trail

- EXTRACTED: 166 (94%)
- INFERRED: 10 (6%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*