"""
Main Runner: Simulasi Sesi Penimbangan AD-4328 (RS-232C)
--------------------------------------------------------
Menjalankan N siklus timbang, membaca data via simulasi RS-232,
lalu mencetak semua label ke PDF.

Untuk integrasi hardware nyata:
    Ganti 'read_stable()' simulator dengan pembacaan pyserial:

        import serial
        ser = serial.Serial(
            port     = 'COM3',       # sesuaikan port
            baudrate = 2400,         # F17 = [2] → 2400 bps (manual sec 14-2)
            bytesize = 7,            # 7 data bits
            parity   = serial.PARITY_EVEN,
            stopbits = 1,
            timeout  = 5
        )
        raw = ser.readline().decode('ascii').strip()
        # parse: ST,GS,+00055.5kg
"""

import time
import random
import os
from datetime import datetime
from ad4328_simulator import AD4328Simulator
from label_generator   import generate_label_sheet


# ============================================================
# KONFIGURASI
# ============================================================
JUMLAH_TIMBANG = 8          # Jumlah siklus timbang dalam sesi ini
SHIFT          = "A"        # Shift aktif
TIPE_DEFAULT   = "Waste In"
ALAS_LIST      = ["Troli", "Pallet", "Kayu"]
OPERATOR       = "Budi Santoso"
MIN_WEIGHT     = 0.001      # kg (minimum)
MAX_WEIGHT     = 999.999    # kg (maksimum)
MIN_DIV        = 0.001      # kg (3 decimal places)
OUTPUT_DIR     = os.path.join(os.path.dirname(__file__), 'outputs')


# ============================================================
# PARSER DATA SERIAL AD-4328
# ============================================================
def parse_serial_frame(raw: str) -> dict:
    """
    Parse frame Format 1 AD-4328:
        ST,GS,+00055.5kg
        UN,GS,-00010.0kg
        OL,...
    Sesuai manual section 10-4 / 14-8.
    """
    result = {
        "stable": False, "overload": False,
        "gross": 0.0,    "unit": "kg",
        "header2": "GS", "raw": raw,
    }
    try:
        parts = raw.strip().split(",")
        if len(parts) < 3:
            return result

        h1 = parts[0].strip()
        h2 = parts[1].strip()
        wd = parts[2].strip()

        result["stable"]   = (h1 == "ST")
        result["overload"] = (h1 == "OL")
        result["header2"]  = h2

        # Unit: ambil dari akhir string
        for unit in ["kg", "lb", "t"]:
            if wd.endswith(unit):
                result["unit"]  = unit
                wd = wd[:-len(unit)]
                break

        result["gross"] = float(wd)
    except Exception as e:
        result["error"] = str(e)
    return result


# ============================================================
# GENERATE KODE RECORD
# ============================================================
def make_kode(index: int) -> str:
    """Format: T-YYMMDDXXX"""
    now = datetime.now()
    return f"T-{now.strftime('%y%m%d')}{index:03d}"


# ============================================================
# SESI TIMBANG
# ============================================================
def run_session():
    print("=" * 60)
    print("  SISTEM TIMBANGAN PERSIAPAN CORRUGATED")
    print("  Simulasi Koneksi RS-232C | AD-4328 OP-04")
    print("=" * 60)
    print(f"  Sesi    : {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}")
    print(f"  Shift   : {SHIFT}")
    print(f"  Operator: {OPERATOR}")
    print(f"  Jumlah  : {JUMLAH_TIMBANG} timbang")
    print("=" * 60)

    # Inisialisasi simulator
    sim = AD4328Simulator(
        min_weight=MIN_WEIGHT,
        max_weight=MAX_WEIGHT,
        min_div=MIN_DIV,
        unit="kg",
        noise=0.03,
    )
    sim.start()
    time.sleep(0.3)  # Warmup

    records = []

    for i in range(1, JUMLAH_TIMBANG + 1):
        print(f"\n[{i:02d}/{JUMLAH_TIMBANG}] Menempatkan beban...")

        # Pilih alas acak
        alas = random.choice(ALAS_LIST)

        # Tare sesuai alas
        tare_weights = {"Kayu": 2.5, "Troli": 8.0, "Pallet": 15.0}
        tare_val = tare_weights.get(alas, 0.0) + random.gauss(0, 0.1)

        # Simulasi: set tare dulu (kosong + alas)
        sim.place_load(tare_val)
        time.sleep(0.5)
        data_tare = sim.read_stable(timeout=5.0)
        sim.tare()  # Tekan TARE

        # Letakkan beban utama
        gross_target = round(random.uniform(MIN_WEIGHT, MAX_WEIGHT), 3)
        sim.place_load(gross_target + tare_val)

        print(f"         Target berat  : {gross_target:.1f} kg")
        print(f"         Alas          : {alas} ({tare_val:.1f} kg)")
        print(f"         Menunggu stabilisasi...")

        # Baca hingga stabil (sesuai CF6 settling detection)
        data = sim.read_stable(timeout=10.0)

        if data is None:
            print(f"         ⚠ Timeout! Timbang gagal.")
            sim.remove_load()
            continue

        # Parse frame (simulasi parsing serial nyata)
        parsed = parse_serial_frame(data["raw"])

        if parsed.get("overload"):
            print(f"         ✖ OVERLOAD! Lewati.")
            sim.remove_load()
            continue

        # Pilih tipe acak untuk testing
        tipe_list = ["Waste In", "Waste Out", "Produksi", "Bahan Baku"]
        tipe = random.choice(tipe_list)

        record = {
            "weight"     : round(data["gross"], 3),
            "tare"       : round(tare_val, 3),
            "net"        : round(data["gross"] - tare_val, 3),
            "unit"       : data["unit"],
            "stable"     : data["stable"],
            "tanggal"    : data["ts"].strftime("%d/%m/%Y"),
            "kode"       : make_kode(i),
            "shift"      : SHIFT,
            "alas"       : alas,
            "tipe"       : tipe,
            "keterangan" : f"Batch {i:03d} - {tipe}",
            "operator"   : OPERATOR,
            "ts"         : data["ts"],
            "raw"        : data["raw"],
        }
        records.append(record)

        status = "✔ STABIL" if data["stable"] else "~ Tidak Stabil"
        print(f"         Gross         : {record['weight']:.3f} kg")
        print(f"         Net           : {record['net']:.3f} kg")
        print(f"         Status        : {status}")
        print(f"         Frame RS-232  : {data['raw']}")
        print(f"         Kode          : {record['kode']}")

        # Simulasi angkat beban
        sim.remove_load()
        time.sleep(0.3)

    sim.stop()

    print(f"\n{'='*60}")
    print(f"  Total berhasil: {len(records)} dari {JUMLAH_TIMBANG} timbang")

    if not records:
        print("  Tidak ada data. Selesai.")
        return

    # Generate PDF label
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    ts_str = datetime.now().strftime("%Y%m%d_%H%M%S")
    pdf_path = os.path.join(OUTPUT_DIR, f"label_timbangan_{ts_str}.pdf")

    print(f"\n  Membuat file label PDF...")
    generate_label_sheet(records, pdf_path, cols=2, rows=4)
    print(f"  ✔ Label tersimpan: {pdf_path}")

    # Ringkasan
    total_gross = sum(r["weight"] for r in records)
    total_net   = sum(r["net"]    for r in records)
    print(f"\n  RINGKASAN SESI")
    print(f"  {'Kode':<18} {'Gross':>12} {'Net':>12} {'Alas':<8} {'Tipe'}")
    print(f"  {'-'*60}")
    for r in records:
        print(f"  {r['kode']:<18} {r['weight']:>11.3f}kg {r['net']:>11.3f}kg "
              f"{r['alas']:<8} {r['tipe']}")
    print(f"  {'-'*60}")
    print(f"  {'TOTAL':<18} {total_gross:>11.3f}kg {total_net:>11.3f}kg")
    print(f"{'='*60}\n")

    return pdf_path


if __name__ == "__main__":
    run_session()
