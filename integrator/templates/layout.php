<?php
/**
 * @var string $title
 * @var string $content
 * @var array<int, array{type: string, message: string}> $flashes
 * @var \Integrator\App|null $app
 */

$current = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$user = $app?->auth()->user();

$links = [
    '' => 'Dashboard',
    'sync' => 'Sinkronisasi',
    'logs' => 'Log',
    'mappings' => 'Pemetaan ID',
    'reference' => 'Referensi Feeder',
    'settings' => 'Pengaturan',
];

$isActive = static function (string $path) use ($current): bool {
    if ($path === '') {
        return $current === '';
    }

    return $current === $path || str_starts_with($current, $path.'/');
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Integrator') ?> · Integrator SIAKAD → Neo Feeder</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <h1>Integrator SIAKAD</h1>
        <div class="sub">Jembatan data ke Neo Feeder PDDikti</div>
        <nav>
            <?php foreach ($links as $path => $label): ?>
                <a href="<?= e(url($path === '' ? '/' : $path)) ?>" class="<?= $isActive($path) ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="footer">
            <?php if ($user): ?>
                <div>Masuk sebagai <strong><?= e($user['name'] ?? '') ?></strong></div>
                <form method="post" action="/logout" style="margin-top:8px">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm" type="submit">Keluar</button>
                </form>
            <?php endif; ?>
            <div style="margin-top:10px">PHP <?= e(PHP_VERSION) ?></div>
        </div>
    </aside>
    <main class="main">
        <?php foreach (($flashes ?? []) as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>
</body>
</html>
