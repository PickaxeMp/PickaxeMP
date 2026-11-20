# Community 16

> 37 nodes · cohesion 0.16

## Key Concepts

- **.parse()** (32 connections) — `src/PickaxeMP/auth/jwt/JwtParser.php`
- **.generateKeyPair()** (21 connections) — `tests/support/CryptoFixture.php`
- **.createJwt()** (19 connections) — `tests/support/CryptoFixture.php`
- **.verify()** (18 connections) — `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- **.encode()** (17 connections) — `src/PickaxeMP/auth/jwt/Base64Url.php`
- **JwtParserTest** (16 connections) — `tests/unit/auth/JwtParserTest.php`
- **JwtVerifierTest** (16 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.malformedJwt()** (11 connections) — `src/PickaxeMP/auth/AuthException.php`
- **.parseAndVerify()** (7 connections) — `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- **.testCorruptedSignatureFails()** (7 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.fromArray()** (6 connections) — `src/PickaxeMP/auth/jwt/JwtHeader.php`
- **.testModifiedHeaderFails()** (6 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testModifiedPayloadFails()** (6 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testZeroSignatureFails()** (6 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testParsesValidToken()** (5 connections) — `tests/unit/auth/JwtParserTest.php`
- **.testRejectsWrongEcdsaSignatureLength()** (5 connections) — `tests/unit/auth/JwtParserTest.php`
- **.testExpiredTokenFails()** (5 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testFutureNotBeforeFails()** (5 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testTokenWithinClockSkewPasses()** (5 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testValidSignaturePasses()** (5 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.testWrongPublicKeyFails()** (5 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.requireIdentity()** (4 connections) — `src/PickaxeMP/auth/AuthenticationResult.php`
- **.testRejectsInvalidJsonInPayload()** (4 connections) — `tests/unit/auth/JwtParserTest.php`
- **.testSigningInputIsOriginalText()** (4 connections) — `tests/unit/auth/JwtParserTest.php`
- **.testEcKeyRejectedForRsaAlgorithm()** (4 connections) — `tests/unit/auth/JwtVerifierTest.php`
- *... and 12 more nodes in this community*

## Relationships

- [Community 22](Community_22.md) (12 shared connections)
- [Community 25](Community_25.md) (11 shared connections)
- [Community 43](Community_43.md) (10 shared connections)
- [Community 65](Community_65.md) (6 shared connections)
- [Community 4](Community_4.md) (5 shared connections)
- [Community 64](Community_64.md) (4 shared connections)
- [Community 7](Community_7.md) (4 shared connections)
- [Community 36](Community_36.md) (3 shared connections)
- [Community 51](Community_51.md) (2 shared connections)
- [Community 62](Community_62.md) (1 shared connections)
- [Community 73](Community_73.md) (1 shared connections)
- [Community 15](Community_15.md) (1 shared connections)

## Source Files

- `src/PickaxeMP/auth/AuthException.php`
- `src/PickaxeMP/auth/AuthenticationResult.php`
- `src/PickaxeMP/auth/jwt/Base64Url.php`
- `src/PickaxeMP/auth/jwt/JwtHeader.php`
- `src/PickaxeMP/auth/jwt/JwtParser.php`
- `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- `tests/support/CryptoFixture.php`
- `tests/unit/auth/JwtParserTest.php`
- `tests/unit/auth/JwtVerifierTest.php`

## Audit Trail

- EXTRACTED: 129 (78%)
- INFERRED: 36 (22%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*