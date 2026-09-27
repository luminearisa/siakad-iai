<?php

declare(strict_types=1);

namespace Integrator\Support;

use RuntimeException;

/**
 * Minimal PHP template renderer (plain PHP files, no engine, no cache warm-up).
 */
final class View
{
    /**
     * @param  array<string, mixed>  $shared  data available to every template (e.g. the App instance)
     */
    public function __construct(
        private readonly string $templatesPath,
        private readonly array $shared = []
    ) {
    }

    /**
     * Render one template inside the shared layout.
     *
     * @param  array<string, mixed>  $data
     */
    public function page(string $template, array $data = []): string
    {
        $data = [...$this->shared, ...$data];
        $data['flashes'] = $this->pullFlashes();

        $content = $this->partial($template, $data);

        return $this->partial('layout', [...$data, 'content' => $content]);
    }

    /**
     * Render a template fragment (used for both pages and partials).
     *
     * @param  array<string, mixed>  $data
     */
    public function partial(string $template, array $data = []): string
    {
        $data = [...$this->shared, ...$data];
        $path = $this->templatesPath.'/'.$template.'.php';

        if (! is_file($path)) {
            throw new RuntimeException("Template [{$template}] tidak ditemukan.");
        }

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $path;
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    private function pullFlashes(): array
    {
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);

        return is_array($flashes) ? $flashes : [];
    }
}
