<?php
/**
 * @var array<string, array<int, \Integrator\Sync\SyncerInterface>> $groups
 * @var array<string, int> $mappingCounts
 * @var array<string, array<string, mixed>> $statuses
 * @var array<int, array<string, mixed>> $semesters
 * @var array<string, mixed>|null $lastRun
 * @var array<string, mixed> $values
 */
?>
<div class="page-head">
    <div>
        <h2>Sinkronisasi</h2>
        <p>Jalankan per entity. Mulai dari Referensi, lalu Mahasiswa → Kelas → KRS → AKM → Nilai.</p>
    </div>
    <?php if ($lastRun !== null): ?>
        <div class="actions">
            <a class="btn btn-secondary" href="/sync/run/<?= e((string) $lastRun['run_id']) ?>">Hasil run terakhir</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Jalankan sinkronisasi</h3>
    <form method="post" action="/sync/run">
        <?= csrf_field() ?>
        <div class="row">
            <div class="field">
                <label for="entity">Entity</label>
                <select id="entity" name="entity" required>
                    <?php foreach ($groups as $group => $syncers): ?>
                        <optgroup label="<?= e($group) ?>">
                            <?php foreach ($syncers as $syncer): ?>
                                <option value="<?= e($syncer->key()) ?>"><?= e($syncer->label()) ?> · <?= e($syncer->key()) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="semester_code">Semester (untuk entity perkuliahan)</label>
                <select id="semester_code" name="semester_code">
                    <option value="">— tidak dipakai —</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= e((string) ($semester['feeder_code'] ?? '')) ?>"<?= selected($values['semester_code'], (string) ($semester['feeder_code'] ?? '')) ?>>
                            <?= e((string) ($semester['feeder_code'] ?? '')) ?> — <?= e((string) ($semester['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($semesters === []): ?>
                    <div class="help warn-text">Daftar semester kosong: periksa koneksi SIAKAD di halaman Pengaturan.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="limit">Batas baris (0 = semua)</label>
                <input type="number" id="limit" name="limit" value="<?= e((string) $values['limit']) ?>" min="0">
                <div class="help">Gunakan 5–20 baris untuk uji coba pertama.</div>
            </div>
        </div>
        <div class="row">
            <div class="checkbox field">
                <input type="checkbox" id="dry_run" name="dry_run" value="1"<?= checked($values['dry_run']) ?>>
                <label for="dry_run" style="margin:0">Dry-run (tidak mengirim apa pun ke feeder)</label>
            </div>
            <div class="checkbox field">
                <input type="checkbox" id="with_dependencies" name="with_dependencies" value="1">
                <label for="with_dependencies" style="margin:0">Jalankan juga entity prasyarat, berurutan</label>
            </div>
            <div class="checkbox field">
                <input type="checkbox" id="force" name="force" value="1">
                <label for="force" style="margin:0">Paksa kirim ulang meski isinya tidak berubah</label>
            </div>
        </div>
        <div class="actions">
            <button class="btn" type="submit">Jalankan</button>
            <span class="muted">Dry-run tetap membaca data SIAKAD dan menyusun payload, hanya panggilan ke feeder yang dilewati.</span>
        </div>
    </form>
</div>

<div class="card">
    <h3>Entity dan urutannya</h3>
    <table>
        <thead>
        <tr>
            <th>Entity</th>
            <th>Prasyarat</th>
            <th>Fungsi feeder</th>
            <th>Scope</th>
            <th>Semester</th>
            <th>Terpetakan</th>
            <th>Run terakhir</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($groups as $group => $syncers): ?>
            <tr>
                <th colspan="7" style="background:#f8fafc"><?= e($group) ?></th>
            </tr>
            <?php foreach ($syncers as $syncer): ?>
                <?php $status = $statuses[$syncer->key()] ?? null; ?>
                <tr>
                    <td>
                        <strong><?= e($syncer->label()) ?></strong><br>
                        <code><?= e($syncer->key()) ?></code>
                        <div class="muted"><?= e($syncer->description()) ?></div>
                    </td>
                    <td><?= $syncer->dependsOn() === [] ? '—' : '<code>'.e(implode('</code>, <code>', $syncer->dependsOn())).'</code>' ?></td>
                    <td>
                        <?php if ($syncer->actInsert() === null): ?>
                            <span class="badge badge-info">tarik referensi</span>
                        <?php else: ?>
                            <code><?= e($syncer->actInsert()) ?></code>
                            <?php if ($syncer->actUpdate() !== null): ?><br><code><?= e($syncer->actUpdate()) ?></code><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= e($syncer->scope() ?? '—') ?></td>
                    <td><?= $syncer->requiresSemester() ? 'wajib' : '—' ?></td>
                    <td><?= e((string) ($mappingCounts[$syncer->key()] ?? 0)) ?></td>
                    <td>
                        <?php if ($status === null): ?>
                            <span class="muted">belum pernah</span>
                        <?php else: ?>
                            <a href="/sync/run/<?= e((string) $status['run_id']) ?>"><?= e(human_datetime((string) $status['started_at'])) ?></a><br>
                            <span class="badge <?= $status['mode'] === 'live' ? 'badge-warn' : 'badge-info' ?>"><?= e((string) $status['mode']) ?></span>
                            <span class="badge badge-ok"><?= e((string) $status['succeeded']) ?></span>
                            <span class="badge badge-danger"><?= e((string) $status['failed']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
