//! Binary frame codec shared with the PHP kernel.
//!
//! Wire layout (16-byte header, big-endian, identical to
//! `PickaxeMP\transport\sidecar\IpcFrame`):
//!
//! ```text
//! MAGIC(2) | VERSION(1) | TYPE(1) | REQUESTID(8) | LEN(4) | PAYLOAD(LEN)
//! ```
//!
//! The magic is `0x504B` ("PK"). A wrong magic, an unsupported version or an
//! oversized length is a hard error: silently skipping bytes would desync the
//! stream and corrupt every later message.

use bytes::{Buf, BufMut, BytesMut};

/// Frame magic, big-endian.
pub const MAGIC: u16 = 0x504B;

/// Protocol version. Bumped when the layout changes.
pub const VERSION: u8 = 1;

/// Total header size in bytes.
pub const HEADER_SIZE: usize = 16;

/// Largest payload accepted. Mirrors `IpcFrame::MAX_PAYLOAD` in PHP.
pub const MAX_PAYLOAD: usize = 1024 * 1024;

/// Errors from frame decoding.
#[derive(Debug, thiserror::Error, PartialEq, Eq)]
pub enum FrameError {
    #[error("gecersiz IPC sihirli sayisi: 0x{0:04X}")]
    BadMagic(u16),

    #[error("desteklenmeyen IPC protokol surumu: {0} (beklenen: {VERSION})")]
    UnsupportedVersion(u8),

    #[error("IPC yuku siniri asildi: {0} bayt (sinir: {MAX_PAYLOAD})")]
    PayloadTooLarge(u32),

    #[error("cerceve yuku cok buyuk: {0} bayt")]
    EncodePayloadTooLarge(usize),
}

/// A single IPC message.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Frame {
    pub kind: u8,
    pub request_id: u64,
    pub payload: Vec<u8>,
}

impl Frame {
    pub fn new(kind: u8, request_id: u64, payload: Vec<u8>) -> Result<Self, FrameError> {
        if payload.len() > MAX_PAYLOAD {
            return Err(FrameError::EncodePayloadTooLarge(payload.len()));
        }

        Ok(Self {
            kind,
            request_id,
            payload,
        })
    }

    /// Total encoded size.
    pub fn encoded_len(&self) -> usize {
        HEADER_SIZE + self.payload.len()
    }

    /// Appends the encoded frame to `buffer`.
    pub fn encode_into(&self, buffer: &mut BytesMut) -> Result<(), FrameError> {
        if self.payload.len() > MAX_PAYLOAD {
            return Err(FrameError::EncodePayloadTooLarge(self.payload.len()));
        }

        buffer.reserve(self.encoded_len());
        buffer.put_u16(MAGIC);
        buffer.put_u8(VERSION);
        buffer.put_u8(self.kind);
        buffer.put_u64(self.request_id);
        buffer.put_u32(self.payload.len() as u32);
        buffer.put_slice(&self.payload);

        Ok(())
    }

    pub fn encode(&self) -> Result<Vec<u8>, FrameError> {
        let mut buffer = BytesMut::with_capacity(self.encoded_len());
        self.encode_into(&mut buffer)?;
        Ok(buffer.to_vec())
    }

    /// Decodes one frame from the front of `buffer`.
    ///
    /// Returns `Ok(None)` when the buffer holds only part of a frame; in that
    /// case nothing is consumed so the caller can read more bytes.
    pub fn decode(buffer: &mut BytesMut) -> Result<Option<Self>, FrameError> {
        if buffer.len() < HEADER_SIZE {
            return Ok(None);
        }

        let magic = u16::from_be_bytes([buffer[0], buffer[1]]);

        if magic != MAGIC {
            return Err(FrameError::BadMagic(magic));
        }

        if buffer[2] != VERSION {
            return Err(FrameError::UnsupportedVersion(buffer[2]));
        }

        let kind = buffer[3];
        let request_id = u64::from_be_bytes([
            buffer[4], buffer[5], buffer[6], buffer[7], buffer[8], buffer[9], buffer[10],
            buffer[11],
        ]);
        let length = u32::from_be_bytes([buffer[12], buffer[13], buffer[14], buffer[15]]);

        if length as usize > MAX_PAYLOAD {
            return Err(FrameError::PayloadTooLarge(length));
        }

        let total = HEADER_SIZE + length as usize;

        if buffer.len() < total {
            return Ok(None);
        }

        buffer.advance(HEADER_SIZE);
        let payload = buffer.split_to(length as usize).to_vec();

        Ok(Some(Self {
            kind,
            request_id,
            payload,
        }))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn header_size_is_stable() {
        let frame = Frame::new(1, 0, Vec::new()).expect("valid");
        assert_eq!(frame.encode().expect("encode").len(), HEADER_SIZE);
    }

    #[test]
    fn round_trip() {
        let frame = Frame::new(7, 42, b"merhaba".to_vec()).expect("valid");
        let mut buffer = BytesMut::from(&frame.encode().expect("encode")[..]);

        let decoded = Frame::decode(&mut buffer)
            .expect("decode")
            .expect("complete");

        assert_eq!(decoded, frame);
        assert!(buffer.is_empty());
    }

    #[test]
    fn partial_header_yields_none_and_consumes_nothing() {
        let frame = Frame::new(1, 1, b"x".to_vec()).expect("valid");
        let encoded = frame.encode().expect("encode");
        let mut buffer = BytesMut::from(&encoded[..10]);

        assert_eq!(Frame::decode(&mut buffer).expect("decode"), None);
        assert_eq!(buffer.len(), 10);
    }

    #[test]
    fn partial_payload_yields_none_then_succeeds() {
        let frame = Frame::new(2, 7, b"abcdef".to_vec()).expect("valid");
        let encoded = frame.encode().expect("encode");

        let mut buffer = BytesMut::from(&encoded[..18]);
        assert_eq!(Frame::decode(&mut buffer).expect("decode"), None);

        buffer.extend_from_slice(&encoded[18..]);
        let decoded = Frame::decode(&mut buffer)
            .expect("decode")
            .expect("complete");
        assert_eq!(decoded.payload, b"abcdef");
    }

    #[test]
    fn two_frames_in_one_buffer() {
        let a = Frame::new(1, 1, b"bir".to_vec()).expect("valid");
        let b = Frame::new(2, 2, b"iki".to_vec()).expect("valid");

        let mut buffer = BytesMut::new();
        a.encode_into(&mut buffer).expect("encode");
        b.encode_into(&mut buffer).expect("encode");

        assert_eq!(Frame::decode(&mut buffer).expect("decode"), Some(a));
        assert_eq!(Frame::decode(&mut buffer).expect("decode"), Some(b));
        assert!(buffer.is_empty());
    }

    #[test]
    fn rejects_bad_magic() {
        let frame = Frame::new(1, 1, b"x".to_vec()).expect("valid");
        let mut encoded = frame.encode().expect("encode");
        encoded[0] = b'X';
        encoded[1] = b'X';

        let mut buffer = BytesMut::from(&encoded[..]);
        assert!(matches!(
            Frame::decode(&mut buffer),
            Err(FrameError::BadMagic(_))
        ));
    }

    #[test]
    fn rejects_unsupported_version() {
        let frame = Frame::new(1, 1, b"x".to_vec()).expect("valid");
        let mut encoded = frame.encode().expect("encode");
        encoded[2] = 99;

        let mut buffer = BytesMut::from(&encoded[..]);
        assert!(matches!(
            Frame::decode(&mut buffer),
            Err(FrameError::UnsupportedVersion(99))
        ));
    }

    #[test]
    fn rejects_oversized_length() {
        let frame = Frame::new(1, 1, b"x".to_vec()).expect("valid");
        let mut encoded = frame.encode().expect("encode");
        encoded[12..16].copy_from_slice(&((MAX_PAYLOAD as u32) + 1).to_be_bytes());

        let mut buffer = BytesMut::from(&encoded[..]);
        assert!(matches!(
            Frame::decode(&mut buffer),
            Err(FrameError::PayloadTooLarge(_))
        ));
    }

    #[test]
    fn rejects_oversized_payload_on_construction() {
        let error = Frame::new(1, 1, vec![0u8; MAX_PAYLOAD + 1]).expect_err("should fail");
        assert_eq!(error, FrameError::EncodePayloadTooLarge(MAX_PAYLOAD + 1));
    }

    #[test]
    fn binary_payload_survives() {
        let payload: Vec<u8> = (0..=255u8).chain([0, 255]).collect();
        let frame = Frame::new(9, 999, payload.clone()).expect("valid");

        let mut buffer = BytesMut::from(&frame.encode().expect("encode")[..]);
        let decoded = Frame::decode(&mut buffer)
            .expect("decode")
            .expect("complete");

        assert_eq!(decoded.payload, payload);
    }

    /// The PHP implementation must produce byte-identical headers.
    #[test]
    fn matches_php_layout() {
        let frame = Frame::new(0x10, 0x0102030405060708, b"AB".to_vec()).expect("valid");
        let encoded = frame.encode().expect("encode");

        assert_eq!(&encoded[0..2], &[0x50, 0x4B]);
        assert_eq!(encoded[2], 1);
        assert_eq!(encoded[3], 0x10);
        assert_eq!(&encoded[4..12], &[1, 2, 3, 4, 5, 6, 7, 8]);
        assert_eq!(&encoded[12..16], &[0, 0, 0, 2]);
        assert_eq!(&encoded[16..], b"AB");
    }
}
