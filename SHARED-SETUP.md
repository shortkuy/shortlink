# SafeLink Shared — Setup Shared Hosting (AMAN & Tidak Kena Suspend)

> **PHP API proxy** — JavaScript tetap di static site (GitHub Pages/Netlify),
> semua database operation lewat PHP endpoint di hosting Anda.
> DB credentials tidak bocor, ada rate limiting, pakai prepared statements.

## ⚠️ Kenapa Setup Ini Aman untuk Shared Hosting?

| Risiko | Solusi di SafeLink Shared |
|--------|---------------------------|
| Kena suspend karena CPU tinggi | Rate limiting (max 20 create/jam, 120 get/jam per IP) |
| Kena suspend karena query lambat | Indexes di semua kolom yang sering di-query + LIMIT |
| SQL injection | Prepared statements (mysqli prepared) |
| DB credentials bocor | config.php protected via .htaccess (direct access → 403) |
| Abuse (bot spam create) | Rate limit per IP + API key untuk admin operations |
| Bandwidth berlebihan | JSON response minimal, tidak return data berlebihan |
| Brute force API key | API key 48 char hex (random_int) |

## 🚀 Setup (5 Langkah)

### Langkah 1: Setup Database MySQL di cPanel

1. Login ke cPanel (biasanya `https://yourdomain.com/cpanel`)
2. Cari section **Databases** → klik **MySQL Database Wizard**
3. **New Database**: ketik `safelink` → Next
4. **Create User**:
   - Username: `safelink_user`
   - Password: generate password kuat (16+ karakter, kombinasi huruf+angka)
   - **Catat password!**
5. **Privileges**: centang **ALL PRIVILEGES** → Next
6. Catat nama lengkap database & user (biasanya `username_safelink` & `username_safelink_user`)

### Langkah 2: Import SQL Schema

1. Di cPanel, buka **phpMyAdmin**
2. Pilih database `username_safelink` di sidebar kiri
3. Klik tab **Import**
4. Pilih file `schema.sql` dari package ini
5. Klik **Go** → tunggu sampai sukses
6. Verifikasi: klik tabel `links`, harus kosong (belum ada link)
   Klik tabel `settings`, harus ada 12 rows default settings

### Langkah 3: Upload PHP Files ke Hosting

Upload 3 file ini ke folder `public_html` (atau subfolder):

1. **api.php** — PHP API endpoint
2. **config.php** — DB credentials (PROTECTED)
3. **.htaccess** — Security rules

Cara upload:
- cPanel → **File Manager** → `public_html` → **Upload** → pilih 3 file
- Atau via FTP (FileZilla)

### Langkah 4: Edit config.php

Edit file `config.php` yang sudah di-upload, ganti:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_username_safelink');      // ← Ganti
define('DB_USER', 'your_username_safelink_user');  // ← Ganti
define('DB_PASS', 'YourStrongPassword123!');        // ← Ganti
define('API_KEY', 'change-this-to-random-24-byte-hex-string');  // ← Ganti
define('ALLOWED_ORIGINS', 'https://yourdomain.com');  // ← Ganti dengan domain Anda
```

**Generate API_KEY** (random 48 char hex):
- Via SSH/terminal: `php -r "echo bin2hex(random_bytes(24));"`
- Atau online: https://www.random.org/strings/?num=24&len=2&digits=on&format=html
- Atau via PHP: buat file `genkey.php` dengan `<?php echo bin2hex(random_bytes(24));` → akses di browser → hapus file setelah dapat key

### Langkah 5: Test API Endpoint

Buka di browser: `https://yourdomain.com/api.php?action=health`

Harus return JSON:
```json
{"ok": true, "time": "2026-11-07T12:34:56"}
```

Jika error 500 → cek `config.php` (DB credentials salah)
Jika 403 → `.htaccess` mungkin terlalu ketat, cek error log cPanel
Jika 404 → file `api.php` tidak di-upload atau di folder salah

**Test config.php protected**: buka `https://yourdomain.com/config.php` → harus **403 Forbidden**

### Langkah 6: Update Static Site Config

Di file `js/config.js` (untuk GitHub Pages/Netlify), update:

```javascript
apiEndpoint: "https://yourdomain.com/api.php",
apiKey: "CHANGE-TO-SAME-API-KEY-IN-CONFIG-PHP",
```

**PENTING**: `apiKey` harus sama persis dengan `API_KEY` di `config.php`.

### Langkah 7: Deploy Static Site

Upload semua file HTML/CSS/JS ke:
- **GitHub Pages** (public repo, gratis) — lihat README.md
- **Netlify** (drag & drop ke https://app.netlify.com/drop)
- **Vercel** (import dari Git repo)
- Atau hosting yang sama (tapi pisahkan folder agar api.php & config.php tetap aman)

### Langkah 8: Test Cross-Device

1. Buka website Anda → login dengan PIN `123456`
2. Buat shortlink → dapat URL seperti `https://yourdomain.github.io/s/?id=abc123`
3. Buka URL itu di browser/device/IP berbeda → **link tetap berfungsi!**

---

## 📊 Rate Limiting (Anti-Suspend)

Default rate limits (bisa diubah di table `settings`):

| Endpoint | Public/Admin | Max per IP per jam |
|----------|--------------|-------------------|
| create | Public | 20 link |
| get | Public | 120 requests |
| increment | Public | 120 requests |
| list | Admin (API key) | 30 requests |
| delete | Admin (API key) | No limit |
| stats | Admin (API key) | No limit |
| health | Public | No limit |

Untuk ubah rate limits, edit via phpMyAdmin:
```sql
UPDATE settings SET value = '30' WHERE `key` = 'rate_limit_max_create';
UPDATE settings SET value = '30' WHERE `key` = 'rate_limit_window_minutes';
```

## 🔒 Security Best Practices

### 1. JANGAN commit config.php ke GitHub
File `config.php` berisi DB password & API key. **JANGAN** masukkan ke repo public GitHub.
Hanya upload langsung ke hosting via cPanel File Manager / FTP.

### 2. Gunakan HTTPS
Pastikan hosting Anda pakai SSL (gratis via Let's Encrypt di cPanel → SSL/TLS).
HTTP request dari HTTPS page akan di-block browser (mixed content).

### 3. Update API_KEY secara berkala
Setiap 3-6 bulan, generate API_KEY baru:
1. Generate: `php -r "echo bin2hex(random_bytes(24));"`
2. Update di `config.php` di hosting
3. Update di `js/config.js` di static site
4. Re-deploy static site

### 4. Monitor database size
Shared hosting biasanya batasi database size (100MB-1GB).
Cek via phpMyAdmin → database `username_safelink` → lihat size.

Kalau mendekati limit, hapus link lama yang tidak dipakai via admin dashboard.

### 5. Backup database
Setup cron job di cPanel → Cron Jobs:
```bash
0 3 * * * mysqldump -u username_safelink_user -p'PASSWORD' username_safelink > /home/username/backups/safelink_$(date +\%Y\%m\%d).sql
```
Atau backup manual via phpMyAdmin → Export → SQL.

---

## ❓ FAQ

### Q: Apakah ini benar-benar aman dari suspend?

**A:** Iya, selama Anda:
- Pakai rate limiting (sudah default di api.php)
- Tidak ada query yang lambat (sudah di-index)
- Tidak ada abuse (rate limit akan block spammer)
- Monitor database size (jangan biarkan grow tak terbatas)

Shared hosting provider biasanya suspend kalau:
- CPU usage tinggi berlebihan → rate limiting mencegah ini
- Banyak concurrent connections → mysqli auto-close setiap request
- Query lambat > 30 detik → query di SafeLink < 10ms (indexed)
- Storage berlebihan → hapus link lama secara berkala

### Q: Bagaimana kalau kena suspend?

**A:** Hubungi support hosting Anda, jelaskan:
- "Saya pakai PHP API dengan prepared statements"
- "Ada rate limiting per IP"
- "Query sudah indexed"
- "Database size masih < 100MB"

Biasanya mereka akan unsuspend setelah Anda jelaskan.

### Q: Bisa pakai hosting gratis (000webhost, InfinityFree)?

**A:** Bisa, TAPI:
- Rate limit hosting gratis lebih ketat (InfinityFree: 50 requests/day untuk beberapa endpoint)
- Mungkin tidak support `random_int()` (PHP 7+)
- Performance lebih lambat
- Tidak ada SSH access (sulit debugging)

**Rekomendasi**: Pakai hosting berbayar murah (Niagahoster/Hostinger ~Rp 15K/bln) untuk stabilitas.

### Q: Apakah API_KEY di client code aman?

**A:** API_KEY di `js/config.js` akan terlihat di browser devtools. TAPI:
- API_KEY hanya dipakai untuk admin operations (list, delete, stats)
- Tidak bisa akses DB langsung (DB credentials ada di config.php yang TIDAK public)
- Untuk keamanan lebih tinggi, batasi origin di `.htaccess` (sudah default)

Untuk keamanan maksimum, pertimbangkan versi Next.js + database (PIN auth server-side).

---

## 🆘 Troubleshooting

### Error: "Failed to load resource" / 404 di safelink page
1. Cek `apiEndpoint` di `js/config.js` — pastikan URL benar
2. Test `https://yourdomain.com/api.php?action=health` di browser — harus return JSON
3. Cek CORS: pastikan origin static site Anda di-allow di `config.php` `ALLOWED_ORIGINS`

### Error: "Unauthorized" saat list/delete/stats
`apiKey` di `js/config.js` tidak match dengan `API_KEY` di `config.php`.
Pastikan sama persis (case-sensitive).

### Error: "Rate limit exceeded"
IP Anda terlalu banyak request dalam 1 jam. Tunggu 1 jam atau:
- Hapus record di table `rate_limits` via phpMyAdmin
- Atau ubah rate limit di table `settings`

### Error: "Database connection failed"
DB credentials di `config.php` salah. Cek:
- DB_HOST (biasanya `localhost`)
- DB_NAME (format: `username_dbname`)
- DB_USER (format: `username_dbuser`)
- DB_PASS

### Error: 403 Forbidden saat akses config.php
Itu **benar** — `config.php` harus 403 (protected by .htaccess).

### Database penuh
Hapus link lama:
```sql
DELETE FROM links WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```
Atau via admin dashboard → delete individual links.

---

## 📞 Support

Untuk bantuan: `support@safelink.example`
