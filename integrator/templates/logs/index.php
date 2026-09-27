<?php
/**
 * @var array<string, mixed> $filters
 * @var array<int, array<string, mixed>> $logs
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<int, string> $entities
 * @var array<string, int> $statusCounts
 */
$query = static function (array $overrides = []) use ($filters): string {
    $parameters = array_filter([...$filters, ...$overrides], static fn ($value) => $value !== null && $value !== '');

    return $parameters === [] ? '' : '?'.http_build_query($parameters);
};
?>
<div class="page-head">
    <div>
        <h2>Log Sinkronisasi</h2>
        <p>Jejak setiap baris yang dikirim, dilewati, atau ditolak Neo Feeder.</p>
    </div>
    <div class="pill-list">
        <?php foreach ($statusCounts as $status => $count): ?>
            <a class="<?= status_badge((string) $status) ?>" href="/logs?status=<?= e((string) $status) ?>"><?= e((string) $status) ?>: <?= e((string) $count) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="/logs">
        <div class="row">
            <div class="field">
                <label for="entity">Entity</label>
                <select id="entity" name="entity">
                    <option value="">— semua —</option>
                    <?php foreach ($entities as $entity): ?>
                        <option value="<?= e($entity) ?>"<?= selected($filters['entity'] ?? '', $entity) ?>><?= e($entity) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">— semua —</option>
                    <?php foreach (['succeeded', 'failed', 'skipped', 'planned'] as $status): ?>
                        <option value="<?= e($status) ?>"<?= selected($filters['status'] ?? '', $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="search">Cari kunci / pesan / id feeder</label>
                <input type="text" id="search" name="search" value="<?= e((string) ($filters['search'] ?? '')) ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn btn-secondary" href="/logs">Reset</a>
            <span class="muted"><?= e((string) $total) ?> baris cocok</span>
        </div>
    </form>
</div>

<div class="card">
    <table>
        <thead>
        <tr>
            <th>Waktu</th>
            <th>Entity</th>
            <th>Status</th>
            <th>Kategori</th>
            <th>Kunci lokal</th>
            <th>ID feeder</th>
            <th>Pesan</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($logs === []): ?>
            <tr><td colspan="8" class="empty">Belum ada log pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="nowrap"><?= e(human_datetime((string) $log['created_at'])) ?></td>
                <td><a href="/logs?entity=<?= e((string) $log['entity']) ?>"><?= e((string) $log['entity']) ?></a></td>
                <td><span class="<?= status_badge((string) $log['status']) ?>"><?= e((string) $log['status']) ?></span></td>
                <td>
                    <?php if (! empty($log['category'])): ?>
                        <a class="badge-warn" href="/logs?category=<?= e(urlencode((string) $log['category'])) ?>"><?= e((string) $log['category']) ?></a>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><code><?= e((string) ($log['local_key'] ?? '—')) ?></code></td>
                <td><code><?= e((string) ($log['feeder_id'] ?? '—')) ?></code></td>
                <td>
                    <?= e((string) ($log['message'] ?? '')) ?>
                    <?php if (! empty($log['hint'])): ?>
                        <br><span class="muted">Saran: <?= e((string) $log['hint']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (! empty($log['response'])): ?>
                        <details>
                            <summary>detail</summary>
                            <pre><?= e((string) json_encode(json_decode((string) $log['response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-secondary btn-sm" href="<?= e('/logs'.$query(['page' => $page - 1])) ?>">← Sebelumnya</a>
            <?php endif; ?>
            <span>Halaman <?= e((string) $page) ?> dari <?= e((string) $pages) ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-secondary btn-sm" href="<?= e('/logs'.$query(['page' => $page + 1])) ?>">Berikutnya →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
