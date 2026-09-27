<?php
/**
 * @var array<string, mixed> $run
 * @var array<string, int> $statusCounts
 * @var array<string, mixed> $filters
 * @var array<int, array<string, mixed>> $logs
 * @var \Integrator\Sync\SyncerInterface|null $entity
 * @var array<int, array<string, string>> $preview
 */
?>
<div class="page-head">
    <div>
        <h2>Hasil: <?= e((string) $run['entity']) ?></h2>
        <p>
            Mode <strong><?= e((string) $run['mode']) ?></strong>
            <?php if (! empty($run['semester_code'])): ?> · semester <strong><?= e((string) $run['semester_code']) ?></strong><?php endif; ?>
            · mulai <?= e(human_datetime((string) $run['started_at'])) ?>
            <?php if (! empty($run['finished_at'])): ?> · selesai <?= e(human_datetime((string) $run['finished_at'])) ?><?php endif; ?>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="/sync">Kembali</a>
        <a class="btn btn-secondary" href="/logs?run_id=<?= e((string) $run['run_id']) ?>">Buka di log</a>
    </div>
</div>

<?php if (! empty($run['notes'])): ?>
    <div class="flash flash-error"><?= e((string) $run['notes']) ?></div>
<?php endif; ?>

<div class="grid cols-4">
    <div class="stat"><div class="label">Total baris</div><div class="value"><?= e((string) $run['total']) ?></div></div>
    <div class="stat"><div class="label">Berhasil</div><div class="value"><?= e((string) $run['succeeded']) ?></div></div>
    <div class="stat"><div class="label">Gagal</div><div class="value"><?= e((string) $run['failed']) ?></div></div>
    <div class="stat"><div class="label">Dilewati</div><div class="value"><?= e((string) $run['skipped']) ?></div></div>
</div>

<?php if ((int) $run['planned'] > 0): ?>
    <div class="flash flash-info">
        Mode dry-run: <?= e((string) $run['planned']) ?> baris siap dikirim dan tidak ada perubahan pada Neo Feeder.
        Jalankan ulang tanpa dry-run setelah hasilnya sesuai harapan.
    </div>
<?php endif; ?>

<div class="card" style="margin-top:18px">
    <h3>Filter status</h3>
    <div class="pill-list">
        <a class="badge" href="/sync/run/<?= e((string) $run['run_id']) ?>">Semua: <?= e((string) array_sum($statusCounts)) ?></a>
        <?php foreach ($statusCounts as $status => $count): ?>
            <a class="<?= status_badge((string) $status) ?>" href="/sync/run/<?= e((string) $run['run_id']) ?>?status=<?= e((string) $status) ?>"><?= e((string) $status) ?>: <?= e((string) $count) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3>Detail per baris</h3>
    <?php if ($logs === []): ?>
        <p class="empty">Tidak ada baris pada filter ini.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Status</th>
                <th>Kunci lokal</th>
                <th>ID feeder</th>
                <th>Aksi</th>
                <th>Pesan</th>
                <th>Detail</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span class="<?= status_badge((string) $log['status']) ?>"><?= e((string) $log['status']) ?></span></td>
                    <td><code><?= e((string) ($log['local_key'] ?? '—')) ?></code></td>
                    <td><code><?= e((string) ($log['feeder_id'] ?? '—')) ?></code></td>
                    <td><?= e((string) $log['action']) ?></td>
                    <td><?= e((string) ($log['message'] ?? '')) ?></td>
                    <td>
                        <?php if (! empty($log['response'])): ?>
                            <details>
                                <summary>Payload / respons</summary>
                                <pre><?= e((string) json_encode(json_decode((string) $log['response'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if ($log['status'] === 'failed' && $entity !== null): ?>
                            <form method="post" action="/sync/retry" style="margin-top:6px">
                                <?= csrf_field() ?>
                                <input type="hidden" name="entity" value="<?= e((string) $log['entity']) ?>">
                                <input type="hidden" name="local_key" value="<?= e((string) ($log['local_key'] ?? '')) ?>">
                                <input type="hidden" name="semester_code" value="<?= e((string) ($run['semester_code'] ?? '')) ?>">
                                <input type="hidden" name="dry_run" value="<?= $run['mode'] === 'dry-run' ? '1' : '0' ?>">
                                <button class="btn btn-secondary btn-sm" type="submit">Coba lagi baris ini</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($entity !== null && $preview !== []): ?>
    <div class="card">
        <h3>Pemetaan kolom yang dipakai</h3>
        <p class="hint">
            Sumber: <code><?= e((string) ($entity->sourceEndpoint() ?? 'feeder')) ?></code>
            <?php if ($entity->scope() !== null): ?> · scope <code><?= e((string) $entity->scope()) ?></code><?php endif; ?>
            · definisi di <code>config/feeder_mapping.php</code>
        </p>
        <table>
            <thead><tr><th>Kolom feeder</th><th>Sumber SIAKAD</th><th>Wajib</th></tr></thead>
            <tbody>
            <?php foreach ($preview as $row): ?>
                <tr>
                    <td><code><?= e($row['field']) ?></code></td>
                    <td><code><?= e($row['source']) ?></code></td>
                    <td><?= e($row['required']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
