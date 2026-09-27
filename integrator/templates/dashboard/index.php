<?php
/**
 * @var \Integrator\Support\Settings $settings
 * @var array<string, mixed>|null $snapshot
 * @var array<string, string>|null $siakadError
 * @var array<string, mixed>|null $check
 * @var array<string, int> $mappingCounts
 * @var array<string, int> $referenceCounts
 * @var array<string, int> $totals
 * @var array<int, array<string, mixed>> $runs
 * @var array<int, array<string, mixed>> $failures
 * @var array<string, \Integrator\Sync\SyncerInterface> $entities
 * @var bool $referenceFresh
 */
?>
<div class="page-head">
    <div>
        <h2>Dashboard</h2>
        <p>Status koneksi, isi ledger, dan aktivitas sinkronisasi terakhir.</p>
    </div>
    <form method="post" action="/dashboard/check">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Uji koneksi SIAKAD &amp; Neo Feeder</button>
    </form>
</div>

<?php if (! $settings->siakadConfigured() || ! $settings->feederConfigured()): ?>
    <div class="flash flash-info">
        Konfigurasi belum lengkap.
        <?= $settings->siakadConfigured() ? '' : 'SIAKAD (base URL + API key) ' ?>
        <?= $settings->feederConfigured() ? '' : 'Neo Feeder (host + username + password) ' ?>
        bisa diisi di <a href="/settings">halaman Pengaturan</a>.
    </div>
<?php endif; ?>

<?php if ($check !== null): ?>
    <div class="grid cols-2">
        <?php foreach (['siakad' => 'SIAKAD', 'feeder' => 'Neo Feeder'] as $key => $label): ?>
            <?php $result = $check[$key] ?? null; ?>
            <?php if (is_array($result)): ?>
                <div class="card" style="border-left:4px solid <?= ! empty($result['ok']) ? 'var(--ok)' : 'var(--danger)' ?>">
                    <h3><?= e($label) ?> — <?= ! empty($result['ok']) ? 'terhubung' : 'gagal' ?></h3>
                    <p class="hint"><?= e((string) ($result['message'] ?? '')) ?></p>
                    <?php if (! empty($result['details'])): ?>
                        <table>
                            <tbody>
                            <?php foreach ($result['details'] as $name => $value): ?>
                                <tr>
                                    <th style="width:38%"><?= e((string) $name) ?></th>
                                    <td><?= e((string) $value) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($siakadError !== null): ?>
    <div class="flash flash-error">
        SIAKAD menolak permintaan: <?= e((string) $siakadError['message']) ?>
        <br><span class="muted"><?= e((string) $siakadError['hint']) ?></span>
    </div>
<?php endif; ?>

<div class="grid cols-4">
    <?php
    $cards = [
        'Mahasiswa di SIAKAD' => $snapshot['counts']['students'] ?? '—',
        'Mahasiswa aktif' => $snapshot['counts']['students_active'] ?? '—',
        'Kelas (periode aktif)' => $snapshot['counts']['classes'] ?? '—',
        'KRS (periode aktif)' => $snapshot['counts']['enrollments'] ?? '—',
        'Total baris terpetakan' => array_sum($mappingCounts),
        'Baris berhasil' => $totals['succeeded'] ?? 0,
        'Baris gagal' => $totals['failed'] ?? 0,
        'Baris direncanakan (dry-run)' => $totals['planned'] ?? 0,
    ];
    ?>
    <?php foreach ($cards as $label => $value): ?>
        <div class="stat">
            <div class="label"><?= e((string) $label) ?></div>
            <div class="value"><?= e((string) $value) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:18px">
    <h3>Referensi Neo Feeder</h3>
    <?php if (! $referenceFresh): ?>
        <p class="warn-text">Referensi belum ditarik atau sudah lebih dari 24 jam. Jalankan entity <strong>Referensi Neo Feeder</strong> di halaman Sinkronisasi sebelum mengirim data.</p>
    <?php else: ?>
        <p class="hint">Data acuan terbaru tersedia.</p>
    <?php endif; ?>
    <div class="pill-list">
        <?php foreach (['pt' => 'PT', 'prodi' => 'Prodi', 'periode' => 'Periode', 'dosen' => 'Dosen', 'kategori_kegiatan' => 'Kategori', 'dictionary' => 'Kamus kolom'] as $kind => $label): ?>
            <span class="badge"><?= e($label) ?>: <?= e((string) ($referenceCounts[$kind] ?? 0)) ?></span>
        <?php endforeach; ?>
    </div>
    <div class="actions" style="margin-top:12px">
        <a class="btn btn-secondary btn-sm" href="/reference">Lihat referensi</a>
    </div>
</div>

<div class="card">
    <h3>Ledger per entity</h3>
    <table>
        <thead>
        <tr>
            <th>Entity</th>
            <th>Label</th>
            <th>Fungsi feeder</th>
            <th>Scope SIAKAD</th>
            <th>Terpetakan</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($entities as $key => $entity): ?>
            <tr>
                <td><code><?= e($key) ?></code></td>
                <td><?= e($entity->label()) ?></td>
                <td><code><?= e($entity->actInsert() ?? '—') ?></code><?= $entity->actUpdate() ? ' / <code>'.e($entity->actUpdate()).'</code>' : '' ?></td>
                <td><?= e($entity->scope() ?? '—') ?></td>
                <td><?= e((string) ($mappingCounts[$key] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="grid cols-2">
    <div class="card">
        <h3>Riwayat sinkronisasi</h3>
        <?php if ($runs === []): ?>
            <p class="empty">Belum ada sinkronisasi dijalankan.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Waktu</th><th>Entity</th><th>Mode</th><th>Hasil</th></tr></thead>
                <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td class="nowrap"><?= e(human_datetime((string) $run['started_at'])) ?></td>
                        <td>
                            <a href="/sync/run/<?= e((string) $run['run_id']) ?>"><?= e((string) $run['entity']) ?></a>
                            <?php if (! empty($run['semester_code'])): ?>
                                <span class="badge"><?= e((string) $run['semester_code']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $run['mode'] === 'live' ? 'badge-warn' : 'badge-info' ?>"><?= e((string) $run['mode']) ?></span></td>
                        <td class="nowrap">
                            <span class="badge badge-ok"><?= e((string) $run['succeeded']) ?></span>
                            <span class="badge badge-danger"><?= e((string) $run['failed']) ?></span>
                            <span class="badge badge-muted"><?= e((string) $run['skipped']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Kegagalan tersering</h3>
        <?php if ($failures === []): ?>
            <p class="empty">Tidak ada kegagalan tercatat.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Entity</th><th>Pesan</th><th>Jumlah</th></tr></thead>
                <tbody>
                <?php foreach ($failures as $failure): ?>
                    <tr>
                        <td><?= e((string) $failure['entity']) ?></td>
                        <td><?= e((string) $failure['message']) ?></td>
                        <td><?= e((string) $failure['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
