# Community 7

> 47 nodes · cohesion 0.09

## Key Concepts

- **JwtAlgorithm** (26 connections) — `src/PickaxeMP/auth/jwt/JwtAlgorithm.php`
- **.invalidKey()** (23 connections) — `src/PickaxeMP/auth/AuthException.php`
- **EcdsaSignature** (13 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.derToRaw()** (13 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.rawToDer()** (13 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.doVerify()** (12 connections) — `src/PickaxeMP/auth/jwt/OpenSslJwtVerifier.php`
- **EcdsaSignatureTest** (12 connections) — `tests/unit/auth/EcdsaSignatureTest.php`
- **VerificationResult** (9 connections) — `src/PickaxeMP/auth/jwt/VerificationResult.php`
- **.isValidRaw()** (8 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.ecdsaCoordinateBytes()** (7 connections) — `src/PickaxeMP/auth/jwt/JwtAlgorithm.php`
- **.rawSignatureLength()** (6 connections) — `src/PickaxeMP/auth/jwt/JwtAlgorithm.php`
- **OpenSslJwtVerifier** (6 connections) — `src/PickaxeMP/auth/jwt/OpenSslJwtVerifier.php`
- **.verify()** (6 connections) — `src/PickaxeMP/auth/jwt/OpenSslJwtVerifier.php`
- **SignatureVerifierInterface** (5 connections) — `src/PickaxeMP/auth/jwt/SignatureVerifierInterface.php`
- **.decodeInteger()** (4 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.decodeLength()** (4 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.encodeLength()** (4 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.verify()** (4 connections) — `src/PickaxeMP/auth/jwt/SignatureVerifierInterface.php`
- **.failure()** (4 connections) — `src/PickaxeMP/auth/jwt/VerificationResult.php`
- **.testEcdsaRoundTripBothDirectionsForAllCoordinates()** (4 connections) — `tests/unit/auth/EcdsaSignatureTest.php`
- **.encodeInteger()** (3 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.padLeft()** (3 connections) — `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- **.isEcdsa()** (3 connections) — `src/PickaxeMP/auth/jwt/JwtAlgorithm.php`
- **.pemToDer()** (3 connections) — `src/PickaxeMP/auth/jwt/TrustedPublicKey.php`
- **.success()** (3 connections) — `src/PickaxeMP/auth/jwt/VerificationResult.php`
- *... and 22 more nodes in this community*

## Relationships

- [Community 25](Community_25.md) (18 shared connections)
- [Community 4](Community_4.md) (13 shared connections)
- [Community 36](Community_36.md) (6 shared connections)
- [Community 16](Community_16.md) (4 shared connections)
- [Community 22](Community_22.md) (4 shared connections)
- [Community 64](Community_64.md) (2 shared connections)

## Source Files

- `src/PickaxeMP/auth/AuthException.php`
- `src/PickaxeMP/auth/jwt/EcdsaSignature.php`
- `src/PickaxeMP/auth/jwt/JwtAlgorithm.php`
- `src/PickaxeMP/auth/jwt/OpenSslJwtVerifier.php`
- `src/PickaxeMP/auth/jwt/SignatureVerifierInterface.php`
- `src/PickaxeMP/auth/jwt/TrustedPublicKey.php`
- `src/PickaxeMP/auth/jwt/VerificationResult.php`
- `tests/unit/auth/EcdsaSignatureTest.php`

## Audit Trail

- EXTRACTED: 132 (93%)
- INFERRED: 10 (7%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*