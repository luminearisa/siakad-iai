<?php
/**
 * @var string $entity
 * @var string $search
 * @var int $page
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, int> $counts
 * @var array<int, string> $entities
 */
?>
<div class="page-head">
    <div>
        <h2>Pemetaan ID</h2>
        <p>Ledger SIAKAD ↔ Neo Feeder. Jangan hapus pemetaan kecuali baris di feeder benar-benar sudah tidak ada.</p>
    </div>
</div>

<div class="card">
    <form method="get" action="/mappings">
        <div class="row">
            <div class="field">
                <label for="entity">Entity</label>
                <select id="entity" name="entity">
                    <?php foreach ($entities as $key): ?>
                        <option value="<?= e($key) ?>"<?= selected($entity, $key) ?>><?= e($key) ?> (<?= e((string) ($counts[$key] ?? 0)) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="search">Cari kunci lokal / id feeder</label>
                <input type="text" id="search" name="search" value="<?= e($search) ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Tampilkan</button>
        </div>
    </form>
</div>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="empty">Belum ada pemetaan untuk entity ini.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Kunci lokal SIAKAD</th>
                <th>ID feeder</th>
                <th>Sinkron terakhir</th>
                <th>Perubahan terakhir</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><code><?= e((string) $row['local_key']) ?></code></td>
                    <td>
                        <form method="post" action="/mappings/update" class="actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entity" value="<?= e($entity) ?>">
                            <input type="hidden" name="local_key" value="<?= e((string) $row['local_key']) ?>">
                            <input type="text" name="feeder_id" value="<?= e((string) ($row['feeder_id'] ?? '')) ?>" style="max-width:190px">
                            <button class="btn btn-secondary btn-sm" type="submit">Simpan</button>
                        </form>
                    </td>
                    <td class="nowrap"><?= e(human_datetime((string) ($row['last_synced_at'] ?? ''))) ?></td>
                    <td class="nowrap"><?= e(substr((string) ($row['payload_hash'] ?? '—'), 0, 10)) ?></td>
                    <td>
                        <form method="post" action="/mappings/delete" onsubmit="return confirm('Hapus pemetaan ini? Baris akan dikirim ulang pada sinkronisasi berikutnya.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entity" value="<?= e($entity) ?>">
                            <input type="hidden" name="local_key" value="<?= e((string) $row['local_key']) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
