<?php

declare(strict_types=1);

/**
 * Aturan validasi pra-kirim ke Neo Feeder PDDikti.
 *
 * Isi file ini mengikuti aturan yang dipakai Neo Feeder (versi 3.x) dan
 * praktik pelaporan PDDikti:
 *
 * - NIK 16 digit angka, NISN 10 digit angka;
 * - nomor HP wajib diisi sejak patch 3.0.1 dan HARUS berformat 08… (tanpa +62/62);
 * - email wajib valid karena dipakai verifikasi OTP pelaporan;
 * - seluruh kode/id yang dikirim harus ada di tabel referensi feeder
 *   (prodi, periode, dosen) — kalau tidak, feeder menolak baris tersebut;
 * - nilai angka 0–100, indeks 0–4, IPS/IPK 0–4, SKS > 0;
 * - komposisi SKS mata kuliah harus sama dengan SKS totalnya;
 * - jumlah mahasiswa pada kelas tidak boleh melebihi kapasitasnya;
 * - untuk jenjang D3/D4/S1/S2/S3, nomor ijazah tidak dikirim ke feeder
 *   (dikosongkan sesuai aturan patch 3.0.1).
 *
 * `notes` adalah temuan tingkat entity (bukan per baris) yang ditampilkan
 * sebagai catatan kesiapan data, misalnya pemetaan yang masih memakai nilai
 * default sehingga berisiko salah lapor.
 */

return [
    'minimum_feeder_version' => '3.0.1',
    'minimum_feeder_version_note' => 'Patch Neo Feeder 3.0.1 menambah kewajiban nomor HP, validasi email, akun khusus WS, dan aturan nomor ijazah. Sejak 3.1 validasi PDDikti makin ketat.',

    'entities' => [
        'students' => [
            'label' => 'Mahasiswa (Biodata)',
            'prodi_source_field' => 'study_program.code',
            'rules' => [
                ['field' => 'nama_mahasiswa', 'rule' => 'required', 'code' => 'nama_kosong', 'message' => 'Nama mahasiswa wajib diisi.'],
                ['field' => 'jenis_kelamin', 'rule' => 'required', 'code' => 'jenis_kelamin_kosong', 'message' => 'Jenis kelamin wajib diisi.'],
                ['field' => 'jenis_kelamin', 'rule' => 'in:L,P', 'code' => 'jenis_kelamin_tidak_valid', 'message' => 'Jenis kelamin harus L (laki-laki) atau P (perempuan) sesuai kode Web Service PDDikti.', 'hint' => 'Periksa data jenis kelamin pada biodata mahasiswa di SIAKAD.'],
                ['field' => 'nik', 'rule' => 'required', 'code' => 'nik_kosong', 'message' => 'NIK wajib diisi — PDDikti menolak mahasiswa tanpa NIK.', 'hint' => 'Lengkapi NIK 16 digit pada biodata mahasiswa. Bila di SIAKAD sudah terisi, periksa scope students.pii pada API key integrator.'],
                ['field' => 'nik', 'rule' => 'digits:16', 'code' => 'nik_format', 'message' => 'NIK harus tepat 16 digit angka.', 'hint' => 'Hapus spasi/tanda baca; NIK yang belum terbit tidak boleh diisi asal.'],
                ['field' => 'nisn', 'rule' => 'digits:10', 'code' => 'nisn_format', 'message' => 'NISN harus tepat 10 digit angka bila diisi.'],
                ['field' => 'nama_ibu_kandung', 'rule' => 'required', 'code' => 'nama_ibu_kosong', 'severity' => 'warning', 'message' => 'Nama ibu kandung belum diisi; PDDikti memakainya untuk pencocokan data pokok dan feeder bisa menolak biodata tanpa kolom ini.', 'hint' => 'Lengkapi nama ibu kandung pada biodata mahasiswa. Bila di SIAKAD sudah terisi tetapi kosong di sini, kunci API belum memegang scope students.pii.'],
                ['field' => 'tempat_lahir', 'rule' => 'required', 'code' => 'tempat_lahir_kosong', 'message' => 'Tempat lahir wajib diisi.'],
                ['field' => 'tanggal_lahir', 'rule' => 'required', 'code' => 'tanggal_lahir_kosong', 'message' => 'Tanggal lahir wajib diisi.'],
                ['field' => 'tanggal_lahir', 'rule' => 'date', 'code' => 'tanggal_lahir_format', 'message' => 'Tanggal lahir harus berformat YYYY-MM-DD.'],
                ['field' => 'tanggal_lahir', 'rule' => 'not_future', 'code' => 'tanggal_lahir_masa_depan', 'message' => 'Tanggal lahir tidak boleh di masa depan.'],
                ['field' => 'handphone', 'rule' => 'required', 'code' => 'handphone_kosong', 'message' => 'Nomor HP wajib diisi sejak patch Neo Feeder 3.0.1.', 'hint' => 'Lengkapi nomor HP mahasiswa; kolom ini dipakai PDDikti untuk verifikasi. Bila di SIAKAD sudah terisi, periksa scope students.pii pada API key.'],
                ['field' => 'handphone', 'rule' => 'phone_id', 'code' => 'handphone_format', 'message' => 'Nomor HP harus berformat 08… (contoh 081234567890).', 'hint' => 'Jangan memakai +62, 62, atau 0-21 di depan: PDDikti menolak format tersebut.'],
                ['field' => 'email', 'rule' => 'required', 'code' => 'email_kosong', 'message' => 'Email wajib diisi karena dipakai verifikasi OTP pelaporan.', 'hint' => 'Bila di SIAKAD sudah terisi, periksa scope students.pii pada API key integrator.'],
                ['field' => 'email', 'rule' => 'email', 'code' => 'email_format', 'message' => 'Format email tidak valid.'],
            ],
            'notes' => [
                'nation' => 'id_agama dan id_alat_transportasi dikirim dengan nilai default 1. Pastikan nilai default itu memang sesuai data mahasiswa, atau lengkapi kolomnya di SIAKAD.',
            ],
        ],

        'student_registrations' => [
            'label' => 'Mahasiswa (Riwayat Pendidikan)',
            'prodi_source_field' => 'study_program.code',
            'rules' => [
                ['field' => 'nim', 'rule' => 'required', 'code' => 'nim_kosong', 'message' => 'NIM wajib diisi.'],
                ['field' => 'id_prodi', 'rule' => 'required', 'code' => 'prodi_kosong', 'message' => 'Program studi belum terpetakan ke id prodi feeder.', 'hint' => 'Tarik referensi Neo Feeder lalu pastikan kode prodi di SIAKAD sama dengan kode di PDDikti.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi tidak ditemukan pada referensi prodi feeder.', 'hint' => 'Jalankan "tarik referensi" pada halaman Referensi.'],
                ['field' => 'tanggal_masuk', 'rule' => 'required', 'code' => 'tanggal_masuk_kosong', 'message' => 'Tanggal masuk wajib diisi.'],
                ['field' => 'tanggal_masuk', 'rule' => 'date', 'code' => 'tanggal_masuk_format', 'message' => 'Tanggal masuk harus berformat YYYY-MM-DD.'],
                ['field' => 'tanggal_masuk', 'rule' => 'not_future', 'code' => 'tanggal_masuk_masa_depan', 'message' => 'Tanggal masuk tidak boleh di masa depan.'],
            ],
            'notes' => [
                'registration_type' => 'id_jenis_pendaftaran dan id_pembiayaan dikirim dengan nilai default 1. Verifikasi jenis pendaftaran (baru/transfer) dan pembiayaan mahasiswa agar tidak salah lapor.',
            ],
        ],

        'courses' => [
            'label' => 'Mata Kuliah',
            'rules' => [
                ['field' => 'kode_mata_kuliah', 'rule' => 'required', 'code' => 'kode_mk_kosong', 'message' => 'Kode mata kuliah wajib diisi.'],
                ['field' => 'nama_mata_kuliah', 'rule' => 'required', 'code' => 'nama_mk_kosong', 'message' => 'Nama mata kuliah wajib diisi.'],
                ['field' => 'id_prodi', 'rule' => 'required', 'code' => 'prodi_kosong', 'message' => 'Program studi mata kuliah belum terpetakan ke id prodi feeder.', 'hint' => 'Mata kuliah umum (MKU) tetap harus ditempelkan ke program studi di SIAKAD sebelum bisa dilaporkan ke PDDikti.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi tidak ditemukan pada referensi prodi feeder.'],
                ['field' => 'sks_mata_kuliah', 'rule' => 'required', 'code' => 'sks_kosong', 'message' => 'SKS mata kuliah wajib diisi.'],
                ['field' => 'sks_mata_kuliah', 'rule' => 'integer', 'code' => 'sks_format', 'message' => 'SKS mata kuliah harus bilangan bulat.'],
                ['field' => 'sks_mata_kuliah', 'rule' => 'min:1', 'code' => 'sks_nol', 'message' => 'SKS mata kuliah minimal 1.'],
                ['rule' => 'sum_equals', 'target' => 'sks_mata_kuliah', 'parts' => ['sks_tatap_muka', 'sks_praktek', 'sks_praktek_lapangan', 'sks_simulasi'], 'code' => 'komposisi_sks', 'message' => 'Total komposisi SKS (tatap muka + praktek + praktek lapangan + simulasi) tidak sama dengan SKS mata kuliah.', 'hint' => 'PDDikti menolak mata kuliah yang komposisi SKS-nya tidak seimbang.'],
            ],
            'notes' => [
                'metode_kuliah' => 'id_jenis_mata_kuliah, id_kelompok_mata_kuliah, dan metode_kuliah dikirim dengan nilai default 1 (wajib). Pastikan sesuai jenis mata kuliah sebenarnya.',
            ],
        ],

        'curricula' => [
            'label' => 'Kurikulum',
            'rules' => [
                ['field' => 'nama_kurikulum', 'rule' => 'required', 'code' => 'nama_kurikulum_kosong', 'message' => 'Nama kurikulum wajib diisi.'],
                ['field' => 'id_prodi', 'rule' => 'required', 'code' => 'prodi_kosong', 'message' => 'Program studi kurikulum belum terpetakan ke id prodi feeder.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi tidak ditemukan pada referensi prodi feeder.'],
                ['field' => 'id_semester', 'rule' => 'required', 'code' => 'semester_kosong', 'message' => 'Semester kurikulum wajib diisi.'],
                ['field' => 'id_semester', 'rule' => 'exists:periode', 'code' => 'semester_tidak_dikenal', 'message' => 'Id semester tidak ditemukan pada referensi periode feeder.', 'hint' => 'Tarik referensi periode dari feeder, lalu samakan kode semester pada SIAKAD.'],
                ['field' => 'jumlah_sks_wajib', 'rule' => 'integer', 'code' => 'sks_wajib_format', 'message' => 'Jumlah SKS wajib harus bilangan bulat.'],
                ['field' => 'jumlah_sks_wajib', 'rule' => 'min:1', 'code' => 'sks_wajib_nol', 'message' => 'Jumlah SKS wajib minimal 1.'],
            ],
            'notes' => [
                'sks_pilihan' => 'jumlah_sks_pilihan dikirim 0. Isi manual bila kurikulum memang memuat mata kuliah pilihan.',
            ],
        ],

        'classes' => [
            'label' => 'Kelas Kuliah',
            'rules' => [
                ['field' => 'nama_kelas_kuliah', 'rule' => 'required', 'code' => 'nama_kelas_kosong', 'message' => 'Nama kelas wajib diisi.'],
                ['field' => 'kode_mata_kuliah', 'rule' => 'required', 'code' => 'kode_mk_kosong', 'message' => 'Kode mata kuliah kelas wajib diisi.'],
                ['field' => 'id_prodi', 'rule' => 'required', 'code' => 'prodi_kosong', 'message' => 'Program studi kelas belum terpetakan ke id prodi feeder.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi tidak ditemukan pada referensi prodi feeder.'],
                ['field' => 'id_semester', 'rule' => 'required', 'code' => 'semester_kosong', 'message' => 'Semester kelas wajib diisi.'],
                ['field' => 'id_semester', 'rule' => 'exists:periode', 'code' => 'semester_tidak_dikenal', 'message' => 'Id semester tidak ditemukan pada referensi periode feeder.'],
                ['field' => 'kapasitas', 'rule' => 'integer', 'code' => 'kapasitas_format', 'message' => 'Kapasitas kelas harus bilangan bulat.'],
                ['field' => 'kapasitas', 'rule' => 'min:1', 'code' => 'kapasitas_nol', 'message' => 'Kapasitas kelas minimal 1.'],
                ['field' => 'jumlah_mahasiswa', 'rule' => 'integer', 'code' => 'jumlah_mahasiswa_format', 'message' => 'Jumlah mahasiswa kelas harus bilangan bulat.'],
                ['field' => 'jumlah_mahasiswa', 'rule' => 'lte:kapasitas', 'code' => 'jumlah_melebihi_kapasitas', 'message' => 'Jumlah mahasiswa melebihi kapasitas kelas.', 'hint' => 'Samakan kapasitas kelas di SIAKAD dengan jumlah peserta KRS yang benar-benar terdafar di feeder.'],
            ],
        ],

        'class_lecturers' => [
            'label' => 'Dosen Pengampu Kelas',
            'rules' => [
                ['field' => 'id_kelas', 'rule' => 'required', 'code' => 'kelas_kosong', 'message' => 'Kelas belum tersinkron sehingga id_kelas belum ada.', 'hint' => 'Sinkronkan entitas Kelas Kuliah lebih dulu.'],
                ['field' => 'id_dosen', 'rule' => 'required', 'code' => 'dosen_kosong', 'message' => 'Dosen pengampu belum terpetakan ke id dosen feeder.', 'hint' => 'Dosen harus lebih dulu ada di PDDikti (NIDN terdaftar) sebelum bisa diampu ke kelas.'],
                ['field' => 'id_dosen', 'rule' => 'exists:dosen', 'code' => 'dosen_tidak_dikenal', 'message' => 'Dosen tidak ditemukan pada referensi dosen feeder.', 'hint' => 'Tarik referensi dosen; bila NIDN belum ada, dosen tersebut belum terdaftar di PDDikti.'],
                ['field' => 'sks_substansi_total', 'rule' => 'integer', 'code' => 'sks_substansi_format', 'message' => 'SKS substansi harus bilangan bulat.'],
                ['field' => 'sks_substansi_total', 'rule' => 'min:0', 'code' => 'sks_substansi_negatif', 'message' => 'SKS substansi tidak boleh negatif (harus > 0 bila dosen mengampu penuh).'],
            ],
            'notes' => [
                'pertemuan' => 'rencana_minggu_pertemuan dan realisasi_minggu_pertemuan dikirim 16. Sesuaikan bila kelas berjalan kurang dari 16 pertemuan agar tidak dianggap mengajar penuh.',
            ],
        ],

        'enrollments' => [
            'label' => 'KRS / Peserta Kelas',
            'rules' => [
                ['field' => 'id_kelas', 'rule' => 'required', 'code' => 'kelas_kosong', 'message' => 'Kelas belum tersinkron sehingga id_kelas belum ada.', 'hint' => 'Sinkronkan entitas Kelas Kuliah lebih dulu.'],
                ['field' => 'id_registrasi_mahasiswa', 'rule' => 'required', 'code' => 'registrasi_kosong', 'message' => 'Riwayat pendidikan mahasiswa belum tersinkron sehingga id_registrasi_mahasiswa belum ada.', 'hint' => 'Sinkronkan Mahasiswa (Biodata) lalu Mahasiswa (Riwayat Pendidikan).'],
                ['field' => 'id_semester', 'rule' => 'exists:periode', 'code' => 'semester_tidak_dikenal', 'message' => 'Id semester KRS tidak ditemukan pada referensi periode feeder.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi KRS tidak ditemukan pada referensi prodi feeder.'],
            ],
        ],

        'akm' => [
            'label' => 'AKM (Aktivitas Kuliah Mahasiswa)',
            'rules' => [
                ['field' => 'id_registrasi_mahasiswa', 'rule' => 'required', 'code' => 'registrasi_kosong', 'message' => 'Riwayat pendidikan mahasiswa belum tersinkron.'],
                ['field' => 'id_semester', 'rule' => 'required', 'code' => 'semester_kosong', 'message' => 'Semester AKM wajib diisi.'],
                ['field' => 'id_semester', 'rule' => 'exists:periode', 'code' => 'semester_tidak_dikenal', 'message' => 'Id semester AKM tidak ditemukan pada referensi periode feeder.'],
                ['field' => 'sks_semester', 'rule' => 'required', 'code' => 'sks_semester_kosong', 'message' => 'SKS semester wajib diisi.'],
                ['field' => 'sks_semester', 'rule' => 'integer', 'code' => 'sks_semester_format', 'message' => 'SKS semester harus bilangan bulat.'],
                ['field' => 'sks_semester', 'rule' => 'min:1', 'code' => 'sks_semester_nol', 'message' => 'SKS semester minimal 1; mahasiswa tanpa SKS tidak dilaporkan pada AKM.'],
                ['field' => 'sks_total', 'rule' => 'integer', 'code' => 'sks_total_format', 'message' => 'SKS total harus bilangan bulat.'],
                ['field' => 'ips', 'rule' => 'min:0', 'code' => 'ips_negatif', 'message' => 'IPS tidak boleh negatif.'],
                ['field' => 'ips', 'rule' => 'max:4', 'code' => 'ips_lebih', 'message' => 'IPS tidak boleh melebihi 4.00.'],
                ['field' => 'ipk', 'rule' => 'min:0', 'code' => 'ipk_negatif', 'message' => 'IPK tidak boleh negatif.'],
                ['field' => 'ipk', 'rule' => 'max:4', 'code' => 'ipk_lebih', 'message' => 'IPK tidak boleh melebihi 4.00.'],
                ['when' => ['field' => 'sks_semester', 'rule' => 'filled'], 'field' => 'ips', 'rule' => 'required', 'code' => 'ips_kosong', 'message' => 'IPS wajib diisi karena SKS semester sudah terisi.', 'hint' => 'Hitung ulang nilai semester sebelum melaporkan AKM.'],
            ],
        ],

        'grades' => [
            'label' => 'Nilai Perkuliahan',
            'rules' => [
                ['field' => 'id_kelas', 'rule' => 'required', 'code' => 'kelas_kosong', 'message' => 'Kelas belum tersinkron sehingga id_kelas belum ada.'],
                ['field' => 'id_registrasi_mahasiswa', 'rule' => 'required', 'code' => 'registrasi_kosong', 'message' => 'Riwayat pendidikan mahasiswa belum tersinkron.'],
                ['field' => 'nilai_angka', 'rule' => 'required', 'code' => 'nilai_kosong', 'message' => 'Nilai angka wajib diisi.'],
                ['field' => 'nilai_angka', 'rule' => 'numeric', 'code' => 'nilai_format', 'message' => 'Nilai angka harus berupa angka.'],
                ['field' => 'nilai_angka', 'rule' => 'min:0', 'code' => 'nilai_negatif', 'message' => 'Nilai angka tidak boleh negatif.'],
                ['field' => 'nilai_angka', 'rule' => 'max:100', 'code' => 'nilai_lebih', 'message' => 'Nilai angka tidak boleh melebihi 100.'],
                ['field' => 'nilai_huruf', 'rule' => 'required', 'code' => 'huruf_kosong', 'message' => 'Nilai huruf wajib diisi.'],
                ['field' => 'nilai_huruf', 'rule' => 'in:A,A-,B+,B,B-,C+,C,C-,D,E,T', 'code' => 'huruf_tidak_valid', 'message' => 'Nilai huruf harus salah satu dari A, A-, B+, B, B-, C+, C, C-, D, E, atau T sesuai aturan PDDikti.'],
                ['field' => 'nilai_indeks', 'rule' => 'min:0', 'code' => 'indeks_negatif', 'message' => 'Nilai indeks tidak boleh negatif.'],
                ['field' => 'nilai_indeks', 'rule' => 'max:4', 'code' => 'indeks_lebih', 'message' => 'Nilai indeks tidak boleh melebihi 4.00.'],
            ],
            'notes' => [
                'krs_prasyarat' => 'Nilai hanya bisa dikirim untuk mahasiswa yang sudah punya KRS di kelas tersebut. Sinkronkan KRS (enrollments) sebelum nilai; feeder akan menolak nilai tanpa KRS.',
            ],
        ],

        'activities' => [
            'label' => 'Aktivitas Mahasiswa',
            'rules' => [
                ['field' => 'judul', 'rule' => 'required', 'code' => 'judul_kosong', 'message' => 'Judul aktivitas wajib diisi.'],
                ['field' => 'id_mahasiswa', 'rule' => 'required', 'code' => 'mahasiswa_kosong', 'message' => 'Mahasiswa belum tersinkron sehingga id_mahasiswa belum ada.'],
                ['field' => 'id_prodi', 'rule' => 'exists:prodi', 'code' => 'prodi_tidak_dikenal', 'message' => 'Id prodi aktivitas tidak ditemukan pada referensi prodi feeder.'],
                ['field' => 'id_semester', 'rule' => 'exists:periode', 'code' => 'semester_tidak_dikenal', 'message' => 'Id semester aktivitas tidak ditemukan pada referensi periode feeder.'],
                ['field' => 'tanggal_mulai', 'rule' => 'date', 'code' => 'tanggal_mulai_format', 'message' => 'Tanggal mulai aktivitas harus berformat YYYY-MM-DD.'],
                ['field' => 'tanggal_selesai', 'rule' => 'date', 'code' => 'tanggal_selesai_format', 'message' => 'Tanggal selesai aktivitas harus berformat YYYY-MM-DD.'],
                ['field' => 'tanggal_selesai', 'rule' => 'after_or_equal:tanggal_mulai', 'code' => 'tanggal_selesai_mendahului', 'message' => 'Tanggal selesai tidak boleh lebih awal daripada tanggal mulai.'],
            ],
            'notes' => [
                'jenis_aktivitas' => 'id_jenis_aktivitas dikirim dengan nilai default 1. Untuk MBKM/pertukaran, pastikan jenis aktivitas dan konversi SKS-nya benar karena memengaruhi AKM dan nilai.',
            ],
        ],

        'activity_supervisors' => [
            'label' => 'Pembimbing Aktivitas',
            'rules' => [
                ['field' => 'id_aktivitas', 'rule' => 'required', 'code' => 'aktivitas_kosong', 'message' => 'Aktivitas belum tersinkron sehingga id_aktivitas belum ada.'],
                ['field' => 'id_dosen', 'rule' => 'required', 'code' => 'dosen_kosong', 'message' => 'Pembimbing belum terpetakan ke id dosen feeder.'],
                ['field' => 'id_dosen', 'rule' => 'exists:dosen', 'code' => 'dosen_tidak_dikenal', 'message' => 'Dosen pembimbing tidak ditemukan pada referensi dosen feeder.'],
                ['field' => 'urutan_promotor', 'rule' => 'integer', 'code' => 'urutan_format', 'message' => 'Urutan pembimbing harus bilangan bulat.'],
                ['field' => 'urutan_promotor', 'rule' => 'min:1', 'code' => 'urutan_nol', 'message' => 'Urutan pembimbing minimal 1.'],
            ],
        ],

        'graduates' => [
            'label' => 'Lulusan / Mahasiswa Keluar',
            'prodi_source_field' => 'study_program_code',
            'rules' => [
                ['field' => 'id_registrasi_mahasiswa', 'rule' => 'required', 'code' => 'registrasi_kosong', 'message' => 'Riwayat pendidikan mahasiswa belum tersinkron.'],
                ['field' => 'tanggal_keluar', 'rule' => 'required', 'code' => 'tanggal_keluar_kosong', 'message' => 'Tanggal keluar/lulus wajib diisi.'],
                ['field' => 'tanggal_keluar', 'rule' => 'date', 'code' => 'tanggal_keluar_format', 'message' => 'Tanggal keluar harus berformat YYYY-MM-DD.'],
                ['field' => 'tanggal_keluar', 'rule' => 'not_future', 'code' => 'tanggal_keluar_masa_depan', 'message' => 'Tanggal keluar tidak boleh di masa depan.'],
                ['field' => 'ipk', 'rule' => 'required', 'code' => 'ipk_kosong', 'message' => 'IPK wajib diisi agar kelulusan tidak ditolak.'],
                ['field' => 'ipk', 'rule' => 'numeric', 'code' => 'ipk_format', 'message' => 'IPK harus berupa angka.'],
                ['field' => 'ipk', 'rule' => 'min:0', 'code' => 'ipk_negatif', 'message' => 'IPK tidak boleh negatif.'],
                ['field' => 'ipk', 'rule' => 'max:4', 'code' => 'ipk_lebih', 'message' => 'IPK tidak boleh melebihi 4.00.'],
                ['field' => 'jumlah_sks', 'rule' => 'required', 'code' => 'sks_lulus_kosong', 'message' => 'Jumlah SKS lulus wajib diisi.'],
                ['field' => 'jumlah_sks', 'rule' => 'integer', 'code' => 'sks_lulus_format', 'message' => 'Jumlah SKS lulus harus bilangan bulat.'],
                ['field' => 'jumlah_sks', 'rule' => 'min:1', 'code' => 'sks_lulus_nol', 'message' => 'Jumlah SKS lulus minimal 1.'],
                // Aturan patch 3.0.1: nomor ijazah tidak dikirim untuk jenjang D3/D4/S1/S2/S3.
                ['when' => ['field' => 'study_program_degree', 'rule' => 'in', 'values' => ['D3', 'D4', 'S1', 'S2', 'S3']], 'field' => 'nomor_ijazah', 'rule' => 'empty', 'code' => 'nomor_ijazah_harus_kosong', 'message' => 'Nomor ijazah tidak boleh dikirim untuk jenjang D3/D4/S1/S2/S3 — PDDikti mengisinya sendiri.', 'hint' => 'Hapus pemetaan nomor ijazah pada konfigurasi entities.graduates.fields.'],
            ],
            'notes' => [
                'jenis_keluar' => 'id_jenis_keluar dikirim dengan nilai default 1 (lulus). Untuk DO, mutasi, atau mengundurkan diri, sesuaikan nilainya sesuai jenis keluar PDDikti.',
                'tanggal_ijazah' => 'Patch Neo Feeder 3.0.1 menambah kolom tanggal terbit ijazah pada data kelulusan, tetapi SIAKAD belum menyimpan kolom tersebut. Tambahkan kolomnya bila tanggal ijazah akan dilaporkan.',
            ],
        ],
    ],
];
