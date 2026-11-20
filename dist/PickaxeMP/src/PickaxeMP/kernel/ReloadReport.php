<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Bir reload işleminin tamamının sonucu.
 */
final class ReloadReport
{
    /**
     * @param list<ReloadResult> $results
     */
    public function __construct(
        public readonly ReloadRequest $request,
        public readonly array $results,
        public readonly bool $success,
        public readonly string $failureReason = '',
        public readonly float $durationMs = 0.0
    ) {
    }

    /**
     * @return list<ReloadResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function committedCount(): int
    {
        return count(array_filter(
            $this->results,
            static fn (ReloadResult $r): bool => $r->status === ReloadStatus::Committed
        ));
    }

    /**
     * Konsola basılacak kısa özet.
     *
     * @return list<string>
     */
    public function toLines(): array
    {
        $lines = [];

        foreach ($this->results as $result) {
            $tag = match ($result->status) {
                ReloadStatus::Committed => '[OK]',
                ReloadStatus::RolledBack => '[ROLLBACK]',
                ReloadStatus::Failed => '[ERROR]',
                ReloadStatus::Skipped => '[SKIP]',
            };

            $line = sprintf('%s %s', $tag, $result->moduleId);

            if ($result->message !== '') {
                $line .= ' - ' . $result->message;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
