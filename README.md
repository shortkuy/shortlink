# SafeLink Cloud — Static Site + Supabase Backend

**Static site** (deploy ke GitHub Pages / cPanel / Netlify) + **Supabase** sebagai database cloud.

Link disimpan di cloud PostgreSQL → **bisa diakses dari browser/device/IP manapun**.

## 🚀 Quick Start (5 Menit)

### Step 1: Setup Supabase (Backend)

1. Daftar di https://supabase.com (gratis)
2. Buat project baru
3. Buka SQL Editor → paste isi `supabase-schema.sql` → Run
4. Catat **Project URL** & **anon public key** dari Settings → API

Lihat `SUPABASE-SETUP.md` untuk panduan detail.

### Step 2: Update config.js

Edit `js/config.js`:

```javascript
supabaseUrl: "https://YOUR-PROJECT-ID.supabase.co",
supabaseAnonKey: "YOUR-ANON-KEY-HERE",
```

### Step 3: Deploy ke Hosting

Pilih salah satu:

#### A. GitHub Pages (Gratis)
1. Buat repo public di GitHub
2. Upload semua file ke repo (drag & drop via web atau git push)
3. Settings → Pages → Branch: main → / (root) → Save
4. Tunggu 1-2 menit → live di `https://USERNAME.github.io/REPO/`

#### B. cPanel / Shared Hosting
1. Login ke cPanel → File Manager → `public_html`
2. Upload ZIP → Extract
3. Done — `https://yourdomain.com/`

#### C. Netlify (Drag & Drop)
1. Buka https://app.netlify.com/drop
2. Drag folder `safelink-cloud` ke browser
3. Done — dapat URL langsung

#### D. Vercel / Cloudflare Pages
- Import dari Git repo, deploy otomatis

### Step 4: Login & Test

1. Buka website Anda → klik "Sign In with PIN" (atau akses `/login.html` langsung)
2. Default PIN: `123456` (**WAJIB ganti** — lihat di bawah)
3. Buat shortlink → dapat URL seperti `https://yourdomain.com/s/?id=abc123`
4. Buka URL itu di browser/device/IP berbeda → **link tetap berfungsi!**

## 🔑 Default Credentials

- **PIN**: `123456` (CHANGE THIS — see below)
- **Admin URLs** (hidden, no nav link):
  - `admin.html` (dashboard)
  - `settings.html` (developer settings)
  - `login.html` (PIN entry)

## ⚙️ Cara Ganti PIN

1. Buka browser console (F12 → Console)
2. Jalankan:
   ```js
   async function h(p){const e=new TextEncoder().encode(p);const d=await crypto.subtle.digest('SHA-256',e);return Array.from(new Uint8Array(d)).map(b=>b.toString(16).padStart(2,'0')).join('')}
   await h('YOUR_NEW_PIN')
   ```
3. Copy hash output
4. Paste ke `pinHash` di `js/config.js`
5. Upload ulang config.js ke hosting

## 📦 What's Included

```
safelink-cloud/
├── index.html              # Landing + generator (PIN protected)
├── s/index.html            # Safelink page (?id=xxx, fetch from Supabase)
├── admin.html              # Admin dashboard (stats + link list)
├── settings.html           # Settings editor (PIN protected)
├── login.html              # PIN entry
├── pricing.html            # Pricing (Rp 150K, 250K, 500K, Bahasa Indonesia)
├── about.html, contact.html, privacy.html, terms.html, disclaimer.html
├── sitemap.xml, robots.txt, .nojekyll
├── supabase-schema.sql     # ⚙️ Run di Supabase SQL Editor
├── SUPABASE-SETUP.md       # Panduan setup Supabase lengkap
├── README.md
├── css/style.css           # Pure CSS modern dark theme
└── js/
    ├── config.js           # ⚙️ EDIT: Supabase URL + key, branding, ads
    ├── articles.js          # 32 articles (tech, cloud, video, movies, security)
    ├── supabase.js         # Supabase REST API client
    ├── safelink.js         # Core logic (async, Supabase-backed)
    └── ui.js                # Shared header & footer
```

## ✨ Fitur

- ⚡ Short URLs: `domain.com/s/?id=abc123` (5-10 chars)
- 🌐 **Cross-device**: Link bisa diakses dari browser/device/IP manapun
- 🛡️ Two modes: Shortlink (auto-redirect) atau Safelink (manual click)
- 📦 Static site (no Node.js runtime) + cloud database (Supabase)
- 💰 Multi-ad-network: AdSense, Adsterra, Monetag, MGid, PropellerAds, custom slots
- 🔒 PIN-protected admin & settings
- 📝 32 articles included (technology, cloud, video, movies, security)
- 🎨 Modern dark theme with gradient (responsive mobile-friendly)
- 🇮🇩 Pricing dalam Rupiah + Bahasa Indonesia
- 🚀 Deploy to GitHub Pages, cPanel, Netlify, Vercel, Cloudflare Pages

## 🆚 Bedanya dengan Versi Lain

| Fitur | Static (v5.0) | **Cloud (v5.1)** | Next.js + DB |
|-------|---------------|------------------|--------------|
| Database | localStorage | **Supabase** | MySQL/PostgreSQL |
| Cross-device | ❌ | **✅** | ✅ |
| Server runtime | ❌ | ❌ | ✅ (Next.js) |
| Deploy ke GitHub Pages | ✅ | **✅** | ❌ |
| Setup difficulty | Easy | **Easy** | Hard |
| Free tier | Unlimited | **500MB Supabase** | Vercel + DB |

## ⚠️ Limitations

- PIN auth client-side (bisa di-bypass dengan inspect JS — untuk keamanan tinggi gunakan versi Next.js + database)
- Anon key Supabase visible di client code (OK karena RLS policy membatasi operasi)
- Supabase free tier: 500MB database, cukup untuk ~100 ribu link

## 📚 Tutorial

- `SUPABASE-SETUP.md` — Panduan setup Supabase (5 menit)
- `../TUTORIAL-STATIC.html` — Tutorial lengkap deploy (Bahasa Indonesia)

## 📄 License

Commercial — see `pricing.html` for license tiers.
