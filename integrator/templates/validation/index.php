<?php
/**
 * Halaman validasi pelaporan.
 *
 * Menampilkan: status versi feeder, tombol pemeriksaan, rekap alasan tidak valid,
 * cakupan per entity + per program studi, dan daftar temuan yang bisa difilter.
 *
 * @var array<string, mixed>|null $run
 * @var array<int, array<string, mixed>> $findings
 * @var array<int, array<string, mixed>> $byCode
 * @var array<int, array<string, mixed>> $coverage
 * @var array<int, array<string, mixed>> $coverageProdi
 * @var array<string, mixed> $coverageTotals
 * @var array<int, string> $entities
 */
?>

<div class="page-head">
    <div>
        <h2>Validasi Pelaporan</h2>
        <p class="muted">
            Memeriksa data SIAKAD terhadap aturan Neo Feeder sebelum dikirim, lalu menghitung
            persentase pelaporan per program studi. Pemeriksaan tidak mengirim apa pun ke feeder.
        </p>
    </div>
</div>

<?php if (! $versionOk): ?>
    <div class="flash flash-error">
        <strong>Versi Neo Feeder belum bisa dipastikan.</strong>
        Aplikasi ini memvalidasi aturan patch <?= e($minimumVersion) ?>.
        <?php if ($feederVersion !== null && $feederVersion !== ''): ?>
            Versi tercatat: <code><?= e($feederVersion) ?></code>.
        <?php else: ?>
            Versi belum diisi — perbarui lewat tombol “Uji koneksi” pada dashboard atau isi manual di halaman Pengaturan.
        <?php endif; ?>
        <?php if (is_string($versionNote) && $versionNote !== ''): ?>
            <br><span class="muted"><?= e($versionNote) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="/validation/run">
        <?= csrf_field() ?>
        <div class="row">
            <div class="field">
                <label for="entity">Entity</label>
                <select id="entity" name="entity">
                    <option value="">— semua entity —</option>
                    <?php foreach ($entities as $entity): ?>
                        <option value="<?= e($entity) ?>"><?= e($entity) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="semester_code">Semester (kode feeder)</label>
                <input type="text" id="semester_code" name="semester_code" value="<?= e($semester) ?>" placeholder="20251">
            </div>
            <div class="field">
                <label for="limit">Batasi baris</label>
                <input type="number" id="limit" name="limit" value="0" min="0">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button class="btn" type="submit">Periksa kesiapan pelaporan</button>
            </div>
        </div>
    </form>

    <?php if ($run !== null): ?>
        <p class="muted">
            Pemeriksaan terakhir: <code><?= e((string) $run['run_id']) ?></code>
            (<?= e((string) ($run['status'] ?? '-')) ?>) pada <?= e(human_datetime((string) ($run['finished_at'] ?? $run['started_at']))) ?>,
            lingkup <?= e((string) ($run['scope'] ?? 'semua')) ?>.
        </p>
    <?php else: ?>
        <p class="muted">Belum ada pemeriksaan. Jalankan sekali untuk melihat data yang berisiko ditolak feeder.</p>
    <?php endif; ?>
</div>

<div class="grid cols-4">
    <?php
    $stats = [
        'Baris diperiksa' => number_format((int) ($coverageTotals['total'] ?? 0), 0, ',', '.'),
        'Lolos aturan' => number_format((int) ($coverageTotals['valid'] ?? 0), 0, ',', '.'),
        'Tidak valid' => number_format((int) ($coverageTotals['invalid'] ?? 0), 0, ',', '.'),
        'Cakupan pelaporan' => number_format((float) ($coverageTotals['percentage'] ?? 0), 2, ',', '.').'%',
    ];
    ?>
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat">
            <div class="label"><?= e((string) $label) ?></div>
            <div class="value"><?= e((string) $value) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($coverage !== []): ?>
    <div class="card">
        <h3>Cakupan per entity</h3>
        <table>
            <thead>
            <tr>
                <th>Entity</th>
                <th>Total</th>
                <th>Valid</th>
                <th>Tidak valid</th>
                <th>Sudah tersinkron</th>
                <th>Menunggu</th>
                <th>Perlu diperbarui</th>
                <th>Cakupan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($coverage as $row): ?>
                <tr>
                    <td><code><?= e((string) $row['entity']) ?></code></td>
                    <td><?= e(number_format((int) $row['total'], 0, ',', '.')) ?></td>
                    <td><?= e(number_format((int) $row['valid'], 0, ',', '.')) ?></td>
                    <td>
                        <?php if ((int) $row['invalid'] > 0): ?>
                            <a href="/validation?entity=<?= e(urlencode((string) $row['entity'])) ?>&amp;severity=error" class="badge-danger">
                                <?= e(number_format((int) $row['invalid'], 0, ',', '.')) ?>
                            </a>
                        <?php else: ?>
                            <span class="badge-ok">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(number_format((int) $row['synced'], 0, ',', '.')) ?></td>
                    <td><?= e(number_format((int) $row['pending'], 0, ',', '.')) ?></td>
                    <td><?= e(number_format((int) $row['stale'], 0, ',', '.')) ?></td>
                    <td class="nowrap"><?= e(number_format((float) $row['percentage'], 2, ',', '.')) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($coverageProdi !== []): ?>
    <div class="card">
        <h3>Persentase pelaporan per program studi</h3>
        <table>
            <thead>
            <tr>
                <th>Program studi</th>
                <th>Sudah tersinkron</th>
                <th>Total</th>
                <th>Tidak valid</th>
                <th>Cakupan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($coverageProdi as $row): ?>
                <?php $total = (int) $row['total']; $synced = (int) $row['synced']; ?>
                <tr>
                    <td><?= e((string) ($row['prodi_label'] ?? $row['prodi'])) ?></td>
                    <td><?= e(number_format($synced, 0, ',', '.')) ?></td>
                    <td><?= e(number_format($total, 0, ',', '.')) ?></td>
                    <td><?= e(number_format((int) $row['invalid'], 0, ',', '.')) ?></td>
                    <td class="nowrap">
                        <?= e($total > 0 ? number_format($synced / $total * 100, 2, ',', '.') : '0,00') ?>%
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($byCode !== []): ?>
    <div class="card">
        <h3>Rekap alasan tidak valid</h3>
        <table>
            <thead>
            <tr>
                <th>Kode</th>
                <th>Entity</th>
                <th>Jumlah</th>
                <th>Penjelasan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($byCode as $row): ?>
                <tr>
                    <td><a href="/validation?code=<?= e(urlencode((string) $row['code'])) ?>"><code><?= e((string) $row['code']) ?></code></a></td>
                    <td><?= e((string) $row['entity']) ?></td>
                    <td><?= e(number_format((int) $row['total'], 0, ',', '.')) ?></td>
                    <td>
                        <?= e((string) ($row['message'] ?? '')) ?>
                        <?php if (! empty($row['hint'])): ?>
                            <br><span class="muted"><?= e((string) $row['hint']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Daftar temuan</h3>

    <form method="get" action="/validation">
        <div class="row">
            <div class="field">
                <label for="severity">Tingkat</label>
                <select id="severity" name="severity">
                    <option value="">— semua —</option>
                    <?php foreach (['error' => 'error (ditolak feeder)', 'warning' => 'warning (perlu dilengkapi)', 'note' => 'note (catatan)'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= selected($filters['severity'] ?? '', $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="entityFilter">Entity</label>
                <select id="entityFilter" name="entity">
                    <option value="">— semua —</option>
                    <?php foreach ($entities as $entity): ?>
                        <option value="<?= e($entity) ?>"<?= selected($filters['entity'] ?? '', $entity) ?>><?= e($entity) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="search">Cari kunci / pesan</label>
                <input type="text" id="search" name="search" value="<?= e((string) ($filters['search'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button class="btn btn-secondary" type="submit">Terapkan</button>
            </div>
        </div>
    </form>

    <table>
        <thead>
        <tr>
            <th>Tingkat</th>
            <th>Entity</th>
            <th>Kunci lokal</th>
            <th>Kolom</th>
            <th>Kode</th>
            <th>Pesan &amp; saran</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($findings === []): ?>
            <tr>
                <td colspan="6" class="empty">
                    <?= $run === null
                        ? 'Belum ada pemeriksaan.'
                        : 'Tidak ada temuan pada filter ini.' ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($findings as $finding): ?>
            <tr>
                <td><span class="<?= $finding['severity'] === 'error' ? 'badge-danger' : ($finding['severity'] === 'warning' ? 'badge-warn' : 'badge-muted') ?>"><?= e((string) $finding['severity']) ?></span></td>
                <td><?= e((string) $finding['entity']) ?></td>
                <td><code><?= e((string) ($finding['local_key'] ?? '—')) ?></code></td>
                <td><code><?= e((string) ($finding['field'] ?? '—')) ?></code></td>
                <td><code><?= e((string) $finding['code']) ?></code></td>
                <td>
                    <?= e((string) ($finding['message'] ?? '')) ?>
                    <?php if (! empty($finding['hint'])): ?>
                        <br><span class="muted"><?= e((string) $finding['hint']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php
            $query = array_filter([
                'severity' => $filters['severity'] ?? null,
                'entity' => $filters['entity'] ?? null,
                'code' => $filters['code'] ?? null,
                'search' => $filters['search'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
            ?>
            <?php if ($page > 1): ?>
                <a href="/validation?<?= e(http_build_query($query + ['page' => $page - 1])) ?>">← sebelumnya</a>
            <?php endif; ?>
            <span class="muted">halaman <?= e((string) $page) ?> dari <?= e((string) $pages) ?> (<?= e(number_format($totalFindings, 0, ',', '.')) ?> temuan)</span>
            <?php if ($page < $pages): ?>
                <a href="/validation?<?= e(http_build_query($query + ['page' => $page + 1])) ?>">berikutnya →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (($run['started_at'] ?? null) !== null): ?>
    <div class="card">
        <h3>Riwayat pemeriksaan</h3>
        <table>
            <thead>
            <tr>
                <th>Waktu</th>
                <th>Lingkup</th>
                <th>Semester</th>
                <th>Status</th>
                <th>Baris</th>
                <th>Tidak valid</th>
                <th>Cakupan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($runs as $history): ?>
                <tr>
                    <td class="nowrap"><?= e(human_datetime((string) ($history['finished_at'] ?? $history['started_at']))) ?></td>
                    <td><?= e((string) $history['scope']) ?></td>
                    <td><?= e((string) ($history['semester'] ?? '—')) ?></td>
                    <td><span class="<?= ($history['status'] ?? '') === 'finished' ? 'badge-ok' : 'badge-warn' ?>"><?= e((string) $history['status']) ?></span></td>
                    <td><?= e(number_format((int) $history['total_records'], 0, ',', '.')) ?></td>
                    <td><?= e(number_format((int) $history['total_invalid'], 0, ',', '.')) ?></td>
                    <td><?= e(number_format((float) $history['percentage'], 2, ',', '.')) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="/validation/purge" onsubmit="return confirm('Hapus riwayat pemeriksaan yang lebih lama dari 30 hari?')">
            <?= csrf_field() ?>
            <input type="hidden" name="days" value="30">
            <button class="btn btn-secondary btn-sm" type="submit">Bersihkan riwayat &gt; 30 hari</button>
        </form>
    </div>
<?php endif; ?>
