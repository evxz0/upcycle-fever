# Product Requirements Document (PRD)
## Upcycle Fever: Eco-Crafting Studio

| | |
|---|---|
| Versi | 1.0 (Draft) |
| Tanggal | 12 November 2026 |
| Tim | Afif Fawwaz Firmansyah, Raffi Faza Fadhlan Prasetya, Farhan Maulana Yusuf, Alfian Hafidz Fikri Alfarisi, M. Zacky Fadilla Andriansyah |
| Sumber | Proposal Pengembangan Produk (13 Oktober 2026) + keputusan tech stack terbaru |

---

## 1. Ringkasan Produk

Upcycle Fever adalah game edukasi berbasis web bergenre time-management (terinspirasi Cooking Fever/Overcooked). Pemain mengelola studio kerajinan: menerima pesanan pelanggan, memilah bahan bekas, melakukan crafting sesuai resep, dan menyelesaikan pesanan sebelum patience bar pelanggan habis. Game mendukung **SDG 12 (Target 12.5)** tentang pengurangan limbah melalui reuse dan upcycling.

### 1.1 Masalah
- Edukasi pengelolaan sampah konvensional (poster, seminar) bersifat satu arah, minim tantangan, dan retensinya rendah.
- Generasi muda kurang memahami konsep ekonomi sirkular dan upcycling secara praktis.

### 1.2 Tujuan Produk
1. Menyediakan game edukasi berbasis browser tanpa instalasi.
2. Memakai Gemini API untuk pesanan dinamis, kuis SDG 12, tren lingkungan, dan narasi dampak.
3. Menyediakan akses bertingkat: Guest (trial), Pemain (penuh), Admin (monitoring dan pengelolaan).

### 1.3 Di Luar Cakupan (Non-Goals)
- Pengelolaan sampah fisik atau verifikasi fisik bahan.
- Pengukuran emisi nyata. Nilai Eco-Impact adalah estimasi simulasi dari rumus tertanam, bukan hasil AI.
- Gemini tidak menentukan keberhasilan crafting dan skor utama.

---

## 2. Tech Stack

| Lapisan | Teknologi | Peran |
|---|---|---|
| Frontend | Tailwind CSS + Alpine.js | Render antarmuka 2D, timer, patience bar, animasi interaktif |
| Backend | Laravel + Service Pattern | Logika crafting, validasi resep, manajemen state pemain |
| Real-time | Laravel Reverb (WebSocket) | Sinkronisasi leaderboard dan monitoring admin secara langsung |
| AI | Gemini API via Backend Wrapper | Pesanan dinamis (JSON), kuis edukasi SDG |
| Database | MySQL + Redis | Akun, progres, inventaris; caching, session, queue, leaderboard |

**Perubahan dari proposal:** database tetap MySQL; ditambahkan Redis (cache, session, queue, leaderboard) dan Laravel Reverb (real-time), serta Tailwind CSS pada frontend. Dokumen proposal (3.3 dan 4.2) perlu disesuaikan dengan tambahan tersebut.

### 2.1 Struktur Service Pattern (Backend)

```
app/
├── Http/Controllers/     # tipis: validasi request, panggil service, return JSON
├── Services/
│   ├── Auth/AuthService.php
│   ├── Game/GuestTrialService.php
│   ├── Game/StageService.php
│   ├── Game/CraftingService.php        # validasi resep
│   ├── Game/OrderService.php
│   ├── Game/InventoryService.php
│   ├── Game/ScoreService.php
│   ├── Ai/GeminiService.php            # wrapper + fallback
│   ├── Leaderboard/LeaderboardService.php  # Redis sorted set
│   └── Admin/PlayerMonitoringService.php
├── Models/
├── Events/               # broadcast Reverb
└── Policies / Middleware (role, guest.trial)
```

---

## 3. Pengguna dan Hak Akses

| Peran | Deskripsi | Akses |
|---|---|---|
| **Guest** | Belum login. Bermain tanpa registrasi. | Beranda, panduan, leaderboard publik (read-only), **trial 1 stage**. Tidak ada data tersimpan permanen. |
| **Player** | Sudah registrasi dan login. | Semua stage, simpan progres, inventaris, Tool Shop, kuis, Eco-Impact, achievement, leaderboard. |
| **Admin** | Pengelola sistem. | Dashboard monitoring player, kelola data master dan akun, pantau penggunaan AI. |

---

## 4. Lingkup Fase Ini: Autentikasi dan Manajemen Peran

> Isi fitur gameplay lainnya menunggu instruksi berikutnya. Fase ini hanya mencakup fondasi: konfigurasi base stack, sistem login/registrasi, tiga peran, trial guest, dan dashboard monitoring admin.

### 4.1 Aturan Akses (inti)

**Batasan akses cukup diterapkan pada login.** Artinya:
- Guest dapat langsung menekan "Main sekarang" tanpa login.
- Guest hanya bisa memainkan **1 stage trial**.
- Setelah stage trial selesai (atau guest mencoba stage ke-2), sistem menampilkan layar ajakan **"Login/Daftar untuk melanjutkan"**.
- Tidak ada pembatasan fitur lain bagi guest selain jumlah stage dan penyimpanan data.

### 4.2 Functional Requirements

| ID | Kebutuhan | Prioritas |
|---|---|---|
| AUTH-01 | Registrasi pemain (nama, email, password) dengan validasi dan hashing bawaan Laravel | Must |
| AUTH-02 | Login/logout; redirect berdasarkan role (player ke game, admin ke dashboard) | Must |
| AUTH-03 | Kolom `role` pada user: `player` atau `admin`. Guest tidak punya record user | Must |
| AUTH-04 | Middleware role untuk melindungi rute admin dan rute pemain | Must |
| AUTH-05 | Rate limiting login (anti brute force) | Should |
| AUTH-06 | Status akun pemain (aktif/nonaktif), akun nonaktif tidak bisa login | Should |
| GUEST-01 | Guest dapat masuk ke game tanpa login | Must |
| GUEST-02 | Guest diberi `guest_token` (UUID) di session/Redis dengan TTL, untuk melacak jumlah stage trial | Must |
| GUEST-03 | Guest hanya dapat menyelesaikan **1 stage trial**; permintaan stage berikutnya ditolak di backend (HTTP 403 + kode `LOGIN_REQUIRED`) | Must |
| GUEST-04 | Layar ajakan login/daftar muncul setelah stage trial selesai | Must |
| GUEST-05 | Skor/koin/progres guest tidak disimpan ke MySQL dan tidak masuk leaderboard | Must |
| GUEST-06 | Setelah login, guest diarahkan ke stage 1 penuh (progres trial tidak dimigrasi) | Could |
| ADM-01 | Dashboard admin: total pemain terdaftar, pemain online saat ini, jumlah sesi guest aktif | Must |
| ADM-02 | Monitoring "berapa banyak yang main": pemain aktif hari ini, 7 hari, 30 hari | Must |
| ADM-03 | Presence real-time pemain online via Reverb presence channel | Must |
| ADM-04 | Daftar pemain (cari, filter, status) dengan aksi aktif/nonaktifkan akun | Should |
| ADM-05 | Statistik trial guest: jumlah trial dimulai, diselesaikan, dan yang lanjut registrasi (konversi) | Should |
| ADM-06 | Admin hanya dibuat lewat seeder atau perintah artisan, tidak dari registrasi publik | Must |

### 4.3 Alur Pengguna

**Guest**
1. Buka website, tekan **Main sebagai Tamu**.
2. Backend membuat `guest_token`, menandai trial belum dipakai.
3. Guest bermain Stage 1 (trial).
4. Stage selesai, muncul layar **Login/Daftar untuk lanjut**.
5. Guest mencoba stage 2, backend menolak dengan `LOGIN_REQUIRED`.

**Player**
1. Registrasi atau login, masuk lobi game dan dapat memilih stage (konten gameplay menyusul).

**Admin**
1. Login, masuk dashboard, melihat statistik dan daftar pemain online real-time.

---

## 5. Kebutuhan Data (Fase Ini)

**users**: id, name, email (unique), password, role (`player`|`admin`), is_active, last_login_at, last_seen_at, timestamps.

**guest_sessions** (opsional, untuk statistik admin): id, guest_token (unique), trial_started_at, trial_completed_at, converted_user_id (nullable), ip_hash, timestamps.

**Redis**
- `guest:{token}`: status trial, TTL (mis. 24 jam)
- `presence:online_players`: set pemain online
- Session, cache, dan queue memakai Redis

Catatan: tabel gameplay (orders, recipes, materials, inventory, dll.) dari ERD proposal ditambahkan pada fase berikutnya dan disesuaikan ke MySQL.

---

## 6. Kebutuhan Real-time (Laravel Reverb)

| Channel | Tipe | Kegunaan |
|---|---|---|
| `presence-players` | Presence | Admin melihat pemain online secara live |
| `private-admin.monitoring` | Private | Update angka dashboard (online, trial baru, registrasi baru) |
| `leaderboard` | Public | Sinkronisasi papan peringkat (diimplementasi di fase gameplay) |

---

## 7. Kebutuhan Non-Fungsional

| ID | Kebutuhan |
|---|---|
| NFR-01 | Loading awal maksimal 5 detik; respons endpoint Gemini maksimal 5 detik |
| NFR-02 | Password di-hash (Laravel/bcrypt atau argon2); API key Gemini hanya di `.env` server; CSRF dan validasi input aktif; pembatasan trial divalidasi di **server**, bukan hanya frontend |
| NFR-03 | Gemini gagal atau kuota habis: fallback ke konten statis/model AI lain agar game tetap berjalan |
| NFR-04 | Mampu melayani 50-100 pemain bersamaan (Redis cache, queue untuk panggilan AI, koneksi Reverb) |
| NFR-05 | Konten edukasi mengacu pada literatur resmi PBB tentang SDG 12 |
| NFR-06 | Responsif di Chrome, Firefox, Edge, Safari (desktop dan mobile) |
| NFR-07 | Pengujian: Cypress (E2E: login, trial guest, pembatasan stage, dashboard admin) dan BlazeMeter (beban) |

---

## 8. Roadmap Fitur Gameplay (Menunggu Instruksi)

Dari proposal, akan dirinci pada fase berikutnya:
Dynamic Order & Customer Generator, Crafting Workbench & Recipe Engine, Trash Sorting & Inventory, Time & Patience System, Special Customer Quiz, AI Eco-Trend, Upcycling Blueprint & Fun-Fact, Eco-Impact Report, Studio Upgrade & Tool Shop, Leaderboard & Achievement.

---

## 9. Kriteria Penerimaan (Fase Ini)

- [ ] Guest dapat bermain tanpa login dan hanya dapat menyelesaikan 1 stage.
- [ ] Upaya guest memulai stage kedua ditolak backend dan menampilkan ajakan login.
- [ ] Data guest tidak tersimpan di MySQL permanen dan tidak muncul di leaderboard.
- [ ] Player dapat registrasi, login, logout; akun nonaktif ditolak.
- [ ] Rute admin tidak dapat diakses player atau guest (403).
- [ ] Dashboard admin menampilkan jumlah pemain terdaftar, online, aktif harian/mingguan, dan sesi guest.
- [ ] Jumlah pemain online berubah secara live via Reverb tanpa refresh.
- [ ] Base project berjalan: Laravel + MySQL + Redis + Reverb + Tailwind + Alpine.js, dengan `GeminiService` wrapper (stub + fallback) sudah ada.

---

## 10. Risiko

| Risiko | Mitigasi |
|---|---|
| Guest mengakali trial dengan hapus cookie | Token plus pembatasan ringan berbasis IP-hash di Redis; trial tidak bernilai ekonomi sehingga risiko rendah |
| Kuota/latensi Gemini | Queue, cache hasil, fallback statis, model cadangan |
| Beban WebSocket | Skala Reverb dan Redis; batasi event dashboard (throttle) |
| Ketidaksesuaian dokumen dengan stack baru | Perbarui proposal (tambah Redis, Reverb, dan Tailwind pada bagian teknologi) |
