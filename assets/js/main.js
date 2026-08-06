(function () {
  const form = document.getElementById("donate-form");
  if (!form) return;

  const amountInput = document.getElementById("amount");
  const msgEl = document.getElementById("form-msg");
  const submitBtn = document.getElementById("submit-btn");
  const amountBtns = document.querySelectorAll(".amount-btn");
  const activistEl = document.getElementById("activist_id");

  function formatFa(n) {
    try {
      return Number(n).toLocaleString("fa-IR");
    } catch (_) {
      return String(n);
    }
  }

  /** Escape for safe insertion into HTML (prevent stored XSS from bank fields) */
  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function setActiveAmount(val) {
    amountBtns.forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.amount === String(val));
    });
  }

  amountBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const v = btn.dataset.amount;
      amountInput.value = v;
      setActiveAmount(v);
      hideMsg();
    });
  });

  if (amountInput) {
    amountInput.addEventListener("input", () => {
      setActiveAmount(amountInput.value.trim());
    });
  }

  function showMsg(html, type) {
    msgEl.className = "form-msg show " + (type || "");
    msgEl.innerHTML = html;
  }

  function hideMsg() {
    msgEl.className = "form-msg";
    msgEl.textContent = "";
  }

  function copyBtn(text, label) {
    const safe = esc(text);
    return (
      '<button type="button" class="btn btn-ghost copy-btn" style="width:auto;padding:.35rem .7rem;font-size:.8rem;margin-inline-start:.35rem" data-copy="' +
      safe +
      '">' +
      esc(label) +
      "</button>"
    );
  }

  function csrfToken() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    return csrfMeta ? csrfMeta.getAttribute("content") || "" : "";
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideMsg();

    const amount = parseInt(
      (window.DonateMoney
        ? window.DonateMoney.onlyDigits(amountInput.value)
        : String(amountInput.value).replace(/\D+/g, "")),
      10
    );
    if (!amount || amount < 1) {
      showMsg("لطفاً مبلغ معتبری وارد کنید.", "error");
      return;
    }

    const activist_id = activistEl
      ? parseInt(activistEl.value, 10)
      : parseInt(form.dataset.activist || "0", 10);

    const campaignPreview = form.dataset.campaign ? parseInt(form.dataset.campaign, 10) : 0;
    if (!activist_id && !campaignPreview) {
      showMsg("این فرم فقط روی صفحهٔ فعال یا کمپین کار می‌کند.", "error");
      return;
    }

    const nameEl = document.getElementById("name");
    const messageEl = document.getElementById("message");
    const causeEl = document.getElementById("cause");
    const websiteEl = document.getElementById("website");

    const campaign_id = form.dataset.campaign
      ? parseInt(form.dataset.campaign, 10)
      : (document.getElementById("campaign_id")
          ? parseInt(document.getElementById("campaign_id").value, 10)
          : 0);

    const csrf = csrfToken();
    const payload = {
      amount,
      name: nameEl ? nameEl.value.trim() : "",
      message: messageEl ? messageEl.value.trim().slice(0, 280) : "",
      cause: causeEl ? causeEl.value : (campaign_id ? "campaign" : "activists"),
      activist_id: campaign_id ? 0 : activist_id,
      campaign_id: campaign_id || 0,
      website: websiteEl ? websiteEl.value : "",
      public_post: document.getElementById("public_post")
        ? !!document.getElementById("public_post").checked
        : true,
      anonymous: document.getElementById("anonymous")
        ? !!document.getElementById("anonymous").checked
        : false,
      csrf: csrf,
    };

    submitBtn.disabled = true;
    const old = submitBtn.textContent;
    submitBtn.textContent = "در حال ثبت…";

    try {
      const res = await fetch("/api/start.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      // فقط redirect به دامنهٔ درگاه‌های شناخته‌شده
      if (data.ok && data.mode === "redirect" && data.redirect) {
        try {
          const u = new URL(data.redirect, window.location.origin);
          const host = u.hostname.toLowerCase();
          const okHost =
            host === "payping.ir" ||
            host.endsWith(".payping.ir") ||
            host === "api.payping.ir" ||
            host === "www.zarinpal.com" ||
            host === "zarinpal.com" ||
            host === "sandbox.zarinpal.com" ||
            host === "pay.ir" ||
            host.endsWith(".idpay.ir") ||
            host === "idpay.ir";
          if (u.protocol === "https:" && okHost) {
            window.location.href = u.href;
            return;
          }
        } catch (_) {}
        showMsg("آدرس درگاه نامعتبر است. لطفاً دوباره تلاش کنید.", "error");
        return;
      }

      if (data.ok && (data.mode === "bank" || data.mode === "manual")) {
        const b = data.bank || data.manual || {};
        let html = "<strong>✅ درخواست ثبت شد — واریز مستقیم به فعال</strong><br>";
        if (data.message) html += esc(data.message) + "<br><br>";
        else html += "<br>";
        html +=
          "مبلغ: <strong>" +
          formatFa(data.amount || amount) +
          "</strong> تومان<br>";
        if (data.ref_code) {
          html +=
            "کد پیگیری (در توضیح واریز بنویسید): <code dir='ltr'>" +
            esc(data.ref_code) +
            "</code>" +
            copyBtn(data.ref_code, "کپی کد") +
            "<br><br>";
        }
        if (b.owner) html += "به‌نام: <strong>" + esc(b.owner) + "</strong><br>";
        if (b.card) {
          html +=
            "کارت: <code dir='ltr'>" +
            esc(b.card) +
            "</code>" +
            copyBtn(b.card, "کپی کارت") +
            "<br>";
        }
        if (b.sheba) {
          html +=
            "شبا: <code dir='ltr'>" +
            esc(b.sheba) +
            "</code>" +
            copyBtn(b.sheba, "کپی شبا") +
            "<br>";
        }
        if (b.note) html += "<br><span style='opacity:.9'>" + esc(b.note) + "</span>";

        html +=
          "<div style='margin-top:1rem;padding-top:.75rem;border-top:1px solid rgba(148,163,184,.25)'>" +
          "<label style='display:block;margin-bottom:.35rem'>بعد از واریز، شماره پیگیری بانک (اختیاری):</label>" +
          "<input type='text' id='bank-track' dir='ltr' placeholder='شماره پیگیری / ۴ رقم کارت مبدأ' " +
          "style='width:100%;margin-bottom:.5rem;border-radius:10px;border:1px solid rgba(148,163,184,.3);background:#0b1220;color:#e8eef9;padding:.6rem'>" +
          "<button type='button' class='btn btn-primary' id='btn-report-paid' style='width:100%'>اعلام کردم واریز کردم</button>" +
          "<div id='report-status' style='margin-top:.5rem;font-size:.88rem'></div>" +
          "</div>";

        showMsg(html, "manual");

        msgEl.querySelectorAll("[data-copy]").forEach((btn) => {
          btn.addEventListener("click", () => {
            navigator.clipboard.writeText(btn.getAttribute("data-copy") || "");
            btn.textContent = "کپی شد";
            setTimeout(() => (btn.textContent = "کپی"), 1200);
          });
        });
        const reportBtn = document.getElementById("btn-report-paid");
        if (reportBtn && data.authority) {
          reportBtn.addEventListener("click", async () => {
            const track = (document.getElementById("bank-track") || {}).value || "";
            reportBtn.disabled = true;
            try {
              const token = csrfToken();
              const r2 = await fetch("/api/report-transfer.php", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  "X-CSRF-Token": token,
                },
                body: JSON.stringify({
                  authority: data.authority,
                  track: track,
                  csrf: token,
                }),
              });
              const d2 = await r2.json();
              const st = document.getElementById("report-status");
              if (st) st.textContent = d2.message || (d2.ok ? "ثبت شد" : "خطا");
            } catch (_) {
              const st = document.getElementById("report-status");
              if (st) st.textContent = "خطا در ارتباط";
            }
            reportBtn.disabled = false;
          });
        }
        return;
      }

      showMsg(esc(data.message || "خطایی رخ داد."), "error");
    } catch (err) {
      showMsg("ارتباط با سرور برقرار نشد.", "error");
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = old;
    }
  });
})();
