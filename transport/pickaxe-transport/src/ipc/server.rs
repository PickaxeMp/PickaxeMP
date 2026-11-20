//! IPC server: reads frames from the PHP kernel and dispatches them.
//!
//! Authentication is a challenge-response handshake. The sidecar sends
//! `HELLO {nonce, version, pid}` without the shared token. PHP replies with
//! `HELLO_ACK {proof}`, where `proof` is lowercase hexadecimal
//! HMAC-SHA256(token bytes, `pickaxe-ipc-v1:` + nonce hex). The token itself
//! never crosses the IPC connection.

use super::frame::{Frame, FrameError, HEADER_SIZE, MAX_PAYLOAD};
use super::message::{
    from_json, kind, to_json, Channel, CloseBody, ConnectionClosed, ConnectionOpen, Hello,
    HelloAck, MessageError, PayloadBody, Pong,
};
use bytes::BytesMut;
use hmac::{Hmac, Mac};
use sha2::Sha256;
use std::sync::atomic::{AtomicU64, Ordering};
use std::sync::Arc;
use std::time::{Duration, Instant};
use tokio::io::{AsyncReadExt, AsyncWriteExt};
use tokio::net::TcpStream;
use tokio::sync::mpsc;

/// Maximum time allowed for the PHP side to answer the IPC challenge.
pub const HANDSHAKE_TIMEOUT: Duration = Duration::from_secs(5);

const NONCE_BYTES: usize = 32;
const NONCE_HEX_LEN: usize = NONCE_BYTES * 2;
const PROOF_HEX_LEN: usize = 64;
const PROOF_CONTEXT: &[u8] = b"pickaxe-ipc-v1:";

type HmacSha256 = Hmac<Sha256>;

/// Errors from the IPC layer.
#[derive(Debug, thiserror::Error)]
pub enum IpcError {
    #[error("IO hatasi: {0}")]
    Io(#[from] std::io::Error),

    #[error("cerceve hatasi: {0}")]
    Frame(#[from] FrameError),

    #[error("mesaj hatasi: {0}")]
    Message(#[from] MessageError),

    #[error("istemci kimlik dogrulamasini gecemedi")]
    Unauthorized,

    #[error("istemci beklenmedik bir mesaj gonderdi: 0x{0:02X}")]
    UnexpectedFrame(u8),

    #[error("IPC el sikisma zaman asimina ugradi")]
    HandshakeTimeout,

    #[error("IPC tamponu siniri asildi ({0} bayt)")]
    BufferOverflow(usize),
}

/// Commands the PHP kernel sends to the sidecar.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum Command {
    /// Deliver bytes to a peer.
    SendPayload {
        channel: Channel,
        connection_id: String,
        data: Vec<u8>,
    },
    /// Close a peer connection.
    Close {
        connection_id: String,
        reason: String,
    },
}

/// Events the sidecar reports to the PHP kernel.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum IpcEvent {
    PayloadIn(PayloadBody),
    ConnectionOpen(ConnectionOpen),
    ConnectionClosed(ConnectionClosed),
}

/// Counters shared between the session layer and the IPC layer.
#[derive(Debug, Default)]
pub struct Stats {
    pub active_connections: AtomicU64,
    pub total_connections: AtomicU64,
    pub payloads_received: AtomicU64,
    pub payloads_sent: AtomicU64,
    pub bytes_received: AtomicU64,
    pub bytes_sent: AtomicU64,
}

impl Stats {
    pub fn snapshot(&self) -> Pong {
        Pong {
            uptime_ms: 0,
            active_connections: self.active_connections.load(Ordering::Relaxed),
            total_connections: self.total_connections.load(Ordering::Relaxed),
            payloads_received: self.payloads_received.load(Ordering::Relaxed),
            payloads_sent: self.payloads_sent.load(Ordering::Relaxed),
            bytes_received: self.bytes_received.load(Ordering::Relaxed),
            bytes_sent: self.bytes_sent.load(Ordering::Relaxed),
        }
    }
}

/// Runs the IPC session until the peer disconnects.
///
/// The sidecar sends a `HELLO` challenge first. The peer must answer with a
/// `HELLO_ACK` whose HMAC proof validates against the shared token. Anything
/// else closes the connection: an unauthenticated peer must not be able to
/// inject payloads.
pub async fn run_ipc(
    stream: TcpStream,
    token: String,
    command_tx: mpsc::Sender<Command>,
    event_rx: &mut mpsc::Receiver<IpcEvent>,
    stats: Arc<Stats>,
) -> Result<(), IpcError> {
    run_ipc_with_timeout(
        stream,
        token,
        command_tx,
        event_rx,
        stats,
        HANDSHAKE_TIMEOUT,
    )
    .await
}

async fn run_ipc_with_timeout(
    mut stream: TcpStream,
    token: String,
    command_tx: mpsc::Sender<Command>,
    event_rx: &mut mpsc::Receiver<IpcEvent>,
    stats: Arc<Stats>,
    handshake_timeout: Duration,
) -> Result<(), IpcError> {
    let started = Instant::now();

    let mut read_buffer = BytesMut::with_capacity(HEADER_SIZE * 4);

    // --- Handshake -------------------------------------------------------
    // Any bytes that arrive after HELLO_ACK are handed back so they are not
    // lost: a client may legitimately pipeline a payload with the handshake.
    let nonce = generate_nonce();
    write_and_expect_hello(
        &mut stream,
        &token,
        &nonce,
        &mut read_buffer,
        handshake_timeout,
    )
    .await?;

    tracing::info!("PHP cekirdegi IPC kanalina baglandi.");

    let mut write_buffer = BytesMut::with_capacity(HEADER_SIZE * 4);
    let mut read_chunk = vec![0u8; 8192];

    // Frames that arrived together with the handshake.
    while let Some(frame) = Frame::decode(&mut read_buffer)? {
        handle_frame(frame, &command_tx, &stats, started).await?;
    }

    loop {
        tokio::select! {
            // Outgoing events from the NetherNet layer.
            event = event_rx.recv() => {
                let Some(event) = event else {
                    tracing::info!("Olay kanali kapandi, IPC sonlaniyor.");
                    break;
                };

                let (kind_id, payload) = encode_event(event)?;
                write_buffer.clear();
                Frame::new(kind_id, 0, payload)?.encode_into(&mut write_buffer)?;
                stream.write_all(&write_buffer).await?;
            }

            // Incoming frames from PHP.
            read = stream.read(&mut read_chunk) => {
                let read = read?;

                if read == 0 {
                    tracing::info!("PHP cekirdegi IPC baglantisini kapatti.");
                    break;
                }

                read_buffer.extend_from_slice(&read_chunk[..read]);

                if read_buffer.len() > MAX_PAYLOAD + HEADER_SIZE * 2 {
                    return Err(IpcError::BufferOverflow(read_buffer.len()));
                }

                while let Some(frame) = Frame::decode(&mut read_buffer)? {
                    handle_frame(frame, &command_tx, &stats, started).await?;
                }
            }
        }
    }

    Ok(())
}

async fn write_and_expect_hello(
    stream: &mut TcpStream,
    token: &str,
    nonce: &str,
    read_buffer: &mut BytesMut,
    timeout: Duration,
) -> Result<(), IpcError> {
    let handshake = async {
        let hello = Hello {
            nonce: nonce.to_string(),
            version: env!("CARGO_PKG_VERSION").to_string(),
            pid: std::process::id(),
        };

        let mut buffer = BytesMut::new();
        Frame::new(kind::HELLO, 0, to_json(&hello)?)?.encode_into(&mut buffer)?;
        stream.write_all(&buffer).await?;
        stream.flush().await?;

        let mut chunk = [0u8; 4096];

        loop {
            if let Some(frame) = Frame::decode(read_buffer)? {
                if frame.kind != kind::HELLO_ACK {
                    return Err(IpcError::UnexpectedFrame(frame.kind));
                }

                let ack: HelloAck =
                    from_json(&frame.payload).map_err(|_| IpcError::Unauthorized)?;
                verify_proof(token, nonce, &ack.proof)?;

                return Ok(());
            }

            let read = stream.read(&mut chunk).await?;

            if read == 0 {
                return Err(IpcError::Unauthorized);
            }

            read_buffer.extend_from_slice(&chunk[..read]);

            if read_buffer.len() > MAX_PAYLOAD + HEADER_SIZE * 2 {
                return Err(IpcError::BufferOverflow(read_buffer.len()));
            }
        }
    };

    tokio::time::timeout(timeout, handshake)
        .await
        .map_err(|_| IpcError::HandshakeTimeout)?
}

fn verify_proof(token: &str, nonce: &str, proof: &str) -> Result<(), IpcError> {
    if !is_canonical_lower_hex(nonce, NONCE_HEX_LEN)
        || !is_canonical_lower_hex(proof, PROOF_HEX_LEN)
    {
        return Err(IpcError::Unauthorized);
    }

    let proof_bytes = hex::decode(proof).map_err(|_| IpcError::Unauthorized)?;
    let mut mac =
        HmacSha256::new_from_slice(token.as_bytes()).map_err(|_| IpcError::Unauthorized)?;
    mac.update(PROOF_CONTEXT);
    mac.update(nonce.as_bytes());

    mac.verify_slice(&proof_bytes)
        .map_err(|_| IpcError::Unauthorized)
}

fn is_canonical_lower_hex(value: &str, expected_len: usize) -> bool {
    value.len() == expected_len
        && value
            .bytes()
            .all(|byte| matches!(byte, b'0'..=b'9' | b'a'..=b'f'))
}

fn generate_nonce() -> String {
    use rand::Rng;

    let mut bytes = [0u8; NONCE_BYTES];
    rand::rng().fill(&mut bytes[..]);
    hex::encode(bytes)
}

async fn handle_frame(
    frame: Frame,
    command_tx: &mpsc::Sender<Command>,
    stats: &Arc<Stats>,
    _started: Instant,
) -> Result<(), IpcError> {
    match frame.kind {
        kind::PAYLOAD_OUT => {
            let body = PayloadBody::decode(&frame.payload)?;

            stats
                .bytes_sent
                .fetch_add(body.data.len() as u64, Ordering::Relaxed);
            stats.payloads_sent.fetch_add(1, Ordering::Relaxed);

            if command_tx
                .send(Command::SendPayload {
                    channel: body.channel,
                    connection_id: body.connection_id,
                    data: body.data,
                })
                .await
                .is_err()
            {
                tracing::warn!("Komut kanali kapali, yuk atildi.");
            }
        }

        kind::CLOSE_CONNECTION => {
            let body = CloseBody::decode(&frame.payload)?;

            if command_tx
                .send(Command::Close {
                    connection_id: body.connection_id,
                    reason: body.reason,
                })
                .await
                .is_err()
            {
                tracing::warn!("Komut kanali kapali, kapatma istegi atildi.");
            }
        }

        // Ping is answered directly; it must not wait behind session work.
        kind::PING => {
            tracing::debug!("IPC ping alindi (cevap oturum katmanindan gonderilir).");
        }

        kind::HELLO_ACK => {
            tracing::warn!("Yinelenen HELLO_ACK yoksayildi.");
        }

        other => {
            tracing::warn!("Bilinmeyen IPC cerceve tipi: 0x{other:02X}");
        }
    }

    Ok(())
}

fn encode_event(event: IpcEvent) -> Result<(u8, Vec<u8>), IpcError> {
    match event {
        IpcEvent::PayloadIn(body) => Ok((kind::PAYLOAD_IN, body.encode()?)),
        IpcEvent::ConnectionOpen(open) => Ok((kind::CONNECTION_OPEN, to_json(&open)?)),
        IpcEvent::ConnectionClosed(closed) => Ok((kind::CONNECTION_CLOSED, to_json(&closed)?)),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use hmac::{Hmac, Mac};
    use sha2::Sha256;
    use tokio::net::TcpListener;

    fn proof_for(token: &str, nonce: &str) -> String {
        let mut mac = Hmac::<Sha256>::new_from_slice(token.as_bytes()).expect("valid HMAC key");
        mac.update(b"pickaxe-ipc-v1:");
        mac.update(nonce.as_bytes());
        hex::encode(mac.finalize().into_bytes())
    }

    async fn read_frame(stream: &mut TcpStream, buffer: &mut BytesMut) -> Frame {
        let mut chunk = [0u8; 4096];

        loop {
            if let Some(frame) = Frame::decode(buffer).expect("decode") {
                return frame;
            }

            let read = stream.read(&mut chunk).await.expect("read");
            assert!(read > 0, "peer closed before sending a frame");
            buffer.extend_from_slice(&chunk[..read]);
        }
    }

    async fn write_json_frame(stream: &mut TcpStream, kind: u8, value: &serde_json::Value) {
        let mut out = BytesMut::new();
        Frame::new(kind, 0, to_json(value).expect("json"))
            .expect("frame")
            .encode_into(&mut out)
            .expect("encode");
        stream.write_all(&out).await.expect("write");
    }

    /// The HELLO frame carries a fresh challenge but never the shared secret.
    #[tokio::test]
    async fn handshake_hello_omits_token_and_contains_canonical_nonce() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc(
                stream,
                "shared-secret".to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let hello = read_frame(&mut client, &mut buffer).await;

        assert_eq!(hello.kind, kind::HELLO);
        let json: serde_json::Value = from_json(&hello.payload).expect("hello JSON");
        assert_eq!(json.as_object().expect("object").len(), 3);
        assert!(json.get("token").is_none());

        let nonce = json["nonce"].as_str().expect("nonce");
        assert_eq!(nonce.len(), 64);
        assert!(nonce
            .bytes()
            .all(|byte| byte.is_ascii_digit() || (b'a'..=b'f').contains(&byte)));
        assert_eq!(json["version"], env!("CARGO_PKG_VERSION"));
        assert!(json["pid"].as_u64().expect("pid") > 0);

        drop(client);
        let _ = tokio::time::timeout(std::time::Duration::from_secs(1), server).await;
    }

    /// An acknowledgement containing the shared token is rejected even when
    /// its proof would otherwise be valid.
    #[tokio::test]
    async fn handshake_rejects_ack_with_token() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");
        let token = "shared-secret";

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc(
                stream,
                token.to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let hello = read_frame(&mut client, &mut buffer).await;
        let hello_json: serde_json::Value = from_json(&hello.payload).expect("hello JSON");
        let nonce = hello_json["nonce"].as_str().expect("nonce");

        write_json_frame(
            &mut client,
            kind::HELLO_ACK,
            &serde_json::json!({
                "proof": proof_for(token, nonce),
                "token": token,
            }),
        )
        .await;

        let result = server.await.expect("join");
        assert!(matches!(result, Err(IpcError::Unauthorized)));
    }

    /// A syntactically valid but incorrect proof is rejected.
    #[tokio::test]
    async fn handshake_rejects_wrong_proof() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc(
                stream,
                "shared-secret".to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let _hello = read_frame(&mut client, &mut buffer).await;

        write_json_frame(
            &mut client,
            kind::HELLO_ACK,
            &serde_json::json!({ "proof": "00".repeat(32) }),
        )
        .await;

        let result = server.await.expect("join");
        assert!(matches!(result, Err(IpcError::Unauthorized)));
    }

    /// A malformed proof is an authentication failure, not a protocol error.
    #[tokio::test]
    async fn handshake_rejects_malformed_proof() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc(
                stream,
                "shared-secret".to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let _hello = read_frame(&mut client, &mut buffer).await;

        write_json_frame(
            &mut client,
            kind::HELLO_ACK,
            &serde_json::json!({ "proof": "not-hex" }),
        )
        .await;

        let result = server.await.expect("join");
        assert!(matches!(result, Err(IpcError::Unauthorized)));
    }

    #[tokio::test]
    async fn handshake_accepts_correct_proof_and_reads_payload() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");
        let token = "shared-secret";

        let (event_tx, mut event_rx) = mpsc::channel(8);
        let (cmd_tx, mut cmd_rx) = mpsc::channel(8);

        let server_token = token.to_string();
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc(
                stream,
                server_token,
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let hello = read_frame(&mut client, &mut buffer).await;
        let hello_json: serde_json::Value = from_json(&hello.payload).expect("hello JSON");
        let nonce = hello_json["nonce"].as_str().expect("nonce");

        let mut out = BytesMut::new();
        Frame::new(
            kind::HELLO_ACK,
            0,
            to_json(&serde_json::json!({ "proof": proof_for(token, nonce) })).expect("json"),
        )
        .expect("frame")
        .encode_into(&mut out)
        .expect("encode");

        // Send one payload frame right after the handshake.
        let body = PayloadBody {
            channel: Channel::Reliable,
            connection_id: "c1".to_string(),
            data: b"bedrock-batch".to_vec(),
        };
        Frame::new(kind::PAYLOAD_OUT, 7, body.encode().expect("body"))
            .expect("frame")
            .encode_into(&mut out)
            .expect("encode");
        client.write_all(&out).await.expect("write");

        let command = tokio::time::timeout(std::time::Duration::from_secs(2), cmd_rx.recv())
            .await
            .expect("timeout")
            .expect("command");

        assert_eq!(
            command,
            Command::SendPayload {
                channel: Channel::Reliable,
                connection_id: "c1".to_string(),
                data: b"bedrock-batch".to_vec(),
            }
        );

        // Server → client event.
        event_tx
            .send(IpcEvent::ConnectionClosed(ConnectionClosed {
                connection_id: "c1".to_string(),
                reason: "bye".to_string(),
            }))
            .await
            .expect("send event");

        let mut chunk = [0u8; 4096];
        let event_frame = tokio::time::timeout(std::time::Duration::from_secs(2), async {
            loop {
                if let Some(frame) = Frame::decode(&mut buffer).expect("decode") {
                    return frame;
                }

                let read = client.read(&mut chunk).await.expect("read");
                buffer.extend_from_slice(&chunk[..read]);
            }
        })
        .await
        .expect("timeout");

        assert_eq!(event_frame.kind, kind::CONNECTION_CLOSED);

        drop(client);
        let _ = tokio::time::timeout(std::time::Duration::from_secs(2), server).await;
    }

    /// A peer that connects but never acknowledges the challenge is disconnected.
    #[tokio::test]
    async fn handshake_times_out_when_peer_stays_silent() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc_with_timeout(
                stream,
                "shared-secret".to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
                std::time::Duration::from_millis(50),
            )
            .await
        });

        let client = TcpStream::connect(addr).await.expect("connect");
        let result = tokio::time::timeout(std::time::Duration::from_secs(1), server)
            .await
            .expect("handshake timeout")
            .expect("join");

        assert!(matches!(result, Err(IpcError::HandshakeTimeout)));
        drop(client);
    }

    /// An unauthenticated peer cannot grow the handshake buffer without bound.
    #[tokio::test]
    async fn handshake_rejects_buffer_flood_before_timeout() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let addr = listener.local_addr().expect("addr");

        let (_event_tx, mut event_rx) = mpsc::channel(1);
        let (cmd_tx, _cmd_rx) = mpsc::channel(1);
        let server = tokio::spawn(async move {
            let (stream, _) = listener.accept().await.expect("accept");
            run_ipc_with_timeout(
                stream,
                "shared-secret".to_string(),
                cmd_tx,
                &mut event_rx,
                Arc::new(Stats::default()),
                std::time::Duration::from_secs(2),
            )
            .await
        });

        let mut client = TcpStream::connect(addr).await.expect("connect");
        let mut buffer = BytesMut::new();
        let _hello = read_frame(&mut client, &mut buffer).await;

        // Geçerli bir çerçeve başlığı + eksik yük: decoder hata vermez,
        // ancak el sıkışma tamponu sınırı aşılır.
        let mut flood = Vec::with_capacity(HEADER_SIZE + MAX_PAYLOAD + HEADER_SIZE * 2 + 1);
        flood.extend_from_slice(&0x504Bu16.to_be_bytes());
        flood.push(1);
        flood.push(kind::HELLO_ACK);
        flood.extend_from_slice(&0u64.to_be_bytes());
        flood.extend_from_slice(&(MAX_PAYLOAD as u32).to_be_bytes());
        flood.resize(HEADER_SIZE + MAX_PAYLOAD + HEADER_SIZE * 2 + 1, 0);
        client.write_all(&flood).await.expect("write flood");

        let result = tokio::time::timeout(std::time::Duration::from_secs(3), server)
            .await
            .expect("buffer overflow timeout")
            .expect("join");

        assert!(matches!(result, Err(IpcError::BufferOverflow(_))));
    }

    /// The verifier rejects malformed challenge and proof encodings.
    #[test]
    fn proof_verification_rejects_malformed_nonce_and_proof() {
        let nonce = "0123456789abcdef".repeat(4);
        let proof = "ab".repeat(32);

        assert!(matches!(
            verify_proof("shared-secret", "not-a-nonce", &proof),
            Err(IpcError::Unauthorized)
        ));
        assert!(matches!(
            verify_proof("shared-secret", &nonce, "not-hex"),
            Err(IpcError::Unauthorized)
        ));
    }

    /// A captured proof cannot authenticate a different challenge nonce.
    #[test]
    fn proof_cannot_be_reused_for_different_nonce() {
        let nonce = "11".repeat(32);
        let other_nonce = "22".repeat(32);
        let proof = proof_for("shared-secret", &nonce);

        assert!(verify_proof("shared-secret", &nonce, &proof).is_ok());
        assert!(matches!(
            verify_proof("shared-secret", &other_nonce, &proof),
            Err(IpcError::Unauthorized)
        ));
    }

    /// Uppercase hex is not the canonical proof encoding and is rejected.
    #[test]
    fn proof_verification_rejects_uppercase_proof() {
        let nonce = "11".repeat(32);
        let proof = proof_for("shared-secret", &nonce).to_uppercase();

        assert!(matches!(
            verify_proof("shared-secret", &nonce, &proof),
            Err(IpcError::Unauthorized)
        ));
    }

    /// The same fixed vector is pinned by the PHP IpcChallenge test suite.
    #[test]
    fn proof_verification_matches_cross_language_vector() {
        let nonce = "11".repeat(32);
        let proof = "b8a957da878e7493fa92a0edcfce62b91e784d83f1601580adad649f26c0dd4b";

        assert!(verify_proof("shared-secret", &nonce, proof).is_ok());
    }

    /// Every generated challenge encodes exactly 32 random bytes canonically.
    #[test]
    fn generated_nonce_is_canonical_32_bytes() {
        let nonce = generate_nonce();

        assert_eq!(nonce.len(), 64);
        assert!(nonce
            .bytes()
            .all(|byte| byte.is_ascii_digit() || (b'a'..=b'f').contains(&byte)));
        assert_ne!(nonce, generate_nonce());
    }
}
