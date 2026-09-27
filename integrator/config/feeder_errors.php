<?php

declare(strict_types=1);

/**
 * Katalog pesan error Neo Feeder/PDDikti yang paling sering muncul saat
 * pelaporan, lengkap dengan langkah penanganannya.
 *
 * Pesan feeder tidak selalu jelas bagi operator ("Isian tidak valid", "Data
 * tidak bisa dihapus", dsb). Katalog ini menerjemahkannya menjadi kategori
 * masalah + tindakan yang bisa langsung dijalankan, lalu dipakai halaman
 * Riwayat dan ringkasan kegagalan pada dashboard.
 */

return [
    [
        'category' => 'autentikasi',
        'pattern' => '/token|sesi|session|login|kredensial|credential|username|password|otp|unauthor/i',
        'hint' => 'Periksa username/password WS pada halaman Pengaturan. Sejak patch 3.0.1 PDDikti menyarankan akun khusus WS feeder; pastikan akun tersebut belum kedaluwarsa dan dipakai satu aplikasi saja.',
    ],
    [
        'category' => 'hak_akses',
        'pattern' => '/akses|tidak berhak|forbidden|ditolak|permission|role/i',
        'hint' => 'Akun WS tidak punya hak untuk aksi ini, atau akun sedang dipakai proses lain. Pastikan hanya satu proses sinkronisasi berjalan dan akun WS masih aktif di PDDikti Admin.',
    ],
    [
        'category' => 'duplikat',
        'pattern' => '/sudah (ada|terdaftar|digunakan|dipakai|dilaporkan)|telah (ada|digunakan|terdaftar)|duplikat|duplicate|unik|unique/i',
        'hint' => 'Data sudah ada di PDDikti. Cari data tersebut pada tabel referensi/daftar di feeder, lalu gunakan data yang ada (sinkronisasi bersifat memperbarui, bukan menambah ganda).',
    ],
    [
        'category' => 'ketergantungan',
        'pattern' => '/diacu|masih (digunakan|dipakai)|tidak (bisa|dapat) di(hapus|ubah)|foreign|relasi|bergantung|digunakan oleh data|sedang dipakai/i',
        'hint' => 'Data anak masih mengacu ke data ini (contoh: KRS masih mengacu ke kelas). Hapus/sinkronkan data anak lebih dulu: KRS → nilai → kelas → kurikulum → mata kuliah.',
    ],
    [
        'category' => 'periode',
        'pattern' => '/periode|semester|tahun akademik|tidak aktif|di luar|jadwal pelaporan|tutup/i',
        'hint' => 'Periode pelaporan belum dibuka atau sudah ditutup. Buka periode pada menu pelaporan feeder atau gunakan semester yang sedang aktif.',
    ],
    [
        'category' => 'referensi',
        'pattern' => '/tidak ditemukan|tidak terdaftar|belum terdaftar|tidak dikenal|invalid id|kode prodi|id_prodi|id_dosen|id_semester/i',
        'hint' => 'Id/kode yang dikirim tidak ada di tabel referensi feeder. Tarik referensi terbaru (prodi, periode, dosen) dan samakan kode pada SIAKAD dengan kode PDDikti.',
    ],
    [
        'category' => 'validasi_data',
        'pattern' => '/tidak valid|belum lengkap|wajib|harus diisi|format|nik|nisn|email|nomor hp|handphone|telepon|kode pos|tanggal/i',
        'hint' => 'Ada kolom yang belum memenuhi aturan PDDikti. Jalankan halaman Validasi untuk melihat baris dan kolom yang bermasalah sebelum mengirim ulang.',
    ],
    [
        'category' => 'sks_nilai',
        'pattern' => '/sks|nilai|ipk|ips|indeks|huruf|konversi|komponen evaluasi/i',
        'hint' => 'Periksa perhitungan SKS/nilai: komposisi SKS mata kuliah harus sama dengan totalnya, nilai 0–100, dan indeks 0–4. Hitung ulang AKM sebelum dilaporkan.',
    ],
    [
        'category' => 'kelulusan',
        'pattern' => '/lulus|keluar|ijazah|wisuda|yudisium|ukom|kompetensi|nomor sk/i',
        'hint' => 'Periksa data kelulusan: tanggal keluar, IPK, jumlah SKS, dan jenis keluar. Untuk jenjang D3/D4/S1/S2/S3 nomor ijazah jangan dikirim — PDDikti mengisinya sendiri.',
    ],
    [
        'category' => 'ketersediaan',
        'pattern' => '/sibuk|busy|timeout|timed out|gateway|koneksi|connection|unreachable|curl|ssl|server|antrian|overload|internal error/i',
        'hint' => 'Server feeder sedang sibuk atau koneksi terputus. Ulangi di luar jam sibuk; proses yang gagal sebagian bisa dilanjutkan dari baris terakhir.',
    ],
    [
        'category' => 'versi_patch',
        'pattern' => '/versi|version|patch|perbarui|upgrade|tidak didukung|deprecated|akun khusus ws/i',
        'hint' => 'Aplikasi/WS feeder belum memakai patch terbaru. Perbarui patch Neo Feeder lalu sinkronkan ulang data prefill — PDDikti mewajibkan patch terbaru untuk pelaporan.',
    ],
];
