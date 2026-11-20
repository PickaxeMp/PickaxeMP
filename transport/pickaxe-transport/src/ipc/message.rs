//! Message types carried inside a [`Frame`](super::Frame).
//!
//! Control messages are JSON because they are small, human-readable in logs and
//! easy to extend. Data-carrying messages are a compact binary layout because
//! base64 would add 33% overhead to every Bedrock batch.
//!
//! Binary layouts (all integers big-endian):
//!
//! ```text
//! PayloadIn / PayloadOut : channel(1) | idLen(2) | connectionId(idLen) | data(rest)
//! CloseConnection        : idLen(2) | connectionId(idLen) | reason(rest, UTF-8)
//! ```

use serde::{Deserialize, Serialize};

/// Frame kind identifiers. Values are part of the wire contract with PHP.
pub mod kind {
    pub const HELLO: u8 = 0x01;
    pub const HELLO_ACK: u8 = 0x02;
    pub const PING: u8 = 0x03;
    pub const PONG: u8 = 0x04;

    pub const PAYLOAD_IN: u8 = 0x10;
    pub const PAYLOAD_OUT: u8 = 0x11;
    pub const CLOSE_CONNECTION: u8 = 0x12;

    pub const CONNECTION_OPEN: u8 = 0x20;
    pub const CONNECTION_CLOSED: u8 = 0x21;
}

/// Which data channel a payload travels on.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Channel {
    Reliable,
    Unreliable,
}

impl Channel {
    pub fn to_byte(self) -> u8 {
        match self {
            Self::Reliable => 0,
            Self::Unreliable => 1,
        }
    }

    pub fn from_byte(byte: u8) -> Option<Self> {
        match byte {
            0 => Some(Self::Reliable),
            1 => Some(Self::Unreliable),
            _ => None,
        }
    }
}

/// Errors from decoding a message body.
#[derive(Debug, thiserror::Error, PartialEq, Eq)]
pub enum MessageError {
    #[error("bilinmeyen mesaj tipi: 0x{0:02X}")]
    UnknownKind(u8),

    #[error("mesaj govdesi cok kisa: en az {need} bayt gerekli, {got} var")]
    TooShort { need: usize, got: usize },

    #[error("gecersiz kanal bayti: {0}")]
    InvalidChannel(u8),

    #[error("baglanti kimligi cok uzun: {0} bayt")]
    IdTooLong(usize),

    #[error("gecersiz UTF-8: {0}")]
    InvalidUtf8(String),

    #[error("gecersiz JSON: {0}")]
    InvalidJson(String),
}

/// Largest accepted connection-id length. Ids are short opaque strings.
pub const MAX_ID_LEN: usize = 128;

/// Sidecar → PHP handshake challenge.
///
/// The shared token is deliberately absent from this message. PHP proves
/// possession of it by returning an HMAC over `nonce` in [`HelloAck`].
#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq)]
#[serde(deny_unknown_fields)]
pub struct Hello {
    /// 32 random bytes encoded as exactly 64 lowercase hexadecimal characters.
    pub nonce: String,
    /// Sidecar version, for diagnostics only.
    pub version: String,
    /// Process id, so the PHP side can report a stale child.
    pub pid: u32,
}

/// PHP → sidecar challenge response.
///
/// `proof` is the lowercase hexadecimal HMAC-SHA256 of the challenge message
/// using the raw shared token as the key. The token itself is never sent.
#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq)]
#[serde(deny_unknown_fields)]
pub struct HelloAck {
    pub proof: String,
}

/// Health probe reply.
#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq, Default)]
pub struct Pong {
    pub uptime_ms: u64,
    pub active_connections: u64,
    pub total_connections: u64,
    pub payloads_received: u64,
    pub payloads_sent: u64,
    pub bytes_received: u64,
    pub bytes_sent: u64,
}

/// A new peer finished negotiation.
#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq)]
#[serde(rename_all = "camelCase")]
pub struct ConnectionOpen {
    pub connection_id: String,
    pub network_id: String,
    pub remote_address: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub xuid: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub display_name: Option<String>,
}

/// A peer disconnected.
#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq)]
#[serde(rename_all = "camelCase")]
pub struct ConnectionClosed {
    pub connection_id: String,
    pub reason: String,
}

/// Binary body of `PAYLOAD_IN` / `PAYLOAD_OUT`.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct PayloadBody {
    pub channel: Channel,
    pub connection_id: String,
    pub data: Vec<u8>,
}

impl PayloadBody {
    pub fn encode(&self) -> Result<Vec<u8>, MessageError> {
        let id = self.connection_id.as_bytes();

        if id.len() > MAX_ID_LEN {
            return Err(MessageError::IdTooLong(id.len()));
        }

        let mut out = Vec::with_capacity(3 + id.len() + self.data.len());
        out.push(self.channel.to_byte());
        out.extend_from_slice(&(id.len() as u16).to_be_bytes());
        out.extend_from_slice(id);
        out.extend_from_slice(&self.data);

        Ok(out)
    }

    pub fn decode(bytes: &[u8]) -> Result<Self, MessageError> {
        if bytes.len() < 3 {
            return Err(MessageError::TooShort {
                need: 3,
                got: bytes.len(),
            });
        }

        let channel = Channel::from_byte(bytes[0]).ok_or(MessageError::InvalidChannel(bytes[0]))?;
        let id_len = u16::from_be_bytes([bytes[1], bytes[2]]) as usize;

        if id_len > MAX_ID_LEN {
            return Err(MessageError::IdTooLong(id_len));
        }

        if bytes.len() < 3 + id_len {
            return Err(MessageError::TooShort {
                need: 3 + id_len,
                got: bytes.len(),
            });
        }

        let connection_id = std::str::from_utf8(&bytes[3..3 + id_len])
            .map_err(|e| MessageError::InvalidUtf8(e.to_string()))?
            .to_string();

        Ok(Self {
            channel,
            connection_id,
            data: bytes[3 + id_len..].to_vec(),
        })
    }
}

/// Binary body of `CLOSE_CONNECTION`.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct CloseBody {
    pub connection_id: String,
    pub reason: String,
}

impl CloseBody {
    pub fn encode(&self) -> Result<Vec<u8>, MessageError> {
        let id = self.connection_id.as_bytes();

        if id.len() > MAX_ID_LEN {
            return Err(MessageError::IdTooLong(id.len()));
        }

        let mut out = Vec::with_capacity(2 + id.len() + self.reason.len());
        out.extend_from_slice(&(id.len() as u16).to_be_bytes());
        out.extend_from_slice(id);
        out.extend_from_slice(self.reason.as_bytes());

        Ok(out)
    }

    pub fn decode(bytes: &[u8]) -> Result<Self, MessageError> {
        if bytes.len() < 2 {
            return Err(MessageError::TooShort {
                need: 2,
                got: bytes.len(),
            });
        }

        let id_len = u16::from_be_bytes([bytes[0], bytes[1]]) as usize;

        if id_len > MAX_ID_LEN {
            return Err(MessageError::IdTooLong(id_len));
        }

        if bytes.len() < 2 + id_len {
            return Err(MessageError::TooShort {
                need: 2 + id_len,
                got: bytes.len(),
            });
        }

        let connection_id = std::str::from_utf8(&bytes[2..2 + id_len])
            .map_err(|e| MessageError::InvalidUtf8(e.to_string()))?
            .to_string();
        let reason = std::str::from_utf8(&bytes[2 + id_len..])
            .map_err(|e| MessageError::InvalidUtf8(e.to_string()))?
            .to_string();

        Ok(Self {
            connection_id,
            reason,
        })
    }
}

/// Converts a value into JSON bytes.
pub fn to_json<T: Serialize>(value: &T) -> Result<Vec<u8>, MessageError> {
    serde_json::to_vec(value).map_err(|e| MessageError::InvalidJson(e.to_string()))
}

/// Parses JSON bytes with a size guard.
pub fn from_json<T: for<'de> Deserialize<'de>>(bytes: &[u8]) -> Result<T, MessageError> {
    const MAX_CONTROL_JSON: usize = 64 * 1024;

    if bytes.len() > MAX_CONTROL_JSON {
        return Err(MessageError::TooShort {
            need: MAX_CONTROL_JSON,
            got: bytes.len(),
        });
    }

    serde_json::from_slice(bytes).map_err(|e| MessageError::InvalidJson(e.to_string()))
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn payload_body_round_trip() {
        let body = PayloadBody {
            channel: Channel::Reliable,
            connection_id: "abc-123".to_string(),
            data: vec![1, 2, 3, 255],
        };

        let decoded = PayloadBody::decode(&body.encode().expect("encode")).expect("decode");

        assert_eq!(decoded, body);
    }

    #[test]
    fn payload_body_handles_empty_data() {
        let body = PayloadBody {
            channel: Channel::Unreliable,
            connection_id: "c".to_string(),
            data: Vec::new(),
        };

        let decoded = PayloadBody::decode(&body.encode().expect("encode")).expect("decode");

        assert_eq!(decoded, body);
    }

    #[test]
    fn payload_body_rejects_bad_channel() {
        let mut encoded = PayloadBody {
            channel: Channel::Reliable,
            connection_id: "c".to_string(),
            data: Vec::new(),
        }
        .encode()
        .expect("encode");
        encoded[0] = 9;

        assert_eq!(
            PayloadBody::decode(&encoded),
            Err(MessageError::InvalidChannel(9))
        );
    }

    #[test]
    fn payload_body_rejects_short_input() {
        assert!(matches!(
            PayloadBody::decode(&[0, 0]),
            Err(MessageError::TooShort { .. })
        ));
    }

    #[test]
    fn payload_body_rejects_truncated_id() {
        // channel=0, idLen=10, but no id bytes follow.
        assert!(matches!(
            PayloadBody::decode(&[0, 0, 10]),
            Err(MessageError::TooShort { .. })
        ));
    }

    #[test]
    fn payload_body_rejects_oversized_id() {
        let mut bytes = vec![0u8];
        bytes.extend_from_slice(&((MAX_ID_LEN as u16) + 1).to_be_bytes());

        assert!(matches!(
            PayloadBody::decode(&bytes),
            Err(MessageError::IdTooLong(_))
        ));
    }

    #[test]
    fn close_body_round_trip() {
        let body = CloseBody {
            connection_id: "conn-1".to_string(),
            reason: "zaman asimi".to_string(),
        };

        let decoded = CloseBody::decode(&body.encode().expect("encode")).expect("decode");

        assert_eq!(decoded, body);
    }

    #[test]
    fn close_body_allows_empty_reason() {
        let body = CloseBody {
            connection_id: "c".to_string(),
            reason: String::new(),
        };

        let decoded = CloseBody::decode(&body.encode().expect("encode")).expect("decode");

        assert_eq!(decoded, body);
    }

    #[test]
    fn close_body_rejects_bad_utf8() {
        let mut bytes = vec![0, 1, 0xFF];

        assert!(matches!(
            CloseBody::decode(&bytes),
            Err(MessageError::InvalidUtf8(_))
        ));

        bytes.clear();
        assert!(matches!(
            CloseBody::decode(&bytes),
            Err(MessageError::TooShort { .. })
        ));
    }

    #[test]
    fn hello_json_round_trip_uses_only_nonce_version_and_pid() {
        let nonce = "0123456789abcdef".repeat(4);
        let wire = serde_json::json!({
            "nonce": nonce,
            "version": "0.1.0",
            "pid": 1234,
        });

        let decoded: Hello =
            from_json(&serde_json::to_vec(&wire).expect("encode")).expect("decode");
        let encoded = serde_json::to_value(decoded).expect("encode");

        assert_eq!(encoded.as_object().expect("object").len(), 3);
        assert_eq!(encoded["nonce"], nonce);
        assert_eq!(encoded["version"], "0.1.0");
        assert_eq!(encoded["pid"], 1234);
        assert!(encoded.get("token").is_none());
    }

    #[test]
    fn hello_ack_json_round_trip_uses_only_proof() {
        let proof = "ab".repeat(32);
        let wire = serde_json::json!({ "proof": proof });

        let decoded: HelloAck =
            from_json(&serde_json::to_vec(&wire).expect("encode")).expect("decode");
        let encoded = serde_json::to_value(decoded).expect("encode");

        assert_eq!(encoded.as_object().expect("object").len(), 1);
        assert_eq!(encoded["proof"], proof);
        assert!(encoded.get("token").is_none());
    }

    #[test]
    fn connection_open_uses_camel_case() {
        let open = ConnectionOpen {
            connection_id: "c1".to_string(),
            network_id: "n1".to_string(),
            remote_address: "1.2.3.4:5".to_string(),
            xuid: Some("123".to_string()),
            display_name: Some("Ali".to_string()),
        };

        let json = String::from_utf8(to_json(&open).expect("encode")).expect("utf8");

        assert!(json.contains("\"connectionId\""));
        assert!(json.contains("\"remoteAddress\""));
        assert!(json.contains("\"displayName\""));
    }

    #[test]
    fn connection_open_omits_absent_identity() {
        let open = ConnectionOpen {
            connection_id: "c1".to_string(),
            network_id: "n1".to_string(),
            remote_address: "1.2.3.4:5".to_string(),
            xuid: None,
            display_name: None,
        };

        let json = String::from_utf8(to_json(&open).expect("encode")).expect("utf8");

        assert!(!json.contains("xuid"));
        assert!(!json.contains("displayName"));
    }

    #[test]
    fn json_rejects_garbage() {
        assert!(matches!(
            from_json::<Hello>(b"{not json"),
            Err(MessageError::InvalidJson(_))
        ));
    }

    #[test]
    fn json_rejects_oversized_control_message() {
        let big = vec![b' '; 70 * 1024];
        assert!(matches!(
            from_json::<Hello>(&big),
            Err(MessageError::TooShort { .. })
        ));
    }

    #[test]
    fn channel_byte_mapping_is_stable() {
        assert_eq!(Channel::Reliable.to_byte(), 0);
        assert_eq!(Channel::Unreliable.to_byte(), 1);
        assert_eq!(Channel::from_byte(2), None);
    }
}
