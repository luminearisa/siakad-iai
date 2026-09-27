<?php
/**
 * @var array<string, string> $values
 * @var array<string, mixed>|null $check
 * @var array<string, array{label: string, value: string, ok: bool}> $diagnostics
 * @var array<int, array<string, mixed>> $semesters
 */
?>
<div class="page-head">
    <div>
        <h2>Pengaturan</h2>
        <p>Kredensial SIAKAD (API key hasil modul Integrator) dan koneksi Neo Feeder.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="/reference">Referensi feeder</a>
    </div>
</div>

<?php if ($check !== null): ?>
    <div class="grid cols-2">
        <?php foreach (['siakad' => 'SIAKAD', 'feeder' => 'Neo Feeder'] as $key => $label): ?>
            <?php $result = $check[$key] ?? null; ?>
            <?php if (is_array($result)): ?>
                <div class="card" style="border-left:4px solid <?= ! empty($result['ok']) ? 'var(--ok)' : 'var(--danger)' ?>">
                    <h3><?= e($label) ?> — <?= ! empty($result['ok']) ? 'terhubung' : 'gagal' ?></h3>
                    <p class="hint"><?= e((string) ($result['message'] ?? '')) ?></p>
                    <?php foreach ((array) ($result['details'] ?? []) as $name => $value): ?>
                        <div><strong><?= e((string) $name) ?>:</strong> <?= e((string) $value) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/settings">
    <?= csrf_field() ?>

    <div class="card">
        <h3>1. SIAKAD (sumber data)</h3>
        <div class="row">
            <div class="field">
                <label for="siakad_base_url">Base URL SIAKAD</label>
                <input type="text" id="siakad_base_url" name="siakad_base_url" value="<?= e($values['siakad_base_url']) ?>" placeholder="Format: https://siakad.&lt;domain-kampus&gt;">
                <div class="help">Tanpa garis miring di akhir. Endpoint integrasi: <code>/api/v1/integrator/v1/*</code>.</div>
            </div>
            <div class="field">
                <label for="siakad_api_key">API key SIAKAD</label>
                <input type="text" id="siakad_api_key" name="siakad_api_key" value="" placeholder="<?= e($values['siakad_api_key'] !== '' ? $values['siakad_api_key'] : 'Format: sk_&lt;prefix&gt;.&lt;secret&gt;') ?>">
                <div class="help">Kosongkan bila tidak ingin mengubah. Terbitkan di SIAKAD → Integrator → Kunci API. Disimpan terenkripsi.</div>
            </div>
        </div>
        <div class="checkbox field">
            <input type="checkbox" id="siakad_verify_ssl" name="siakad_verify_ssl" value="1"<?= checked($values['siakad_verify_ssl']) ?>>
            <label for="siakad_verify_ssl" style="margin:0">Verifikasi sertifikat SSL SIAKAD</label>
        </div>
    </div>

    <div class="card">
        <h3>2. Neo Feeder (tujuan data)</h3>
        <div class="row">
            <div class="field">
                <label for="feeder_base_url">Host Web Service Neo Feeder</label>
                <input type="text" id="feeder_base_url" name="feeder_base_url" value="<?= e($values['feeder_base_url']) ?>" placeholder="http://10.0.0.5:8100">
                <div class="help">Alamat instalasi Neo Feeder. Endpoint otomatis menjadi <code>/ws/live2.php</code> atau <code>/ws/sandbox2.php</code>.</div>
            </div>
            <div class="field">
                <label for="feeder_username">Username akun PT</label>
                <input type="text" id="feeder_username" name="feeder_username" value="<?= e($values['feeder_username']) ?>" autocomplete="off">
                <div class="help">Akun Neo Feeder yang dipakai untuk sinkronisasi (biasanya akun Perguruan Tinggi).</div>
            </div>
            <div class="field">
                <label for="feeder_password">Password</label>
                <input type="password" id="feeder_password" name="feeder_password" value="" placeholder="<?= e($values['feeder_password'] !== '' ? $values['feeder_password'] : '••••••') ?>" autocomplete="new-password">
                <div class="help">Kosongkan bila tidak ingin mengubah. Disimpan terenkripsi (AES-256-GCM).</div>
            </div>
        </div>
        <div class="row">
            <div class="checkbox field">
                <input type="checkbox" id="feeder_sandbox" name="feeder_sandbox" value="1"<?= checked($values['feeder_sandbox']) ?>>
                <label for="feeder_sandbox" style="margin:0">Gunakan mode <strong>sandbox</strong> (<code>ws/sandbox2.php</code>)</label>
            </div>
            <div class="checkbox field">
                <input type="checkbox" id="feeder_verify_ssl" name="feeder_verify_ssl" value="1"<?= checked($values['feeder_verify_ssl']) ?>>
                <label for="feeder_verify_ssl" style="margin:0">Verifikasi sertifikat SSL feeder</label>
            </div>
        </div>
        <p class="hint">Matikan mode sandbox hanya setelah proses dry-run dan uji sandbox berjalan tanpa error.</p>
    </div>

    <div class="card">
        <h3>3. Perilaku sinkronisasi</h3>
        <div class="row">
            <div class="field">
                <label for="default_semester_code">Kode semester default (PDDikti)</label>
                <select id="default_semester_code" name="default_semester_code">
                    <option value="">— pilih saat menjalankan —</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= e((string) ($semester['feeder_code'] ?? '')) ?>"<?= selected($values['default_semester_code'], (string) ($semester['feeder_code'] ?? '')) ?>>
                            <?= e((string) ($semester['feeder_code'] ?? '')) ?> — <?= e((string) ($semester['name'] ?? '')) ?> (<?= e((string) ($semester['type'] ?? '')) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help">Kode diambil dari SIAKAD (contoh <code>20251</code> untuk ganjil 2025/2026).</div>
            </div>
            <div class="field">
                <label for="feeder_payload_style">Bentuk body Web Service</label>
                <select id="feeder_payload_style" name="feeder_payload_style">
                    <option value="record"<?= selected($values['feeder_payload_style'], 'record') ?>>record — sesuai dokumentasi resmi (disarankan)</option>
                    <option value="flat"<?= selected($values['feeder_payload_style'], 'flat') ?>>flat — instalasi lama yang menolak `record`</option>
                </select>
                <div class="help">Fungsi tulis (Insert/Update) mengirim field di dalam <code>record</code>. Bila feeder menolak, aplikasi otomatis mencoba bentuk lain dan memakai yang berhasil.</div>
            </div>
            <div class="field">
                <label for="feeder_version">Versi Neo Feeder terpasang</label>
                <input type="text" id="feeder_version" name="feeder_version" value="<?= e($values['feeder_version']) ?>" placeholder="3.0.1">
                <div class="help">
                    Diisi otomatis saat “Uji koneksi”; lengkapi manual bila tidak terdeteksi.
                    <?php if ($values['feeder_version_checked_at'] !== ''): ?>
                        Terakhir diperiksa <?= e(human_datetime($values['feeder_version_checked_at'])) ?>.
                    <?php endif; ?>
                </div>
            </div>
            <div class="field">
                <label for="request_delay_ms">Jeda antar baris (ms)</label>
                <input type="number" id="request_delay_ms" name="request_delay_ms" value="<?= e($values['request_delay_ms']) ?>" min="0" max="5000">
                <div class="help">Gunakan 50–200 ms bila server feeder sering menolak karena sibuk.</div>
            </div>
            <div class="field">
                <label for="batch_size">Batch size (baris per halaman SIAKAD)</label>
                <input type="number" id="batch_size" name="batch_size" value="<?= e($values['batch_size']) ?>" min="1" max="500">
            </div>
            <div class="field">
                <label for="max_requests_per_run">Batas permintaan per run (0 = tanpa batas)</label>
                <input type="number" id="max_requests_per_run" name="max_requests_per_run" value="<?= e($values['max_requests_per_run']) ?>" min="0">
            </div>
        </div>
        <div class="checkbox field">
            <input type="checkbox" id="validate_before_push" name="validate_before_push" value="1"<?= checked($values['validate_before_push']) ?>>
            <label for="validate_before_push" style="margin:0">Validasi tiap baris terhadap aturan Neo Feeder sebelum dikirim (baris yang pasti ditolak tidak dikirim)</label>
        </div>
        <div class="checkbox field">
            <input type="checkbox" id="dry_run" name="dry_run" value="1"<?= checked($values['dry_run']) ?>>
            <label for="dry_run" style="margin:0">Jadikan <strong>dry-run</strong> sebagai mode default (sangat disarankan)</label>
        </div>
    </div>

    <div class="actions">
        <button class="btn" type="submit">Simpan pengaturan</button>
        <a class="btn btn-secondary" href="/">Batal</a>
    </div>
</form>

<div class="card" style="margin-top:18px">
    <h3>Diagnostik lingkungan</h3>
    <table>
        <tbody>
        <?php foreach ($diagnostics as $row): ?>
            <tr>
                <th style="width:36%"><?= e($row['label']) ?></th>
                <td>
                    <span class="badge <?= $row['ok'] ? 'badge-ok' : 'badge-danger' ?>"><?= $row['ok'] ? 'ok' : 'perlu diperiksa' ?></span>
                    <?= e($row['value']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
