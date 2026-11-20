<?php

declare(strict_types=1);

namespace PickaxeMP\utils;

use PickaxeMP\exception\ConfigException;

/**
 * Sunucu ayarlarının tipli ve doğrulanmış görünümü.
 *
 * Ham Config'e doğrudan erişmek yerine bu sınıf kullanılır; böylece geçersiz
 * bir ayar sunucuyu sessizce yanlış davranışa sürüklemez.
 */
final class ServerConfig
{
    public const GAMEMODES = ['survival', 'creative', 'adventure', 'spectator'];
    public const DIFFICULTIES = ['peaceful', 'easy', 'normal', 'hard'];

    public function __construct(
        public readonly string $serverName,
        public readonly string $motd,
        public readonly int $port,
        public readonly int $maxPlayers,
        public readonly string $gamemode,
        public readonly string $difficulty,
        public readonly bool $onlineMode,
        public readonly int $protocolVersion
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $serverName = trim($config->getString('server-name', 'PickaxeMP Server'));

        if ($serverName === '') {
            throw ConfigException::invalid('server-name', 'bos olamaz');
        }

        if (\mb_strlen($serverName) > 64) {
            throw ConfigException::invalid('server-name', 'en fazla 64 karakter olabilir');
        }

        $motd = $config->getString('motd', $serverName);

        if (\mb_strlen($motd) > 128) {
            throw ConfigException::invalid('motd', 'en fazla 128 karakter olabilir');
        }

        $port = $config->getInt('port', 19132);

        if ($port < 1 || $port > 65535) {
            throw ConfigException::invalid('port', '1..65535 araliginda olmali');
        }

        $maxPlayers = $config->getInt('max-players', 20);

        if ($maxPlayers < 1 || $maxPlayers > 1000) {
            throw ConfigException::invalid('max-players', '1..1000 araliginda olmali');
        }

        $gamemode = strtolower($config->getString('gamemode', 'survival'));

        if (!in_array($gamemode, self::GAMEMODES, true)) {
            throw ConfigException::invalid(
                'gamemode',
                'gecerli degerler: ' . implode(', ', self::GAMEMODES)
            );
        }

        $difficulty = strtolower($config->getString('difficulty', 'normal'));

        if (!in_array($difficulty, self::DIFFICULTIES, true)) {
            throw ConfigException::invalid(
                'difficulty',
                'gecerli degerler: ' . implode(', ', self::DIFFICULTIES)
            );
        }

        $onlineMode = $config->getBool('online-mode', false);

        $protocolVersion = $config->getInt('protocol-version', 0);

        if ($protocolVersion < 0) {
            throw ConfigException::invalid('protocol-version', 'negatif olamaz');
        }

        return new self(
            serverName: $serverName,
            motd: $motd,
            port: $port,
            maxPlayers: $maxPlayers,
            gamemode: $gamemode,
            difficulty: $difficulty,
            onlineMode: $onlineMode,
            protocolVersion: $protocolVersion
        );
    }
}
