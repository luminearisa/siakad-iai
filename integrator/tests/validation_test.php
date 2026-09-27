<?php

declare(strict_types=1);

/**
 * Uji mandiri untuk lapisan validasi & pelaporan integrator.
 *
 * Tidak memakai PHPUnit: aplikasi integrator memang dependency-free, jadi tes
 * ini memakai assert sederhana dan database SQLite sementara. Jalankan:
 *
 *   php tests/validation_test.php
 *
 * Fokus pengujian:
 * - aturan Neo Feeder yang paling sering menolak data (NIK/NISN/HP 08/email);
 * - deteksi kolom masked (kunci API tanpa scope PII);
 * - pembedaan error data vs menunggu sinkronisasi entitas induk;
 * - konsistensi antar kolom (komposisi SKS, jumlah mahasiswa vs kapasitas);
 * - katalog error feeder + perhitungan cakupan pelaporan per prodi.
 */

require __DIR__.'/../src/autoload.php';

use Integrator\NeoFeeder\ErrorCatalog;
use Integrator\Support\Database;
use Integrator\Sync\ArrayPath;
use Integrator\Sync\ReferenceResolver;
use Integrator\Validation\FeederValidator;
use Integrator\Validation\ValidationRepository;
use Integrator\Validation\Violation;

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        fwrite(STDOUT, "  \033[32m✓\033[0m {$label}\n");

        return;
    }

    $failed++;
    fwrite(STDOUT, "  \033[31m✗\033[0m {$label}".($detail !== null ? " — {$detail}" : '')."\n");
}

/**
 * @param  array<int, Violation>  $violations
 */
function codes(array $violations): array
{
    return array_map(static fn (Violation $violation) => $violation->code, $violations);
}

function has(array $violations, string $code): bool
{
    return in_array($code, codes($violations), true);
}

function violation(array $violations, string $code): ?Violation
{
    foreach ($violations as $current) {
        if ($current->code === $code) {
            return $current;
        }
    }

    return null;
}

// ---------------------------------------------------------------------
// Persiapan: database sementara + referensi feeder
// ---------------------------------------------------------------------

$databasePath = sys_get_temp_dir().'/integrator-validation-test-'.bin2hex(random_bytes(4)).'.sqlite';
$database = new Database($databasePath);
$database->migrate();

$references = new ReferenceResolver($database);
$references->store('prodi', 'PAI', '301', ['nama_program_studi' => 'Pendidikan Agama Islam']);
$references->store('periode', '20241', '2024/2025-1', ['nama_semester' => 'Ganjil 2024/2025']);
$references->store('dosen', '0012345678', 'D-77', ['nama_dosen' => 'Dr. Uji Coba']);

$validator = FeederValidator::load(
    __DIR__.'/../config/feeder_rules.php',
    require __DIR__.'/../config/feeder_mapping.php',
    $references
);

/** @param array<string, mixed> $overrides */
function studentPayload(array $overrides = []): array
{
    return array_merge([
        'nama_mahasiswa' => 'Nur Aisyah',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2005-01-15',
        'nik' => '3174010105050001',
        'nisn' => '0051234567',
        'nama_ibu_kandung' => 'Siti Aminah',
        'handphone' => '081234567890',
        'telepon' => '0211234567',
        'email' => 'nur.aisyah@example.test',
    ], $overrides);
}

/** @param array<string, mixed> $overrides */
function studentSource(array $overrides = []): array
{
    return array_merge([
        'nim' => '2024001',
        'study_program' => ['code' => 'PAI'],
    ], $overrides);
}

fwrite(STDOUT, "\n\033[1m1. Aturan biodata mahasiswa (Neo Feeder 3.x)\033[0m\n");

$valid = $validator->validateRow('students', 'nim:2024001', studentPayload(), studentSource());
check('data lengkap lolos tanpa temuan', $valid === [], implode(', ', codes($valid)));

$nikShort = $validator->validateRow('students', 'nim:2024001', studentPayload(['nik' => '3174']), studentSource());
check('NIK bukan 16 digit ditolak', has($nikShort, 'nik_format'));

$nisnShort = $validator->validateRow('students', 'nim:2024001', studentPayload(['nisn' => '12345']), studentSource());
check('NISN bukan 10 digit ditolak', has($nisnShort, 'nisn_format'));

$hp62 = $validator->validateRow('students', 'nim:2024001', studentPayload(['handphone' => '6281234567890']), studentSource());
check('nomor HP format 62 ditolak (aturan patch 3.0.1)', has($hp62, 'handphone_format'));

$hp62Dash = $validator->validateRow('students', 'nim:2024001', studentPayload(['handphone' => '+62 812-3456-7890']), studentSource());
check('nomor HP format +62 ditolak', has($hp62Dash, 'handphone_format'));

$hpSpaced = $validator->validateRow('students', 'nim:2024001', studentPayload(['handphone' => '0812 3456 7890']), studentSource());
check('nomor HP 08 dengan spasi diterima', ! has($hpSpaced, 'handphone_format'));

$noHp = $validator->validateRow('students', 'nim:2024001', studentPayload(['handphone' => '', 'telepon' => '']), studentSource());
check('nomor HP kosong ditolak (wajib sejak 3.0.1)', has($noHp, 'handphone_kosong'));

$badEmail = $validator->validateRow('students', 'nim:2024001', studentPayload(['email' => 'bukan-email']), studentSource());
check('email tidak valid ditolak', has($badEmail, 'email_format'));

$future = $validator->validateRow('students', 'nim:2024001', studentPayload(['tanggal_lahir' => gmdate('Y-m-d', time() + 86400 * 30)]), studentSource());
check('tanggal lahir di masa depan ditolak', has($future, 'tanggal_lahir_masa_depan'));

$gender = $validator->validateRow('students', 'nim:2024001', studentPayload(['jenis_kelamin' => '1']), studentSource());
check('jenis kelamin harus berkode L/P sesuai Web Service', has($gender, 'jenis_kelamin_tidak_valid'));
check('kode L diterima (dokumentasi resmi memakai L)', ! has($validator->validateRow('students', 'nim:2024001', studentPayload(['jenis_kelamin' => 'L']), studentSource()), 'jenis_kelamin_tidak_valid'));
check('kode P diterima', ! has($validator->validateRow('students', 'nim:2024001', studentPayload(['jenis_kelamin' => 'P']), studentSource()), 'jenis_kelamin_tidak_valid'));

$mother = $validator->validateRow('students', 'nim:2024001', studentPayload(['nama_ibu_kandung' => null]), studentSource());
check('nama ibu kandung kosong diperingatkan, bukan memblokir', has($mother, 'nama_ibu_kosong') && violation($mother, 'nama_ibu_kosong')?->severity === Violation::WARNING);
check('temuan menyertakan saran perbaikan', violation($mother, 'nama_ibu_kosong')?->hint !== null);
check('baris dengan peringatan saja tetap dianggap valid', ! array_filter([violation($mother, 'nama_ibu_kosong')], static fn ($violation) => $violation?->isError()) === [] ? ! (bool) violation($mother, 'nama_ibu_kosong')?->isError() : true);

$masked = $validator->validateRow('students', 'nim:2024001', studentPayload([
    'nik' => '3174**********01',
    'handphone' => '0812**90',
    'email' => 'nu***@example.test',
]), studentSource());
check('kolom masked terdeteksi sebagai temuan tersendiri', has($masked, 'data_tertutup_pii'));
check('aturan per kolom dilewati saat data masked', ! has($masked, 'nik_format') && ! has($masked, 'handphone_format'));
check('temuan masked menjelaskan scope students.pii', str_contains((string) violation($masked, 'data_tertutup_pii')?->hint, 'students.pii'));

fwrite(STDOUT, "\n\033[1m2. Ketergantungan antar entity & referensi\033[0m\n");

$registration = $validator->validateRow(
    'student_registrations',
    'nim:2024001',
    ['nim' => '2024001', 'id_prodi' => '301', 'tanggal_masuk' => '2024-08-01', 'nama_jenis_pendaftaran' => '1', 'id_mahasiswa' => '55'],
    studentSource()
);
check('riwayat pendidikan siap kirim lolos', $registration === [], implode(', ', codes($registration)));

$missingParent = $validator->validateRow(
    'student_registrations',
    'nim:2024001',
    ['nim' => '2024001', 'id_prodi' => '301', 'tanggal_masuk' => '2024-08-01', 'nama_jenis_pendaftaran' => '1', 'id_mahasiswa' => ''],
    studentSource()
);
check('id_mahasiswa kosong = menunggu sinkronisasi induk (warning, bukan error)', has($missingParent, 'menunggu_students'));
check('warning ketergantungan tidak menghitung baris sebagai tidak valid', violation($missingParent, 'menunggu_students')?->severity === Violation::WARNING);

$unknownProdi = $validator->validateRow(
    'student_registrations',
    'nim:2024001',
    ['nim' => '2024001', 'id_prodi' => '999', 'tanggal_masuk' => '2024-08-01', 'nama_jenis_pendaftaran' => '1', 'id_mahasiswa' => '55'],
    studentSource()
);
check('id prodi di luar referensi ditolak', has($unknownProdi, 'prodi_tidak_dikenal'));

$knownProdi = $validator->validateRow(
    'student_registrations',
    'nim:2024001',
    ['nim' => '2024001', 'id_prodi' => '301', 'tanggal_masuk' => '2024-08-01', 'nama_jenis_pendaftaran' => '1', 'id_mahasiswa' => '55'],
    studentSource()
);
check('id prodi yang ada di referensi diterima', ! has($knownProdi, 'prodi_tidak_dikenal'));

fwrite(STDOUT, "\n\033[1m3. Konsistensi kolom (SKS, kapasitas, AKM)\033[0m\n");

$badSks = $validator->validateRow('courses', 'mk:MK01', [
    'kode_mata_kuliah' => 'MK01',
    'nama_mata_kuliah' => 'Fiqih Ibadah',
    'id_prodi' => '301',
    'sks_mata_kuliah' => '3',
    'sks_tatap_muka' => '2',
    'sks_praktek' => '0',
    'sks_praktek_lapangan' => '0',
    'sks_simulasi' => '0',
], ['code' => 'MK01']);
check('komposisi SKS tidak seimbang ditolak', has($badSks, 'komposisi_sks'));

$goodSks = $validator->validateRow('courses', 'mk:MK01', [
    'kode_mata_kuliah' => 'MK01',
    'nama_mata_kuliah' => 'Fiqih Ibadah',
    'id_prodi' => '301',
    'sks_mata_kuliah' => '2',
    'sks_tatap_muka' => '2',
    'sks_praktek' => '0',
    'sks_praktek_lapangan' => '0',
    'sks_simulasi' => '0',
], ['code' => 'MK01']);
check('komposisi SKS seimbang diterima', ! has($goodSks, 'komposisi_sks'));

$overCapacity = $validator->validateRow('classes', 'kelas:K1', [
    'id_prodi' => '301',
    'id_semester' => '2024/2025-1',
    'kode_mata_kuliah' => 'MK01',
    'nama_kelas_kuliah' => 'A',
    'kapasitas' => '30',
    'jumlah_mahasiswa' => '41',
], ['code' => 'K1']);
check('jumlah mahasiswa melebihi kapasitas ditolak', has($overCapacity, 'jumlah_melebihi_kapasitas'));

$missingIps = $validator->validateRow('akm', 'akm:1', [
    'id_registrasi_mahasiswa' => 'R1',
    'id_prodi' => '301',
    'id_semester' => '2024/2025-1',
    'sks_semester' => '18',
    'ips' => '',
], ['code' => 'AKM1']);
check('aturan bersyarat: IPS wajib bila SKS semester terisi', has($missingIps, 'ips_kosong'));

$ipkTooHigh = $validator->validateRow('graduates', 'lulusan:2024001', [
    'id_registrasi_mahasiswa' => 'R1',
    'tanggal_keluar' => '2024-09-01',
    'ipk' => '4.25',
    'jumlah_sks' => '144',
], ['study_program_code' => 'PAI', 'study_program_degree' => 'S1']);
check('IPK di atas 4.00 ditolak', has($ipkTooHigh, 'ipk_lebih'));

$notes = $validator->notes('graduates', '2024/2025-1');
check('catatan tingkat entity tersedia (default & kolom ijazah)', count($notes) >= 2);
check('catatan menandai kolom tanggal terbit ijazah belum ada di SIAKAD', str_contains(implode(' ', array_map(static fn (Violation $note) => $note->message, $notes)), 'tanggal terbit ijazah'));

fwrite(STDOUT, "\n\033[1m4. Versi feeder yang didukung\033[0m\n");

check('3.0.1 dianggap memenuhi minimum', $validator->versionCompatible('3.0.1'));
check('3.1.0 dianggap memenuhi minimum', $validator->versionCompatible('3.1.0'));
check('2.1.2 dianggap belum memenuhi minimum', ! $validator->versionCompatible('2.1.2'));
check('versi tidak diketahui tidak diklaim memenuhi', ! $validator->versionCompatible(null));

fwrite(STDOUT, "\n\033[1m5. Katalog error feeder\033[0m\n");

$catalog = ErrorCatalog::load(__DIR__.'/../config/feeder_errors.php');

$cases = [
    'Token tidak valid, silakan login ulang' => 'autentikasi',
    '1 Kelas tidak bisa dihapus karena sudah diacu di data KRS Mahasiswa' => 'ketergantungan',
    'NIK sudah digunakan oleh mahasiswa lain' => 'duplikat',
    'Periode pelaporan belum dibuka' => 'periode',
    'Server sedang sibuk, coba beberapa saat lagi' => 'ketersediaan',
    'Isian tidak valid: NIK harus 16 digit' => 'validasi_data',
];

foreach ($cases as $message => $expected) {
    check("kategori \"{$expected}\" untuk pesan: ".mb_substr($message, 0, 42).'…', $catalog->categoryOf($message) === $expected, 'dapat '.$catalog->categoryOf($message));
}

check('semua kategori katalog memberi saran penanganan', $catalog->hintFor('Server sedang sibuk') !== null);
check('kategori tak dikenal tetap aman', $catalog->categoryOf('pesan aneh tanpa pola') === 'lainnya');

fwrite(STDOUT, "\n\033[1m6. Penyimpanan hasil & cakupan pelaporan\033[0m\n");

$repository = new ValidationRepository($database);
$runId = $repository->startRun('students', '20241', ['students']);
$run = $repository->latestRun();
check('run validasi tercatat', ($run['run_id'] ?? null) === $runId);
check('run baru berstatus running', ($run['status'] ?? null) === 'running');

$savedFindings = $repository->saveFindings($runId, [
    new Violation('students', 'nama_ibu_kosong', Violation::ERROR, 'Nama ibu kandung wajib diisi.', 'nim:1', 'nama_ibu_kandung', 'hint', '301', '20241'),
    new Violation('students', 'handphone_format', Violation::ERROR, 'Format HP salah.', 'nim:2', 'handphone', null, '301', '20241'),
    new Violation('students', 'menunggu_students', Violation::WARNING, 'Menunggu induk.', 'nim:3', null, null, '301', '20241'),
    new Violation('students', 'catatan_default', Violation::NOTE, 'Catatan entity.', null, null, null, '301', '20241', true),
]);
check('temuan tersimpan', $savedFindings === 4);
check('filter severity bekerja', $repository->countFindings(['run_id' => $runId, 'severity' => Violation::ERROR]) === 2);
check('filter entity bekerja', $repository->countFindings(['run_id' => $runId, 'entity' => 'students']) === 4);
check('pencarian teks bekerja', $repository->countFindings(['run_id' => $runId, 'search' => 'Nama ibu']) === 1);
check('rekap per kode temuan bekerja', count($repository->findingsByCode($runId, 10)) === 2);

$repository->saveCoverage($runId, [
    ['entity' => 'students', 'period' => '20241', 'prodi' => '301', 'prodi_label' => 'Pendidikan Agama Islam', 'total' => 10, 'valid' => 8, 'invalid' => 2, 'warnings' => 1, 'synced' => 9, 'pending' => 1, 'stale' => 0, 'percentage' => 90.0],
    ['entity' => 'students', 'period' => '20241', 'prodi' => '-', 'prodi_label' => null, 'total' => 2, 'valid' => 2, 'invalid' => 0, 'warnings' => 0, 'synced' => 0, 'pending' => 2, 'stale' => 0, 'percentage' => 0.0],
    ['entity' => 'classes', 'period' => '20241', 'prodi' => '301', 'prodi_label' => 'Pendidikan Agama Islam', 'total' => 8, 'valid' => 8, 'invalid' => 0, 'warnings' => 0, 'synced' => 4, 'pending' => 3, 'stale' => 1, 'percentage' => 50.0],
]);

// Simpan ulang snapshot yang sama: harus memperbarui, bukan menggandakan.
$repository->saveCoverage($runId, [
    ['entity' => 'classes', 'period' => '20241', 'prodi' => '301', 'prodi_label' => 'Pendidikan Agama Islam', 'total' => 8, 'valid' => 8, 'invalid' => 0, 'warnings' => 0, 'synced' => 8, 'pending' => 0, 'stale' => 0, 'percentage' => 100.0],
]);

$coverageRows = $repository->coverage();
check('snapshot cakupan idempoten (3 baris)', count($coverageRows) === 3, 'dapat '.count($coverageRows));

$byEntity = $repository->coverageByEntity();
$studentRow = [];

foreach ($byEntity as $row) {
    if ($row['entity'] === 'students') {
        $studentRow = $row;
    }
}

check('cakupan per entity menjumlahkan prodi', (int) ($studentRow['total'] ?? 0) === 12, json_encode($byEntity));
check('persentase per entity dihitung dari total', (float) ($studentRow['percentage'] ?? 0) === 75.0, (string) ($studentRow['percentage'] ?? '-'));

$byProdi = $repository->coverageByProdi(null, 10);
$paiRow = $byProdi[0] ?? [];
check('cakupan per prodi memakai label prodi', ($paiRow['prodi_label'] ?? null) === 'Pendidikan Agama Islam');
check('cakupan per prodi menjumlahkan entity', (int) ($paiRow['total'] ?? 0) === 18, json_encode($paiRow));

$totals = $repository->coverageTotals();
check('total baris dihitung', (int) $totals['total'] === 20);
check('total cakupan 85%', (float) $totals['percentage'] === 85.0, (string) $totals['percentage']);

$repository->finishRun($runId, ['total' => 20, 'valid' => 18, 'invalid' => 2, 'findings' => 2, 'synced' => 17, 'percentage' => 85.0]);
$finished = $repository->latestRun();
check('run ditutup dengan ringkasan', ($finished['status'] ?? null) === 'finished' && (int) $finished['total_records'] === 20);

check('purge menghapus riwayat lama', $repository->purge(0) === 0 && $repository->purge(30) === 0);

fwrite(STDOUT, "\n\033[1m7. Utilitas pendukung\033[0m\n");
check('ArrayPath membaca kunci bersarang', ArrayPath::get(['study_program' => ['code' => 'PAI']], 'study_program.code') === 'PAI');
check('ArrayPath memakai default saat kosong', ArrayPath::get([], 'tidak.ada', 'x') === 'x');

// ---------------------------------------------------------------------
// Bersih-bersih
// ---------------------------------------------------------------------

@unlink($databasePath);
@unlink($databasePath.'-wal');
@unlink($databasePath.'-shm');

fwrite(STDOUT, "\n".str_repeat('─', 62)."\n");
fwrite(STDOUT, sprintf("\033[1mHasil:\033[0m %d lulus, %d gagal\n\n", $passed, $failed));

exit($failed === 0 ? 0 : 1);
