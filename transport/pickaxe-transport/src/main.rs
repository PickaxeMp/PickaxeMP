//! Sidecar entry point.
//!
//! Starts the IPC listener first (so the PHP kernel can connect), prints its
//! address and a one-time token on stdout as a single machine-readable line,
//! then runs the NetherNet listener.
//!
//! Printing to stdout is deliberate: the PHP side reads it to learn the port
//! and token. Nothing secret is ever logged to stderr.

use pickaxe_transport::config::Config;
use pickaxe_transport::ipc::{self, Command, IpcEvent, Stats};
use pickaxe_transport::nether;
use std::sync::Arc;
use tokio::net::TcpListener;
use tokio::sync::mpsc;

fn main() -> std::process::ExitCode {
    tracing_subscriber::fmt()
        .with_env_filter(
            tracing_subscriber::EnvFilter::try_from_default_env()
                .unwrap_or_else(|_| tracing_subscriber::EnvFilter::new("info")),
        )
        .with_writer(std::io::stderr)
        .init();

    let config = match Config::from_args(std::env::args().skip(1)) {
        Ok(config) => config,
        Err(error) => {
            eprintln!("[pickaxe-transport] yapilandirma hatasi: {error}");
            return std::process::ExitCode::from(64);
        }
    };

    let runtime = match tokio::runtime::Builder::new_multi_thread()
        .enable_all()
        .build()
    {
        Ok(runtime) => runtime,
        Err(error) => {
            eprintln!("[pickaxe-transport] calisma zamani kurulamadi: {error}");
            return std::process::ExitCode::from(70);
        }
    };

    match runtime.block_on(run(config)) {
        Ok(()) => std::process::ExitCode::SUCCESS,
        Err(error) => {
            eprintln!("[pickaxe-transport] hata: {error}");
            std::process::ExitCode::from(70)
        }
    }
}

async fn run(config: Config) -> Result<(), Box<dyn std::error::Error>> {
    let token = generate_token();
    let stats = Arc::new(Stats::default());

    let listener = TcpListener::bind("127.0.0.1:0").await?;
    let ipc_addr = listener.local_addr()?;

    // Single line, parsed by SidecarProcess in PHP. The token is here and not in
    // a log file so that it never persists on disk.
    println!("PICKAXE_IPC {} {}", ipc_addr.port(), token);
    use std::io::Write;
    std::io::stdout().flush()?;

    tracing::info!(
        port = ipc_addr.port(),
        "IPC dinleniyor (yalnizca localhost)."
    );

    let (command_tx, command_rx) = mpsc::channel::<Command>(256);
    let (event_tx, event_rx) = mpsc::channel::<IpcEvent>(256);
    let (ready_tx, ready_rx) = tokio::sync::oneshot::channel();

    let nether_config = config.clone();
    let nether_stats = stats.clone();

    let nether_task = tokio::spawn(async move {
        if let Err(error) = nether::run_nether(
            nether_config,
            command_rx,
            event_tx,
            nether_stats,
            Some(ready_tx),
        )
        .await
        {
            tracing::error!(error = %error, "NetherNet katmani durdu.");
        }
    });

    // Report the real signaling address once the listener is bound, so the PHP
    // side can read it even when port 0 (any free port) was requested.
    if let Ok(Ok(addr)) = tokio::time::timeout(std::time::Duration::from_secs(30), ready_rx).await {
        println!("PICKAXE_SIGNALING {addr}");
        use std::io::Write;
        std::io::stdout().flush()?;
    }

    // Accept the PHP kernel peer. A failed authentication attempt must not
    // terminate the sidecar: otherwise any local process can occupy the single
    // IPC slot and prevent the server from starting.
    run_ipc_accept_loop(listener, token, command_tx.clone(), event_rx, stats).await?;

    drop(command_tx);
    nether_task.abort();

    Ok(())
}

/// Serves the IPC listener until an authenticated peer disconnects.
///
/// Unauthenticated or malformed peers are rejected without consuming the
/// listener slot. The event receiver is shared across attempts so a failed
/// handshake cannot silently discard NetherNet events.
async fn run_ipc_accept_loop(
    listener: TcpListener,
    token: String,
    command_tx: mpsc::Sender<Command>,
    mut event_rx: mpsc::Receiver<IpcEvent>,
    stats: Arc<Stats>,
) -> Result<(), std::io::Error> {
    loop {
        let (stream, remote) = listener.accept().await?;

        if !remote.ip().is_loopback() {
            tracing::warn!(%remote, "Localhost disi IPC baglantisi reddedildi.");
            continue;
        }

        tracing::info!(%remote, "IPC istemcisi kabul edildi.");

        let result = ipc::run_ipc(
            stream,
            token.clone(),
            command_tx.clone(),
            &mut event_rx,
            stats.clone(),
        )
        .await;

        match result {
            Ok(()) => {
                tracing::info!("IPC baglantisi kapandi, kapaniyor.");
                break;
            }
            Err(error) => {
                tracing::warn!(error = %error, "IPC oturumu basarisiz; dinleyici acik kalir.");
                tokio::time::sleep(std::time::Duration::from_millis(100)).await;
            }
        }
    }

    Ok(())
}

/// 32-byte hex token from the OS CSPRNG.
fn generate_token() -> String {
    use rand::Rng;

    let mut rng = rand::rng();
    let mut bytes = [0u8; 32];
    rng.fill(&mut bytes[..]);

    bytes.iter().map(|byte| format!("{byte:02x}")).collect()
}

#[cfg(test)]
mod tests {
    use super::*;
    use bytes::BytesMut;
    use hmac::{Hmac, Mac};
    use pickaxe_transport::ipc::{kind, Channel, Frame, PayloadBody};
    use sha2::Sha256;
    use tokio::io::{AsyncReadExt, AsyncWriteExt};
    use tokio::net::TcpStream;

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

    async fn write_json_frame(stream: &mut TcpStream, frame_kind: u8, value: serde_json::Value) {
        let mut out = BytesMut::new();
        Frame::new(
            frame_kind,
            0,
            serde_json::to_vec(&value).expect("json"),
        )
        .expect("frame")
        .encode_into(&mut out)
        .expect("encode");
        stream.write_all(&out).await.expect("write");
    }

    fn proof_for(token: &str, nonce: &str) -> String {
        let mut mac = Hmac::<Sha256>::new_from_slice(token.as_bytes()).expect("valid HMAC key");
        mac.update(b"pickaxe-ipc-v1:");
        mac.update(nonce.as_bytes());
        hex::encode(mac.finalize().into_bytes())
    }

    #[tokio::test]
    async fn failed_handshake_does_not_consume_the_ipc_slot() {
        let listener = TcpListener::bind("127.0.0.1:0").await.expect("bind");
        let address = listener.local_addr().expect("addr");
        let (command_tx, mut command_rx) = mpsc::channel(8);
        let (_event_tx, event_rx) = mpsc::channel(8);
        let stats = Arc::new(Stats::default());

        let server = tokio::spawn(run_ipc_accept_loop(
            listener,
            "shared-secret".to_string(),
            command_tx,
            event_rx,
            stats,
        ));

        // Kimlik doğrulamayan ilk bağlantı sidecar'ı öldürmemeli.
        let mut attacker = TcpStream::connect(address).await.expect("attacker connect");
        let mut attacker_buffer = BytesMut::new();
        let hello = read_frame(&mut attacker, &mut attacker_buffer).await;
        let attacker_json: serde_json::Value =
            serde_json::from_slice(&hello.payload).expect("hello json");
        let attacker_nonce = attacker_json["nonce"].as_str().expect("nonce");

        write_json_frame(
            &mut attacker,
            kind::HELLO_ACK,
            serde_json::json!({ "proof": proof_for("wrong-token", attacker_nonce) }),
        )
        .await;
        drop(attacker);

        // Aynı listener'a geçerli PHP çekirdeği bağlanabilmeli.
        let mut peer = TcpStream::connect(address).await.expect("valid peer connect");
        let mut peer_buffer = BytesMut::new();
        let hello = read_frame(&mut peer, &mut peer_buffer).await;
        let hello_json: serde_json::Value =
            serde_json::from_slice(&hello.payload).expect("hello json");
        let nonce = hello_json["nonce"].as_str().expect("nonce");

        write_json_frame(
            &mut peer,
            kind::HELLO_ACK,
            serde_json::json!({ "proof": proof_for("shared-secret", nonce) }),
        )
        .await;

        let body = PayloadBody {
            channel: Channel::Reliable,
            connection_id: "c1".to_string(),
            data: b"bedrock-batch".to_vec(),
        }
        .encode()
        .expect("body");
        let mut out = BytesMut::new();
        Frame::new(kind::PAYLOAD_OUT, 7, body)
            .expect("frame")
            .encode_into(&mut out)
            .expect("encode");
        peer.write_all(&out).await.expect("write payload");

        let command = tokio::time::timeout(std::time::Duration::from_secs(2), command_rx.recv())
            .await
            .expect("command timeout")
            .expect("command");
        assert_eq!(
            command,
            Command::SendPayload {
                channel: Channel::Reliable,
                connection_id: "c1".to_string(),
                data: b"bedrock-batch".to_vec(),
            }
        );

        drop(peer);
        server.await.expect("join").expect("accept loop");
    }

    #[test]
    fn token_is_64_hex_chars() {
        let token = generate_token();

        assert_eq!(token.len(), 64);
        assert!(token.chars().all(|c| c.is_ascii_hexdigit()));
    }

    #[test]
    fn tokens_differ() {
        assert_ne!(generate_token(), generate_token());
    }
}
