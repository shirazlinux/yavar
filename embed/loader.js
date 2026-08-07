/**
 * Yavar embed loader — optional script tag without iframe.
 * Usage:
 * <div id="yavar-support" data-slug="my-slug" data-theme="dark" data-lang="fa"></div>
 * <script src="https://yavar.sudoshz.ir/embed/loader.js" async></script>
 */
(function () {
  function boot(el) {
    var slug = el.getAttribute("data-slug") || "";
    if (!slug) return;
    var theme = el.getAttribute("data-theme") || "dark";
    var lang = el.getAttribute("data-lang") || "fa";
    var compact = el.getAttribute("data-compact") === "1" ? "&compact=1" : "";
    var base = el.getAttribute("data-base") || "https://yavar.sudoshz.ir";
    var h = el.getAttribute("data-height") || (compact ? "160" : "220");
    var src =
      base.replace(/\/$/, "") +
      "/embed/widget.php?slug=" +
      encodeURIComponent(slug) +
      "&theme=" +
      encodeURIComponent(theme) +
      "&lang=" +
      encodeURIComponent(lang) +
      compact;
    var iframe = document.createElement("iframe");
    iframe.src = src;
    iframe.title = "Yavar support";
    iframe.loading = "lazy";
    iframe.referrerPolicy = "strict-origin-when-cross-origin";
    iframe.style.cssText =
      "width:100%;max-width:420px;height:" +
      h +
      "px;border:0;border-radius:16px;overflow:hidden;display:block;background:transparent;";
    iframe.setAttribute("sandbox", "allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox");
    el.innerHTML = "";
    el.appendChild(iframe);
  }

  function run() {
    var nodes = document.querySelectorAll("[data-yavar-slug], #yavar-support, .yavar-support");
    nodes.forEach(function (el) {
      if (el.getAttribute("data-slug") || el.getAttribute("data-yavar-slug")) {
        if (!el.getAttribute("data-slug") && el.getAttribute("data-yavar-slug")) {
          el.setAttribute("data-slug", el.getAttribute("data-yavar-slug"));
        }
        boot(el);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }
})();
