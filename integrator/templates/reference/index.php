<?php
/**
 * @var string $kind
 * @var string $search
 * @var array<int, array<string, mixed>> $rows
 * @var int $total
 * @var array<string, int> $counts
 * @var array<string, string> $kinds
 * @var bool $fresh
 */
?>
<div class="page-head">
    <div>
        <h2>Referensi Neo Feeder</h2>
        <p>Data acuan yang ditarik dari feeder. Sinkronisasi lain memakai tabel ini untuk menerjemahkan kode SIAKAD menjadi id PDDikti.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/sync">Tarik / perbarui referensi</a>
    </div>
</div>

<?php if (! $fresh): ?>
    <div class="flash flash-info">
        Data referensi belum ada atau sudah lebih dari 24 jam. Jalankan entity <strong>Referensi Neo Feeder</strong> (pilih mode live; dry-run tidak menarik data).
    </div>
<?php endif; ?>

<div class="card">
    <div class="pill-list">
        <?php foreach ($kinds as $key => $label): ?>
            <a class="<?= $key === $kind ? 'badge badge-info' : 'badge' ?>" href="/reference?kind=<?= e($key) ?>">
                <?= e($label) ?>: <?= e((string) ($counts[$key] ?? 0)) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <form method="get" action="/reference">
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <div class="row">
            <div class="field">
                <label for="search">Cari kode / id / isi baris</label>
                <input type="text" id="search" name="search" value="<?= e($search) ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Cari</button>
            <span class="muted"><?= e((string) $total) ?> baris</span>
        </div>
    </form>
</div>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="empty">Belum ada data untuk kategori ini.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Kunci lokal (dipakai pemetaan)</th>
                <th>ID feeder</th>
                <th>Ringkasan</th>
                <th>Ubah kunci</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><code><?= e((string) $row['reference_key']) ?></code></td>
                    <td><code><?= e((string) ($row['feeder_id'] ?? '—')) ?></code></td>
                    <td>
                        <details>
                            <summary><?= e(short_json((string) json_encode($row['payload'] ?? []), 90)) ?></summary>
                            <pre><?= e((string) json_encode($row['payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                    </td>
                    <td>
                        <form method="post" action="/reference/rename" class="actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kind" value="<?= e($kind) ?>">
                            <input type="hidden" name="reference_key" value="<?= e((string) $row['reference_key']) ?>">
                            <input type="text" name="new_key" placeholder="kunci baru" style="max-width:170px" required>
                            <button class="btn btn-secondary btn-sm" type="submit">Petakan</button>
                        </form>
                        <div class="muted">Pakai bila kode prodi/nidn di SIAKAD berbeda dengan kode PDDikti.</div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($total > 300): ?>
            <p class="muted">Menampilkan 300 baris pertama dari <?= e((string) $total) ?> — gunakan pencarian untuk mempersempit.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
