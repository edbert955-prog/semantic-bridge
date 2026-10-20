# Panduan Push Semantic Bridge ke GitHub (Windows / PowerShell)

## Penting sebelum mulai

- Jangan unggah `.env`, token API, password database, file dump database, `storage/logs/`, atau graph hasil evaluasi yang dibuat saat runtime.
- Jangan gunakan `php artisan migrate:fresh` untuk menyiapkan GitHub. Itu bukan langkah Git dan dapat menghapus tabel.
- Simpan file ontologi sumber di `storage/app/ontologies/` dan source Java di `semantic-reasoner/src/`, serta `semantic-reasoner/pom.xml`.
- File `semantic-reasoner/target/*.jar` adalah hasil build; bangun ulang dari Maven setelah clone.
- Sebelum commit, lihat daftar file yang akan diunggah. Jangan jalankan `git push` jika `.env` atau rahasia terlihat di daftar.

## 1. Siapkan `.gitignore`

Di root project `D:\System_Semantic\semantic-bridge`, buka `.gitignore` yang sudah ada. Gabungkan aturan dari `gitignore_recommended.txt`; jangan mengganti aturan khusus project yang sudah dibutuhkan. Pastikan file `.env.example` tetap dapat dilacak Git, tetapi `.env` tidak.

Di PowerShell:

```powershell
cd "D:\System_Semantic\semantic-bridge"
git status --short
git check-ignore -v .env
```

Jika `git check-ignore` tidak menampilkan aturan untuk `.env`, perbaiki `.gitignore` sebelum lanjut.

## 2. Periksa apakah Git sudah diaktifkan

```powershell
git status
 git remote -v
```

Jika `git status` mengatakan folder belum menjadi repository, jalankan:

```powershell
git init
git branch -M main
```

Jika sudah repository, jangan jalankan `git init` lagi. Periksa remote yang ada dengan `git remote -v`.

## 3. Pastikan `.env` atau log tidak pernah ikut dilacak

```powershell
git ls-files .env
```

Jika perintah tersebut menampilkan `.env`, keluarkan dari index Git tanpa menghapus file lokal:

```powershell
git rm --cached .env
```

Jika `storage/logs` atau graph RDF hasil evaluasi sudah pernah dilacak, keluarkan file runtime dari index (file lokal tetap ada):

```powershell
git rm -r --cached storage/logs
git rm -r --cached storage/app/semantic-graphs
```

Perintah `git rm --cached` mungkin mengatakan path belum dilacak; itu aman, berarti path tersebut tidak perlu dikeluarkan. Jika `vendor` atau `node_modules` pernah terlanjur dilacak, keluarkan juga dengan `git rm -r --cached vendor` atau `git rm -r --cached node_modules` sesuai kebutuhan.

**Jika `.env` atau token pernah ter-push ke repo publik**, anggap rahasia itu sudah bocor: ubah password/token terlebih dahulu dan bersihkan riwayat Git bila perlu. Menghapus file pada commit baru saja tidak menghapusnya dari riwayat lama.

## 4. Stage dan inspeksi semua file

```powershell
git add .
git status --short
git diff --cached --stat
git diff --cached --name-only
```

Periksa hasilnya. Daftar sumber yang biasanya perlu masuk:
- `app/`, `bootstrap/`, `config/`, `database/migrations/`, `database/seeders/`, `routes/`, `resources/`, `tests/`
- `composer.json`, `composer.lock`, `artisan`, `phpunit.xml`, `vite.config.*`
- `semantic-reasoner/pom.xml` dan `semantic-reasoner/src/`
- ontologi sumber di `storage/app/ontologies/`
- README dan `.env.example` yang tidak berisi rahasia

Jangan lanjut jika `.env`, `vendor/`, `node_modules/`, `storage/logs/`, dump database, token, atau `storage/app/semantic-graphs/*.ttl` muncul dalam file staged.

## 5. Buat commit

```powershell
git commit -m "Initial commit: semantic bridge prototype"
```

Jika Git meminta identitas, atur nama dan email untuk Git di komputer ini:

```powershell
git config --global user.name "Nama Kamu"
git config --global user.email "email-yang-dipakai-di-github@example.com"
```

Lalu ulangi `git commit`.

## 6. Buat repository kosong di GitHub dan push

1. Masuk ke GitHub, buat repository baru, misalnya `semantic-bridge`.
2. Untuk cara paling sederhana, buat repository kosong tanpa menambahkan README, `.gitignore`, atau license dari situs GitHub (file-file itu bisa ditambahkan dari project lokal).
3. Salin HTTPS URL repository yang diberikan GitHub.
4. Di PowerShell, ganti URL contoh di bawah dengan URL repository milikmu:

```powershell
git remote add origin https://github.com/USERNAME/semantic-bridge.git
git push -u origin main
```

Jika `origin` sudah ada, jangan menambahkannya lagi. Periksa `git remote -v`, kemudian gunakan URL repo milikmu dengan:

```powershell
git remote set-url origin https://github.com/USERNAME/semantic-bridge.git
git push -u origin main
```

Jangan menaruh access token GitHub di URL atau menyalinnya ke chat. Saat diminta autentikasi, gunakan browser/Git Credential Manager.

## 7. Setelah push

Buka repository GitHub dan periksa file yang terunggah. Jangan mempublikasikan database asli, data mahasiswa, file `.env`, atau log yang bisa memuat path, token, dan data pribadi.

Untuk membagikan masalah reasoner, kirim URL repository bila repository memang aman untuk dibagikan. Jika repository berisi kode kampus/private, jadikan private dan undang kolaborator secara langsung.
