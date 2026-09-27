<?php

declare(strict_types=1);

/**
 * Pemetaan SIAKAD → Neo Feeder.
 *
 * File ini adalah satu-satunya tempat yang perlu diubah bila versi Neo Feeder yang
 * terpasang memakai nama kolom yang berbeda. Nama kolom di bawah diambil dari
 * dokumentasi resmi "DOC API Feeder versi 2.1"; **verifikasi dulu** lewat menu
 * Referensi → Kamus Kolom (fungsi `GetDictionary`) sebelum menjalankan sinkronisasi
 * mode live, karena PDDikti memvalidasi setiap kolom.
 *
 * Sintaks nilai:
 *   nim                     → ambil dari kolom baris SIAKAD (dot path didukung)
 *   @ref:prodi:{kode}       → id hasil penarikan referensi feeder (GetAllProdi, ...)
 *   @semester               → id_semester dari semester yang dipilih
 *   @semester:{kolom}       → id_semester dari kolom baris (kode semester PDDikti)
 *   @mapping:classes:kelas:{kode} → id feeder milik baris entity lain
 *   @literal:1              → nilai tetap
 *   @int:@float:@bool:@date:@datetime  → konversi tipe
 */

return [
    /* --------------------------------------------------------------------- */
    /* Referensi (feeder → lokal)                                            */
    /* --------------------------------------------------------------------- */
    'reference' => [
        'label' => 'Referensi Neo Feeder',
        'description' => 'Menarik data acuan dari feeder: profil PT, program studi, periode, dosen, kategori kegiatan, dan kamus kolom.',
        'sources' => [
            'pt' => [
                'label' => 'Profil Perguruan Tinggi',
                'act' => 'GetProfilPT',
                'key_templates' => ['{kode_perguruan_tinggi}'],
                'id_field' => 'id_perguruan_tinggi',
                'limit' => 100,
            ],
            'prodi' => [
                'label' => 'Program Studi',
                'act' => 'GetAllProdi',
                'key_templates' => ['{kode_program_studi}'],
                'id_field' => 'id_prodi',
                'limit' => 1000,
            ],
            'periode' => [
                'label' => 'Periode / Semester',
                'act' => 'GetPeriode',
                'key_templates' => ['{id_semester}'],
                'id_field' => 'id_semester',
                'limit' => 1000,
            ],
            'dosen' => [
                'label' => 'Dosen',
                'act' => 'GetListDosen',
                'key_templates' => ['{nidn}', '{nip}'],
                'id_field' => 'id_dosen',
                'limit' => 0,
            ],
            'kategori_kegiatan' => [
                'label' => 'Kategori Kegiatan',
                'act' => 'GetKategoriKegiatan',
                'key_templates' => ['{id_kategori_kegiatan}'],
                'id_field' => 'id_kategori_kegiatan',
                'limit' => 1000,
            ],
            'dictionary' => [
                'label' => 'Kamus Kolom (GetDictionary)',
                'act' => 'GetDictionary',
                'key_templates' => ['{nama_tabel}.{nama_kolom}'],
                'id_field' => null,
                'limit' => 0,
            ],
        ],
    ],

    /* --------------------------------------------------------------------- */
    /* Data akademik (SIAKAD → feeder)                                       */
    /* --------------------------------------------------------------------- */
    'entities' => [
        'students' => [
            'label' => 'Mahasiswa (Biodata)',
            'group' => 'Mahasiswa',
            'description' => 'InsertBiodataMahasiswa — identitas mahasiswa. Wajib dijalankan sebelum riwayat pendidikan.',
            'depends_on' => ['reference'],
            'source' => ['endpoint' => 'students', 'scope' => 'students.read', 'semester' => false],
            'local_key' => 'nim:{nim}',
            'act_insert' => 'InsertBiodataMahasiswa',
            'act_update' => 'UpdateBiodataMahasiswa',
            'required' => ['nama_mahasiswa', 'jenis_kelamin'],
            'fields' => [
                'nama_mahasiswa' => 'name',
                'jenis_kelamin' => 'gender',
                'tempat_lahir' => 'birth_place',
                'tanggal_lahir' => '@date:birth_date',
                'nik' => 'nik',
                'nisn' => 'nisn',
                'nama_ibu_kandung' => 'mother_name',
                'jalan' => 'address',
                'kelurahan' => 'district',
                'kode_pos' => 'postal_code',
                'telepon' => 'phone',
                'handphone' => 'phone',
                'email' => 'email',
                'id_mahasiswa' => '@mapping:students:nim:{nim}',
            ],
            'defaults' => [
                'id_agama' => '1',
                'id_alat_transportasi' => '1',
            ],
        ],

        'student_registrations' => [
            'label' => 'Mahasiswa (Riwayat Pendidikan)',
            'group' => 'Mahasiswa',
            'description' => 'InsertRiwayatPendidikanMahasiswa — pendaftaran mahasiswa pada program studi. Menghasilkan id_registrasi_mahasiswa yang dipakai AKM, KRS, dan nilai.',
            'depends_on' => ['students'],
            'source' => ['endpoint' => 'students', 'scope' => 'students.read', 'semester' => false],
            'local_key' => 'nim:{nim}',
            'act_insert' => 'InsertRiwayatPendidikanMahasiswa',
            'act_update' => null,
            'required' => ['id_mahasiswa', 'id_prodi', 'nim'],
            'fields' => [
                'id_mahasiswa' => '@mapping:students:nim:{nim}',
                'id_prodi' => '@ref:prodi:{study_program.code}',
                'nim' => 'nim',
                'tanggal_masuk' => '@date:entry_date',
                'nama_jenis_pendaftaran' => '@literal:1',
            ],
            'defaults' => [
                'id_jenis_pendaftaran' => '1',
                'id_pembiayaan' => '1',
            ],
        ],

        'courses' => [
            'label' => 'Mata Kuliah',
            'group' => 'Kurikulum & Mata Kuliah',
            'description' => 'InsertMataKuliah — katalog mata kuliah program studi.',
            'depends_on' => ['reference'],
            'source' => ['endpoint' => 'courses', 'scope' => 'courses.read', 'semester' => false],
            'local_key' => 'mk:{code}',
            'act_insert' => 'InsertMataKuliah',
            'act_update' => 'UpdateMataKuliah',
            'required' => ['kode_mata_kuliah', 'nama_mata_kuliah', 'id_prodi'],
            'fields' => [
                'kode_mata_kuliah' => 'code',
                'nama_mata_kuliah' => 'name',
                'id_prodi' => '@ref:prodi:{study_program.code}',
                'sks_mata_kuliah' => '@int:credits',
                'sks_tatap_muka' => '@int:credits_breakdown.theory',
                'sks_praktek' => '@int:credits_breakdown.practical',
                'sks_praktek_lapangan' => '@int:credits_breakdown.field_practical',
                'sks_simulasi' => '@int:credits_breakdown.simulation',
                'id_jenis_mata_kuliah' => '@literal:1',
            ],
            'defaults' => [
                'id_jenis_mata_kuliah' => '1',
                'id_kelompok_mata_kuliah' => '1',
                'metode_kuliah' => '1',
            ],
        ],

        'curricula' => [
            'label' => 'Kurikulum',
            'group' => 'Kurikulum & Mata Kuliah',
            'description' => 'InsertKurikulum — kurikulum prodi beserta semester berlakunya.',
            'depends_on' => ['reference'],
            'source' => ['endpoint' => 'curricula', 'scope' => 'curricula.read', 'semester' => false],
            'local_key' => 'kurikulum:{code}',
            'act_insert' => 'InsertKurikulum',
            'act_update' => 'UpdateKurikulum',
            'required' => ['id_prodi', 'nama_kurikulum'],
            'fields' => [
                'id_prodi' => '@ref:prodi:{study_program.code}',
                'nama_kurikulum' => 'name',
                'id_semester' => '@semester',
                'jumlah_sks_wajib' => '@int:start_year',
            ],
            'defaults' => [
                'jumlah_sks_pilihan' => '0',
            ],
        ],

        'classes' => [
            'label' => 'Kelas Kuliah',
            'group' => 'Perkuliahan',
            'description' => 'InsertKelasKuliah — kelas perkuliahan per mata kuliah per semester. Harus dijalankan sebelum KRS dan nilai.',
            'depends_on' => ['reference', 'courses'],
            'source' => ['endpoint' => 'classes', 'scope' => 'classes.read', 'semester' => true],
            'local_key' => 'kelas:{code}',
            'act_insert' => 'InsertKelasKuliah',
            'act_update' => null,
            'required' => ['id_prodi', 'id_semester', 'kode_mata_kuliah', 'nama_kelas_kuliah'],
            'fields' => [
                'id_prodi' => '@ref:prodi:{study_program.code}',
                'id_semester' => '@semester',
                'kode_mata_kuliah' => 'course.code',
                'nama_kelas_kuliah' => 'name',
                'kapasitas' => '@int:capacity',
                'jumlah_mahasiswa' => '@int:enrolled_count',
                'bahasan' => 'name',
            ],
            'defaults' => [
                'mode_kuliah' => '1',
            ],
        ],

        'class_lecturers' => [
            'label' => 'Dosen Pengampu Kelas',
            'group' => 'Perkuliahan',
            'description' => 'InsertDosenPengajarKelasKuliah — pengampu tiap kelas. Komponen wajib untuk IU (indikator kinerja) dosen.',
            'depends_on' => ['classes', 'reference'],
            'source' => ['endpoint' => 'classes', 'scope' => 'classes.read', 'semester' => true],
            'iterate' => ['path' => 'lecturers', 'alias' => 'dosen'],
            'local_key' => 'kelas:{code}|nidn:{dosen.nidn}',
            'act_insert' => 'InsertDosenPengajarKelasKuliah',
            'act_update' => null,
            'required' => ['id_kelas', 'id_dosen'],
            'fields' => [
                'id_kelas' => '@mapping:classes:kelas:{code}',
                'id_dosen' => '@ref:dosen:{dosen.nidn}',
                'id_prodi' => '@ref:prodi:{study_program.code}',
                'id_semester' => '@semester',
                'sks_substansi_total' => '@int:course.credits',
            ],
            'defaults' => [
                'rencana_minggu_pertemuan' => '16',
                'realisasi_minggu_pertemuan' => '16',
            ],
        ],

        'enrollments' => [
            'label' => 'KRS / Peserta Kelas',
            'group' => 'Perkuliahan',
            'description' => 'InsertPesertaKelasKuliah — mahasiswa peserta tiap kelas (hanya baris KRS berstatus enrolled).',
            'depends_on' => ['classes', 'student_registrations'],
            'source' => ['endpoint' => 'enrollments', 'scope' => 'enrollments.read', 'semester' => true],
            'iterate' => ['path' => 'items', 'alias' => 'item'],
            'only_if' => ['item.status' => 'enrolled'],
            'local_key' => 'nim:{student.nim}|kelas:{item.class_code}',
            'act_insert' => 'InsertPesertaKelasKuliah',
            'act_update' => null,
            'required' => ['id_kelas', 'id_registrasi_mahasiswa'],
            'fields' => [
                'id_kelas' => '@mapping:classes:kelas:{item.class_code}',
                'id_registrasi_mahasiswa' => '@mapping:student_registrations:nim:{student.nim}',
                'id_semester' => '@semester',
                'id_prodi' => '@ref:prodi:{student.study_program_code}',
            ],
        ],

        'akm' => [
            'label' => 'AKM (Aktivitas Kuliah Mahasiswa)',
            'group' => 'Nilai & AKM',
            'description' => 'InsertPerkuliahanMahasiswa — SKS dan IPS per mahasiswa per semester. Angka diambil dari mesin KHS SIAKAD, bukan dihitung ulang di sini.',
            'depends_on' => ['student_registrations'],
            'source' => ['endpoint' => 'akm', 'scope' => 'enrollments.read', 'semester' => true],
            'local_key' => 'nim:{student.nim}|semester:{semester.feeder_code}',
            'act_insert' => 'InsertPerkuliahanMahasiswa',
            'act_update' => 'UpdatePerkuliahanMahasiswa',
            'required' => ['id_registrasi_mahasiswa', 'id_semester', 'sks_semester'],
            'fields' => [
                'id_registrasi_mahasiswa' => '@mapping:student_registrations:nim:{student.nim}',
                'id_prodi' => '@ref:prodi:{student.study_program_code}',
                'id_semester' => '@semester',
                'sks_semester' => '@int:sks_semester',
                'ips' => '@float:ips',
                'sks_total' => '@int:sks_total',
                'ipk' => '@float:ipk',
            ],
            'defaults' => [
                'status_mahasiswa' => 'A',
            ],
        ],

        'grades' => [
            'label' => 'Nilai Perkuliahan',
            'group' => 'Nilai & AKM',
            'description' => 'UpdateNilaiPerkuliahanKelas — nilai akhir per mahasiswa per kelas. Hanya nilai yang komponennya sudah lengkap yang dikirim.',
            'depends_on' => ['classes', 'student_registrations'],
            'source' => ['endpoint' => 'grades', 'scope' => 'grades.read', 'semester' => true],
            'iterate' => ['path' => 'grades', 'alias' => 'nilai'],
            'local_key' => 'nim:{nilai.nim}|kelas:{class.code}',
            'act_insert' => 'UpdateNilaiPerkuliahanKelas',
            'act_update' => 'UpdateNilaiPerkuliahanKelas',
            'required' => ['id_kelas', 'id_registrasi_mahasiswa', 'nilai_angka'],
            'fields' => [
                'id_kelas' => '@mapping:classes:kelas:{class.code}',
                'id_registrasi_mahasiswa' => '@mapping:student_registrations:nim:{nilai.nim}',
                'id_semester' => '@semester',
                'nilai_angka' => '@float:nilai.nilai_angka',
                'nilai_huruf' => 'nilai.nilai_huruf',
                'nilai_indeks' => '@float:nilai.grade_point',
            ],
        ],

        'activities' => [
            'label' => 'Aktivitas Mahasiswa (Skripsi)',
            'group' => 'Aktivitas & Kelulusan',
            'description' => 'InsertAktivitasMahasiswa — data skripsi/tugas akhir mahasiswa.',
            'depends_on' => ['students'],
            'source' => [
                'endpoint' => 'activities',
                'scope' => 'activities.read',
                'semester' => false,
                'query' => ['type' => 'thesis'],
            ],
            'local_key' => 'skripsi:{nim}',
            'act_insert' => 'InsertAktivitasMahasiswa',
            'act_update' => null,
            'required' => ['id_mahasiswa', 'judul'],
            'fields' => [
                'id_mahasiswa' => '@mapping:students:nim:{nim}',
                'id_prodi' => '@ref:prodi:{study_program_code}',
                'judul' => 'title',
                'id_semester' => '@semester:{completion_semester_code}',
                'tanggal_mulai' => '@date:start_date',
                'tanggal_selesai' => '@date:completion_date',
            ],
            'defaults' => [
                'id_jenis_aktivitas' => '1',
            ],
        ],

        'activity_supervisors' => [
            'label' => 'Pembimbing Skripsi',
            'group' => 'Aktivitas & Kelulusan',
            'description' => 'InsertBimbingMahasiswa — dosen pembimbing pada aktivitas mahasiswa.',
            'depends_on' => ['activities', 'reference'],
            'source' => [
                'endpoint' => 'activities',
                'scope' => 'activities.read',
                'semester' => false,
                'query' => ['type' => 'thesis'],
            ],
            'iterate' => ['path' => 'supervisors', 'alias' => 'pembimbing'],
            'local_key' => 'skripsi:{nim}|nidn:{pembimbing.nidn}',
            'act_insert' => 'InsertBimbingMahasiswa',
            'act_update' => null,
            'required' => ['id_aktivitas', 'id_dosen'],
            'fields' => [
                'id_aktivitas' => '@mapping:activities:skripsi:{nim}',
                'id_dosen' => '@ref:dosen:{pembimbing.nidn}',
                'urutan_promotor' => '@int:pembimbing.order',
            ],
            'defaults' => [
                'jenis_bimbing_mahasiswa' => '1',
            ],
        ],

        'graduates' => [
            'label' => 'Lulusan / Mahasiswa Keluar',
            'group' => 'Aktivitas & Kelulusan',
            'description' => 'InsertMahasiswaLulusDO — status keluar mahasiswa (lulus, DO, mutasi). Data tidak dapat dihapus di PDDikti, jadi pastikan yudisium sudah final.',
            'depends_on' => ['student_registrations'],
            'source' => ['endpoint' => 'graduates', 'scope' => 'graduation.read', 'semester' => false],
            'local_key' => 'lulusan:{nim}',
            'act_insert' => 'InsertMahasiswaLulusDO',
            'act_update' => null,
            'required' => ['id_registrasi_mahasiswa', 'tanggal_keluar'],
            'fields' => [
                'id_registrasi_mahasiswa' => '@mapping:student_registrations:nim:{nim}',
                'tanggal_keluar' => '@date:graduation_date',
                'ipk' => '@float:ipk',
                'jumlah_sks' => '@int:sks_total',
                'nomor_sk' => 'sk_number',
            ],
            'defaults' => [
                'id_jenis_keluar' => '1',
            ],
        ],
    ],
];
