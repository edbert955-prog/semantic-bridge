# SIAKADU Semantic Bridge — Laravel Patch

Paket ini ditujukan untuk project Laravel BARU yang dibuat di:

`D:\System_Semantic\semantic-bridge`

Paket ini **bukan project Laravel lengkap dengan `vendor/`**. Ekstrak isinya ke root project Laravel yang sudah dibuat. Tidak ada `.env` pribadi dalam ZIP ini sehingga kredensial lokal Anda tidak tertimpa.

## Isi paket

- Koneksi PostgreSQL ke `semantic_hub_db`, `pengajuan_akademik`, dan `siakadu_db`.
- API dengan token Bearer untuk health check, pemetaan, file Turtle, dan evaluasi kelayakan.
- Dashboard web lokal dengan ringkasan koneksi, daftar mapping, log inferensi, dan penampil ontologi.
- Dua tabel metadata di database semantik.
- Seeder sembilan mapping awal.
- Ontologi Turtle: workflow, SIAKADU, dan alignment.
- Evaluator aturan SKL dengan evidence/reasoning trace dan kebijakan `needs_manual_review` untuk bukti yang tidak tersedia.

## Perhatian penting

1. Sistem A backup yang diperiksa berisi tabel `users` dengan peran `mahasiswa`, `dosen_pa`, `kaprodi`, dan `admin`. Pada bagian dump yang diberikan, tabel katalog layanan (`jenis_layanan`), status pengajuan, dan transaksi pengajuan kosong. Karena itu API tidak menganggap layanan/pengajuan sudah tersedia jika record tidak ada.
2. Kode ini adalah fondasi Laravel, RDF/OWL vocabulary/alignment, dan rule evaluator deterministik. Apache Jena/Fuseki, SPARQL endpoint, dan reasoner OWL 2 RL **belum termasuk** dalam paket ini. Jangan menyebutnya sebagai reasoner OWL 2 RL penuh sebelum integrasi tersebut dikerjakan.
3. `semantic_hub_db` harus database terpisah. Jangan menjalankan migration dari folder Sistem A atau Sistem B dan jangan menjalankan `migrate:fresh` pada database mana pun.
4. Untuk penggunaan selain localhost, pasang autentikasi dashboard dan buat role database read-only untuk Sistem A/B. Dashboard web dalam paket ini dimaksudkan untuk pengembangan lokal.
5. Nama kolom koneksi Sistem B diselaraskan dengan file `siakadu_db.sql` yang tersedia: `mahasiswa.npm`, `mahasiswa.ipk`, `mahasiswa.total_sks_lulus`, `krs`, `krs_detail`, `kelas_kuliah`, `mata_kuliah`, dan `pembayaran_ukt`.

## Instalasi

### 1. Ekstrak

Salin semua isi ZIP ke root project Laravel `D:\System_Semantic\semantic-bridge`, pertahankan struktur foldernya. File `routes/web.php`, `routes/api.php`, dan `config/database.php` adalah konfigurasi inti untuk project baru ini.

### 2. Siapkan database

Di pgAdmin buat database `semantic_hub_db`. Jangan menghapus atau mengganti database Sistem A/B.

### 3. Edit `.env`

Salin parameter dari `.env.semantic.example` ke file `.env` milik project. Ganti tiga konfigurasi password/database dengan nilai milik lokal. Pertahankan `APP_KEY` yang sudah dibuat Laravel. Jangan mengganti `.env` menggunakan file contoh.

Buat token acak panjang (misalnya lewat PowerShell: `[guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')`) lalu isi `SEMANTIC_API_TOKEN` dengan hasilnya.

### 4. Routing API

Paket menyertakan `bootstrap/app.php` yang sudah mendaftarkan `routes/api.php`. Dengan demikian, untuk project Laravel baru ini Anda tidak perlu menjalankan `php artisan install:api` hanya demi mendaftarkan route API. Paket ini memakai token integrasi sederhana pada middleware sendiri; Sanctum tidak dibutuhkan untuk endpoint yang disediakan.

### 5. Migration dan seeder

Dari folder project semantik:

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan migrate
php artisan db:seed --class=SemanticMappingSeeder
```

Verifikasi bahwa `.env` menunjuk ke `semantic_hub_db` sebelum `php artisan migrate`.

### 6. Jalankan

```powershell
php artisan serve --host=127.0.0.1 --port=8002
```

Dashboard: `http://127.0.0.1:8002/`
API health: `http://127.0.0.1:8002/api/v1/health` (memerlukan token Bearer).

## Endpoint API

Semua endpoint `/api/v1/*` memerlukan header:

`Authorization: Bearer <SEMANTIC_API_TOKEN>`

- `GET /api/v1/health`
- `GET /api/v1/mappings`
- `GET /api/v1/ontology/workflow`
- `GET /api/v1/ontology/siakadu`
- `GET /api/v1/ontology/alignment`
- `POST /api/v1/eligibility/check`

Contoh body evaluasi:

```json
{
  "npm": "2415061079",
  "service_code": "SKL"
}
```

Atau gunakan pengajuan yang sudah tersimpan di Sistem A:

```json
{
  "pengajuan_id": 1
}
```

Panggilan dengan `pengajuan_id` akan gagal secara wajar jika record pengajuan atau jenis layanannya belum ada.

## Troubleshooting

- Jika `system_a` atau `system_b` tidak tersambung, periksa nama database, host, port, user, password di `.env`.
- Setelah mengubah `.env`, jalankan `php artisan optimize:clear`.
- Jika health check melaporkan tabel Sistem B hilang, periksa apakah dump SIAKADU sudah direstore ke `siakadu_db` yang dikonfigurasi.
- Bila evaluator SKL menghasilkan `needs_manual_review`, periksa `reasons`. Sistem tidak mengarang bukti plagiarisme atau nilai skripsi.
- File `.ttl` adalah artefak ontology/alignment; menyajikan file Turtle belum sama dengan menjalankan reasoner OWL 2 RL.
