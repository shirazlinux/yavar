/**
 * Yavar embed loader — builds an iframe widget from a placeholder div.
 *
 * Usage:
 * <div class="yavar-support" data-slug="my-slug" data-theme="dark" data-lang="fa"></div>
 * <script src="https://donate.sudoshz.ir/embed/loader.js" async></script>
 *
 * Optional data-*:
 *   data-base="https://donate.sudoshz.ir"
 *   data-theme="dark|light|brand"
 *   data-lang="fa|en"
 *   data-compact="1"
 *   data-height="auto" | number (px) — default auto via postMessage
 *   data-width="420"
 */
(function () {
  "use strict";

  var DEFAULT_BASE = "https://donate.sudoshz.ir";
  var iframes = [];

  var SCRIPT_BASE = (function () {
    try {
      var s = document.currentScript;
      if (s && s.src) {
        return new URL(s.src).origin;
      }
    } catch (e) {}
    try {
      var list = document.getElementsByTagName("script");
      for (var i = list.length - 1; i >= 0; i--) {
        var src = list[i].src || "";
        if (/\/embed\/loader\.js(\?|$)/i.test(src)) {
          return new URL(src).origin;
        }
      }
    } catch (e2) {}
    return DEFAULT_BASE;
  })();

  function attr(el, name, fallback) {
    var v = el.getAttribute(name);
    return v == null || v === "" ? fallback : v;
  }

  function boot(el) {
    if (el.getAttribute("data-yavar-booted") === "1") {
      return;
    }
    var slug =
      el.getAttribute("data-slug") ||
      el.getAttribute("data-yavar-slug") ||
      "";
    slug = String(slug).trim();
    if (!slug) {
      return;
    }

    var theme = attr(el, "data-theme", "dark");
    var lang = attr(el, "data-lang", "fa");
    var compact = el.getAttribute("data-compact") === "1";
    var base = (el.getAttribute("data-base") || SCRIPT_BASE || DEFAULT_BASE).replace(
      /\/$/,
      ""
    );
    var maxWidth = attr(el, "data-width", "420");
    var heightAttr = attr(el, "data-height", "auto");
    var fixedHeight = heightAttr !== "auto" && /^\d+$/.test(String(heightAttr));
    var startHeight = fixedHeight
      ? String(heightAttr)
      : compact
        ? "200"
        : "300";

    var src =
      base +
      "/embed/widget.php?slug=" +
      encodeURIComponent(slug) +
      "&theme=" +
      encodeURIComponent(theme) +
      "&lang=" +
      encodeURIComponent(lang) +
      (compact ? "&compact=1" : "") +
      "&_=" +
      Date.now().toString(36);

    el.style.display = "flex";
    el.style.justifyContent = "center";
    el.style.alignItems = "flex-start";
    el.style.width = "100%";
    el.style.maxWidth = "100%";
    el.style.margin = "0 auto";
    el.style.textAlign = "center";
    el.style.overflow = "visible";

    var iframe = document.createElement("iframe");
    iframe.src = src;
    iframe.title = "Yavar support widget";
    iframe.loading = "lazy";
    iframe.referrerPolicy = "strict-origin-when-cross-origin";
    iframe.scrolling = "no";
    iframe.setAttribute(
      "sandbox",
      "allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
    );
    iframe.style.cssText =
      "width:100%;max-width:" +
      maxWidth +
      "px;height:" +
      startHeight +
      "px;min-height:" +
      startHeight +
      "px;border:0;border-radius:16px;overflow:hidden;display:block;margin:0 auto;background:transparent;";

    el.innerHTML = "";
    el.appendChild(iframe);
    el.setAttribute("data-yavar-booted", "1");

    iframes.push({ iframe: iframe, fixed: fixedHeight });
  }

  function collect() {
    return document.querySelectorAll(
      "[data-yavar-slug], [data-slug].yavar-support, #yavar-support, .yavar-support"
    );
  }

  function run() {
    var nodes = collect();
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (
        el.getAttribute("data-slug") ||
        el.getAttribute("data-yavar-slug")
      ) {
        if (!el.getAttribute("data-slug") && el.getAttribute("data-yavar-slug")) {
          el.setAttribute("data-slug", el.getAttribute("data-yavar-slug"));
        }
        boot(el);
      }
    }
  }

  // Auto-height from widget postMessage
  window.addEventListener("message", function (ev) {
    var data = ev.data;
    if (!data || data.type !== "yavar-embed-resize" || !data.height) {
      return;
    }
    var h = parseInt(data.height, 10);
    if (!h || h < 80 || h > 1200) {
      return;
    }
    for (var i = 0; i < iframes.length; i++) {
      var item = iframes[i];
      if (item.fixed) continue;
      try {
        if (item.iframe.contentWindow === ev.source) {
          item.iframe.style.height = h + "px";
          item.iframe.style.minHeight = h + "px";
        }
      } catch (e) {
        // cross-origin comparison via source is fine for contentWindow
      }
    }
    // fallback: if only one auto iframe, resize it
    if (iframes.length === 1 && !iframes[0].fixed) {
      iframes[0].iframe.style.height = h + "px";
      iframes[0].iframe.style.minHeight = h + "px";
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }

  if (typeof MutationObserver !== "undefined") {
    var mo = new MutationObserver(function () {
      run();
    });
    if (document.documentElement) {
      mo.observe(document.documentElement, { childList: true, subtree: true });
    }
  }

  window.YavarEmbed = { refresh: run, base: SCRIPT_BASE };
})();
