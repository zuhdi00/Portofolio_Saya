// Helper bersama index.html & barcode.html
(function () {
  "use strict";
  const C = window.APP_CONFIG;
  const $ = s => document.querySelector(s);
  const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  function url(action, params) {
    const u = new URL(C.API, location.href);
    u.searchParams.set("action", action);
    Object.entries(params || {}).forEach(([k, v]) => { if (v !== "" && v != null) u.searchParams.set(k, v); });
    return u.toString();
  }

  // opts: {params, json, form, admin}
  async function api(action, opts = {}) {
    const init = { method: "GET", headers: {} };
    if (opts.json) { init.method = "POST"; init.headers["Content-Type"] = "application/json"; init.body = JSON.stringify(opts.json); }
    if (opts.form) { init.method = "POST"; init.body = opts.form; }
    if (opts.admin) {
      const pin = adminPin();
      if (!pin) throw new Error("Password admin diperlukan.");
      init.headers["X-Admin-Pin"] = pin;
    }
    let res, data;
    try { res = await fetch(url(action, opts.params), init); }
    catch (e) { throw new Error("Tidak bisa menghubungi server. Cek jaringan / PC server."); }
    try { data = await res.json(); } catch (e) { throw new Error("Respon server tidak valid (HTTP " + res.status + ")."); }
    if (!res.ok || !data.success) {
      if (res.status === 403) { try { sessionStorage.removeItem("adminPin"); } catch (e) {} }
      throw new Error(data.message || "Gagal (HTTP " + res.status + ").");
    }
    return data;
  }

  function adminPin() {
    let p = null;
    try { p = sessionStorage.getItem("adminPin"); } catch (e) {}
    if (!p) {
      p = window.prompt("Masukkan password admin:");
      if (p) try { sessionStorage.setItem("adminPin", p); } catch (e) {}
    }
    return p;
  }

  let tt;
  function toast(m) { const t = $("#toast"); t.textContent = m; t.classList.add("show"); clearTimeout(tt); tt = setTimeout(() => t.classList.remove("show"), 3200); }
  function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  const toDate = s => (s instanceof Date ? s : new Date(String(s).replace(" ", "T")));
  const fmtDate = s => toDate(s).toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
  const fmtTime = s => toDate(s).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
  const today = () => { const d = new Date(); return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0"); };
  function setBusy(btn, on, label) {
    if (on) { btn.dataset.l = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spin"></span>' + (label || ""); }
    else { btn.disabled = false; if (btn.dataset.l) btn.innerHTML = btn.dataset.l; }
  }
  function qrDataUrl(text, cell) {
    try { const q = qrcode(0, "M"); q.addData(text); q.make(); return q.createDataURL(cell || 4, 2); } catch (e) { return ""; }
  }
  const STATUS = { aman: "Aman", perhatian: "Perlu perhatian", bahaya: "Bahaya" };
  const FOLLOW = { open: "Belum ditangani", proses: "Sedang diproses", selesai: "Selesai" };

  window.App = { C, $, esc, api, url, toast, ls, fmtDate, fmtTime, today, setBusy, qrDataUrl, STATUS, FOLLOW, adminPin };
})();
