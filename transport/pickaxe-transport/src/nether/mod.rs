//! NetherNet transport: HTTP signaling plus WebRTC sessions.
//!
//! Responsibilities:
//! - Serve `GET /v1/join` (server-list metadata) and `POST /v1/join/{id}`.
//! - Sign SDP answers with the operator identity (`a=identity`, ES384).
//! - Validate client offers against Minecraft's published token keys.
//! - Carry opaque Bedrock payloads to the PHP kernel over IPC.
//!
//! This module never parses Bedrock packets.

use crate::config::Config;
use crate::ipc::{Channel, ConnectionClosed, ConnectionOpen};
use crate::ipc::{Command, IpcEvent, PayloadBody, Stats};
use bytes::Bytes;
use nethernet::prelude::HttpSignalerConfig;
use nethernet_tokio::util::{identity, jwks, tls};
use nethernet_tokio::{
    AcceptedSession, ConnectionConfig, HttpServerConfig, HttpSignalingServer, NetherServer,
    ServerData, TokenTrust,
};
use std::collections::HashMap;
use std::sync::atomic::Ordering;
use std::sync::Arc;
use tokio::sync::{mpsc, Mutex};

/// Errors from the NetherNet layer.
#[derive(Debug, thiserror::Error)]
pub enum NetherError {
    #[error("NetherNet hatasi: {0}")]
    Inner(String),

    #[error("kimlik dosyasi hazirlanamadi: {0}")]
    Identity(String),

    #[error("TLS yapilandirmasi gecersiz: {0}")]
    Tls(String),

    #[error("Minecraft token anahtarlari alinamadi: {0}")]
    Jwks(String),
}

impl From<nethernet_tokio::NetherError> for NetherError {
    fn from(error: nethernet_tokio::NetherError) -> Self {
        Self::Inner(error.to_string())
    }
}

/// Builds the server-list metadata advertised to clients.
pub fn server_data(config: &Config, players: i32) -> ServerData {
    let mut data = ServerData::new(config.server_name.clone(), config.level_name.clone());
    data.protocol_version = config.protocol_version;
    data.game_version = config.game_version.clone();
    data.max_player_count = config.max_players;
    data.player_count = players;
    data.game_type = config.game_type;

    data
}

/// Runs the NetherNet listener until the command channel closes.
///
/// `ready` receives the bound signaling address once the HTTP listener is up,
/// so the caller can report the real port when `0` was requested.
pub async fn run_nether(
    config: Config,
    mut command_rx: mpsc::Receiver<Command>,
    event_tx: mpsc::Sender<IpcEvent>,
    stats: Arc<Stats>,
    ready: Option<tokio::sync::oneshot::Sender<std::net::SocketAddr>>,
) -> Result<(), NetherError> {
    // --- Identity --------------------------------------------------------
    // Created once, then reused. Clients pin this key, so regenerating it would
    // re-prompt every player.
    let server_identity =
        identity::from_pem_or_create(&config.identity_path, &config.identity_domain)
            .await
            .map_err(|e| NetherError::Identity(e.to_string()))?;

    tracing::info!(
        path = %config.identity_path,
        domain = %config.identity_domain,
        "Sunucu kimligi hazir."
    );

    // --- TLS (optional) --------------------------------------------------
    let tls_config = match (&config.tls_cert_path, &config.tls_key_path) {
        (Some(cert), Some(key)) => Some(
            tls::from_pem(cert, key)
                .await
                .map_err(|e| NetherError::Tls(e.to_string()))?,
        ),
        (None, None) => None,
        _ => {
            return Err(NetherError::Tls(
                "hem sertifika hem ozel anahtar gerekir".to_string(),
            ))
        }
    };

    // --- Token trust -----------------------------------------------------
    // `None` would skip offer validation entirely, so it is never used: at
    // minimum the token is bound to the DTLS certificate.
    let token_trust = if config.verify_minecraft_tokens {
        let keys = jwks::Jwks::minecraft()
            .await
            .map_err(|e| NetherError::Jwks(e.to_string()))?;

        Some(keys.trust().await)
    } else {
        tracing::warn!("Minecraft token dogrulamasi KAPALI. Yalnizca yerel test icin kullanin.");
        Some(TokenTrust::Any)
    };

    // --- Signaling -------------------------------------------------------
    let signaler_config = HttpServerConfig {
        network_id: generate_network_id(),
        signaler: HttpSignalerConfig {
            serve_motd: true,
            max_pending_joins: config.max_pending_joins,
            max_connections_per_address: config.max_connections_per_address,
            token_trust: token_trust.clone(),
            ..Default::default()
        },
        tls: tls_config,
        ..Default::default()
    };

    let signaling = HttpSignalingServer::bind(config.signaling_addr, signaler_config)
        .await
        .map_err(|e| NetherError::Inner(e.to_string()))?;

    signaling.set_server_data(server_data(&config, 0));

    let bound_addr = signaling.local_addr();

    if let Some(ready) = ready {
        let _ = ready.send(bound_addr);
    }

    tracing::info!(
        addr = %bound_addr,
        tls = config.tls_cert_path.is_some(),
        "HTTP signaling dinleniyor."
    );

    // --- NetherNet server ------------------------------------------------
    let connection_config = ConnectionConfig {
        identity: Some(Arc::new(server_identity)),
        // Never allow unsigned answers: the vanilla client refuses them
        // (Mojang guide, section 5.2).
        allow_unsigned_answers: false,
        token_trust,
        infer_peer_candidates: true,
        max_pending: config.max_pending_joins,
        media_addr: config.media_addr,
        ..Default::default()
    };

    let mut listener = NetherServer::bind_with(signaling, connection_config)
        .await
        .map_err(|e| NetherError::Inner(e.to_string()))?;

    tracing::info!("NetherNet sunucusu hazir, istemci bekleniyor.");

    let sessions: Arc<Mutex<HashMap<String, nethernet_tokio::Session>>> = Arc::default();

    // --- Command pump ----------------------------------------------------
    {
        let sessions = sessions.clone();
        let stats = stats.clone();

        tokio::spawn(async move {
            while let Some(command) = command_rx.recv().await {
                match command {
                    Command::SendPayload {
                        channel,
                        connection_id,
                        data,
                    } => {
                        let session = sessions.lock().await.get(&connection_id).cloned();

                        let Some(session) = session else {
                            tracing::debug!(
                                connection_id,
                                "Bilinmeyen baglantiya yuk gonderilmek istendi."
                            );
                            continue;
                        };

                        let bytes = Bytes::from(data);
                        let length = bytes.len() as u64;

                        let result = match channel {
                            Channel::Reliable => session.send(bytes).await,
                            Channel::Unreliable => session.send_unreliable(bytes).await,
                        };

                        match result {
                            Ok(()) => {
                                stats.payloads_sent.fetch_add(1, Ordering::Relaxed);
                                stats.bytes_sent.fetch_add(length, Ordering::Relaxed);
                            }
                            Err(error) => {
                                tracing::warn!(
                                    connection_id,
                                    error = %error,
                                    "Yuk gonderilemedi."
                                );
                            }
                        }
                    }

                    Command::Close {
                        connection_id,
                        reason,
                    } => {
                        let session = sessions.lock().await.remove(&connection_id);

                        if let Some(session) = session {
                            tracing::info!(connection_id, reason, "Baglanti kapatiliyor.");
                            let _ = session.close().await;
                        }
                    }
                }
            }

            tracing::info!("Komut kanali kapandi; oturum pompalayicisi durdu.");
        });
    }

    // --- Accept loop -----------------------------------------------------
    loop {
        let accepted = listener.accept().await;

        let AcceptedSession {
            session,
            mut reliable,
            mut unreliable,
        } = match accepted {
            Ok(accepted) => accepted,
            Err(error) => {
                // A single failed handshake must not kill the listener.
                tracing::warn!(error = %error, "Baglanti kabul edilemedi.");
                continue;
            }
        };

        let connection_id = generate_connection_id();
        let remote = session.remote_addr().await;
        let player = session.player().await;

        sessions
            .lock()
            .await
            .insert(connection_id.clone(), session.clone());

        stats.active_connections.fetch_add(1, Ordering::Relaxed);
        stats.total_connections.fetch_add(1, Ordering::Relaxed);

        let open = ConnectionOpen {
            connection_id: connection_id.clone(),
            network_id: remote.network_id.clone(),
            remote_address: remote
                .socket_addr
                .map(|addr| addr.to_string())
                .unwrap_or_else(|| remote.network_id.clone()),
            xuid: player.as_ref().and_then(|p| p.xuid.clone()),
            display_name: player.as_ref().and_then(|p| p.display_name.clone()),
        };

        tracing::info!(
            connection_id,
            remote = %open.remote_address,
            xuid = open.xuid.as_deref().unwrap_or("-"),
            "Oyuncu baglandi."
        );

        if event_tx.send(IpcEvent::ConnectionOpen(open)).await.is_err() {
            tracing::error!("Olay kanali kapali; yeni baglanti kabul edilemez.");
            break;
        }

        // Reliable channel reader.
        {
            let event_tx = event_tx.clone();
            let stats = stats.clone();
            let connection_id = connection_id.clone();
            let reliable_session = session.clone();

            tokio::spawn(async move {
                read_loop(
                    connection_id,
                    Channel::Reliable,
                    &mut reliable,
                    event_tx,
                    stats,
                    reliable_session,
                )
                .await;
            });
        }

        // Unreliable channel reader.
        {
            let event_tx = event_tx.clone();
            let stats = stats.clone();
            let connection_id = connection_id.clone();
            let unreliable_session = session.clone();

            tokio::spawn(async move {
                read_loop(
                    connection_id,
                    Channel::Unreliable,
                    &mut unreliable,
                    event_tx,
                    stats,
                    unreliable_session,
                )
                .await;
            });
        }
    }

    for (_, session) in sessions.lock().await.drain() {
        let _ = session.close().await;
    }

    Ok(())
}

/// Reads one data channel until it closes or errors, reporting each payload.
async fn read_loop(
    connection_id: String,
    channel: Channel,
    receiver: &mut nethernet_tokio::SessionReceiver,
    event_tx: mpsc::Sender<IpcEvent>,
    stats: Arc<Stats>,
    session: nethernet_tokio::Session,
) {
    loop {
        match receiver.recv().await {
            Ok(Some(data)) => {
                stats.payloads_received.fetch_add(1, Ordering::Relaxed);
                stats
                    .bytes_received
                    .fetch_add(data.len() as u64, Ordering::Relaxed);

                let body = PayloadBody {
                    channel,
                    connection_id: connection_id.clone(),
                    data: data.to_vec(),
                };

                if event_tx.send(IpcEvent::PayloadIn(body)).await.is_err() {
                    tracing::warn!(connection_id, "Olay kanali kapali, okuma durdu.");
                    return;
                }
            }

            Ok(None) => {
                stats.active_connections.fetch_sub(1, Ordering::Relaxed);
                let _ = event_tx
                    .send(IpcEvent::ConnectionClosed(ConnectionClosed {
                        connection_id: connection_id.clone(),
                        reason: "peer_closed".to_string(),
                    }))
                    .await;

                let _ = session.close().await;
                return;
            }

            Err(error) => {
                stats.active_connections.fetch_sub(1, Ordering::Relaxed);
                tracing::warn!(connection_id, error = %error, "Kanal hatasi.");

                let _ = event_tx
                    .send(IpcEvent::ConnectionClosed(ConnectionClosed {
                        connection_id: connection_id.clone(),
                        reason: format!("channel_error: {error}"),
                    }))
                    .await;

                let _ = session.close().await;
                return;
            }
        }
    }
}

/// Random opaque id for the connection map.
fn generate_connection_id() -> String {
    use rand::Rng;

    let mut rng = rand::rng();
    let value: u64 = rng.random();

    format!("c{value:016x}")
}

/// Random network id advertised by the signaling server.
fn generate_network_id() -> String {
    use rand::Rng;

    let mut rng = rand::rng();
    let value: u64 = rng.random();

    value.to_string()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn server_data_carries_config() {
        let config = Config {
            server_name: "Test Server".to_string(),
            level_name: "world".to_string(),
            protocol_version: 2225,
            game_version: "1.26.60".to_string(),
            max_players: 7,
            game_type: 1,
            ..Config::default()
        };

        let data = server_data(&config, 3);

        assert_eq!(data.server_name, "Test Server");
        assert_eq!(data.protocol_version, 2225);
        assert_eq!(data.game_version, "1.26.60");
        assert_eq!(data.max_player_count, 7);
        assert_eq!(data.player_count, 3);
        assert_eq!(data.game_type, 1);
    }

    #[test]
    fn server_data_json_matches_mojang_shape() {
        let config = Config {
            server_name: "PickaxeMP".to_string(),
            ..Config::default()
        };

        let json = server_data(&config, 0).to_json();

        for field in [
            "name",
            "protocol",
            "version",
            "level",
            "players",
            "maxPlayers",
            "gameType",
        ] {
            assert!(json.contains(field), "eksik alan: {field} -> {json}");
        }
    }

    #[test]
    fn connection_ids_are_unique_and_bounded() {
        let a = generate_connection_id();
        let b = generate_connection_id();

        assert_ne!(a, b);
        assert!(a.starts_with('c'));
        assert!(a.len() <= crate::ipc::MAX_ID_LEN);
    }

    #[test]
    fn network_id_is_numeric() {
        let id = generate_network_id();
        assert!(id.parse::<u64>().is_ok());
    }
}
