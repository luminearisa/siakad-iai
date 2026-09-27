<?php

declare(strict_types=1);

namespace Integrator\NeoFeeder;

/**
 * Menerjemahkan pesan error Neo Feeder menjadi kategori + langkah penanganan.
 *
 * Pesan mentah dari feeder sering tidak menjelaskan apa yang harus dilakukan
 * operator. Pola pada `config/feeder_errors.php` memetakan pesan tersebut ke
 * kategori yang bisa ditindaklanjuti, lalu dipakai halaman Riwayat Sinkronisasi
 * dan ringkasan kegagalan di dashboard.
 */
final class ErrorCatalog
{
    /** @var array<string, array{category: string, hint: ?string}> */
    private array $cache = [];

    /**
     * @param  array<int, array<string, mixed>>  $patterns
     */
    public function __construct(private readonly array $patterns) {}

    public static function load(string $path): self
    {
        $patterns = require $path;

        return new self(is_array($patterns) ? $patterns : []);
    }

    /**
     * @return array{category: string, hint: ?string}
     */
    public function explain(?string $message): array
    {
        $message = trim((string) $message);

        if ($message === '') {
            return ['category' => 'tanpa_pesan', 'hint' => null];
        }

        if (isset($this->cache[$message])) {
            return $this->cache[$message];
        }

        foreach ($this->patterns as $entry) {
            $pattern = $entry['pattern'] ?? null;

            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (@preg_match($pattern, $message) === 1) {
                return $this->cache[$message] = [
                    'category' => (string) ($entry['category'] ?? 'lainnya'),
                    'hint' => isset($entry['hint']) ? (string) $entry['hint'] : null,
                ];
            }
        }

        return $this->cache[$message] = ['category' => 'lainnya', 'hint' => null];
    }

    public function categoryOf(?string $message): string
    {
        return $this->explain($message)['category'];
    }

    public function hintFor(?string $message): ?string
    {
        return $this->explain($message)['hint'];
    }

    /**
     * Kategori yang dikenal, untuk filter di halaman riwayat.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->patterns as $entry) {
            $category = $entry['category'] ?? null;

            if (is_string($category) && $category !== '' && ! in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }
}
