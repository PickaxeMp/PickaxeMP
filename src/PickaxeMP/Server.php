<?php

declare(strict_types=1);

namespace PickaxeMP;

final class Server
{
    public const NAME = 'PickaxeMP';
    public const VERSION = '0.0.1';

    public function __construct(private string $dataPath)
    {
    }

    public function start(): void
    {
        echo self::NAME . ' ' . self::VERSION . " başlatılıyor...\n";
        // TODO: ayarları yükle
        // TODO: RakNet dinleyicisini başlat (UDP 19132)
        // TODO: dünyayı yükle
        // TODO: eklentileri yükle
    }

    public function shutdown(): void
    {
        // TODO: dünyayı kaydet, bağlantıları kapat
    }
}
