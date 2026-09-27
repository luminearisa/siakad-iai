<?php

declare(strict_types=1);

/**
 * Mock Neo Feeder Web Service untuk pengujian lokal.
 *
 * Meniru kontrak `ws/live2.php` / `ws/sandbox2.php`: satu endpoint POST, body JSON
 * dengan `act` (+ `token` untuk semua fungsi selain GetToken), dan respons
 * `{error_code, error_desc, data}`. Beberapa validasi sengaja ditiru supaya
 * skenario gagal ikut teruji:
 *
 * - NIK yang sudah dipakai akan ditolak (`error_code = 1`);
 * - `GetPeriode` menolak permintaan tanpa filter/limit bila diinstruksikan;
 * - insert apa pun tanpa `id_prodi` yang dikenal ditolak.
 *
 * Jalankan: php -S 127.0.0.1:3001 integrator/tests/fake_feeder.php
 */

$raw = file_get_contents('php://input') ?: '{}';
$request = json_decode($raw, true);

header('Content-Type: application/json');

if (! is_array($request) || ! isset($request['act'])) {
    echo json_encode(['error_code' => 1, 'error_desc' => 'act tidak dikenali', 'data' => []]);

    return;
}

$act = (string) $request['act'];

// Credential store of the fake installation.
$username = 'operator';
$password = 'rahasia123';
$token = 'mock-token-'.md5($username);

$ok = static function (mixed $data): void {
    echo json_encode(['error_code' => 0, 'error_desc' => '', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$fail = static function (string $message): void {
    echo json_encode(['error_code' => 1, 'error_desc' => $message, 'data' => []], JSON_UNESCAPED_UNICODE);
};

if ($act === 'GetToken') {
    if (($request['username'] ?? '') !== $username || ($request['password'] ?? '') !== $password) {
        $fail('Username atau password salah');

        return;
    }

    $ok(['token' => $token]);

    return;
}

// Everything else requires a valid token.
if (($request['token'] ?? '') !== $token) {
    $fail('Token tidak valid atau sudah kedaluwarsa');

    return;
}

switch ($act) {
    case 'GetProfilPT':
        $ok([[
            'id_perguruan_tinggi' => 'pt-iai-001',
            'kode_perguruan_tinggi' => 'IAI001',
            'nama_perguruan_tinggi' => 'Institut Agama Islam (Mock)',
        ]]);
        break;

    case 'GetAllProdi':
        $ok([
            ['id_prodi' => 'prodi-pai-01', 'kode_program_studi' => 'PAI', 'nama_program_studi' => 'Pendidikan Agama Islam', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-es-02', 'kode_program_studi' => 'ES', 'nama_program_studi' => 'Ekonomi Syariah', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-hes-03', 'kode_program_studi' => 'HES', 'nama_program_studi' => 'Hukum Ekonomi Syariah (Muamalah)', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-hki-04', 'kode_program_studi' => 'HKI', 'nama_program_studi' => 'Hukum Keluarga Islam (Ahwal Syakhshiyyah)', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-iat-05', 'kode_program_studi' => 'IAT', 'nama_program_studi' => 'Ilmu Al-Qur\'an dan Tafsir', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-kpi-06', 'kode_program_studi' => 'KPI', 'nama_program_studi' => 'Komunikasi dan Penyiaran Islam', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-mpi-07', 'kode_program_studi' => 'MPI', 'nama_program_studi' => 'Manajemen Pendidikan Islam', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-pba-08', 'kode_program_studi' => 'PBA', 'nama_program_studi' => 'Pendidikan Bahasa Arab', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-pbs-09', 'kode_program_studi' => 'PBS', 'nama_program_studi' => 'Perbankan Syariah', 'id_perguruan_tinggi' => 'pt-iai-001'],
            ['id_prodi' => 'prodi-pgmi-10', 'kode_program_studi' => 'PGMI', 'nama_program_studi' => 'Pendidikan Guru Madrasah Ibtidaiyah', 'id_perguruan_tinggi' => 'pt-iai-001'],
        ]);
        break;

    case 'GetPeriode':
        $ok([
            [
                'id_semester' => '20251',
                'nama_semester' => '2025/2026 Ganjil',
                'semester' => '1',
                'tahun_ajaran' => '2025/2026',
            ],
            [
                'id_semester' => '20252',
                'nama_semester' => '2025/2026 Genap',
                'semester' => '2',
                'tahun_ajaran' => '2025/2026',
            ],
            [
                'id_semester' => '20261',
                'nama_semester' => '2026/2027 Ganjil',
                'semester' => '1',
                'tahun_ajaran' => '2026/2027',
            ],
            [
                'id_semester' => '20262',
                'nama_semester' => '2026/2027 Genap',
                'semester' => '2',
                'tahun_ajaran' => '2026/2027',
            ],
        ]);
        break;

    case 'GetListDosen':
        $ok([
            ['id_dosen' => 'dosen-77', 'nidn' => '0011223301', 'nip' => '198701012010011001', 'nama_dosen' => 'Dr. Ahmad Dosen, M.Kom'],
            ['id_dosen' => 'dosen-78', 'nidn' => '0011223302', 'nip' => '198701012010011002', 'nama_dosen' => 'Dr. Hj. Siti Fatimah, M.Ag'],
            ['id_dosen' => 'dosen-79', 'nidn' => '0011223303', 'nip' => '198701012010011003', 'nama_dosen' => 'Muhammad Zaid, M.E.Sy'],
            ['id_dosen' => 'dosen-80', 'nidn' => '0011223304', 'nip' => '198701012010011004', 'nama_dosen' => 'Prof. Dr. H. Lukman Hakim, M.A.'],
            ['id_dosen' => 'dosen-81', 'nidn' => '0011223305', 'nip' => '198701012010011005', 'nama_dosen' => 'Dr. Nurul Hidayah, M.Pd.'],
            ['id_dosen' => 'dosen-82', 'nidn' => '0011223306', 'nip' => '198701012010011006', 'nama_dosen' => 'Ridwan Kamil, Lc., M.H.'],
        ]);
        break;

    case 'GetKategoriKegiatan':
        $ok([
            ['id_kategori_kegiatan' => '1', 'nama_kategori_kegiatan' => 'Magang/Praktik Kerja'],
            ['id_kategori_kegiatan' => '2', 'nama_kategori_kegiatan' => 'Asisten Mengajar'],
        ]);
        break;

    case 'GetDictionary':
        $ok([
            ['nama_tabel' => 'mahasiswa', 'nama_kolom' => 'nama_mahasiswa', 'tipe_data' => 'varchar'],
            ['nama_tabel' => 'mahasiswa', 'nama_kolom' => 'jenis_kelamin', 'tipe_data' => 'char'],
            ['nama_tabel' => 'mahasiswa', 'nama_kolom' => 'nik', 'tipe_data' => 'char'],
            ['nama_tabel' => 'kelas_kuliah', 'nama_kolom' => 'nama_kelas_kuliah', 'tipe_data' => 'varchar'],
        ]);
        break;

    default:
        // Insert/Update family: validate the payload shape and echo an id back.
        if (! str_starts_with($act, 'Insert') && ! str_starts_with($act, 'Update')) {
            $fail("Fungsi {$act} tidak dikenal pada mock feeder");

            return;
        }

        if ($act === 'InsertBiodataMahasiswa') {
            if (empty($request['nama_mahasiswa']) || empty($request['jenis_kelamin'])) {
                $fail('nama_mahasiswa dan jenis_kelamin wajib diisi');

                return;
            }

            if (($request['nik'] ?? '') === '3201012345670001' && ($request['_allow_nik'] ?? false) !== true) {
                $fail('NIK 3201012345670001 sudah digunakan mahasiswa lain');

                return;
            }

            $ok(['id_mahasiswa' => 'mhs-'.substr(md5((string) $request['nama_mahasiswa'].($request['nik'] ?? '')), 0, 8)]);
            break;
        }

        if ($act === 'InsertRiwayatPendidikanMahasiswa') {
            if (empty($request['id_prodi']) || empty($request['id_mahasiswa'])) {
                $fail('id_mahasiswa dan id_prodi wajib diisi');

                return;
            }

            $ok(['id_registrasi_mahasiswa' => 'reg-'.substr(md5((string) $request['id_mahasiswa']), 0, 8)]);
            break;
        }

        if ($act === 'InsertKelasKuliah') {
            if (empty($request['id_semester']) || empty($request['kode_mata_kuliah'])) {
                $fail('id_semester dan kode_mata_kuliah wajib diisi');

                return;
            }

            $ok(['id_kelas' => 'kelas-'.substr(md5((string) $request['id_semester'].(string) $request['kode_mata_kuliah']), 0, 8)]);
            break;
        }

        $ok(['status' => 'ok', 'act' => $act, 'received' => array_keys($request)]);
}
