/**
 * ============================================================
 *  SafeLink Shared — Core Library
 * ------------------------------------------------------------
 *  Uses PHP API endpoint on shared hosting as backend.
 *  All DB operations go through api.php (rate-limited, safe).
 *  Falls back to localStorage if API not configured.
 * ============================================================
 */
(function (global) {
  "use strict";

  var CONFIG = global.SAFELINK_CONFIG || {};
  var STORAGE_KEY = "safelink_links_v5";
  var SESSION_KEY = "safelink_session";
  var SETTINGS_KEY = "safelink_settings";
  var CHARSET = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

  // Check if PHP API is available
  function useApi() {
    return global.ApiClient && global.ApiClient.isConfigured();
  }

  /* === Generate random short ID === */
  function generateShortId(length) {
    length = length || 6;
    var arr = new Uint32Array(length);
    if (global.crypto && global.crypto.getRandomValues) {
      global.crypto.getRandomValues(arr);
    } else {
      for (var i = 0; i < length; i++) arr[i] = Math.floor(Math.random() * 1e9);
    }
    var out = "";
    for (var i = 0; i < length; i++) out += CHARSET[arr[i] % CHARSET.length];
    return out;
  }

  /* === Validate URL === */
  function isValidUrl(url) {
    try {
      var u = new URL(url);
      return u.protocol === "http:" || u.protocol === "https:";
    } catch (e) {
      return false;
    }
  }

  /* === LOCAL STORAGE FALLBACK === */
  function lsGetAllLinks() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }
  function lsSaveLink(entry) {
    var all = lsGetAllLinks();
    all[entry.id] = entry;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    return entry;
  }
  function lsGetLink(id) { return lsGetAllLinks()[id] || null; }
  function lsDeleteLink(id) {
    var all = lsGetAllLinks();
    delete all[id];
    localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
  }

  /* === Get all links === */
  async function getAllLinks() {
    if (useApi()) {
      try {
        return await global.ApiClient.listLinks(100);
      } catch (e) {
        console.warn("SafeLink: API error, falling back to localStorage", e);
        return Object.values(lsGetAllLinks());
      }
    }
    return Object.values(lsGetAllLinks());
  }

  /* === Get a single link === */
  async function getLink(id) {
    if (useApi()) {
      try {
        return await global.ApiClient.getLink(id);
      } catch (e) {
        console.warn("SafeLink: API getLink error", e);
        return lsGetLink(id);
      }
    }
    return lsGetLink(id);
  }

  /* === Save a link (create via API) === */
  async function saveLink(entry) {
    if (useApi()) {
      try {
        return await global.ApiClient.insertLink(entry);
      } catch (e) {
        console.warn("SafeLink: API insert error, falling back to localStorage", e);
        return lsSaveLink(entry);
      }
    }
    return lsSaveLink(entry);
  }

  /* === Delete a link === */
  async function deleteLink(id) {
    if (useApi()) {
      try {
        return await global.ApiClient.deleteLink(id);
      } catch (e) {
        console.warn("SafeLink: API delete error", e);
        lsDeleteLink(id);
        return false;
      }
    }
    lsDeleteLink(id);
    return true;
  }

  /* === Increment clicks === */
  async function incrementClicks(id) {
    if (useApi()) {
      try {
        return await global.ApiClient.incrementClicks(id);
      } catch (e) {
        console.warn("SafeLink: incrementClicks error", e);
        return false;
      }
    }
    var link = lsGetLink(id);
    if (!link) return false;
    link.clicks = (link.clicks || 0) + 1;
    link.lastClickAt = Date.now();
    lsSaveLink(link);
    return true;
  }

  /* === Create a new shortlink === */
  async function createShortLink(dest, timer, mode, title, idLength) {
    timer = Math.max(0, Math.min(10, timer !== undefined ? timer : (CONFIG.defaultTimer || 1)));
    mode = mode === "shortlink" ? "shortlink" : (CONFIG.defaultMode || "safelink");
    idLength = Math.max(5, Math.min(10, idLength || CONFIG.defaultIdLength || 6));
    title = title && title.trim() ? title.trim() : null;

    // Generate ID client-side (server will also check uniqueness)
    var id = generateShortId(idLength);
    if (!useApi()) {
      var all = lsGetAllLinks();
      var attempts = 0;
      while (all[id] && attempts < 5) {
        id = generateShortId(idLength);
        attempts++;
      }
    }

    // Compute article title & intro for link previews (stored in DB)
    var article = getSeededArticle(id);
    var articleTitle = article ? article.title : null;
    var articleIntro = article ? article.intro : null;

    var entry = {
      id: id,
      dest: dest,
      timer: timer,
      mode: mode,
      title: title,
      articleTitle: articleTitle,
      articleIntro: articleIntro,
      idLength: idLength,
      createdAt: Date.now(),
      clicks: 0,
      lastClickAt: null
    };

    if (useApi()) {
      // Send to PHP API — it will store articleTitle & articleIntro in DB
      // and return the proper shortUrl pointing to the `go` endpoint
      var result = await saveLink(entry);
      if (result && result.shortUrl) {
        entry.shortUrl = result.shortUrl;
      }
      return entry;
    }

    // localStorage fallback
    await saveLink(entry);
    return entry;
  }

  /* === Build short URL — auto-detects domain, NO manual setup needed === */
  function buildShortUrl(entry) {
    // If API returned a shortUrl (points to PHP go endpoint for proper previews), use it
    if (entry && entry.shortUrl) return entry.shortUrl;

    // Fallback: auto-detect from current page URL
    // Works automatically for any domain (GitHub Pages, cPanel, Netlify, custom domain)
    var id = typeof entry === 'string' ? entry : (entry && entry.id ? entry.id : '');
    var base = CONFIG.baseUrl;
    if (!base) {
      // Auto-detect: get origin + base path (minus the current filename)
      var path = window.location.pathname;
      var basepath = path.substring(0, path.lastIndexOf("/")) || "";
      base = window.location.origin + basepath;
    }
    return base + "/s/?id=" + id;
  }

  /* === Pick article by hash === */
  function getSeededArticle(id) {
    var articles = global.ARTICLES || [];
    if (!articles.length) return null;
    var hash = 0;
    for (var i = 0; i < id.length; i++) {
      hash = ((hash << 5) - hash + id.charCodeAt(i)) | 0;
    }
    var idx = Math.abs(hash) % articles.length;
    return articles[idx];
  }

  /* === Settings === */
  function getSettings() {
    var stored = {};
    try {
      var raw = localStorage.getItem(SETTINGS_KEY);
      if (raw) stored = JSON.parse(raw);
    } catch (e) {}
    var ads = Object.assign({}, CONFIG.ads || {}, stored.ads || {});
    var inject = Object.assign({}, CONFIG.inject || {}, stored.inject || {});
    return {
      brand: stored.brand || CONFIG.brand || "SafeLink",
      tagline: stored.tagline || CONFIG.tagline || "",
      version: CONFIG.version || "5.2",
      ads: ads,
      inject: inject
    };
  }

  function saveSettings(settings) {
    try {
      localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
      return true;
    } catch (e) { return false; }
  }

  function resetSettings() { localStorage.removeItem(SETTINGS_KEY); }

  /* === SHA-256 === */
  async function sha256(text) {
    var enc = new TextEncoder().encode(text);
    var buf = await crypto.subtle.digest("SHA-256", enc);
    var arr = Array.from(new Uint8Array(buf));
    return arr.map(function (b) { return b.toString(16).padStart(2, "0"); }).join("");
  }

  /* === PIN verification === */
  async function verifyPin(inputPin) {
    var inputHash = await sha256(inputPin);
    return inputHash === CONFIG.pinHash;
  }

  /* === Session === */
  function isLoggedIn() {
    var s = sessionStorage.getItem(SESSION_KEY);
    if (!s) return false;
    try {
      var data = JSON.parse(s);
      if (Date.now() - data.ts > 7 * 24 * 60 * 60 * 1000) {
        sessionStorage.removeItem(SESSION_KEY);
        return false;
      }
      return true;
    } catch (e) { return false; }
  }

  async function login(pin) {
    var ok = await verifyPin(pin);
    if (ok) {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify({ ts: Date.now() }));
    }
    return ok;
  }

  function logout() { sessionStorage.removeItem(SESSION_KEY); }

  /* === Toast === */
  function toast(message, type) {
    type = type || "";
    var existing = document.querySelector(".toast");
    if (existing) existing.remove();
    var el = document.createElement("div");
    el.className = "toast " + type;
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add("show"); });
    setTimeout(function () {
      el.classList.remove("show");
      setTimeout(function () { el.remove(); }, 400);
    }, 2500);
  }

  /* === Copy to clipboard === */
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
        resolve();
      } catch (e) { reject(e); }
    });
  }

  /* === Render ad === */
  function renderAd(code) {
    if (!CONFIG.ads || !CONFIG.ads.enabled) return "";
    if (!code || !code.trim()) return "";
    return code;
  }

  /* === Check backend status === */
  function isCloudEnabled() { return useApi(); }

  /* === Expose API === */
  global.SafeLink = {
    config: CONFIG,
    generateShortId: generateShortId,
    isValidUrl: isValidUrl,
    getAllLinks: getAllLinks,
    saveLink: saveLink,
    getLink: getLink,
    deleteLink: deleteLink,
    incrementClicks: incrementClicks,
    createShortLink: createShortLink,
    buildShortUrl: buildShortUrl,
    getSeededArticle: getSeededArticle,
    getSettings: getSettings,
    saveSettings: saveSettings,
    resetSettings: resetSettings,
    sha256: sha256,
    verifyPin: verifyPin,
    isLoggedIn: isLoggedIn,
    login: login,
    logout: logout,
    toast: toast,
    copyText: copyText,
    renderAd: renderAd,
    isCloudEnabled: isCloudEnabled,
  };
})(window);
