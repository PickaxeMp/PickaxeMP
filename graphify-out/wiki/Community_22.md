# Community 22

> 32 nodes · cohesion 0.17

## Key Concepts

- **SessionManager** (27 connections) — `src/PickaxeMP/session/SessionManager.php`
- **JwtParser** (26 connections) — `src/PickaxeMP/auth/jwt/JwtParser.php`
- **JwtVerifier** (22 connections) — `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- **AuthenticationEngine** (20 connections) — `src/PickaxeMP/auth/AuthenticationEngine.php`
- **CertificateValidator** (20 connections) — `src/PickaxeMP/auth/certificate/CertificateValidator.php`
- **.testDuplicateLoginIsRejected()** (19 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **.testUntrustedChainIsRejectedAndSessionFails()** (19 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **.testValidChainAuthenticatesAndReleasesLoginSuccess()** (19 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **SessionAuthenticatorTest.php** (18 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **SessionAuthenticator** (12 connections) — `src/PickaxeMP/session/SessionAuthenticator.php`
- **CertificateValidatorTest.php** (9 connections) — `tests/unit/auth/CertificateValidatorTest.php`
- **JwtVerifierTest.php** (9 connections) — `tests/unit/auth/JwtVerifierTest.php`
- **.create()** (8 connections) — `src/PickaxeMP/session/SessionManager.php`
- **.empty()** (7 connections) — `src/PickaxeMP/auth/certificate/TrustedKeyStore.php`
- **SessionAuthenticatorTest** (7 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **CertificateValidator.php** (6 connections) — `src/PickaxeMP/auth/certificate/CertificateValidator.php`
- **.logger()** (6 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **.count()** (5 connections) — `src/PickaxeMP/session/SessionManager.php`
- **.playerCount()** (5 connections) — `src/PickaxeMP/session/SessionManager.php`
- **.__construct()** (4 connections) — `src/PickaxeMP/auth/certificate/CertificateValidator.php`
- **.__construct()** (4 connections) — `src/PickaxeMP/session/SessionAuthenticator.php`
- **.testSessionCannotPlayWithoutAuthentication()** (4 connections) — `tests/integration/SessionAuthenticatorTest.php`
- **.__construct()** (3 connections) — `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- **.closeAll()** (3 connections) — `src/PickaxeMP/session/SessionManager.php`
- **.setUp()** (3 connections) — `tests/unit/auth/JwtVerifierTest.php`
- *... and 7 more nodes in this community*

## Relationships

- [Community 43](Community_43.md) (17 shared connections)
- [Community 26](Community_26.md) (16 shared connections)
- [Community 25](Community_25.md) (14 shared connections)
- [Community 15](Community_15.md) (12 shared connections)
- [Community 16](Community_16.md) (12 shared connections)
- [Community 19](Community_19.md) (11 shared connections)
- [Community 36](Community_36.md) (9 shared connections)
- [Community 54](Community_54.md) (9 shared connections)
- [Community 27](Community_27.md) (8 shared connections)
- [Community 7](Community_7.md) (4 shared connections)
- [Community 69](Community_69.md) (4 shared connections)
- [Community 64](Community_64.md) (3 shared connections)

## Source Files

- `src/PickaxeMP/auth/AuthenticationEngine.php`
- `src/PickaxeMP/auth/certificate/CertificateValidator.php`
- `src/PickaxeMP/auth/certificate/TrustedKeyStore.php`
- `src/PickaxeMP/auth/jwt/JwtParser.php`
- `src/PickaxeMP/auth/jwt/JwtVerifier.php`
- `src/PickaxeMP/session/SessionAuthenticator.php`
- `src/PickaxeMP/session/SessionManager.php`
- `tests/integration/SessionAuthenticatorTest.php`
- `tests/unit/auth/CertificateValidatorTest.php`
- `tests/unit/auth/JwtParserTest.php`
- `tests/unit/auth/JwtVerifierTest.php`

## Audit Trail

- EXTRACTED: 194 (92%)
- INFERRED: 16 (8%)
- AMBIGUOUS: 0 (0%)

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*