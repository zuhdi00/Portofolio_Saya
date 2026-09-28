(function () {
  "use strict";
  const { C, $, esc, api, toast, ls, fmtDate, fmtTime, setBusy, STATUS } = window.App;
  const S = { locations: [], sheet: [], editing: null };

  $("#divList").innerHTML = C.DIVISI.map(d => `<option value="${esc(d)}">`).join("");
  $("#pHeader").value = ls("lblHeader") || C.LABEL_HEADER;
  $("#pBaseUrl").value = ls("lblBase") || new URL("index.html", location.href).toString();
  try { S.sheet = JSON.parse(ls("lblSheet") || "[]"); } catch (e) { S.sheet = []; }

  /* ---------- lokasi ---------- */
  async function load() {
    try {
      S.locations = (await api("locations")).locations;
      $("#conn").className = "conn on"; $("#conn").querySelector("span").textContent = "Tersambung";
      $("#errBanner").classList.add("hidden");
    } catch (e) {
      $("#conn").className = "conn off"; $("#conn").querySelector("span").textContent = "Offline";
      $("#errBanner").textContent = e.message + " Anda tetap bisa membuat barcode manual di bawah.";
      $("#errBanner").classList.remove("hidden");
    }
    renderTable();
  }
  function renderTable() {
    const q = $("#locSearch").value.toLowerCase().trim();
    const rows = S.locations.filter(l => !q || [l.code, l.name, l.area, l.divisi].join(" ").toLowerCase().includes(q));
    $("#locCount").textContent = "(" + S.locations.length + ")";
    $("#locBody").innerHTML = rows.length ? rows.map(l => `<tr>
      <td><input type="checkbox" class="chk" value="${esc(l.code)}" aria-label="Pilih ${esc(l.code)}"></td>
      <td><b>${esc(l.code)}</b></td><td>${esc(l.name)}</td><td>${esc(l.area || "")}</td><td>${esc(l.divisi || "")}</td>
      <td>${l.barcodeType === "CODE128" ? "Code 128" : "QR"}</td>
      <td>${l.lastCheckAt ? `<span class="chip ${esc(l.lastStatus)}">${STATUS[l.lastStatus]}</span> <span class="small muted">${fmtDate(l.lastCheckAt)} ${fmtTime(l.lastCheckAt)}</span>` : '<span class="small muted">Belum pernah</span>'}</td>
      <td class="act"><button class="btn sm" data-add="${esc(l.code)}">+ Cetak</button> <button class="btn sm" data-ed="${esc(l.code)}">Ubah</button> <button class="btn sm danger" data-del="${esc(l.code)}">Hapus</button></td></tr>`).join("")
      : `<tr><td colspan="8" class="muted">${S.locations.length ? "Tidak ada yang cocok." : "Belum ada lokasi."}</td></tr>`;
    $("#locBody").querySelectorAll("[data-add]").forEach(b => b.onclick = () => addToSheet([locItem(b.dataset.add)]));
    $("#locBody").querySelectorAll("[data-ed]").forEach(b => b.onclick = () => edit(b.dataset.ed));
    $("#locBody").querySelectorAll("[data-del]").forEach(b => b.onclick = () => del(b.dataset.del));
    $("#chkAll").checked = false;
  }
  const locItem = code => { const l = S.locations.find(x => x.code === code); return { code: l.code, name: l.name, area: l.area || "", type: l.barcodeType || "QR" }; };
  $("#locSearch").oninput = renderTable;
  $("#chkAll").onchange = e => document.querySelectorAll(".chk").forEach(c => c.checked = e.target.checked);
  $("#btnAddSelected").onclick = () => {
    const sel = [...document.querySelectorAll(".chk:checked")].map(c => locItem(c.value));
    if (!sel.length) { toast("Centang lokasi dulu."); return; }
    addToSheet(sel);
  };

  function nextCode() {
    let n = S.locations.length + 1, c;
    do { c = "SPS-LOC-" + String(n).padStart(3, "0"); n++; } while (S.locations.some(l => l.code === c));
    return c;
  }
  function resetForm() {
    S.editing = null; $("#formTitle").textContent = "Tambah lokasi"; $("#lCode").disabled = false;
    ["lCode", "lName", "lArea", "lDiv"].forEach(i => $("#" + i).value = ""); $("#lType").value = "QR";
    $("#lCode").placeholder = nextCode(); $("#btnCancelEdit").classList.add("hidden");
  }
  function edit(code) {
    const l = S.locations.find(x => x.code === code); S.editing = code;
    $("#formTitle").textContent = "Ubah lokasi " + code; $("#lCode").value = code; $("#lCode").disabled = true;
    $("#lName").value = l.name; $("#lArea").value = l.area || ""; $("#lDiv").value = l.divisi || ""; $("#lType").value = l.barcodeType || "QR";
    $("#btnCancelEdit").classList.remove("hidden"); window.scrollTo(0, 0); $("#lName").focus();
  }
  $("#btnCancelEdit").onclick = resetForm;
  $("#btnSaveLoc").onclick = async e => {
    const code = ($("#lCode").value.trim() || $("#lCode").placeholder).toUpperCase(), name = $("#lName").value.trim();
    if (!name) { toast("Isi nama lokasi."); return; }
    if (!/^[A-Z0-9_\-./]+$/.test(code)) { toast("Kode hanya boleh huruf, angka, - _ . /"); return; }
    if (!S.editing && S.locations.some(l => l.code === code)) { toast("Kode " + code + " sudah dipakai."); return; }
    const btn = e.currentTarget; setBusy(btn, true, " Menyimpan…");
    try {
      S.locations = (await api("location_save", { admin: true, json: { code, name, area: $("#lArea").value, divisi: $("#lDiv").value, barcodeType: $("#lType").value } })).locations;
      toast("Lokasi " + code + " disimpan."); resetForm(); renderTable();
    } catch (err) { toast(err.message); } finally { setBusy(btn, false); }
  };
  async function del(code) {
    if (!confirm("Nonaktifkan lokasi " + code + "? Riwayat laporannya tetap tersimpan.")) return;
    try { S.locations = (await api("location_delete", { admin: true, json: { code } })).locations; toast("Lokasi dinonaktifkan."); renderTable(); }
    catch (err) { toast(err.message); }
  }

  /* ---------- manual ---------- */
  $("#btnAddManual").onclick = () => {
    const items = $("#manualList").value.split(/\r?\n/).map(l => l.split("|").map(s => s.trim())).filter(p => p[0])
      .map(([code, name, area]) => ({ code: code.toUpperCase(), name: name || "", area: area || "", type: "QR" }));
    if (!items.length) { toast("Isi minimal satu baris."); return; }
    addToSheet(items); $("#manualList").value = "";
  };

  /* ---------- lembar label ---------- */
  function addToSheet(items) { S.sheet.push(...items); saveSheet(); renderSheet(); toast(items.length + " label ditambahkan."); }
  function saveSheet() { ls("lblSheet", JSON.stringify(S.sheet)); }
  const opts = () => ({
    type: $("#pType").value, size: $("#pSize").value, content: $("#pContent").value, base: $("#pBaseUrl").value.trim(),
    header: $("#pHeader").value, showName: $("#pName").checked, showArea: $("#pArea").checked, showCode: $("#pCode").checked
  });
  const typeOf = (it, o) => o.type === "auto" ? (it.type || "QR") : o.type;
  function qrText(it, o) {
    if (o.content !== "url" || !o.base) return it.code;
    try { const u = new URL(o.base); u.searchParams.set("loc", it.code); return u.toString(); } catch (e) { return it.code; }
  }
  function barcodeHtml(it, o) {
    if (typeOf(it, o) === "CODE128") {
      const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      try { JsBarcode(svg, it.code, { format: "CODE128", displayValue: false, margin: 0, height: 60, width: 2 }); } catch (e) { return "<span>Kode tidak valid</span>"; }
      svg.setAttribute("preserveAspectRatio", "none"); svg.style.width = "100%"; svg.style.height = o.size === "s" ? "12mm" : o.size === "m" ? "22mm" : "34mm";
      return svg.outerHTML;
    }
    const q = qrcode(0, "M"); q.addData(qrText(it, o)); q.make();
    return q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  }
  function renderSheet() {
    const o = opts();
    $("#sheetCount").textContent = S.sheet.length ? "(" + S.sheet.length + " label)" : "";
    $("#labelSheet").innerHTML = S.sheet.length ? S.sheet.map((it, i) => `<div class="lbl size-${o.size}">
      <button class="rm" data-i="${i}" title="Hapus label" aria-label="Hapus label">×</button>
      <button class="dl" data-i="${i}" title="Unduh PNG">PNG</button>
      ${o.header ? `<div class="h">${esc(o.header)}</div>` : ""}
      <div class="bc">${barcodeHtml(it, o)}</div>
      ${o.showName && it.name ? `<div class="n">${esc(it.name)}</div>` : ""}
      ${o.showCode ? `<div class="c">${esc(it.code)}</div>` : ""}
      ${o.showArea && it.area ? `<div class="a">${esc(it.area)}</div>` : ""}</div>`).join("")
      : `<p class="muted" style="padding:20px;text-align:center;width:100%">Lembar masih kosong. Tambahkan dari tabel lokasi atau input manual.</p>`;
    $("#labelSheet").querySelectorAll(".rm").forEach(b => b.onclick = () => { S.sheet.splice(+b.dataset.i, 1); saveSheet(); renderSheet(); });
    $("#labelSheet").querySelectorAll(".dl").forEach(b => b.onclick = () => downloadPng(S.sheet[+b.dataset.i]));
  }
  ["pType", "pSize", "pContent", "pHeader", "pName", "pArea", "pCode", "pBaseUrl"].forEach(id => $("#" + id).addEventListener("input", () => {
    $("#pBaseUrl").classList.toggle("hidden", $("#pContent").value !== "url");
    ls("lblHeader", $("#pHeader").value); ls("lblBase", $("#pBaseUrl").value);
    renderSheet();
  }));
  $("#btnClear").onclick = () => { if (S.sheet.length && confirm("Kosongkan lembar cetak?")) { S.sheet = []; saveSheet(); renderSheet(); } };
  $("#btnPrint").onclick = () => { if (!S.sheet.length) { toast("Lembar masih kosong."); return; } window.print(); };

  // PNG 1 label (± 300 dpi) untuk dikirim ke percetakan
  async function downloadPng(it) {
    const o = opts(), W = 900, pad = 40;
    const c = document.createElement("canvas"), ctx = c.getContext("2d");
    const lines = [];
    if (o.showName && it.name) lines.push(["bold 54px Arial", "#111", it.name]);
    if (o.showCode) lines.push(["40px Arial", "#111", it.code]);
    if (o.showArea && it.area) lines.push(["32px Arial", "#555", it.area]);
    const isQR = typeOf(it, o) !== "CODE128";
    const bcH = isQR ? 560 : 260;
    const H = pad + (o.header ? 50 : 0) + bcH + 20 + lines.length * 62 + pad;
    c.width = W; c.height = H;
    ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = "#999"; ctx.setLineDash([12, 8]); ctx.lineWidth = 3; ctx.strokeRect(6, 6, W - 12, H - 12); ctx.setLineDash([]);
    ctx.textAlign = "center"; let y = pad;
    if (o.header) { ctx.font = "26px Arial"; ctx.fillStyle = "#555"; ctx.fillText(o.header, W / 2, y + 26); y += 50; }
    if (isQR) {
      const q = qrcode(0, "M"); q.addData(qrText(it, o)); q.make();
      const n = q.getModuleCount(), cell = Math.floor(bcH / n), off = (W - cell * n) / 2;
      ctx.fillStyle = "#000";
      for (let r = 0; r < n; r++) for (let k = 0; k < n; k++) if (q.isDark(r, k)) ctx.fillRect(off + k * cell, y + r * cell, cell, cell);
    } else {
      const bc = document.createElement("canvas");
      JsBarcode(bc, it.code, { format: "CODE128", displayValue: false, margin: 0, height: bcH, width: 4 });
      const w = Math.min(W - pad * 2, bc.width); ctx.drawImage(bc, (W - w) / 2, y, w, bcH);
    }
    y += bcH + 20;
    lines.forEach(([font, color, text]) => { ctx.font = font; ctx.fillStyle = color; ctx.fillText(text, W / 2, y + 48, W - pad * 2); y += 62; });
    const a = document.createElement("a"); a.download = "label-" + it.code.replace(/[^A-Za-z0-9_-]/g, "_") + ".png";
    a.href = c.toDataURL("image/png"); a.click();
  }

  $("#pBaseUrl").classList.toggle("hidden", $("#pContent").value !== "url");
  resetForm(); renderSheet(); load().then(resetForm);
})();
