# Status diagnostik reasoner (berdasarkan log yang dibagikan)

## Yang sudah terbukti

1. Laravel membaca `semantic.reasoner.jar_path`, dan `file_exists(...)` mengembalikan `true`.
2. Java + JAR + ontologi + file Turtle input berhasil dijalankan secara langsung dari PowerShell:
   `OWL2_RL_REASONING_OK triples=965`, exit code `0`.
3. Skrip `run_reasoner_20261020_152315_21918ad7.cmd` ketika dijalankan manual juga berakhir dengan exit code `0` dan menghasilkan 965 triple.
4. Request API yang dijalankan melalui Laravel masih mencatat exit code `-1073740791`, tanpa stdout/stderr, pada log terbaru yang diberikan.

## Kesimpulan sementara

Reasoner Java dan input yang diuji bisa bekerja di sesi terminal. Kegagalan terjadi pada pemanggilan proses saat request HTTP Laravel berjalan di lingkungan Windows, atau pada konteks proses yang berbeda. Log yang tersedia belum menunjukkan penyebab native crash secara pasti karena belum ada laporan JVM `hs_err_pid_*.log` yang relevan.

Jangan menandai reasoner berhasil hanya karena graph RDF dasar berhasil dipublikasikan ke Fuseki. Verifikasi keberhasilan reasoner pada respons API harus menunjukkan `reasoner_status = owl2rl_reasoned`.

## Untuk issue GitHub

Sertakan:
- versi Windows, PHP (`php -v`), Java (`java -version`), Maven (`mvn -version`)
- `SemanticReasonerClient.php`, `SemanticRdfService.php`, `pom.xml`, dan source Java reasoner
- satu log error API terbaru yang menyertakan `exit_code`, Java path, JAR path, input path, serta stdout/stderr
- hasil tes langsung yang berhasil (`OWL2_RL_REASONING_OK triples=965`, exit `0`)

Jangan menyertakan `.env`, API token, password, dump database, data mahasiswa, atau graph evaluasi yang berisi data pribadi.
