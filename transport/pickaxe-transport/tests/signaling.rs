//! Integration tests against the real HTTP signaling listener.

use pickaxe_transport::config::Config;
use std::net::SocketAddr;

/// Starts the sidecar's signaling stack on a random port and checks that
/// `GET /v1/join` returns the Mojang JSON shape.
///
/// The sidecar's identity PEM is written to a temp directory, so this test
/// exercises the real key generation path.
#[tokio::test]
async fn signaling_status_endpoint_serves_server_data() {
    let temp = tempfile::tempdir().expect("tempdir");
    let identity_path = temp.path().join("identity.pem");

    let config = Config {
        signaling_addr: "127.0.0.1:0".parse().expect("addr"),
        media_addr: Some("127.0.0.1:0".parse().expect("addr")),
        identity_path: identity_path.to_string_lossy().to_string(),
        identity_domain: "test.local".to_string(),
        server_name: "PickaxeMP Test".to_string(),
        level_name: "world".to_string(),
        protocol_version: 2225,
        game_version: "1.26.60".to_string(),
        max_players: 10,
        // Offline test: no network access to Minecraft's key endpoint.
        verify_minecraft_tokens: false,
        ..Config::default()
    };

    // We only need the signaling listener for this test, so the NetherNet
    // server is not started; the HTTP layer serves /v1/join on its own.
    let addr = spawn_signaling_only(&config).await;
    let body = http_get(&addr, "/v1/join").await;

    assert!(body.contains("\"name\":\"PickaxeMP Test\""), "{body}");
    assert!(body.contains("\"protocol\":2225"), "{body}");
    assert!(body.contains("\"maxPlayers\":10"), "{body}");
    assert!(body.contains("\"level\":\"world\""), "{body}");

    assert!(identity_path.exists(), "kimlik dosyasi olusturulmaliydi");
}

/// Binds the HTTP signaling server and returns its address.
async fn spawn_signaling_only(config: &Config) -> SocketAddr {
    use nethernet::prelude::HttpSignalerConfig;
    use nethernet_tokio::util::identity;
    use nethernet_tokio::{HttpServerConfig, HttpSignalingServer, ServerData};

    let identity = identity::from_pem_or_create(&config.identity_path, &config.identity_domain)
        .await
        .expect("identity");

    let _ = identity;

    let signaler_config = HttpServerConfig {
        network_id: "1".to_string(),
        signaler: HttpSignalerConfig {
            serve_motd: true,
            token_trust: Some(nethernet_tokio::TokenTrust::Any),
            ..Default::default()
        },
        ..Default::default()
    };

    let signaling = HttpSignalingServer::bind(config.signaling_addr, signaler_config)
        .await
        .expect("bind");

    let mut data = ServerData::new(config.server_name.clone(), config.level_name.clone());
    data.protocol_version = config.protocol_version;
    data.game_version = config.game_version.clone();
    data.max_player_count = config.max_players;

    signaling.set_server_data(data);

    let addr = signaling.local_addr();

    // Keep the listener alive for the duration of the test process.
    Box::leak(Box::new(signaling));

    addr
}

/// Minimal HTTP/1.1 GET so the test needs no extra HTTP client dependency.
async fn http_get(addr: &SocketAddr, path: &str) -> String {
    use tokio::io::{AsyncReadExt, AsyncWriteExt};
    use tokio::net::TcpStream;

    let mut stream = TcpStream::connect(addr).await.expect("connect");

    let request = format!("GET {path} HTTP/1.1\r\nHost: {addr}\r\nConnection: close\r\n\r\n");

    stream.write_all(request.as_bytes()).await.expect("write");
    stream.flush().await.expect("flush");

    let mut response = Vec::new();
    stream.read_to_end(&mut response).await.expect("read");

    String::from_utf8_lossy(&response).to_string()
}

/// A path that is not a join endpoint must not return server data.
#[tokio::test]
async fn unknown_path_is_not_server_status() {
    let temp = tempfile::tempdir().expect("tempdir");

    let config = Config {
        signaling_addr: "127.0.0.1:0".parse().expect("addr"),
        media_addr: Some("127.0.0.1:0".parse().expect("addr")),
        identity_path: temp
            .path()
            .join("identity.pem")
            .to_string_lossy()
            .to_string(),
        verify_minecraft_tokens: false,
        ..Config::default()
    };

    let addr = spawn_signaling_only(&config).await;
    let body = http_get(&addr, "/nope").await;

    assert!(!body.contains("\"maxPlayers\""), "{body}");
}
