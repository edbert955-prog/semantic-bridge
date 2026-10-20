# SIAKADU Semantic Bridge 2.0 — Upgrade Pack

Paket ini adalah **patch/overlay** untuk project Laravel Semantic Bridge yang sudah berjalan, bukan project Laravel baru. Paket dibuat agar dapat diekstrak ke root `semantic-bridge` tanpa menimpa `.env`, `config/database.php`, atau database sumber.

## Yang ditambahkan

- Evaluasi SKL dengan status kriteria `pass`, `fail`, dan `unknown` pada `reasoning_trace`.
- Gate validasi: jika akun, katalog layanan, atau data sumber belum tervalidasi, hasil ditahan sebagai `needs_manual_review`.
- Pembuatan file Turtle per evaluasi di `storage/app/semantic-graphs/`.
- Penyimpanan metadata graph dan status reasoner pada `semantic_inference_logs` melalui **satu migration tambahan**.
- SPARQL Console pada `/sparql-console`, API query read-only, endpoint graph, dan integrasi opsional Apache Jena Fuseki.
- Helper Java Apache Jena untuk reasoning profile OWL 2 RL. Helper ini baru aktif setelah build Maven dan pengaktifan `.env`.

## 1. Cadangkan project

Sebelum ekstrak, salin folder `D:\System_Semantic\semantic-bridge` sebagai cadangan. Jangan hapus database atau migration yang sudah berhasil. ZIP ini sengaja **tidak** berisi migration create-table awal untuk `semantic_mappings` atau `semantic_inference_logs`, supaya tidak mengulangi error tabel duplikat yang pernah terjadi.

## 2. Ekstrak ZIP

Ekstrak seluruh isi `siakadu_semantic_bridge_v2_upgrade.zip` ke:

```text
D:\System_Semantic\semantic-bridge
```

Izinkan file dengan nama yang sama ditimpa. Struktur folder harus dipertahankan. File penting yang diperbarui antara lain:

- `app/Services/SemanticEligibilityService.php`
- `app/Services/SemanticRdfService.php`
- `app/Services/FusekiClient.php`
- `app/Services/SemanticReasonerClient.php`
- `app/Http/Controllers/SemanticDashboardController.php`
- `routes/api.php` dan `routes/web.php`
- `config/semantic.php`
- `resources/views/...`
- `storage/app/ontologies/alignment.ttl`

`config/database.php` dan `.env` milikmu tidak disertakan serta tidak akan ditimpa.

## 3. Tambahkan variabel `.env`

Buka `.env.semantic-addon.example`. Salin baris konfigurasi dari file itu ke `.env` project, lalu sesuaikan bila perlu. **Jangan menimpa `APP_KEY`, DB_DATABASE, atau password yang sudah benar.**

Pastikan koneksi utama tetap mengarah ke `semantic_hub_db`, sedangkan `system_a` dan `system_b` mengarah ke `pengajuan_akademik` dan `siakadu_db`.

Untuk tahap awal, gunakan:

```dotenv
FUSEKI_ENABLED=false
SEMANTIC_REASONER_ENABLED=false
```

Dengan nilai tersebut, evaluasi bisa membuat file Turtle lokal tanpa memerlukan Fuseki atau Maven terlebih dahulu.

Token API yang sebelumnya `12345678` terlalu sederhana. Ganti `SEMANTIC_API_TOKEN` dengan string acak panjang pada `.env`; token yang sama harus dikirim pada header `Authorization: Bearer ...` saat pengujian API. Jangan kirim token sebenarnya ke publik.

## 4. Jalankan migration tambahan

Buka terminal PowerShell pada `D:\System_Semantic\semantic-bridge`:

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan migrate
```

Migration baru bernama `2026_10_20_130001_add_rdf_metadata_to_semantic_inference_logs.php`. Migration ini hanya menambah kolom RDF/reasoner ke `semantic_inference_logs`; tidak menjalankan `migrate:fresh`, tidak menghapus tabel, dan tidak mengubah database Sistem A/B.

Pastikan migration tersebut berstatus `Ran`. Jika status database utama bukan `semantic_hub_db`, **berhenti dulu** dan perbaiki `.env`; jangan menjalankan migration ke database sumber.

Seeder mapping yang sudah berhasil dijalankan tidak perlu diulang. Jika mapping ternyata kosong, barulah jalankan `php artisan db:seed --class=SemanticMappingSeeder`.

## 5. Uji Laravel dan API tanpa Fuseki

Pastikan Laravel berjalan pada port 8002:

```powershell
php artisan serve --host=127.0.0.1 --port=8002
```

Di terminal PowerShell baru:

```powershell
$token = "GANTI_DENGAN_TOKEN_ENV"
$headers = @{ Authorization = "Bearer $token" }

Invoke-RestMethod -Uri "http://127.0.0.1:8002/api/v1/health" -Headers $headers | ConvertTo-Json -Depth 8
```

Uji evaluasi menggunakan ID pengajuan yang benar-benar ada di Sistem A:

```powershell
$body = @{ pengajuan_id = 1 } | ConvertTo-Json
Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8002/api/v1/eligibility/check" `
  -Headers $headers -ContentType "application/json" -Body $body |
  ConvertTo-Json -Depth 12
```

Ganti `1` jika ID pengajuanmu berbeda. Hasil respons mempunyai properti `rdf` dengan `graph_uri`, `download_url`, `triple_count`, `reasoner_status`, dan `status`. Pada mode awal, `rdf.status` biasanya `local_only`, sementara `reasoner_status` `not_configured`.

File graph lokal disimpan di:

```text
storage/app/semantic-graphs/{request_uuid}.ttl
storage/app/semantic-graphs/{request_uuid}.source.ttl
```

Halaman UI:

- `/` — Pipeline Overview
- `/mappings` — Ontology Mappings
- `/ontologies` — RDF / OWL Files
- `/inference-logs` — Audit, reasoning trace, dan tautan unduh Turtle
- `/sparql-console` — SPARQL Console (memerlukan Fuseki aktif untuk menjalankan query)

## 6. Aktifkan Apache Jena Fuseki untuk SPARQL yang nyata

Ikuti `docs/FUSEKI_SETUP.md`. Setelah Fuseki jalan di localhost pada port 3030, tambahkan ke `.env`:

```dotenv
FUSEKI_ENABLED=true
FUSEKI_QUERY_URL=http://127.0.0.1:3030/siakadu/query
FUSEKI_UPDATE_URL=http://127.0.0.1:3030/siakadu/update
FUSEKI_DATA_URL=http://127.0.0.1:3030/siakadu/data
FUSEKI_USERNAME=
FUSEKI_PASSWORD=
```

Lalu jalankan `php artisan optimize:clear`. Endpoint graph menggunakan SPARQL Update dan Graph Store Protocol, jadi dataset Fuseki harus mengizinkan update di lingkungan development. Jangan expose Fuseki tanpa autentikasi ke jaringan publik.

Uji:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8002/api/v1/fuseki/health" -Headers $headers | ConvertTo-Json -Depth 6
```

Sesudah evaluasi baru dipicu, graph akan dipublikasikan ke named graph dan diuji dengan query SPARQL. Evaluasi lama yang graph-nya belum diterbitkan dapat dikirim ulang melalui API `POST /api/v1/rdf/graphs/{request_uuid}/publish` dengan token API.

## 7. Aktifkan reasoner OWL 2 RL (opsional tetapi disarankan untuk demonstrasi Semantic Web)

Di dalam ZIP sudah ada project Maven di folder `semantic-reasoner`. Project tersebut memakai Apache Jena 6.2.0 dan OWL 2 RL rule-based reasoning. Apache Jena 6.x membutuhkan Java 21 atau lebih baru.

Pastikan `java -version` menunjukkan Java 21+, dan Maven tersedia pada PATH. Lalu jalankan:

```powershell
cd D:\System_Semantic\semantic-bridge\semantic-reasoner
mvn clean package
```

Build harus menghasilkan:

```text
D:\System_Semantic\semantic-bridge\semantic-reasoner\target\semantic-owl2rl-reasoner.jar
```

Tambahkan/ubah `.env`:

```dotenv
SEMANTIC_REASONER_ENABLED=true
SEMANTIC_REASONER_JAR="D:/System_Semantic/semantic-bridge/semantic-reasoner/target/semantic-owl2rl-reasoner.jar"
SEMANTIC_JAVA_BINARY=java
SEMANTIC_REASONER_TIMEOUT=90
```

Jalankan `php artisan optimize:clear`, kemudian buat evaluasi baru. Saat berhasil, log akan menampilkan `reasoner_status=owl2rl_reasoned`. Jika Java/JAR gagal, graph RDF dasar tetap disimpan dan status berubah menjadi `reasoner_failed`; evaluator tidak mengubah keputusan bisnis hanya karena reasoner gagal.

Catatan: reasoner memproses ontologi lokal dan graph evaluasi lalu menulis model hasil inferensi ke Turtle. Periksa file `.ttl` dan trace hasil evaluasi sebelum menggunakannya dalam laporan.

## 8. Periksa audit di PostgreSQL

Pilih `semantic_hub_db` pada pgAdmin:

```sql
SELECT
    request_uuid,
    service_code,
    applicant_npm,
    result_status,
    rdf_graph_uri,
    rdf_status,
    rdf_triple_count,
    reasoner_status,
    reasoner_triple_count,
    sparql_verified
FROM public.semantic_inference_logs
ORDER BY created_at DESC
LIMIT 20;
```

## PENTING

- Tidak perlu `php artisan migrate:fresh`.
- Jangan jalankan migration ini dari folder Sistem A atau Sistem B.
- `eligible` berarti hanya aturan prototipe yang dikonfigurasi terpenuhi; aturan SKL harus divalidasi terhadap kebijakan akademik resmi sebelum dipakai di luar demo.
- Bukti plagiarisme belum memiliki sumber verifikasi resmi dalam skema yang dianalisis; maka hasil yang belum memiliki bukti harus tetap `needs_manual_review`.
- Dashboard Laravel saat ini untuk development lokal. Tambahkan autentikasi administrator dan otorisasi sebelum dipasang di server bersama.

## 9. Kriteria uji penerimaan (selesaikan secara berurutan)

### A. PHP, route, dan database metadata

```powershell
php artisan optimize:clear
php artisan route:list --path=api
php artisan route:list --path=ontologies
php artisan migrate:status
```

Periksa bahwa migration `2026_10_20_130001_add_rdf_metadata_to_semantic_inference_logs` berstatus `Ran` dan semua route baru tampil.

### B. Satu evaluasi baru menghasilkan RDF

Gunakan `pengajuan_id` yang benar-benar ada di Sistem A:

```powershell
$token = "TOKEN_SEMANTIC_API_DARI_ENV"
$headers = @{ Authorization = "Bearer $token" }
$body = @{ pengajuan_id = 1 } | ConvertTo-Json
Invoke-RestMethod -Method Post `
  -Uri "http://127.0.0.1:8002/api/v1/eligibility/check" `
  -Headers $headers -ContentType "application/json" -Body $body |
  ConvertTo-Json -Depth 12
```

Respons harus memiliki `data.rdf.graph_uri`, `data.rdf.download_url`, `data.rdf.status`, dan `data.reasoning_trace`. `success: true` berarti evaluasi berjalan, bukan berarti pemohon layak. Tanpa Fuseki, `data.rdf.status` yang normal adalah `local_only`; tanpa JAR Jena, `reasoner_status` adalah `not_configured`.

Pastikan file berikut terbentuk:

```text
storage/app/semantic-graphs/<request_uuid>.source.ttl
storage/app/semantic-graphs/<request_uuid>.ttl
```

Buka `/inference-logs`; satu baris baru harus menunjukkan status RDF dan langkah trace. Buka tautan Turtle untuk memeriksa fakta yang benar-benar dipakai.

### C. Pemeriksaan log di pgAdmin

Jalankan terhadap database `semantic_hub_db`:

```sql
SELECT request_uuid, result_status, rdf_graph_uri, rdf_status,
       rdf_triple_count, reasoner_status, reasoner_triple_count,
       sparql_verified, reasoning_trace
FROM public.semantic_inference_logs
ORDER BY created_at DESC
LIMIT 10;
```

### D. Uji SPARQL dengan Fuseki

Setelah Fuseki aktif dan `FUSEKI_ENABLED=true`, buka `/sparql-console`, jalankan query bawaan, lalu lakukan evaluasi baru. Pastikan `rdf_status=published_verified` dan `sparql_verified=true`. File graf lokal tetap dipertahankan meskipun Fuseki sedang tidak tersedia.

### E. Uji OWL 2 RL dengan Apache Jena

Pastikan Java 21+ dan Maven, lalu di folder `semantic-reasoner` jalankan `mvn clean package`. Aktifkan `SEMANTIC_REASONER_ENABLED=true`, jalankan `php artisan optimize:clear`, dan kirim evaluasi baru. Hasil berhasil harus mencatat `reasoner_status=owl2rl_reasoned` dan nilai `reasoner_triple_count`.

**Jangan menyatakan integrasi reasoner selesai hanya karena `SEMANTIC_REASONER_ENABLED=true`.** Pastikan JAR benar-benar ada dan status log menunjukkan `owl2rl_reasoned`. Jika komputer belum punya Maven/Fuseki, fungsi tersebut belum aktif; mode RDF lokal tetap dapat diuji.

## 10. Batas cakupan versi ini

Versi ini menyelesaikan penyimpanan audit evaluasi, pembentukan instance RDF Turtle, API graph, console/query SPARQL yang read-only, integrasi Fuseki opsional, dan helper reasoner OWL 2 RL berbasis Apache Jena. Pengiriman hasil dari Semantic Bridge ke status workflow Sistem A belum dilakukan secara otomatis; endpoint evaluasi hanya mengembalikan hasil kepada pemanggil. Untuk mengubah status pengajuan, perlu perubahan terpisah pada source code Sistem A dan aturan persetujuan yang disepakati.
