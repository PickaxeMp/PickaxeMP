<?php

declare(strict_types=1);

namespace PickaxeMP\kernel;

/**
 * Tek bir modülün reload sonucu.
 */
enum ReloadStatus: string
{
    case Committed = 'committed';
    case RolledBack = 'rolled_back';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
