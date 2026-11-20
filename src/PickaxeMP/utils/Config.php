<?php

declare(strict_types=1);

namespace PickaxeMP\utils;

use PickaxeMP\exception\ConfigException;

/**
 * PickaxeMP ayar okuyucusu — belgelenmiş bir YAML ALT KÜMESİ.
 *
 * Bu sınıf genel amaçlı bir YAML ayrıştırıcısı DEĞİLDİR. Desteklenen ve
 * desteklenmeyen her şey aşağıda açıkça listelenmiştir; desteklenmeyen bir
 * yapı sessizce yanlış yorumlanmak yerine ConfigException fırlatır.
 *
 * DESTEKLENEN
 *  - Blok eşleme (map), 2 veya daha fazla boşluk girintiyle
 *  - Blok dizi (sequence), "- " önekiyle
 *  - Dizi içinde satır içi eşleme başlangıcı: "- key: value"
 *  - Skalerler: tırnaklı string, tırnaksız string, tam sayı, ondalık, bool, null
 *  - Satır içi akış dizisi: [a, b, c] (yalnızca skaler öğeler)
 *  - Yorumlar: "#" (tırnak içinde değilse ve satır başında veya boşluktan sonra)
 *  - Tek tırnak ('...' ve '' kaçışı) ve çift tırnak ("..." ile \n \t \" \\ vb.)
 *  - CRLF ve LF satır sonları
 *
 * DESTEKLENMEYEN (açık hata verir)
 *  - Sekme ile girinti
 *  - Akış eşlemesi {a: b}
 *  - Blok skaler (| ve >)
 *  - Çapa / takma ad (&, *)
 *  - Etiket (!)
 *  - Çoklu belge (---, ...)
 *  - Birleştirme anahtarı (<<)
 *  - Yinelenen anahtar
 *
 * Anahtar yolunda ayırıcı "." karakteridir; bu nedenle anahtar adlarında "."
 * kullanılmamalıdır.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data, private readonly string $source)
    {
        $this->data = $data;
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw ConfigException::unreadable($path, 'dosya yok veya okunamiyor');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw ConfigException::unreadable($path, 'icerik okunamadi');
        }

        return self::fromString($contents, $path);
    }

    public static function fromString(string $contents, string $source = '<string>'): self
    {
        $lines = self::tokenize($contents, $source);

        if ($lines === []) {
            return new self([], $source);
        }

        $index = 0;
        $data = self::parseBlock($lines, $index, $lines[0]['indent'], $source);

        if ($index < count($lines)) {
            throw ConfigException::syntax($source, $lines[$index]['line'], 'beklenmeyen icerik');
        }

        if (!is_array($data) || array_is_list($data)) {
            throw ConfigException::syntax($source, $lines[0]['line'], 'kok seviyesi bir esleme olmali');
        }

        /** @var array<string, mixed> $data */
        return new self($data, $source);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function has(string $path): bool
    {
        $sentinel = new \stdClass();

        return $this->find($path, $sentinel) !== $sentinel;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->find($path, $default);
    }

    public function getString(string $path, ?string $default = null): string
    {
        $value = $this->find($path, $default);

        if (!is_string($value)) {
            throw ConfigException::invalid($path, 'metin bekleniyordu, alinan: ' . get_debug_type($value));
        }

        return $value;
    }

    public function getInt(string $path, ?int $default = null): int
    {
        $value = $this->find($path, $default);

        if (!is_int($value)) {
            throw ConfigException::invalid($path, 'tam sayi bekleniyordu, alinan: ' . get_debug_type($value));
        }

        return $value;
    }

    public function getFloat(string $path, ?float $default = null): float
    {
        $value = $this->find($path, $default);

        if (is_int($value)) {
            return (float) $value;
        }

        if (!is_float($value)) {
            throw ConfigException::invalid($path, 'ondalik sayi bekleniyordu, alinan: ' . get_debug_type($value));
        }

        return $value;
    }

    public function getBool(string $path, ?bool $default = null): bool
    {
        $value = $this->find($path, $default);

        if (!is_bool($value)) {
            throw ConfigException::invalid($path, 'mantiksal deger bekleniyordu, alinan: ' . get_debug_type($value));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getArray(string $path, ?array $default = null): array
    {
        $value = $this->find($path, $default);

        if (!is_array($value)) {
            throw ConfigException::invalid($path, 'dizi bekleniyordu, alinan: ' . get_debug_type($value));
        }

        return $value;
    }

    private function find(string $path, mixed $default): mixed
    {
        if ($path === '') {
            return $this->data;
        }

        $cursor = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Satırları { girinti, icerik, satirNo } kayıtlarına dönüştürür.
     *
     * @return list<array{indent: int, content: string, line: int}>
     */
    private static function tokenize(string $contents, string $source): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
        $rawLines = explode("\n", $normalized);
        $result = [];

        foreach ($rawLines as $offset => $raw) {
            $lineNo = $offset + 1;

            // Girinti: yalnızca boşluk. Sekme kabul edilmez.
            $indent = 0;
            $length = strlen($raw);

            while ($indent < $length && ($raw[$indent] === ' ' || $raw[$indent] === "\t")) {
                if ($raw[$indent] === "\t") {
                    throw ConfigException::syntax($source, $lineNo, 'girinti icin sekme kullanilamaz, bosluk kullanin');
                }

                $indent++;
            }

            $body = substr($raw, $indent);
            $body = self::stripInlineComment($body, $source, $lineNo);
            $body = rtrim($body);

            if ($body === '') {
                continue;
            }

            if ($body === '---' || $body === '...') {
                throw ConfigException::syntax($source, $lineNo, 'coklu belge desteklenmiyor');
            }

            $result[] = ['indent' => $indent, 'content' => $body, 'line' => $lineNo];
        }

        return $result;
    }

    private static function stripInlineComment(string $body, string $source, int $lineNo): string
    {
        $quote = null;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($quote !== null) {
                if ($quote === '"' && $char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '#' && ($i === 0 || $body[$i - 1] === ' ')) {
                return substr($body, 0, $i);
            }
        }

        if ($quote !== null) {
            throw ConfigException::syntax($source, $lineNo, 'kapanmamis tirnak');
        }

        return $body;
    }

    /**
     * @param list<array{indent: int, content: string, line: int}> $lines
     */
    private static function parseBlock(array $lines, int &$index, int $indent, string $source): array
    {
        if ($index >= count($lines)) {
            return [];
        }

        $line = $lines[$index];

        if ($line['indent'] !== $indent) {
            throw ConfigException::syntax($source, $line['line'], 'beklenmeyen girinti seviyesi');
        }

        if (self::isSequenceItem($line['content'])) {
            return self::parseSequence($lines, $index, $indent, $source);
        }

        return self::parseMap($lines, $index, $indent, $source);
    }

    /**
     * @param list<array{indent: int, content: string, line: int}> $lines
     * @return array<string, mixed>
     */
    private static function parseMap(array $lines, int &$index, int $indent, string $source): array
    {
        $result = [];
        $count = count($lines);

        while ($index < $count) {
            $line = $lines[$index];

            if ($line['indent'] < $indent) {
                break;
            }

            if ($line['indent'] > $indent) {
                throw ConfigException::syntax($source, $line['line'], 'beklenmeyen girinti (fazla bosluk)');
            }

            if (self::isSequenceItem($line['content'])) {
                throw ConfigException::syntax($source, $line['line'], 'esleme icinde dizi ogesi beklenmiyordu');
            }

            [$key, $value] = self::splitPair($line['content'], $source, $line['line']);

            if (array_key_exists($key, $result)) {
                throw ConfigException::syntax($source, $line['line'], "yinelenen anahtar: '{$key}'");
            }

            $index++;

            if ($value === '') {
                $result[$key] = ($index < $count && $lines[$index]['indent'] > $indent)
                    ? self::parseBlock($lines, $index, $lines[$index]['indent'], $source)
                    : null;

                continue;
            }

            $result[$key] = self::parseScalar($value, $source, $line['line']);
        }

        return $result;
    }

    /**
     * @param list<array{indent: int, content: string, line: int}> $lines
     * @return list<mixed>
     */
    private static function parseSequence(array $lines, int &$index, int $indent, string $source): array
    {
        $result = [];
        $count = count($lines);

        while ($index < $count) {
            $line = $lines[$index];

            if ($line['indent'] !== $indent || !self::isSequenceItem($line['content'])) {
                break;
            }

            $rest = trim(substr($line['content'], 1));
            $index++;

            if ($rest === '') {
                $result[] = ($index < $count && $lines[$index]['indent'] > $indent)
                    ? self::parseBlock($lines, $index, $lines[$index]['indent'], $source)
                    : null;

                continue;
            }

            $result[] = self::parseSequenceItem($rest, $lines, $index, $indent, $source, $line['line']);
        }

        return $result;
    }

    /**
     * "- key: value" biçimindeki satır içi eşleme başlangıcını ve devamındaki
     * kardeş anahtarları tek bir eşlemeye birleştirir.
     *
     * @param list<array{indent: int, content: string, line: int}> $lines
     */
    private static function parseSequenceItem(
        string $rest,
        array $lines,
        int &$index,
        int $indent,
        string $source,
        int $lineNo
    ): mixed {
        if (!self::hasPairSeparator($rest)) {
            return self::parseScalar($rest, $source, $lineNo);
        }

        [$key, $value] = self::splitPair($rest, $source, $lineNo);
        $item = [];

        if ($value === '') {
            $item[$key] = ($index < count($lines) && $lines[$index]['indent'] > $indent)
                ? self::parseBlock($lines, $index, $lines[$index]['indent'], $source)
                : null;
        } else {
            $item[$key] = self::parseScalar($value, $source, $lineNo);
        }

        if ($index < count($lines) && $lines[$index]['indent'] > $indent) {
            $nested = self::parseBlock($lines, $index, $lines[$index]['indent'], $source);

            if (!is_array($nested) || array_is_list($nested)) {
                throw ConfigException::syntax($source, $lineNo, 'dizi ogesinin devaminda esleme bekleniyordu');
            }

            foreach ($nested as $nestedKey => $nestedValue) {
                if (array_key_exists($nestedKey, $item)) {
                    throw ConfigException::syntax($source, $lineNo, "yinelenen anahtar: '{$nestedKey}'");
                }

                $item[$nestedKey] = $nestedValue;
            }
        }

        return $item;
    }

    private static function isSequenceItem(string $content): bool
    {
        return $content === '-' || str_starts_with($content, '- ');
    }

    private static function hasPairSeparator(string $content): bool
    {
        $quote = null;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if ($quote !== null) {
                if ($quote === '"' && $char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === ':' && ($i + 1 === $length || $content[$i + 1] === ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitPair(string $content, string $source, int $lineNo): array
    {
        $quote = null;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if ($quote !== null) {
                if ($quote === '"' && $char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === ':' && ($i + 1 === $length || $content[$i + 1] === ' ')) {
                $key = trim(substr($content, 0, $i));
                $value = trim(substr($content, $i + 1));

                if ($key === '') {
                    throw ConfigException::syntax($source, $lineNo, 'bos anahtar');
                }

                if ($key === '<<') {
                    throw ConfigException::syntax($source, $lineNo, 'birlestirme anahtari desteklenmiyor');
                }

                if (preg_match('/[\[\]{}]/', $key) === 1) {
                    throw ConfigException::syntax($source, $lineNo, "gecersiz anahtar: '{$key}'");
                }

                return [$key, $value];
            }
        }

        throw ConfigException::syntax($source, $lineNo, 'anahtar/deger ayirici ":" bulunamadi');
    }

    private static function parseScalar(string $raw, string $source, int $lineNo): mixed
    {
        $value = trim($raw);

        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first === '|' || $first === '>') {
            throw ConfigException::syntax($source, $lineNo, 'blok skaler (| veya >) desteklenmiyor');
        }

        if ($first === '&' || $first === '*') {
            throw ConfigException::syntax($source, $lineNo, 'capa/takma ad (& veya *) desteklenmiyor');
        }

        if ($first === '!') {
            throw ConfigException::syntax($source, $lineNo, 'etiket (!) desteklenmiyor');
        }

        if ($first === '{') {
            throw ConfigException::syntax($source, $lineNo, 'akis eslemesi {...} desteklenmiyor');
        }

        if ($first === '[') {
            return self::parseFlowSequence($value, $source, $lineNo);
        }

        if ($first === '"' || $first === "'") {
            return self::parseQuoted($value, $source, $lineNo);
        }

        if (self::hasPairSeparator($value)) {
            throw ConfigException::syntax($source, $lineNo, 'skaler icinde ": " bulundu; satir ici esleme desteklenmiyor');
        }

        return self::parsePlain($value);
    }

    /**
     * @return list<mixed>
     */
    private static function parseFlowSequence(string $value, string $source, int $lineNo): array
    {
        if (!str_ends_with($value, ']')) {
            throw ConfigException::syntax($source, $lineNo, 'akis dizisi kapatilmamis');
        }

        $inner = trim(substr($value, 1, -1));

        if ($inner === '') {
            return [];
        }

        $items = [];
        $buffer = '';
        $quote = null;
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($quote === '"' && $char === '\\') {
                    if ($i + 1 < $length) {
                        $buffer .= $inner[++$i];
                    }

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '[' || $char === ']' || $char === '{' || $char === '}') {
                throw ConfigException::syntax($source, $lineNo, 'ic ice akis yapilari desteklenmiyor');
            }

            if ($char === ',') {
                $items[] = self::parseScalar(trim($buffer), $source, $lineNo);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ($quote !== null) {
            throw ConfigException::syntax($source, $lineNo, 'akis dizisinde kapanmamis tirnak');
        }

        if (trim($buffer) !== '') {
            $items[] = self::parseScalar(trim($buffer), $source, $lineNo);
        }

        return $items;
    }

    private static function parseQuoted(string $value, string $source, int $lineNo): string
    {
        $quote = $value[0];

        if (!str_ends_with($value, $quote) || strlen($value) < 2) {
            throw ConfigException::syntax($source, $lineNo, 'kapanmamis tirnak');
        }

        $inner = substr($value, 1, -1);

        if ($quote === "'") {
            return str_replace("''", "'", $inner);
        }

        $result = '';
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];

            if ($char !== '\\') {
                $result .= $char;
                continue;
            }

            $i++;

            if ($i >= $length) {
                throw ConfigException::syntax($source, $lineNo, 'kacis dizisi eksik');
            }

            $escape = $inner[$i];

            $result .= match ($escape) {
                '"' => '"',
                '\\' => '\\',
                '/' => '/',
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0C",
                '0' => "\0",
                default => throw ConfigException::syntax($source, $lineNo, "bilinmeyen kacis dizisi: \\{$escape}"),
            };
        }

        return $result;
    }

    private static function parsePlain(string $value): mixed
    {
        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null' || $value === '~') {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int !== false) {
            return $int;
        }

        $float = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($float !== false) {
            return (float) $float;
        }

        return $value;
    }
}
