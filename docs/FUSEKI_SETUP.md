# Setup Apache Jena Fuseki di Windows

Fuseki adalah triple store/API RDF yang melayani protokol SPARQL 1.1 Query, Update, dan Graph Store. Gunakan distribusi resmi Apache Jena Fuseki: https://jena.apache.org/download/ . Jena 6.x membutuhkan Java 21 atau yang lebih baru.

## 1. Download dan ekstrak

1. Pastikan Java 21+ tersedia: `java -version`.
2. Download distribusi biner Apache Jena Fuseki dari halaman resmi di atas.
3. Ekstrak ke folder terpisah, misalnya `D:\tools\apache-jena-fuseki-6.2.0`.

## 2. Jalankan di localhost

Buka PowerShell di direktori instalasi Fuseki yang berisi launcher `fuseki-server.bat` (pada beberapa distribusi launcher berada di subfolder `bat`). Jalankan perintah setara berikut dari folder instalasi:

```powershell
.\fuseki-server.bat --localhost --update --loc=databases/siakadu /siakadu
```

Jika launcher berada pada subfolder `bat`, jalankan `./bat/fuseki-server.bat --localhost --update --loc=databases/siakadu /siakadu` dari root distribusi. Folder `databases/siakadu` digunakan untuk menyimpan dataset secara persisten. Jika versi distribusi menaruh launcher di `bat`, gunakan path launcher yang benar dari folder ekstraksi. Biarkan terminal Fuseki tetap berjalan.

## 3. Endpoint yang digunakan Laravel

```text
Query:  http://127.0.0.1:3030/siakadu/query
Update: http://127.0.0.1:3030/siakadu/update
Data:   http://127.0.0.1:3030/siakadu/data
```

Ini sesuai pola endpoint Fuseki dokumentasi Apache Jena: `/{dataset}/query`, `/{dataset}/update`, dan `/{dataset}/data`.

Tambahkan konfigurasi `FUSEKI_ENABLED=true` dan tiga URL tersebut ke `.env` project Laravel, lalu `php artisan optimize:clear`.

## 4. Uji koneksi

Buka `http://127.0.0.1:8002/sparql-console`, atau panggil API health:

```powershell
$token = "TOKEN_SEMANTIC_API"
$headers = @{ Authorization = "Bearer $token" }
Invoke-RestMethod -Uri "http://127.0.0.1:8002/api/v1/fuseki/health" -Headers $headers | ConvertTo-Json -Depth 6
```

Jika Fuseki tidak dapat dijangkau, status menjadi `unavailable` tetapi evaluasi masih dapat membuat file Turtle lokal.

## 5. Keamanan

Setup ini untuk development lokal saja. `--update` mengizinkan penulisan graph melalui API. Jangan expose port 3030 ke jaringan publik tanpa autentikasi dan konfigurasi server yang benar. Jika Fuseki dikonfigurasi dengan Basic Auth, isi `FUSEKI_USERNAME` dan `FUSEKI_PASSWORD` pada `.env`.

Referensi resmi:
- https://jena.apache.org/documentation/fuseki2/
- https://jena.apache.org/documentation/fuseki2/soh.html
- https://jena.apache.org/documentation/fuseki2/fuseki-data-access-control.html
