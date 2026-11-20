//! IPC transport between the PHP kernel and this sidecar.
//!
//! The sidecar is the **server**: it binds `127.0.0.1:0`, prints its port and a
//! one-time token, and waits for exactly one authenticated peer. A second peer
//! is refused while the first is connected.
//!
//! The one-time token stays out of the handshake messages. The sidecar sends
//! `HELLO {nonce, version, pid}` with a fresh 32-byte random challenge, and PHP
//! answers `HELLO_ACK {proof}`. The proof is the lowercase hexadecimal
//! HMAC-SHA256 of `pickaxe-ipc-v1:` followed by the lowercase nonce hex, keyed
//! with the raw token bytes. The handshake is bounded to five seconds.

pub mod frame;
pub mod message;
pub mod server;

pub use frame::{Frame, FrameError, HEADER_SIZE, MAGIC, MAX_PAYLOAD, VERSION};
pub use message::{
    kind, to_json, Channel, CloseBody, ConnectionClosed, ConnectionOpen, Hello, HelloAck,
    MessageError, PayloadBody, Pong, MAX_ID_LEN,
};
pub use server::{run_ipc, Command, IpcError, IpcEvent, Stats, HANDSHAKE_TIMEOUT};
