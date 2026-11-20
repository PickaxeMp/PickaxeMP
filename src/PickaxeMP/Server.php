<?php

declare(strict_types=1);

namespace PickaxeMP;

use PickaxeMP\command\CommandMap;
use PickaxeMP\command\HelpCommand;
use PickaxeMP\command\ListCommand;
use PickaxeMP\command\PluginsCommand;
use PickaxeMP\command\ReloadConfigCommand;
use PickaxeMP\command\SaveAllCommand;
use PickaxeMP\command\SayCommand;
use PickaxeMP\command\StatusCommand;
use PickaxeMP\command\StopCommand;
use PickaxeMP\command\VersionCommand;
use PickaxeMP\console\ConsoleCommandDispatcher;
use PickaxeMP\console\ConsoleCommandSender;
use PickaxeMP\console\ConsoleManager;
use PickaxeMP\console\ConsoleRenderer;
use PickaxeMP\console\StdinConsoleReader;
use PickaxeMP\exception\ConfigException;
use PickaxeMP\kernel\ModuleRegistry;
use PickaxeMP\kernel\ReloadManager;
use PickaxeMP\kernel\ServerKernel;
use PickaxeMP\scheduler\Scheduler;
use PickaxeMP\utils\Config;
use PickaxeMP\utils\Logger;
use PickaxeMP\utils\ServerConfig;

/**
 * PickaxeMP sunucusunun giriş noktası.
 *
 * Sorumluluk: yapılandırmayı bulmak, servisleri kurmak, çekirdeği başlatmak
 * ve tick döngüsünü döndürmek. Oyun mantığı burada DEĞİL, modüllerde yaşar.
 *
 * DÜRÜST DURUM: Phase 0 itibarıyla ağ, dünya, oyuncu ve eklenti sistemleri
 * YOKTUR. Bu sınıf yalnızca çalışan bir iskelet kurar.
 */
final class Server
{
    public const NAME = 'PickaxeMP';
    public const VERSION = '0.1.0-dev';

    /** Saniyedeki tick sayısı (Minecraft Bedrock ile aynı). */
    public const TICKS_PER_SECOND = 20;

    private ?ServerKernel $kernel = null;

    private bool $running = false;

    private int $tick = 0;

    /** Konsol yöneticisi (null ise konsol devre dışı). */
    private ?ConsoleManager $consoleManager = null;

    /** Komut kayıt merkezi. */
    private readonly CommandMap $commandMap;

    /** Konsol renderleyici. */
    private readonly ConsoleRenderer $renderer;

    /** Komut gönderici. */
    private readonly ConsoleCommandSender $sender;

    /** Komut dağıtıcısı. */
    private readonly ConsoleCommandDispatcher $dispatcher;

    public function __construct(
        private readonly string $dataPath,
        private readonly ?Logger $logger = null
    ) {
        $this->commandMap = new CommandMap();
        $this->renderer = new ConsoleRenderer();
        $this->sender = new ConsoleCommandSender($this->renderer);
        $this->dispatcher = new ConsoleCommandDispatcher($this->commandMap, $this->sender);

        $this->registerCommands();
    }

    /** Komutları kayıt eder. */
    private function registerCommands(): void
    {
        $this->commandMap->register(new HelpCommand($this->sender, $this->commandMap));
        $this->commandMap->register(new VersionCommand($this->sender));
        $this->commandMap->register(new StatusCommand($this->sender));
        $this->commandMap->register(new ListCommand($this->sender));
        $this->commandMap->register(new SaveAllCommand($this->sender));
        $this->commandMap->register(new PluginsCommand($this->sender));
        $this->commandMap->register(new SayCommand($this->sender));
        $this->commandMap->register(new ReloadConfigCommand($this->sender));
        $this->commandMap->register(new StopCommand($this->sender, $this->getConsoleManager()));
    }

    /** Konsol yöneticisini döndürür (ilk erişimde oluşturur). */
    public function getConsoleManager(): ConsoleManager
    {
        if ($this->consoleManager === null) {
            $this->consoleManager = new ConsoleManager(
                $this->commandMap,
                $this->renderer,
                $this->sender,
                $this->dispatcher
            );
        }

        return $this->consoleManager;
    }

    /**
     * Yapılandırmayı yükler, servisleri kurar ve çekirdeği başlatır.
     *
     * Ayar dosyası arama sırası:
     *   1. {dataPath}/config.yml      (çalışma zamanı, .gitignore'da)
     *   2. {dataPath}/resources/config.yml (dağıtılan varsayılan)
     */
    public function boot(): ServerKernel
    {
        if ($this->kernel !== null) {
            return $this->kernel;
        }

        $configPath = $this->resolveConfigPath();
        $config = Config::fromFile($configPath);
        $settings = ServerConfig::fromConfig($config);

        $logger = $this->logger ?? new Logger(self::NAME);
        $logger->info(sprintf('%s %s baslatiliyor...', self::NAME, self::VERSION));

        $scheduler = new Scheduler($logger);
        $modules = new ModuleRegistry();
        $this->registerCoreModules($modules);

        $kernel = new ServerKernel($logger, $config, $settings, $scheduler, $modules);
        $kernel->boot();

        $this->kernel = $kernel;

        // Konsol okuyucuyu bağla (stdin mevcutsa).
        $console = $this->getConsoleManager();
        $reader = new StdinConsoleReader();
        $console->setReader($reader);

        $this->renderer->writeInfo(sprintf('%s %s baslatiliyor...', self::NAME, self::VERSION));
        $this->renderer->writeInfo('PHP runtime yuklendi.');
        $this->renderer->writeInfo('Yapilandirma yuklendi.');
        $this->renderer->writeSuccess('PickaxeMP calismaya hazir.');
        $this->renderer->writeLine('Komut icin "help" yazin.');
        $this->renderer->writePrompt('PickaxeMP > ');

        return $kernel;
    }

    public function getKernel(): ?ServerKernel
    {
        return $this->kernel;
    }

    public function createReloadManager(): ReloadManager
    {
        $kernel = $this->requireKernel();

        return new ReloadManager(
            $kernel->getModules(),
            $kernel->getLogger(),
            $this->dataPath . DIRECTORY_SEPARATOR . 'run' . DIRECTORY_SEPARATOR . 'reload.lock'
        );
    }

    /**
     * Ana tick döngüsü. Durdurulana kadar çalışır.
     *
     * @param int|null $maxTicks Test/CI için üst sınır; null = sınırsız
     * @param callable|null $shouldStop true dönerse döngü nazikçe biter
     */
    public function run(?int $maxTicks = null, ?callable $shouldStop = null): void
    {
        $kernel = $this->requireKernel();

        $this->running = true;
        $tickDurationUs = (int) (1_000_000 / self::TICKS_PER_SECOND);
        $executed = 0;

        while ($this->running) {
            if ($shouldStop !== null && $shouldStop()) {
                break;
            }

            // Konsol girişini her tick'te kontrol et (non-blocking).
            $console = $this->getConsoleManager();
            $console->pollInput();
            $console->processPendingCommands();

            // Konsol tarafından stop istenmişse döngüyü sonlandır.
            if ($console->isStopRequested()) {
                break;
            }

            $this->tick++;
            $kernel->tick($this->tick);

            $executed++;

            if ($maxTicks !== null && $executed >= $maxTicks) {
                break;
            }

            usleep($tickDurationUs);
        }

        // Döngüden çıkınca komut istemini göstermeyi bırak ve kapan.
        $console->shutdown();
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function shutdown(): void
    {
        $this->running = false;
        $this->kernel?->shutdown();
    }

    public function getTick(): int
    {
        return $this->tick;
    }

    private function requireKernel(): ServerKernel
    {
        if ($this->kernel === null) {
            throw new \LogicException('Sunucu once boot() ile baslatilmalidir.');
        }

        return $this->kernel;
    }

    private function resolveConfigPath(): string
    {
        $runtime = $this->dataPath . DIRECTORY_SEPARATOR . 'config.yml';

        if (is_file($runtime)) {
            return $runtime;
        }

        $bundled = $this->dataPath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'config.yml';

        if (is_file($bundled)) {
            return $bundled;
        }

        throw ConfigException::unreadable($bundled, 'varsayilan ayar dosyasi bulunamadi');
    }

    private function registerCoreModules(ModuleRegistry $modules): void
    {
        // Phase 0'da gerçek bir oyun modülü yok. Modül kaydı Phase 1+ ile gelir;
        // burada sahte modül kaydetmek "çalışıyor" izlenimi yaratırdı.
    }
}
