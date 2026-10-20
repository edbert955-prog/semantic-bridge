# Semantic Bridge 2.0 — Alur Data

1. Sistem A mengirim `pengajuan_id` ke API Laravel.
2. Laravel membaca pengajuan, pemilik pengajuan, dan jenis layanan dari koneksi read-only Sistem A.
3. Laravel mencari mahasiswa Sistem B dengan NPM yang sama. Primary key dari database berbeda tidak pernah dianggap sama hanya karena nilainya kebetulan cocok.
4. Rule evaluator memberi status setiap kriteria: `pass`, `fail`, atau `unknown`. Data sumber/katalog yang belum tervalidasi menahan keputusan sebagai `needs_manual_review`.
5. Laravel menyimpan `reasons`, `evidence`, dan `reasoning_trace` ke `semantic_inference_logs`.
6. Laravel membentuk instance RDF Turtle berdasarkan fakta yang benar-benar dibaca dan menamai graph berdasarkan request UUID.
7. Jika helper Java dikonfigurasi, Apache Jena menjalankan reasoning profile OWL 2 RL atas ontologi lokal dan graph evaluasi. Hasilnya disimpan sebagai Turtle terinferen.
8. Jika Fuseki diaktifkan, graph dipublikasikan ke named graph dan diverifikasi lewat SPARQL. Jika Fuseki mati, file Turtle lokal tetap tersedia.
9. Sistem A kelak bisa memanggil endpoint eligibility dan menggunakan hasil serta alasan; langkah ini belum mengubah workflow atau status pengajuan secara otomatis.

## Arti status

- `eligible`: prerequisite identitas, layanan, dan sumber tersedia; tidak ada persyaratan yang gagal dan tidak ada bukti wajib yang hilang.
- `ineligible`: prerequisite terverifikasi dan setidaknya satu aturan wajib terbukti gagal.
- `needs_manual_review`: identity/service/source belum terverifikasi atau bukti wajib belum tersedia.

`success: true` pada respons API berarti request dievaluasi, bukan bahwa pemohon layak.

## Batas penting

Aturan SKL pada `config/semantic.php` adalah aturan prototipe untuk proyek kuliah. Bukti bebas plagiarisme belum punya sumber status resmi yang dapat dibuktikan pada database yang dianalisis, sehingga evaluator tidak menandainya otomatis sebagai terpenuhi.
