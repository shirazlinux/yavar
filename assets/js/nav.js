/**
 * Mobile nav: hamburger toggle + backdrop + Escape
 */
(function () {
  var header = document.querySelector(".site-header");
  var toggle = document.getElementById("nav-toggle");
  var nav = document.getElementById("site-nav");
  var backdrop = document.getElementById("nav-backdrop");
  if (!header || !toggle || !nav) return;

  function isOpen() {
    return toggle.getAttribute("aria-expanded") === "true";
  }

  function setOpen(open) {
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    header.classList.toggle("nav-open", open);
    nav.classList.toggle("is-open", open);
    document.body.classList.toggle("nav-open", open);
    if (backdrop) {
      if (open) backdrop.removeAttribute("hidden");
      else backdrop.setAttribute("hidden", "");
    }
  }

  function close() {
    setOpen(false);
  }

  toggle.addEventListener("click", function (e) {
    e.stopPropagation();
    setOpen(!isOpen());
  });

  if (backdrop) {
    backdrop.addEventListener("click", close);
  }

  nav.addEventListener("click", function (e) {
    var a = e.target.closest("a");
    if (a) close();
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && isOpen()) {
      close();
      toggle.focus();
    }
  });

  // desktop resize: always reset
  var mq = window.matchMedia("(min-width: 901px)");
  function onMq(e) {
    if (e.matches) close();
  }
  if (mq.addEventListener) mq.addEventListener("change", onMq);
  else if (mq.addListener) mq.addListener(onMq);
})();

/**
 * داشبورد: روی دسکتاپ منو همیشه باز؛ روی موبایل پیش‌فرض جمع
 */
(function () {
  var details = document.querySelector(".dash-nav__details");
  if (!details) return;
  var mq = window.matchMedia("(min-width: 901px)");
  function sync(initial) {
    if (mq.matches) {
      details.open = true;
      details.dataset.desktop = "1";
    } else {
      if (initial || details.dataset.desktop === "1") {
        details.open = false;
      }
      delete details.dataset.desktop;
    }
  }
  sync(true);
  if (mq.addEventListener) mq.addEventListener("change", function () { sync(false); });
  else if (mq.addListener) mq.addListener(function () { sync(false); });
})();
