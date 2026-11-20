<?php

declare(strict_types=1);

namespace PickaxeMP\exception;

use RuntimeException;

/**
 * PickaxeMP içindeki tüm beklenen hataların ortak atası.
 *
 * Beklenmeyen hataları (Error, TypeError) yakalamak için kullanılmaz; onlar
 * programlama hatasıdır ve düzeltilmelidir.
 */
class PickaxeException extends RuntimeException
{
}
