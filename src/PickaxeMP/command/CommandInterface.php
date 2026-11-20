<?php

declare(strict_types=1);

namespace PickaxeMP\command;

/**
 * Bir konsol veya oyun içi komutunun sözleşmesi.
 *
 * Tüm komutlar bu arayüzü uygular. Komut adını, açıklamasını ve çalıştırma
 * mantığını tanımlar. Çalışma sırasında konsol çıkışına doğrudan yazar.
 */
interface CommandInterface
{
    /** Komutun benzersiz adı. Örn. "help", "status", "stop". */
    public function getName(): string;

    /** Kullanıcıya gösterilecek kısa açıklama. */
    public function getDescription(): string;

    /**
     * Komutu çalıştırır.
     *
     * @param string[] $args Komut satırından gelen parametreler
     * @return int Çıkış kodu (0 = başarı, diğer = hata)
     */
    public function execute(array $args): int;

    /** Komutun kullanım örneği. Örn. "stop" veya "say <mesaj>". */
    public function getUsage(): string;
}
