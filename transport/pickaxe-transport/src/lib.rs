//! PickaxeMP NetherNet transport sidecar.
//!
//! The sidecar owns the NetherNet (WebRTC) transport and speaks a small framed
//! IPC protocol to the PHP kernel. It never interprets Bedrock packets: the
//! payloads it carries are opaque bytes.
//!
//! Layout:
//! - [`ipc`] — frame codec and message types shared with PHP.
//! - [`nether`] — NetherNet HTTP signaling + session handling.
//! - [`config`] — runtime configuration.

pub mod config;
pub mod ipc;
pub mod nether;

pub use config::Config;
pub use ipc::{Frame, FrameError};
