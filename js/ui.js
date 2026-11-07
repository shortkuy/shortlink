/**
 * SafeLink Static — Shared UI (header, footer, common logic)
 * Injects header & footer on every page. Handles auth state.
 */
(function () {
  "use strict";

  var SL = window.SafeLink;
  if (!SL) return;
  var cfg = SL.config;

  /* === Build header HTML === */
  function headerHTML(authed) {
    var navLinks = [
      '<a href="' + relPath() + 'index.html">Home</a>',
      '<a href="' + relPath() + 'pricing.html" class="mobile-hidden">Pricing</a>',
      '<a href="' + relPath() + 'about.html" class="mobile-hidden">About</a>',
      '<a href="' + relPath() + 'contact.html" class="mobile-hidden">Contact</a>'
    ];
    if (authed) {
      navLinks.push('<a href="' + relPath() + 'admin.html" class="admin-link" title="Admin dashboard">🔒 <span class="mobile-hidden">Admin</span></a>');
    }
    return '' +
      '<header class="site-header">' +
        '<div class="container">' +
          '<a href="' + relPath() + 'index.html" class="logo">' +
            '<span class="logo-mark">S</span>' +
            '<span>' + escapeHtml(cfg.brand || 'SafeLink') + '</span>' +
            '<span class="logo-version">v' + (cfg.version || '5.0') + '</span>' +
          '</a>' +
          '<nav class="nav-links">' + navLinks.join('') + '</nav>' +
        '</div>' +
      '</header>';
  }

  /* === Build footer HTML === */
  function footerHTML() {
    var year = new Date().getFullYear();
    return '' +
      '<footer class="site-footer">' +
        '<div class="container">' +
          '<div class="footer-grid">' +
            '<div>' +
              '<div class="footer-brand">' +
                '<span class="logo-mark">S</span>' +
                '<strong style="color:white;font-size:13px">' + escapeHtml(cfg.brand || 'SafeLink') + '</strong>' +
              '</div>' +
              '<p>Shortlink &amp; safelink generator with multi-ad-network support. Pure static site, deploy anywhere.</p>' +
            '</div>' +
            '<div>' +
              '<h3>Pages</h3>' +
              '<ul>' +
                '<li><a href="' + relPath() + 'index.html">Home</a></li>' +
                '<li><a href="' + relPath() + 'pricing.html">Pricing</a></li>' +
                '<li><a href="' + relPath() + 'about.html">About</a></li>' +
                '<li><a href="' + relPath() + 'contact.html">Contact</a></li>' +
              '</ul>' +
            '</div>' +
            '<div>' +
              '<h3>Legal</h3>' +
              '<ul>' +
                '<li><a href="' + relPath() + 'privacy.html">Privacy Policy</a></li>' +
                '<li><a href="' + relPath() + 'terms.html">Terms of Service</a></li>' +
                '<li><a href="' + relPath() + 'disclaimer.html">Disclaimer</a></li>' +
              '</ul>' +
            '</div>' +
            '<div>' +
              '<h3>Resources</h3>' +
              '<ul>' +
                '<li><a href="' + relPath() + 'sitemap.xml" target="_blank">Sitemap</a></li>' +
                '<li><a href="' + relPath() + 'robots.txt" target="_blank">robots.txt</a></li>' +
              '</ul>' +
            '</div>' +
          '</div>' +
          '<div class="footer-bottom">' +
            '© ' + year + ' ' + escapeHtml(cfg.brand || 'SafeLink') + ' · Built for monetization · Supports all ad networks' +
          '</div>' +
        '</div>' +
      '</footer>';
  }

  /* === Compute relative path from current page to root ===
     e.g. /s/index.html → '../'
          /index.html → ''
          /about.html → ''
   */
  function relPath() {
    var path = window.location.pathname;
    if (path === "/" || path === "") return "";
    var parts = path.split("/").filter(Boolean);
    // Last part is the file (e.g. "index.html")
    if (parts.length === 0) return "";
    // If we're in a subdirectory (e.g. /s/), go up
    var last = parts[parts.length - 1];
    if (last.indexOf(".") !== -1) {
      // It's a file
      parts.pop();
    }
    // Each remaining directory = one "../"
    var up = parts.length;
    var result = "";
    for (var i = 0; i < up; i++) result += "../";
    return result;
  }

  /* === Escape HTML === */
  function escapeHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  /* === Inject header & footer into page === */
  function inject() {
    var authed = SL.isLoggedIn();
    var headerSlot = document.querySelector("[data-header]");
    var footerSlot = document.querySelector("[data-footer]");

    if (headerSlot) headerSlot.innerHTML = headerHTML(authed);
    if (footerSlot) footerSlot.innerHTML = footerHTML();

    // Apply custom_head injection (meta tags, fonts, etc.)
    var settings = SL.getSettings();
    if (settings.inject && settings.inject.customHead && settings.inject.customHead.trim()) {
      var headDiv = document.createElement("div");
      headDiv.innerHTML = settings.inject.customHead;
      // Move all elements to <head>
      while (headDiv.firstChild) {
        var node = headDiv.firstChild;
        if (node.tagName === "LINK" || node.tagName === "META") {
          document.head.appendChild(node);
        } else {
          document.head.appendChild(node);
        }
      }
    }
  }

  /* === Boot on DOM ready === */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", inject);
  } else {
    inject();
  }
})();
