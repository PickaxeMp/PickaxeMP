//! Runtime configuration for the sidecar.
//!
//! Every value can be overridden from the command line; the PHP kernel passes
//! them explicitly so that a single source of truth (`config.yml`) governs both
//! processes.

use std::net::SocketAddr;

/// How the sidecar was asked to run.
#[derive(Debug, Clone)]
pub struct Config {
    /// TCP address for NetherNet HTTP signaling (vanilla clients call this).
    pub signaling_addr: SocketAddr,

    /// UDP address WebRTC media is bound to. `0.0.0.0:0` shares one random port.
    pub media_addr: Option<SocketAddr>,

    /// Where the server identity PEM lives. Created on first run, then reused.
    pub identity_path: String,

    /// Operator domain shown to clients in the first-use prompt.
    pub identity_domain: String,

    /// Optional TLS certificate chain (PEM). Enables HTTPS signaling.
    pub tls_cert_path: Option<String>,

    /// Optional TLS private key (PEM).
    pub tls_key_path: Option<String>,

    /// Server list name shown in the client's server browser.
    pub server_name: String,

    /// World name reported to clients.
    pub level_name: String,

    /// Bedrock protocol version reported to clients.
    pub protocol_version: u32,

    /// Bedrock game version string, e.g. "1.26.60".
    pub game_version: String,

    /// Maximum number of players advertised.
    pub max_players: i32,

    /// Default game type: 0 = survival, 1 = creative, 2 = adventure.
    pub game_type: u8,

    /// Validate client tokens against Minecraft's published keys.
    ///
    /// When false, any well-formed unexpired token is accepted. This is still
    /// signature-bound to the DTLS certificate, but it does **not** prove the
    /// player owns the account — only use it for local testing.
    pub verify_minecraft_tokens: bool,

    /// Concurrent joins waiting for an answer; extra joins get HTTP 503.
    pub max_pending_joins: usize,

    /// Connections allowed per peer address.
    pub max_connections_per_address: usize,
}

impl Default for Config {
    fn default() -> Self {
        Self {
            signaling_addr: "0.0.0.0:19132".parse().expect("valid literal"),
            media_addr: Some("0.0.0.0:0".parse().expect("valid literal")),
            identity_path: "run/server-identity.pem".to_string(),
            identity_domain: "pickaxe.local".to_string(),
            tls_cert_path: None,
            tls_key_path: None,
            server_name: "PickaxeMP Server".to_string(),
            level_name: "world".to_string(),
            protocol_version: 2225,
            game_version: "1.26.60".to_string(),
            max_players: 20,
            game_type: 0,
            verify_minecraft_tokens: true,
            max_pending_joins: 64,
            max_connections_per_address: 16,
        }
    }
}

/// Errors produced while parsing arguments.
#[derive(Debug, thiserror::Error)]
pub enum ConfigError {
    #[error("bilinmeyen arguman: {0}")]
    UnknownArgument(String),

    #[error("gecersiz deger ({flag}): {value}")]
    InvalidValue { flag: String, value: String },

    #[error("eksik deger: {0}")]
    MissingValue(String),

    #[error("TLS icin hem --tls-cert hem --tls-key gerekir")]
    IncompleteTls,
}

impl Config {
    /// Parses `--flag value` pairs.
    ///
    /// Unknown flags are rejected rather than ignored: a typo in the PHP side
    /// must not silently produce a server with defaults.
    pub fn from_args<I, S>(args: I) -> Result<Self, ConfigError>
    where
        I: IntoIterator<Item = S>,
        S: Into<String>,
    {
        let mut config = Self::default();

        let args: Vec<String> = args.into_iter().map(Into::into).collect();
        let mut index = 0;

        while index < args.len() {
            let flag = args[index].clone();
            index += 1;

            let mut take_value = |flag: &str| -> Result<String, ConfigError> {
                let value = args
                    .get(index)
                    .cloned()
                    .ok_or_else(|| ConfigError::MissingValue(flag.to_string()))?;
                index += 1;
                Ok(value)
            };

            match flag.as_str() {
                "--signaling" => {
                    let value = take_value("--signaling")?;
                    config.signaling_addr =
                        value.parse().map_err(|_| ConfigError::InvalidValue {
                            flag: "--signaling".to_string(),
                            value,
                        })?;
                }
                "--media" => {
                    let value = take_value("--media")?;
                    config.media_addr = if value == "none" {
                        None
                    } else {
                        Some(value.parse().map_err(|_| ConfigError::InvalidValue {
                            flag: "--media".to_string(),
                            value,
                        })?)
                    };
                }
                "--identity-path" => config.identity_path = take_value("--identity-path")?,
                "--identity-domain" => config.identity_domain = take_value("--identity-domain")?,
                "--tls-cert" => config.tls_cert_path = Some(take_value("--tls-cert")?),
                "--tls-key" => config.tls_key_path = Some(take_value("--tls-key")?),
                "--server-name" => config.server_name = take_value("--server-name")?,
                "--level-name" => config.level_name = take_value("--level-name")?,
                "--protocol-version" => {
                    let value = take_value("--protocol-version")?;
                    config.protocol_version =
                        value.parse().map_err(|_| ConfigError::InvalidValue {
                            flag: "--protocol-version".to_string(),
                            value,
                        })?;
                }
                "--game-version" => config.game_version = take_value("--game-version")?,
                "--max-players" => {
                    let value = take_value("--max-players")?;
                    config.max_players = value.parse().map_err(|_| ConfigError::InvalidValue {
                        flag: "--max-players".to_string(),
                        value,
                    })?;
                }
                "--game-type" => {
                    let value = take_value("--game-type")?;
                    config.game_type = value.parse().map_err(|_| ConfigError::InvalidValue {
                        flag: "--game-type".to_string(),
                        value,
                    })?;
                }
                "--no-token-verification" => config.verify_minecraft_tokens = false,
                "--max-pending-joins" => {
                    let value = take_value("--max-pending-joins")?;
                    config.max_pending_joins =
                        value.parse().map_err(|_| ConfigError::InvalidValue {
                            flag: "--max-pending-joins".to_string(),
                            value,
                        })?;
                }
                "--max-connections-per-address" => {
                    let value = take_value("--max-connections-per-address")?;
                    config.max_connections_per_address =
                        value.parse().map_err(|_| ConfigError::InvalidValue {
                            flag: "--max-connections-per-address".to_string(),
                            value,
                        })?;
                }
                other => return Err(ConfigError::UnknownArgument(other.to_string())),
            }
        }

        if config.tls_cert_path.is_some() != config.tls_key_path.is_some() {
            return Err(ConfigError::IncompleteTls);
        }

        if config.max_players < 1 {
            return Err(ConfigError::InvalidValue {
                flag: "--max-players".to_string(),
                value: config.max_players.to_string(),
            });
        }

        Ok(config)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn defaults_are_valid() {
        let config = Config::default();
        assert_eq!(config.protocol_version, 2225);
        assert_eq!(config.max_players, 20);
        assert!(config.verify_minecraft_tokens);
    }

    #[test]
    fn parses_overrides() {
        let config = Config::from_args([
            "--signaling",
            "127.0.0.1:20000",
            "--server-name",
            "Test",
            "--max-players",
            "5",
            "--no-token-verification",
        ])
        .expect("valid args");

        assert_eq!(config.signaling_addr.to_string(), "127.0.0.1:20000");
        assert_eq!(config.server_name, "Test");
        assert_eq!(config.max_players, 5);
        assert!(!config.verify_minecraft_tokens);
    }

    #[test]
    fn rejects_unknown_flag() {
        let error = Config::from_args(["--bilinmeyen"]).expect_err("should fail");
        assert!(matches!(error, ConfigError::UnknownArgument(_)));
    }

    #[test]
    fn rejects_missing_value() {
        let error = Config::from_args(["--server-name"]).expect_err("should fail");
        assert!(matches!(error, ConfigError::MissingValue(_)));
    }

    #[test]
    fn rejects_bad_socket_address() {
        let error = Config::from_args(["--signaling", "not-an-address"]).expect_err("should fail");
        assert!(matches!(error, ConfigError::InvalidValue { .. }));
    }

    #[test]
    fn rejects_tls_without_key() {
        let error = Config::from_args(["--tls-cert", "a.pem"]).expect_err("should fail");
        assert!(matches!(error, ConfigError::IncompleteTls));
    }

    #[test]
    fn rejects_zero_max_players() {
        let error = Config::from_args(["--max-players", "0"]).expect_err("should fail");
        assert!(matches!(error, ConfigError::InvalidValue { .. }));
    }

    #[test]
    fn media_can_be_disabled() {
        let config = Config::from_args(["--media", "none"]).expect("valid");
        assert!(config.media_addr.is_none());
    }
}
