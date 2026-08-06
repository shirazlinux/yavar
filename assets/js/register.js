(function () {
  /** ارقام فارسی/عربی → انگلیسی */
  function digitsEn(s) {
    var map = {
      "۰": "0", "۱": "1", "۲": "2", "۳": "3", "۴": "4",
      "۵": "5", "۶": "6", "۷": "7", "۸": "8", "۹": "9",
      "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4",
      "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9",
    };
    return String(s || "")
      .replace(/[۰-۹٠-٩]/g, function (d) {
        return map[d] || d;
      })
      .replace(/\D+/g, "");
  }

  function wireDigitsEn(el) {
    if (!el) return;
    el.addEventListener("input", function () {
      var start = el.selectionStart;
      var before = el.value;
      var after = digitsEn(before);
      // برای فیلد کپچا فقط رقم؛ برای OTP هم
      if (el.getAttribute("data-digits-en") === "1" || el.id === "otp" || el.id === "captcha") {
        if (before !== after) {
          el.value = after;
          try {
            el.setSelectionRange(after.length, after.length);
          } catch (e) {}
        }
      }
    });
  }

  function hasPersian(s) {
    return /[\u0600-\u06FF]/.test(s || "");
  }
  function wirePassword(el) {
    if (!el) return;
    el.setAttribute("dir", "ltr");
    el.style.direction = "ltr";
    el.style.textAlign = "left";
    el.addEventListener("input", function () {
      var w = document.getElementById("pass-warn");
      if (!w) return;
      w.hidden = !hasPersian(el.value);
    });
  }
  wirePassword(document.getElementById("password"));
  wirePassword(document.getElementById("password2"));
  wireDigitsEn(document.getElementById("captcha"));
  wireDigitsEn(document.getElementById("otp"));
  wireDigitsEn(document.getElementById("phone"));

  function markCaptchaPassed() {
    var box = document.querySelector(".captcha-box");
    if (!box) return;
    box.classList.add("captcha-box--ok");
    box.setAttribute("data-captcha-passed", "1");
    box.innerHTML =
      '<div class="captcha-box__head">' +
      '<span class="captcha-box__badge captcha-box__badge--ok">تأیید شد</span>' +
      '<span class="captcha-box__hint">کپچای این نشست قبول است — دوباره لازم نیست.</span>' +
      "</div>" +
      '<input type="hidden" name="captcha" id="captcha" value="ok">';
  }

  function showNewCaptcha(q, hint) {
    var box = document.querySelector(".captcha-box");
    if (!box) return;
    box.classList.remove("captcha-box--ok");
    box.setAttribute("data-captcha-passed", "0");
    box.innerHTML =
      '<div class="captcha-box__head">' +
      '<span class="captcha-box__badge">کپچا</span>' +
      '<span class="captcha-box__hint">' +
      (hint || "پاسخ را با عدد انگلیسی بنویسید") +
      "</span></div>" +
      '<div class="captcha-box__challenge" aria-hidden="true">' +
      (q || "") +
      "</div>" +
      '<label class="captcha-box__label" for="captcha">پاسخ را با عدد انگلیسی بنویسید *</label>' +
      '<input class="captcha-box__input ltr-field" id="captcha" name="captcha" required inputmode="numeric" pattern="[0-9]*" autocomplete="off" dir="ltr" placeholder="مثلاً 12" data-digits-en="1">';
    wireDigitsEn(document.getElementById("captcha"));
  }

  var btn = document.getElementById("btn-send-otp");
  if (!btn) return;
  btn.addEventListener("click", async function () {
    var phone = digitsEn((document.getElementById("phone") || {}).value || "");
    // اگر phone field has leading 0, digitsEn keeps it
    var phoneEl = document.getElementById("phone");
    if (phoneEl) phone = (phoneEl.value || "").replace(/[۰-۹٠-٩]/g, function (d) {
      var m = { "۰":"0","۱":"1","۲":"2","۳":"3","۴":"4","۵":"5","۶":"6","۷":"7","۸":"8","۹":"9","٠":"0","١":"1","٢":"2","٣":"3","٤":"4","٥":"5","٦":"6","٧":"7","٨":"8","٩":"9" };
      return m[d] || d;
    });
    var box = document.querySelector(".captcha-box");
    var passed = box && box.getAttribute("data-captcha-passed") === "1";
    var captcha = passed ? "ok" : digitsEn((document.getElementById("captcha") || {}).value || "");
    var csrf = (document.getElementById("csrf") || {}).value || "";
    var st = document.getElementById("otp-status");
    if (!passed && !captcha) {
      if (st) st.textContent = "ابتدا پاسخ کپچا را با عدد انگلیسی وارد کنید.";
      return;
    }
    if (st) st.textContent = "در حال ارسال…";
    btn.disabled = true;
    try {
      var res = await fetch("/api/send-otp.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ phone: phone, captcha: captcha, csrf: csrf }),
      });
      var data = await res.json();
      if (st) st.textContent = data.message || (data.ok ? "ارسال شد" : "خطا");
      if (data.ok || data.captcha_passed) {
        markCaptchaPassed();
      } else if (data.captcha_question) {
        showNewCaptcha(data.captcha_question, data.captcha_hint);
      }
    } catch (e) {
      if (st) st.textContent = "خطا در ارتباط";
    }
    setTimeout(function () {
      btn.disabled = false;
    }, 60000);
  });
})();
