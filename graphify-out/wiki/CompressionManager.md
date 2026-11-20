# CompressionManager

> God node · 40 connections · `src/PickaxeMP/protocol/compression/CompressionManager.php`

**Community:** [Community 0](Community_0.md)

## Connections by Relation

### calls
- .engine() `EXTRACTED`
- .engine() `EXTRACTED`
- .setUp() `EXTRACTED`
- .testSettingsCanBeReplaced() `EXTRACTED`
- .testCompressedRoundTripBothStrategies() `EXTRACTED`
- .testRakNetStrategyAddsGamePacketWrapper() `EXTRACTED`
- .testRawStrategyIsPassThroughWhenCompressionDisabled() `EXTRACTED`
- .testManagerCompressesAboveThreshold() `EXTRACTED`
- .testManagerWithCompressionDisabledPassesThrough() `EXTRACTED`
- .testRakNetStrategyRejectsBadMarker() `EXTRACTED`
- .testRakNetStrategyRejectsEmptyInput() `EXTRACTED`
- .testRakNetStrategyRejectsTruncatedBody() `EXTRACTED`
- .testManagerRejectsSnappyWhenUnavailable() `EXTRACTED`
- .testManagerSkipsCompressionBelowThreshold() `EXTRACTED`
- .testNegotiateFallsBackToZlibForUnsupportedAlgorithm() `EXTRACTED`
- .testNegotiateKeepsSupportedAlgorithm() `EXTRACTED`
- .testRejectsUnknownStrategy() `EXTRACTED`
- .testStrategyIsExposed() `EXTRACTED`

### contains
- CompressionManager.php `EXTRACTED`

### imports
- ProtocolPipelineTest.php `EXTRACTED`
- PacketCodecTest.php `EXTRACTED`
- WorldPacketTest.php `EXTRACTED`
- ProtocolEngine.php `EXTRACTED`
- CompressionTest.php `EXTRACTED`
- PacketBatchTest.php `EXTRACTED`
- PacketBatch.php `EXTRACTED`

### method
- .compress() `EXTRACTED`
- .decompress() `EXTRACTED`
- .shouldCompress() `EXTRACTED`
- .negotiate() `EXTRACTED`
- .__construct() `EXTRACTED`
- .getSettings() `EXTRACTED`
- .setSettings() `EXTRACTED`

### references
- CompressionSettings `EXTRACTED`
- ProtocolEngine `EXTRACTED`
- PacketBatch `EXTRACTED`
- ZlibCompressor `EXTRACTED`
- .__construct() `EXTRACTED`
- .__construct() `EXTRACTED`
- .getCompression() `EXTRACTED`

---

*Part of the graphify knowledge wiki. See [index](index.md) to navigate.*