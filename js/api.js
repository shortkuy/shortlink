/**
 * ============================================================
 *  SafeLink Shared — PHP API Client
 * ------------------------------------------------------------
 *  Pure vanilla JS, no dependencies.
 *  Talks to api.php on your shared hosting via fetch().
 *  All operations are rate-limited server-side (safe for shared hosting).
 * ============================================================
 */
(function (global) {
  "use strict";

  var CONFIG = global.SAFELINK_CONFIG || {};
  var ENDPOINT = CONFIG.apiEndpoint || "";
  var API_KEY = CONFIG.apiKey || "";

  // Check if API is configured
  function isConfigured() {
    return ENDPOINT && ENDPOINT.indexOf("yourdomain.com") === -1;
  }

  // Make API request
  async function request(action, options) {
    options = options || {};
    var url = ENDPOINT + "?action=" + encodeURIComponent(action);
    var headers = {
      "Content-Type": "application/json",
    };
    if (API_KEY) headers["X-API-Key"] = API_KEY;

    var fetchOpts = {
      method: options.method || "GET",
      headers: headers,
    };
    if (options.body) fetchOpts.body = JSON.stringify(options.body);
    if (options.params) {
      Object.keys(options.params).forEach(function (k) {
        url += "&" + encodeURIComponent(k) + "=" + encodeURIComponent(options.params[k]);
      });
    }

    var res = await fetch(url, fetchOpts);
    var data;
    try { data = await res.json(); } catch (e) {
      throw new Error("Invalid response from server (status " + res.status + ")");
    }
    if (!res.ok) {
      throw new Error(data.error || ("Request failed with status " + res.status));
    }
    return data;
  }

  // === Insert a new link (public, rate-limited) ===
  async function insertLink(entry) {
    var data = await request("create", {
      method: "POST",
      body: {
        id: entry.id,
        dest: entry.dest,
        timer: entry.timer,
        mode: entry.mode,
        title: entry.title,
        articleTitle: entry.articleTitle,
        articleIntro: entry.articleIntro,
        idLength: entry.idLength || 6,
      },
    });
    return data;
  }

  // === Get a link by ID (public, rate-limited) ===
  async function getLink(id) {
    var data = await request("get", { params: { id: id } });
    return data.link || null;
  }

  // === List all links (admin only, requires API key) ===
  async function listLinks(limit) {
    var data = await request("list", { params: { limit: limit || 100 } });
    return data.links || [];
  }

  // === Increment click counter (public, rate-limited) ===
  async function incrementClicks(id) {
    var data = await request("increment", { params: { id: id } });
    return data.ok;
  }

  // === Delete a link (admin only, requires API key) ===
  async function deleteLink(id) {
    var data = await request("delete", { params: { id: id } });
    return data.ok;
  }

  // === Get stats (admin only) ===
  async function getStats() {
    var data = await request("stats");
    return data.stats || {};
  }

  // === Health check (public) ===
  async function health() {
    try {
      var data = await request("health");
      return data.ok === true;
    } catch (e) {
      return false;
    }
  }

  // === Expose API ===
  global.ApiClient = {
    isConfigured: isConfigured,
    insertLink: insertLink,
    getLink: getLink,
    listLinks: listLinks,
    incrementClicks: incrementClicks,
    deleteLink: deleteLink,
    getStats: getStats,
    health: health,
  };
})(window);
