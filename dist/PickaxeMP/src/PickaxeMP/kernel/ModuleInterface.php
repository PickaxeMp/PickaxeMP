<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Çekirdeğe bağlanan her modülün uygulaması gereken sözleşme.
 *
 * Sözleşme bilinçli olarak küçük tutulmuştur: modüller birbirini tanımaz,
 * yalnızca kimlik ve bağımlılık beyan eder.
 */
interface ModuleInterface
{
    /**
     * Modülün benzersiz kimliği. Örn. "core.config", "plugin.economy".
     */
    public function getId(): string;

    /**
     * Bu modülün başlaması için önce başlaması gereken modül kimlikleri.
     *
     * @return list<string>
     */
    public function getDependencies(): array;

    /**
     * Çekirdeğin bu modülü nasıl ele alacağını belirler.
     */
    public function getLifecyclePolicy(): LifecyclePolicy;

    /**
     * Modülü başlatır. İstisna fırlatırsa çekirdek başlatmayı geri alır.
     */
    public function boot(): void;

    /**
     * Modülü durdurur. İstisna fırlatsa bile çekirdek diğer modülleri kapatır.
     */
    public function shutdown(): void;
}
