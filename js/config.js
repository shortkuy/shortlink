/**
 * ============================================================
 *  SafeLink Shared — Configuration
 * ------------------------------------------------------------
 *  Static site (deploy to GitHub Pages / Netlify)
 *  + PHP API endpoint (deploy to your shared hosting / cPanel)
 *  + MySQL database (in your shared hosting)
 *
 *  Setup:
 *  1. Upload api.php, config.php, .htaccess to your shared hosting (cPanel)
 *  2. Edit config.php with DB credentials from cPanel → MySQL Databases
 *  3. Run schema.sql via phpMyAdmin
 *  4. Generate API_KEY in config.php (random 24-byte hex)
 *  5. Update apiEndpoint & apiKey below
 *  6. Upload static files (HTML/CSS/JS) to GitHub Pages / Netlify
 *
 *  NOTE: baseUrl is AUTO-DETECTED — no need to set it manually.
 *  Works on any domain (GitHub Pages, cPanel, Netlify, custom domain).
 * ============================================================
 */
window.SAFELINK_CONFIG = {
  // === Branding ===
  brand: "SafeLink",
  tagline: "Shortlink & Safelink Generator",
  version: "5.3-shared",

  // === PIN Protection ===
  // Hash of the owner PIN (default: 123456 — CHANGE THIS!)
  // To generate: see SHARED-SETUP.md or run in browser console:
  //   async function h(p){const e=new TextEncoder().encode(p);const d=await crypto.subtle.digest('SHA-256',e);return Array.from(new Uint8Array(d)).map(b=>b.toString(16).padStart(2,'0')).join('')}
  //   await h('YOUR_PIN')
  pinHash: "8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92", // sha256("123456")

  // === PHP API Endpoint (REQUIRED) ===
  // URL of api.php on your shared hosting.
  // Example: "https://yourdomain.com/api.php"
  apiEndpoint: "https://yourdomain.com/api.php",

  // === API Key (for admin operations: list, delete, stats) ===
  // MUST match API_KEY in config.php on your server.
  apiKey: "change-this-to-random-24-byte-hex-string",

  // === Timer / cooldown (seconds, 0-10) ===
  // Default: 1 second (auto-redirects fast for shortlink mode)
  timerMin: 0,
  timerMax: 10,
  defaultTimer: 1,

  // === Default mode ===
  // "shortlink" = auto-redirect after timer
  // "safelink" = manual click required (better for monetization)
  defaultMode: "safelink",

  // === Default ID length (5-10 chars) ===
  defaultIdLength: 6,

  // === Ad networks (paste full code from each provider) ===
  ads: {
    enabled: true,
    adsenseClient: "",
    adsenseSlot: "",
    adsterraCode: "",
    monetagCode: "",
    mgidCode: "",
    propellerAdsCode: "",
    customAdTop: "",
    customAdBottom: "",
  },

  // === Custom script injection (advanced) ===
  inject: {
    customCss: "",
    customJs: "",
    customHead: "",
  },

  // === Base URL (AUTO-DETECTED — leave empty!) ===
  // Don't set this. The domain is auto-detected from the browser.
  // Works on any domain without manual configuration.
  baseUrl: "",
};
