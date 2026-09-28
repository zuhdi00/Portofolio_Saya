(function () {
  "use strict";
  const { C, $, esc, api, url, toast, ls, fmtDate, fmtTime, today, setBusy, qrDataUrl, STATUS, FOLLOW } = window.App;
  const S = { locations: [], checklist: [], stats: null, reports: [], cur: null };

  $("#divisi").innerHTML = C.DIVISI.map(d => `<option>${esc(d)}</option>`).join("");

  /* ---------- navigasi ---------- */
  document.querySelectorAll("nav.tabs button").forEach(b => b.addEventListener("click", () => show(b.dataset.view)));
  function show(id) {
    document.querySelectorAll(".view").forEach(v => v.classList.toggle("active", v.id === id));
    document.querySelectorAll("nav.tabs button").forEach(b => b.dataset.view === id ? b.setAttribute("aria-current", "page") : b.removeAttribute("aria-current"));
    if (id !== "v-scan") { stopScan(); closeCam(); }
    else if (S.cur) startPhotoCam(false); else startScan();
    if (id === "v-history") loadReports();
    if (id === "v-loc") refreshInit();
    window.scrollTo(0, 0);
  }
  function setConn(state, txt) { const c = $("#conn"); c.className = "conn " + state; c.querySelector("span").textContent = txt; }
  function showErr(m) { const b = $("#errBanner"); b.textContent = m; b.classList.toggle("hidden", !m); }

  /* ---------- scanner (live) ---------- */
  let scanner = null, scanning = false;
  const Lib = () => window.__Html5QrcodeLibrary__;
  const camBlockedMsg = () => "Browser memblokir kamera di alamat <b>http://</b>. Buka lewat <b>https://</b>, atau di Chrome buka " +
    "<code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code>, isi <code>" + location.origin + "</code>, pilih Enabled, lalu Relaunch.";
  async function startScan() {
    if (scanner || S.cur) return;
    const L = Lib(); if (!L) { toast("Modul scanner gagal dimuat."); return; }
    const hint = $("#camHint"); hint.classList.add("hidden");
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { hint.innerHTML = camBlockedMsg(); hint.classList.remove("hidden"); return; }
    try {
      scanning = true;
      scanner = new L.Html5Qrcode("reader", { verbose: false });
      await scanner.start({ facingMode: "environment" },
        { fps: 12, qrbox: (w, h) => { const s = Math.floor(Math.min(w, h) * 0.8); return { width: s, height: Math.floor(s * 0.7) }; } },
        txt => { if (!scanning) return; scanning = false; if (navigator.vibrate) navigator.vibrate(120); stopScan().then(() => handleCode(txt)); }, () => {});
      $("#btnStartScan").classList.add("hidden"); $("#btnStopScan").classList.remove("hidden");
    } catch (e) {
      scanning = false; scanner = null; $("#reader").innerHTML = "";
      hint.textContent = "Kamera tidak bisa dibuka (izin ditolak / dipakai aplikasi lain). Izinkan akses kamera lalu tekan tombol di atas.";
      hint.classList.remove("hidden");
    }
  }
  $("#btnStartScan").onclick = startScan;
  $("#btnStopScan").onclick = () => stopScan();
  async function stopScan() {
    scanning = false;
    if (scanner) { const s = scanner; scanner = null; try { await s.stop(); } catch (e) {} try { s.clear(); } catch (e) {} $("#reader").innerHTML = ""; }
    $("#btnStartScan").classList.remove("hidden"); $("#btnStopScan").classList.add("hidden");
  }
  // input manual & foto barcode hanya aktif jika ALLOW_MANUAL = true di config.js
  if (C.ALLOW_MANUAL) {
    $("#manualBox").classList.remove("hidden");
    $("#barcodeFile").onchange = async e => {
      const f = e.target.files[0]; e.target.value = ""; if (!f) return;
      const L = Lib(); if (!L) return;
      const fr = new L.Html5Qrcode("file-reader", { verbose: false });
      try { handleCode(await fr.scanFile(f, false)); }
      catch (err) { toast("Barcode tidak terbaca."); }
      finally { try { fr.clear(); } catch (e) {} }
    };
    $("#btnManual").onclick = () => {
      const code = $("#manualCode").value.trim() || $("#manualLoc").value;
      if (!code) { toast("Pilih lokasi atau ketik kodenya dulu."); return; }
      handleCode(code);
    };
  }
  // dukung link langsung: index.html?loc=SPS-LOC-001 (isi QR bisa berupa URL ini)
  function codeFromText(t) {
    t = String(t).trim();
    try { const u = new URL(t); return u.searchParams.get("loc") || t; } catch (e) { return t; }
  }

  /* ---------- form laporan ---------- */
  function handleCode(raw) {
    const code = codeFromText(raw); if (!code) return;
    const loc = S.locations.find(l => l.code.toLowerCase() === code.toLowerCase());
    S.cur = { code: loc ? loc.code : code.toUpperCase(), loc, photos: [], status: null, checks: {} };
    if (C.ALLOW_MANUAL) { $("#manualCode").value = ""; $("#manualLoc").value = ""; }
    renderLocCard();
    $("#registerBox").classList.toggle("hidden", !!loc);
    ["regName", "regArea", "catatan", "tindak", "pic"].forEach(id => $("#" + id).value = "");
    $("#petugas").value = ls("petugas") || "";
    if (ls("divisi")) $("#divisi").value = ls("divisi");
    $("#shift").value = guessShift();
    renderStatus(); renderChecks(); renderPhotos();
    $("#step-scan").classList.add("hidden"); $("#step-report").classList.remove("hidden");
    window.scrollTo(0, 0);
    toast(loc ? "Lokasi: " + loc.name : "Kode " + S.cur.code + " belum terdaftar");
    startPhotoCam(true);
  }
  function guessShift() { const h = new Date().getHours(); return h >= 7 && h < 15 ? "Shift 1" : h >= 15 && h < 23 ? "Shift 2" : "Shift 3"; }
  function renderLocCard() {
    const c = S.cur, l = c.loc;
    $("#locCard").innerHTML = `<img style="width:72px;height:72px;background:#fff;border-radius:6px;padding:3px;image-rendering:pixelated" src="${qrDataUrl(c.code, 3)}" alt="">
      <div style="min-width:0"><span class="code">${esc(c.code)}</span>
      <h3>${esc(l ? l.name : "Lokasi belum terdaftar")}</h3>
      <div class="small muted">${esc(l ? [l.area, l.divisi].filter(Boolean).join(" · ") : "Isi nama lokasi di bawah")}</div>
      <div class="small muted">${l && l.lastCheckAt ? "Terakhir dicek " + fmtDate(l.lastCheckAt) + " " + fmtTime(l.lastCheckAt) + " oleh " + esc(l.lastPetugas) : "Belum pernah dicek"}</div></div>`;
  }
  function renderStatus() { document.querySelectorAll("#statusSeg button").forEach(b => b.setAttribute("aria-pressed", String(!!S.cur && S.cur.status === b.dataset.v))); }
  document.querySelectorAll("#statusSeg button").forEach(b => b.onclick = () => { S.cur.status = b.dataset.v; renderStatus(); });
  function renderChecks() {
    if (!S.checklist.length) { $("#checks").innerHTML = '<p class="small muted">Checklist belum dimuat.</p>'; return; }
    $("#checks").innerHTML = S.checklist.map(({ key, label }) => `<div class="check"><span>${esc(label)}</span>
      <div class="seg" role="group" aria-label="${esc(label)}">
        <button type="button" class="c-ok" data-k="${esc(key)}" data-v="ok" aria-pressed="${S.cur.checks[key] === "ok"}">OK</button>
        <button type="button" class="c-bad" data-k="${esc(key)}" data-v="masalah" aria-pressed="${S.cur.checks[key] === "masalah"}">Masalah</button></div></div>`).join("");
    $("#checks").querySelectorAll("button").forEach(b => b.onclick = () => {
      const k = b.dataset.k; S.cur.checks[k] = S.cur.checks[k] === b.dataset.v ? null : b.dataset.v; renderChecks();
    });
  }
  function renderPhotos() {
    const g = $("#photoGrid"), n = S.cur.photos.length;
    g.innerHTML = S.cur.photos.map((p, i) => `<div class="ph"><img src="${p.preview}" alt="Foto ${i + 1}">
      <button class="x" data-i="${i}" aria-label="Hapus foto ${i + 1}">×</button>
      <input data-i="${i}" placeholder="Keterangan foto ${i + 1}" value="${esc(p.caption)}"></div>`).join("");
    g.querySelectorAll(".x").forEach(b => b.onclick = () => { URL.revokeObjectURL(S.cur.photos[b.dataset.i].preview); S.cur.photos.splice(+b.dataset.i, 1); renderPhotos(); });
    g.querySelectorAll("input").forEach(inp => inp.oninput = () => S.cur.photos[inp.dataset.i].caption = inp.value);
    $("#photoCount").textContent = n ? n + " foto" : "";
    $("#btnShutter").disabled = n >= C.MAX_PHOTOS;
  }

  /* ---------- kamera foto realtime (tanpa upload file) ---------- */
  let camStream = null, capturing = false;
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  async function startPhotoCam(auto) {
    const msg = $("#camMsg"); msg.textContent = "";
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { msg.innerHTML = camBlockedMsg(); return; }
    try {
      closeCam();
      camStream = await navigator.mediaDevices.getUserMedia({ audio: false,
        video: { facingMode: { ideal: "environment" }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
      const v = $("#cam"); v.srcObject = camStream; await v.play().catch(() => {});
      for (let i = 0; i < 40 && !v.videoWidth; i++) await sleep(100);   // tunggu kamera siap
      if (auto && S.cur && !S.cur.photos.length) autoCapture();
    } catch (e) { msg.textContent = "Kamera tidak bisa dibuka. Izinkan akses kamera di browser, lalu tekan \u201cAmbil foto\u201d."; }
  }
  function closeCam() {
    if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
    const v = $("#cam"); if (v) v.srcObject = null;
  }
  async function autoCapture() {
    const cd = $("#countdown"), sec = Math.max(0, C.AUTO_PHOTO_DELAY ?? 3);
    for (let n = sec; n > 0; n--) { if (!S.cur || !camStream) { cd.textContent = ""; return; } cd.textContent = n; await sleep(800); }
    cd.textContent = "";
    await capture();
  }
  async function capture() {
    if (capturing || !S.cur) return;
    const v = $("#cam");
    if (!camStream || !v.videoWidth) { await startPhotoCam(false); if (!camStream || !v.videoWidth) return; }
    if (S.cur.photos.length >= C.MAX_PHOTOS) { toast("Maksimal " + C.MAX_PHOTOS + " foto."); return; }
    capturing = true;
    try {
      const r = Math.min(1, 1600 / Math.max(v.videoWidth, v.videoHeight));
      const c = document.createElement("canvas"); c.width = Math.round(v.videoWidth * r); c.height = Math.round(v.videoHeight * r);
      const ctx = c.getContext("2d"); ctx.drawImage(v, 0, 0, c.width, c.height);
      // cap waktu + kode lokasi + petugas (bukti foto realtime)
      const who = $("#petugas").value.trim();
      const txt = new Date().toLocaleString("id-ID") + "  " + S.cur.code + (who ? "  " + who : "");
      const fs = Math.max(14, Math.round(c.width / 45));
      ctx.font = `600 ${fs}px Arial`; const w = ctx.measureText(txt).width;
      ctx.fillStyle = "rgba(0,0,0,.55)"; ctx.fillRect(c.width - w - fs * 1.2, c.height - fs * 1.9, w + fs * 1.2, fs * 1.9);
      ctx.fillStyle = "#fff"; ctx.fillText(txt, c.width - w - fs * 0.6, c.height - fs * 0.6);
      const blob = await new Promise(res => c.toBlob(res, "image/jpeg", 0.8));
      S.cur.photos.push({ blob, preview: URL.createObjectURL(blob), caption: "" });
      const f = $("#flash"); f.classList.add("on"); setTimeout(() => f.classList.remove("on"), 150);
      if (navigator.vibrate) navigator.vibrate(60);
      renderPhotos();
    } finally { capturing = false; }
  }
  $("#btnShutter").onclick = capture;
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) { closeCam(); stopScan(); }
    else if (S.cur) startPhotoCam(false);
    else if ($("#v-scan").classList.contains("active")) startScan();
  });
  $("#btnCancel").onclick = resetReport;
  function resetReport() {
    closeCam();
    if (S.cur) S.cur.photos.forEach(p => URL.revokeObjectURL(p.preview));
    S.cur = null; $("#step-report").classList.add("hidden"); $("#step-scan").classList.remove("hidden"); window.scrollTo(0, 0);
    if ($("#v-scan").classList.contains("active")) startScan();   // siap scan titik berikutnya
  }

  $("#btnSave").onclick = async () => {
    const c = S.cur, btn = $("#btnSave"), petugas = $("#petugas").value.trim();
    if (!petugas) { toast("Isi nama petugas."); $("#petugas").focus(); return; }
    if (!c.photos.length) { toast("Ambil minimal 1 foto ruangan."); return; }
    if (!c.status) { toast("Pilih status kondisi lokasi."); return; }
    if (!c.loc && !$("#regName").value.trim()) { toast("Isi nama lokasi untuk kode baru ini."); $("#regName").focus(); return; }
    ls("petugas", petugas); ls("divisi", $("#divisi").value);
    const data = {
      locCode: c.code, petugas, divisi: $("#divisi").value, shift: $("#shift").value, status: c.status,
      checks: Object.fromEntries(Object.entries(c.checks).filter(([, v]) => v)),
      catatan: $("#catatan").value.trim(), tindakLanjut: $("#tindak").value.trim(), pic: $("#pic").value.trim(),
      captions: c.photos.map(p => p.caption.trim()),
      newLocation: c.loc ? null : { name: $("#regName").value.trim(), area: $("#regArea").value.trim() }
    };
    const fd = new FormData();
    fd.append("data", JSON.stringify(data));
    c.photos.forEach((p, i) => fd.append("photos[]", p.blob, "foto_" + (i + 1) + ".jpg"));
    setBusy(btn, true, " Mengunggah…");
    try {
      const r = await api("report_save", { form: fd });
      toast("Laporan " + r.reportNo + " tersimpan.");
      resetReport(); refreshInit();
    } catch (e) { toast(e.message + " Data form tidak hilang, coba simpan lagi."); }
    finally { setBusy(btn, false); }
  };

  /* ---------- data awal & lokasi ---------- */
  async function refreshInit() {
    try {
      const r = await api("init");
      S.locations = r.locations; S.checklist = r.checklist; S.stats = r.stats;
      setConn("on", "Tersambung"); showErr("");
      renderLocations(); renderStats();
      if (S.cur && !S.cur.photos.length && !S.cur.status) renderChecks();
    } catch (e) { setConn("off", "Offline"); showErr(e.message); }
  }
  function renderLocations() {
    if (C.ALLOW_MANUAL) $("#manualLoc").innerHTML = '<option value="">Pilih lokasi…</option>' + S.locations.map(l => `<option value="${esc(l.code)}">${esc(l.name)} (${esc(l.code)})</option>`).join("");
    const un = S.locations.filter(l => !+l.checkedToday);
    $("#uncheckedInfo").textContent = S.locations.length ? (un.length ? `${un.length} dari ${S.locations.length} lokasi belum dicek hari ini.` : "Semua lokasi sudah dicek hari ini.") : "";
    const sorted = [...S.locations].sort((a, b) => (+a.checkedToday) - (+b.checkedToday));
    $("#locList").innerHTML = sorted.length ? sorted.map(l => `<button class="item" data-code="${esc(l.code)}">
      <img class="qr" src="${qrDataUrl(l.code, 3)}" alt="">
      <span class="body"><span class="t" style="display:block">${esc(l.name)}</span>
      <span class="m" style="display:block">${esc(l.code)}${l.area ? " · " + esc(l.area) : ""}</span>
      <span class="m" style="display:block">${l.lastCheckAt ? "Terakhir: " + fmtDate(l.lastCheckAt) + " " + fmtTime(l.lastCheckAt) + " – " + esc(l.lastPetugas) : "Belum pernah dicek"}</span></span>
      ${+l.checkedToday ? `<span class="chip ${esc(l.lastStatus)}">${STATUS[l.lastStatus] || "-"}</span>` : `<span class="chip none">Belum dicek</span>`}</button>`).join("")
      : `<div class="empty">Belum ada lokasi. Tambahkan di halaman Kelola barcode.</div>`;
    $("#locList").querySelectorAll(".item").forEach(b => b.onclick = () => { show("v-scan"); handleCode(b.dataset.code); });
  }
  function renderStats() {
    const s = S.stats; if (!s) return;
    $("#stats").innerHTML = `<div class="stat"><b>${s.reportsToday}</b><span>Laporan hari ini</span></div>
      <div class="stat"><b>${s.locationsCheckedToday}/${s.locationsTotal}</b><span>Lokasi dicek hari ini</span></div>
      <div class="stat"><b style="color:var(--warn)">${s.perhatianOpen}</b><span>Perlu perhatian (belum selesai)</span></div>
      <div class="stat"><b style="color:var(--bad)">${s.bahayaOpen}</b><span>Bahaya (belum selesai)</span></div>`;
  }

  /* ---------- riwayat ---------- */
  const filterParams = () => ({ q: $("#fText").value.trim(), status: $("#fStatus").value, followup: $("#fFollow").value, from: $("#fFrom").value, to: $("#fTo").value });
  let deb;
  ["fText", "fStatus", "fFollow", "fFrom", "fTo"].forEach(id => $("#" + id).addEventListener("input", () => { clearTimeout(deb); deb = setTimeout(loadReports, 350); }));
  async function loadReports() {
    $("#btnCsv").href = url("export_csv", filterParams());
    renderStats();
    try {
      const r = await api("reports", { params: { ...filterParams(), limit: 300 } });
      S.reports = r.reports; renderReports();
    } catch (e) { $("#reportList").innerHTML = `<div class="empty">${esc(e.message)}</div>`; }
  }
  function renderReports() {
    $("#reportList").innerHTML = S.reports.length ? S.reports.map(r => `<button class="item" data-id="${r.id}">
      ${r.firstPhotoId ? `<img class="thumb" src="${url("photo", { id: r.firstPhotoId })}" alt="" loading="lazy">` : `<span class="thumb"></span>`}
      <span class="body"><span class="t" style="display:block">${esc(r.locName)}</span>
      <span class="m" style="display:block">${esc(r.reportNo)} · ${fmtDate(r.createdAt)} ${fmtTime(r.createdAt)} · ${esc(r.petugas)}</span>
      <span class="m" style="display:block">${r.status !== "aman" ? FOLLOW[r.followUp] + " · " : ""}${r.photoCount} foto${r.catatan ? " · " + esc(r.catatan) : ""}</span></span>
      <span class="chip ${esc(r.status)}">${STATUS[r.status] || "-"}</span></button>`).join("")
      : `<div class="empty">Tidak ada laporan untuk filter ini.</div>`;
    $("#reportList").querySelectorAll(".item").forEach(b => b.onclick = () => openReport(b.dataset.id));
  }
  async function openReport(id) {
    let r;
    try { r = (await api("report", { params: { id } })).report; } catch (e) { toast(e.message); return; }
    const res = { ok: ["aman", "OK"], masalah: ["bahaya", "Masalah"] };
    $("#sheet").innerHTML = `<button class="btn close" id="mClose">Tutup</button>
      <span class="chip ${esc(r.status)}">${STATUS[r.status]}</span>
      <h2 style="margin-top:8px">${esc(r.locName)}</h2>
      <dl class="kv"><dt>No. laporan</dt><dd>${esc(r.reportNo)}</dd><dt>Kode lokasi</dt><dd>${esc(r.locCode)}</dd><dt>Area</dt><dd>${esc(r.area || "-")}</dd>
      <dt>Waktu</dt><dd>${fmtDate(r.createdAt)}, ${fmtTime(r.createdAt)}</dd><dt>Petugas</dt><dd>${esc(r.petugas)} (${esc(r.divisi || "-")}, ${esc(r.shift || "-")})</dd>
      <dt>Catatan</dt><dd>${esc(r.catatan || "-")}</dd><dt>Tindak lanjut</dt><dd>${esc(r.tindakLanjut || "-")}</dd><dt>PIC</dt><dd>${esc(r.pic || "-")}</dd></dl>
      <h3 style="margin-top:16px">Checklist</h3>
      <div>${r.checks.length ? r.checks.map(c => `<div style="display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid var(--line);padding:6px 0"><span>${esc(c.label)}</span><span class="chip ${res[c.result][0]}">${res[c.result][1]}</span></div>`).join("") : '<p class="small muted">Tidak ada item dinilai.</p>'}</div>
      <h3 style="margin-top:16px">Foto (${r.photos.length})</h3>
      <div class="photos">${r.photos.map((p, i) => `<a class="ph" href="${url("photo", { id: p.id })}" target="_blank" rel="noopener"><img src="${url("photo", { id: p.id })}" alt="Foto ${i + 1}" loading="lazy"><div class="small" style="padding:6px 8px">${esc(p.caption || "Foto " + (i + 1))}</div></a>`).join("")}</div>
      <div class="panel" style="margin-top:16px">
        <h3>Tindak lanjut</h3>
        <div class="row"><select class="i" id="mFu">${Object.entries(FOLLOW).map(([k, v]) => `<option value="${k}" ${r.followUp === k ? "selected" : ""}>${v}</option>`).join("")}</select>
        <input class="i" id="mFuNote" placeholder="Catatan penanganan" value="${esc(r.followUpNote || "")}"></div>
        ${r.followUpAt ? `<p class="small muted" style="margin:6px 0 0">Diperbarui ${fmtDate(r.followUpAt)} ${fmtTime(r.followUpAt)}</p>` : ""}
        <button class="btn primary block" id="mFuSave" style="margin-top:10px">Simpan tindak lanjut</button>
      </div>
      <div class="row"><button class="btn" id="mPrint">Cetak laporan</button><button class="btn danger" id="mDel">Hapus (admin)</button></div>`;
    $("#modal").classList.add("open");
    $("#mClose").onclick = closeModal;
    $("#mFuSave").onclick = async e => {
      const b = e.currentTarget; setBusy(b, true, " Menyimpan…");
      try { await api("report_followup", { json: { id: r.id, followUp: $("#mFu").value, note: $("#mFuNote").value } }); toast("Tindak lanjut disimpan."); closeModal(); loadReports(); refreshInit(); }
      catch (err) { toast(err.message); } finally { setBusy(b, false); }
    };
    $("#mPrint").onclick = () => printReport(r);
    $("#mDel").onclick = async () => {
      if (!confirm("Hapus laporan " + r.reportNo + " beserta fotonya?")) return;
      try { await api("report_delete", { json: { id: r.id }, admin: true }); toast("Laporan dihapus."); closeModal(); loadReports(); refreshInit(); }
      catch (err) { toast(err.message); }
    };
  }
  function printReport(r) {
    const w = window.open("", "_blank"); if (!w) { toast("Izinkan pop-up untuk mencetak."); return; }
    const res = { ok: "OK", masalah: "MASALAH" };
    w.document.write(`<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>${esc(r.reportNo)}</title>
<style>body{font-family:Arial,sans-serif;max-width:780px;margin:20px auto;color:#14213D;padding:0 14px;font-size:13px}h1{margin:0;font-size:20px}
table{border-collapse:collapse;width:100%;margin:10px 0}td{border:1px solid #bbb;padding:5px 8px;vertical-align:top}td:first-child{width:160px;background:#f2f2f2}
.g{display:grid;grid-template-columns:1fr 1fr;gap:8px}figure{margin:0;break-inside:avoid}img{width:100%;border:1px solid #ccc}figcaption{font-size:11px}
.sig{display:flex;justify-content:space-between;margin-top:40px}.sig div{text-align:center;width:40%}@page{size:A4;margin:12mm}</style></head><body>
<h1>Laporan Pemantauan Lokasi</h1><div>${esc(C.COMPANY)} · ${esc(r.reportNo)}</div>
<table><tr><td>Lokasi</td><td>${esc(r.locName)} (${esc(r.locCode)})</td></tr><tr><td>Area</td><td>${esc(r.area || "-")}</td></tr>
<tr><td>Waktu</td><td>${fmtDate(r.createdAt)}, ${fmtTime(r.createdAt)}</td></tr><tr><td>Petugas</td><td>${esc(r.petugas)} – ${esc(r.divisi || "")}, ${esc(r.shift || "")}</td></tr>
<tr><td>Status</td><td><b>${STATUS[r.status]}</b></td></tr>
${r.checks.map(c => `<tr><td>${esc(c.label)}</td><td>${res[c.result]}</td></tr>`).join("")}
<tr><td>Catatan</td><td>${esc(r.catatan || "-")}</td></tr><tr><td>Tindak lanjut</td><td>${esc(r.tindakLanjut || "-")}</td></tr>
<tr><td>PIC</td><td>${esc(r.pic || "-")}</td></tr><tr><td>Status tindak lanjut</td><td>${FOLLOW[r.followUp]}${r.followUpNote ? " – " + esc(r.followUpNote) : ""}</td></tr></table>
<div class="g">${r.photos.map(p => `<figure><img src="${url("photo", { id: p.id })}"><figcaption>${esc(p.caption || "")}</figcaption></figure>`).join("")}</div>
<div class="sig"><div>Petugas<br><br><br><br>( ${esc(r.petugas)} )</div><div>Mengetahui<br><br><br><br>( ................................ )</div></div>
<script>window.onload=()=>setTimeout(()=>window.print(),400)<\/script></body></html>`);
    w.document.close();
  }
  function closeModal() { $("#modal").classList.remove("open"); }
  $("#modal").addEventListener("click", e => { if (e.target.id === "modal") closeModal(); });
  document.addEventListener("keydown", e => { if (e.key === "Escape") closeModal(); });

  /* ---------- start ---------- */
  $("#fFrom").value = ""; $("#fTo").value = "";
  refreshInit().then(() => {
    const p = new URLSearchParams(location.search).get("loc");
    if (p) handleCode(p); else startScan();
  });
})();
