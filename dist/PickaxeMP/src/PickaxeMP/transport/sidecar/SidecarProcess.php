<?php

declare(strict_types=1);

namespace PickaxeMP\transport\sidecar;

use PickaxeMP\exception\TransportException;

/**
 * Rust sidecar sürecinin yaşam döngüsü.
 *
 * Sorumluluk: süreci başlatmak, el sıkışma bilgisini okumak, sağlığını izlemek
 * ve temiz kapatmak. Protokol mantığı burada DEĞİL, SidecarTransport'ta.
 *
 * NEDEN DOSYA, PIPE DEĞİL?
 * Windows'ta `proc_open` ile açılan pipe'larda `stream_set_blocking()` etkisiz
 * kalır ve `stream_get_contents()` bloklar; bu da sunucu başlangıcını kilitler.
 * Bu yüzden stdout/stderr dosyaya yönlendirilir ve el sıkışma satırı dosyadan
 * yoklanır. Linux'ta da aynı kod yolu çalışır.
 *
 * El sıkışma: sidecar stdout'a iki satır yazar:
 *   PICKAXE_IPC <port> <token>     — IPC kontrol kanalı (yalnızca localhost)
 *   PICKAXE_SIGNALING <addr>       — NetherNet HTTP signaling adresi
 *
 * Token yalnızca bu dosyada taşınır; süreç kapanınca silinir.
 */
final class SidecarProcess implements SidecarProcessInterface
{
    /** @var resource|null */
    private $process = null;

    private ?int $ipcPort = null;

    private ?string $token = null;

    private ?string $signalingAddress = null;

    private ?int $pid = null;

    private ?string $stdoutPath = null;

    private ?string $stderrPath = null;

    /** @var list<string> */
    private array $stdoutLines = [];

    /**
     * @param list<string> $arguments Binary'den sonra geçirilecek argümanlar
     * @param string|null $runtimeDirectory El sıkışma/log dosyalarının yazılacağı dizin
     */
    public function __construct(
        private readonly string $binaryPath,
        private readonly array $arguments = [],
        private readonly int $startupTimeoutMs = 30000,
        private readonly ?string $runtimeDirectory = null
    ) {
    }

    public static function defaultBinaryPath(string $projectRoot): string
    {
        $relative = 'transport/pickaxe-transport/target/release/pickaxe-transport';

        if (PHP_OS_FAMILY === 'Windows') {
            return $projectRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative) . '.exe';
        }

        return $projectRoot . DIRECTORY_SEPARATOR . $relative;
    }

    public function start(): void
    {
        if ($this->process !== null) {
            throw TransportException::alreadyRunning();
        }

        if (!is_file($this->binaryPath)) {
            throw TransportException::startFailed(
                "Sidecar ikilisi bulunamadi: {$this->binaryPath} (once `cargo build --release` calistirin)"
            );
        }

        $directory = $this->runtimeDirectory ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pickaxe-mp');
        $this->ensureDirectory($directory);

        $this->stdoutPath = $directory . DIRECTORY_SEPARATOR . 'sidecar-' . bin2hex(random_bytes(6)) . '.out';
        $this->stderrPath = $directory . DIRECTORY_SEPARATOR . 'sidecar-' . bin2hex(random_bytes(6)) . '.err';

        // Dosya tanımlayıcıları: pipe bloklaması yok.
        $descriptors = [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', $this->stdoutPath, 'w'],
            2 => ['file', $this->stderrPath, 'w'],
        ];

        $command = array_merge([$this->binaryPath], $this->arguments);
        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw TransportException::startFailed('proc_open basarisiz');
        }

        $this->process = $process;
        $this->awaitHandshake();
    }

    public function isRunning(): bool
    {
        if ($this->process === null) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'] === true;
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getIpcPort(): int
    {
        if ($this->ipcPort === null) {
            throw TransportException::startFailed('IPC portu henuz bilinmiyor');
        }

        return $this->ipcPort;
    }

    public function getToken(): string
    {
        if ($this->token === null) {
            throw TransportException::startFailed('IPC tokeni henuz bilinmiyor');
        }

        return $this->token;
    }

    public function getSignalingAddress(): ?string
    {
        return $this->signalingAddress;
    }

    /**
     * Süreci durdurur ve geçici dosyaları siler. Idempotenttir.
     */
    public function stop(): void
    {
        if ($this->process !== null) {
            $process = $this->process;
            $this->process = null;

            $status = proc_get_status($process);

            if ($status['running'] === true) {
                proc_terminate($process);
            }

            proc_close($process);
        }

        $this->cleanupFiles();
        $this->ipcPort = null;
        $this->token = null;
        $this->signalingAddress = null;
        $this->pid = null;
    }

    public function readErrorOutput(): string
    {
        return $this->readFile($this->stderrPath);
    }

    /**
     * @return list<string>
     */
    public function getStdoutLines(): array
    {
        return $this->stdoutLines;
    }

    private function awaitHandshake(): void
    {
        $deadline = microtime(true) + ($this->startupTimeoutMs / 1000.0);

        while (microtime(true) < $deadline) {
            $this->consumeStdout();

            // İki satır da gerekli: IPC için port+token, teşhis için signaling
            // adresi. Yalnızca birini beklemek yarış koşulu yaratır.
            if ($this->ipcPort !== null && $this->token !== null && $this->signalingAddress !== null) {
                $this->pid = proc_get_status($this->process)['pid'] ?? null;

                return;
            }

            if (!$this->isRunning()) {
                $this->consumeStdout();
                $error = trim($this->readErrorOutput());

                $this->stop();

                throw TransportException::startFailed(
                    'Sidecar beklenmedik sekilde kapandi.' . ($error === '' ? '' : ' ' . $error)
                );
            }

            usleep(15000);
        }

        $error = trim($this->readErrorOutput());
        $missing = $this->ipcPort === null
            ? 'IPC satiri'
            : 'signaling satiri';
        $this->stop();

        throw TransportException::startFailed(
            "Sidecar el sikismasi zaman asimina ugradi ({$missing} gelmedi)."
            . ($error === '' ? '' : ' ' . $error)
        );
    }

    /**
     * Yeni stdout satırlarını işler. Aynı satır iki kez işlenmez.
     */
    private function consumeStdout(): void
    {
        if ($this->stdoutPath === null || !is_file($this->stdoutPath)) {
            return;
        }

        $contents = @file_get_contents($this->stdoutPath);

        if ($contents === false || $contents === '') {
            return;
        }

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || in_array($line, $this->stdoutLines, true)) {
                continue;
            }

            $this->stdoutLines[] = $line;
            $this->handleLine($line);
        }
    }

    private function handleLine(string $line): void
    {
        if (str_starts_with($line, 'PICKAXE_IPC ')) {
            $parts = preg_split('/\s+/', $line) ?: [];

            if (count($parts) >= 3) {
                $this->ipcPort = (int) $parts[1];
                $this->token = $parts[2];
            }

            return;
        }

        if (str_starts_with($line, 'PICKAXE_SIGNALING ')) {
            $this->signalingAddress = trim(substr($line, strlen('PICKAXE_SIGNALING ')));
        }
    }

    private function readFile(?string $path): string
    {
        if ($path === null || !is_file($path)) {
            return '';
        }

        $contents = @file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw TransportException::startFailed("Calisma dizini olusturulamadi: {$directory}");
        }
    }

    private function cleanupFiles(): void
    {
        foreach ([$this->stdoutPath, $this->stderrPath] as $path) {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }

        if ($this->stderrPath !== null) {
            $directory = dirname($this->stderrPath);

            // Yalnızca sidecar'a ait geçici dosyalar kaldıysa dizini de sil.
            $remaining = @scandir($directory);

            if (is_array($remaining) && count($remaining) === 2) {
                @rmdir($directory);
            }
        }
    }
}
