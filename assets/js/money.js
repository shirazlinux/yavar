(function () {
  function toEnDigits(s) {
    return String(s || "").replace(/[۰-۹]/g, function (d) {
      return "۰۱۲۳۴۵۶۷۸۹".indexOf(d);
    }).replace(/[٠-٩]/g, function (d) {
      return "٠١٢٣٤٥٦٧٨٩".indexOf(d);
    });
  }
  function onlyDigits(s) {
    return toEnDigits(s).replace(/\D+/g, "");
  }
  function formatGrouped(n) {
    var s = onlyDigits(n);
    if (!s) return "";
    try {
      return Number(s).toLocaleString("fa-IR");
    } catch (e) {
      return s.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
  }
  function wireMoneyInputs() {
    document.querySelectorAll(".money-input").forEach(function (el) {
      el.setAttribute("dir", "ltr");
      el.style.textAlign = "left";
      el.addEventListener("input", function () {
        var pos = el.selectionStart;
        var raw = onlyDigits(el.value);
        el.value = formatGrouped(raw);
      });
      el.addEventListener("blur", function () {
        el.value = formatGrouped(el.value);
      });
      if (el.value) el.value = formatGrouped(el.value);
    });
  }
  function formatAmountButtons() {
    document.querySelectorAll(".amount-btn").forEach(function (btn) {
      var a = btn.getAttribute("data-amount");
      if (a && !btn.dataset.formatted) {
        btn.textContent = formatGrouped(a);
        btn.dataset.formatted = "1";
      }
    });
  }
  function formatAmountField() {
    var amount = document.getElementById("amount");
    if (!amount) return;
    amount.setAttribute("dir", "ltr");
    amount.classList.add("money-input");
    if (amount.type === "number") {
      try { amount.type = "text"; } catch (e) {}
    }
    amount.setAttribute("inputmode", "numeric");
  }
  document.addEventListener("DOMContentLoaded", function () {
    formatAmountField();
    wireMoneyInputs();
    formatAmountButtons();
  });
  window.DonateMoney = { formatGrouped: formatGrouped, onlyDigits: onlyDigits };
})();
