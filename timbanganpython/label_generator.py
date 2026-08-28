"""
Label Generator - Timbangan Persiapan Corrugated
Menghasilkan label PDF dari data timbangan AD-4328
"""

import os
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import textwrap

# Register a better font if available; fallback to Helvetica
try:
    pdfmetrics.registerFont(TTFont('Inter', os.path.join(os.path.dirname(__file__), 'fonts', 'Inter-Regular.ttf')))
    DEFAULT_FONT = 'Inter'
except Exception:
    DEFAULT_FONT = 'Helvetica'


# Ukuran label (mm) - bisa disesuaikan dengan printer label
LABEL_W = 100 * mm
LABEL_H = 70  * mm

# Warna tema
COLOR_HEADER  = colors.HexColor("#0D4F6E")
COLOR_ACCENT  = colors.HexColor("#1A6B8A")
COLOR_TEXT    = colors.HexColor("#1A202C")
COLOR_MUTED   = colors.HexColor("#6B7A8D")
COLOR_WHITE   = colors.white
COLOR_LIGHT   = colors.HexColor("#EDF2F7")
COLOR_OK      = colors.HexColor("#27AE60")
COLOR_WARN    = colors.HexColor("#F39C12")
COLOR_BORDER  = colors.HexColor("#CBD5E0")


def draw_label(c: canvas.Canvas, x: float, y: float,
               data: dict, label_no: int = 1):
    """
    Gambar satu label timbangan pada posisi (x, y).

    data dict keys:
        weight      : float   - berat gross (kg)
        tare        : float   - berat tare (kg)
        net         : float   - berat net (kg)
        unit        : str     - "kg"
        stable      : bool    - status stabil
        tanggal     : str     - tanggal timbang
        kode        : str     - kode record (T-XXXXXXXX)
        shift       : str     - "A" / "B" / "C"
        alas        : str     - "Kayu" / "Troli" / "Pallet"
        tipe        : str     - "Waste In" / "Produksi" / dll
        keterangan  : str     - keterangan opsional
        operator    : str     - nama operator
    """
    w = LABEL_W
    h = LABEL_H

    # === BACKGROUND ===
    c.setFillColor(COLOR_WHITE)
    c.roundRect(x, y, w, h, 3*mm, fill=1, stroke=0)

    # === BORDER ===
    c.setStrokeColor(COLOR_BORDER)
    c.setLineWidth(0.5)
    c.roundRect(x, y, w, h, 3*mm, fill=0, stroke=1)

    # === HEADER STRIP ===
    c.setFillColor(COLOR_HEADER)
    c.roundRect(x, y + h - 14*mm, w, 14*mm, 3*mm, fill=1, stroke=0)
    # Tutup sudut bawah header agar rata
    c.rect(x, y + h - 14*mm, w, 6*mm, fill=1, stroke=0)

    # Judul
    c.setFillColor(COLOR_WHITE)
    c.setFont(DEFAULT_FONT + '-Bold' if DEFAULT_FONT != 'Helvetica' else 'Helvetica-Bold', 9)
    c.drawString(x + 4*mm, y + h - 6*mm, "LABEL TIMBANGAN")
    c.setFont(DEFAULT_FONT, 6.5)
    c.drawString(x + 4*mm, y + h - 10*mm, "Persiapan Corrugated")

    # Kode di kanan header
    c.setFont(DEFAULT_FONT + '-Bold' if DEFAULT_FONT != 'Helvetica' else 'Helvetica-Bold', 7)
    kode = data.get("kode", "-")
    c.drawRightString(x + w - 4*mm, y + h - 6*mm, kode)
    c.setFont(DEFAULT_FONT, 6)
    c.drawRightString(x + w - 4*mm, y + h - 10*mm,
                      data.get("tanggal", "-"))

    # === BERAT DISPLAY ===
    berat_y = y + h - 14*mm - 18*mm
    c.setFillColor(COLOR_LIGHT)
    c.roundRect(x + 3*mm, berat_y, 42*mm, 16*mm, 2*mm, fill=1, stroke=0)
    c.setStrokeColor(COLOR_ACCENT)
    c.setLineWidth(1.2)
    c.roundRect(x + 3*mm, berat_y, 42*mm, 16*mm, 2*mm, fill=0, stroke=1)

    c.setFillColor(COLOR_MUTED)
    c.setFont(DEFAULT_FONT, 5.5)
    c.drawCentredString(x + 24*mm, berat_y + 13*mm, "BERAT GROSS")

    weight_str = f"{data.get('weight', 0):.3f}"
    unit_str   = data.get("unit", "kg")
    c.setFillColor(COLOR_HEADER)
    # larger, clearer weight
    c.setFont(DEFAULT_FONT + '-Bold' if DEFAULT_FONT != 'Helvetica' else 'Helvetica-Bold', 20)
    # draw number right-aligned inside weight box
    c.drawRightString(x + 40*mm, berat_y + 5*mm, weight_str)
    c.setFont(DEFAULT_FONT + '-Bold' if DEFAULT_FONT != 'Helvetica' else 'Helvetica-Bold', 9)
    c.drawString(x + 41*mm, berat_y + 6*mm, unit_str)

    # Status stable / unstable
    status_col = COLOR_OK if data.get("stable", True) else COLOR_WARN
    status_lbl = "STABIL" if data.get("stable", True) else "TIDAK STABIL"
    c.setFillColor(status_col)
    c.roundRect(x + 3*mm, berat_y - 5*mm, 20*mm, 4*mm, 1*mm, fill=1, stroke=0)
    c.setFillColor(COLOR_WHITE)
    c.setFont("Helvetica-Bold", 5)
    c.drawCentredString(x + 13*mm, berat_y - 3*mm, status_lbl)

    # Net & Tare
    c.setFillColor(COLOR_MUTED)
    c.setFont(DEFAULT_FONT, 5.5)
    net_val  = data.get("net",  0)
    tare_val = data.get("tare", 0)
    # separate lines for clarity
    c.drawString(x + 25*mm, berat_y - 1.5*mm, f"Net: {net_val:.3f} {unit_str}")
    c.drawString(x + 25*mm, berat_y - 5.5*mm, f"Tare: {tare_val:.3f} {unit_str}")

    # === INFO PANEL KANAN ===
    info_x = x + 50*mm
    info_y = y + h - 14*mm - 4*mm

    def info_row(label, value, iy, bold_val=False):
        c.setFillColor(COLOR_MUTED)
        c.setFont(DEFAULT_FONT, 5.5)
        c.drawString(info_x, iy, label)
        c.setFillColor(COLOR_TEXT)
        c.setFont((DEFAULT_FONT + '-Bold') if bold_val and DEFAULT_FONT != 'Helvetica' else ('Helvetica-Bold' if bold_val else DEFAULT_FONT), 6)
        c.drawString(info_x + 20*mm, iy, str(value))

    info_row("Shift",      f"Shift {data.get('shift', '-')}",   info_y - 0*mm)
    info_row("Alas",       data.get("alas", "-"),                info_y - 5*mm)
    info_row("Tipe",       data.get("tipe", "-"),                info_y - 10*mm)
    info_row("Operator",   data.get("operator", "-"),            info_y - 15*mm)
    info_row("Label No.",  f"#{label_no:04d}",                   info_y - 20*mm, True)

    # === KETERANGAN ===
    ket = data.get("keterangan", "")
    if ket:
        # Expand keterangan box slightly and wrap text into two lines
        box_h = 12*mm
        c.setFillColor(COLOR_LIGHT)
        c.rect(x + 3*mm, y + 2*mm, w - 6*mm, box_h, fill=1, stroke=0)
        c.setFillColor(COLOR_MUTED)
        c.setFont(DEFAULT_FONT, 5)
        c.drawString(x + 4*mm, y + 12*mm - 2*mm, "Keterangan:")
        c.setFillColor(COLOR_TEXT)
        c.setFont(DEFAULT_FONT, 6)
        # Wrap teks menjadi beberapa baris
        wrapped = textwrap.wrap(ket, width=42)
        for i, line in enumerate(wrapped[:2]):
            c.drawString(x + 4*mm, y + 8*mm - i*4*mm, line)

    # === DIVIDER ===
    c.setStrokeColor(COLOR_BORDER)
    c.setLineWidth(0.3)
    c.line(x + 47*mm,
           y + h - 14*mm - 1*mm,
           x + 47*mm,
           y + 11*mm)

    # === FOOTER ===
    c.setFillColor(COLOR_LIGHT)
    c.rect(x, y, w, 10*mm, fill=1, stroke=0)
    c.setStrokeColor(COLOR_BORDER)
    c.setLineWidth(0.3)
    c.line(x, y + 10*mm, x + w, y + 10*mm)

    c.setFillColor(COLOR_MUTED)
    c.setFont("Helvetica", 5)
    ts = data.get("ts", datetime.now()).strftime("%d/%m/%Y %H:%M:%S")
    c.drawString(x + 3*mm, y + 6.5*mm, f"Waktu: {ts}")
    c.drawString(x + 3*mm, y + 3*mm,   "AD-4328 RS-232C | Persiapan Corr")
    c.setFont("Helvetica-Bold", 5)
    c.drawRightString(x + w - 3*mm, y + 5*mm,
                      f"Label #{label_no:04d}")


def generate_label_sheet(records: list, output_path: str,
                          cols: int = 2, rows: int = 4):
    """
    Buat file PDF berisi banyak label dari daftar records.
    records : list of dict (setiap item = satu timbangan)
    """
    margin_x = 15 * mm
    margin_y = 18 * mm
    gap_x    = 8  * mm
    gap_y    = 8  * mm

    page_w, page_h = A4
    c = canvas.Canvas(output_path, pagesize=A4)
    c.setTitle("Label Timbangan Persiapan Corr")

    idx = 0
    page_num = 1

    while idx < len(records):
        # Header halaman
        c.setFillColor(COLOR_HEADER)
        c.rect(0, page_h - 18*mm, page_w, 18*mm, fill=1, stroke=0)
        c.setFillColor(COLOR_WHITE)
        c.setFont("Helvetica-Bold", 11)
        c.drawString(15*mm, page_h - 10*mm, "LABEL TIMBANGAN — PERSIAPAN CORRUGATED")
        c.setFont("Helvetica", 7)
        c.drawRightString(page_w - 15*mm, page_h - 10*mm,
                          f"Dicetak: {datetime.now().strftime('%d/%m/%Y %H:%M')}  |  Hal. {page_num}")

        start_y = page_h - 18*mm - margin_y

        for row in range(rows):
            for col in range(cols):
                if idx >= len(records):
                    break
                lx = margin_x + col * (LABEL_W + gap_x)
                # position the top of the label at start_y - row*(LABEL_H+gap)
                ly = start_y - row * (LABEL_H + gap_y) - LABEL_H
                draw_label(c, lx, ly, records[idx], label_no=idx + 1)
                idx += 1

        # Footer halaman
        c.setFillColor(COLOR_MUTED)
        c.setFont("Helvetica", 6)
        c.drawCentredString(page_w / 2, 10*mm,
                            f"Halaman {page_num}  |  Sistem Timbangan Persiapan Corr  |  AD-4328")

        if idx < len(records):
            c.showPage()
            page_num += 1

    c.save()
    return output_path
