"""
AD-4328 RS-232 Simulator & Label Generator
Mensimulasikan data stream dari timbangan A&D AD-4328 via OP-04 (RS-232C)
Format data: ST,GS,+00367.0kg<CR><LF>  (berdasarkan manual section 14-8)
"""

import random
import time
import threading
import queue
from datetime import datetime


# ============================================================
# SIMULATOR: Meniru output serial AD-4328 (OP-04 RS-232C)
# Format sesuai manual section 10-4 / 14-8:
#   Header1: ST=Stable, UN=Unstable, OL=OverLoad
#   Header2: GS=Gross, NT=Net, TR=Tare, PT=Preset Tare
#   Weight : +00055.5 (8 digit termasuk desimal)
#   Unit   : kg
#   Term   : <CR><LF>
# ============================================================

class AD4328Simulator:
    """
    Simulasi AD-4328 dengan OP-04 (RS-232C).
    Menghasilkan data sesuai Format 1 (CF12=0):
        ST,GS,+00055.5kg\r\n
    """

    def __init__(self, min_weight=0.001, max_weight=999.999,
                 min_div=0.001, unit="kg", noise=0.05):
        self.min_weight = min_weight
        self.max_weight = max_weight
        self.min_div    = min_div
        self.unit       = unit
        self.noise      = noise
        self._target    = 0.0
        self._current   = 0.0
        self._stable    = False
        self._tare      = 0.0
        self._running   = False
        self._queue     = queue.Queue()
        self._lock      = threading.Lock()

    def _round_to_div(self, value):
        """Bulatkan ke minimum division (CF sesuai setting)."""
        return round(round(value / self.min_div) * self.min_div, 9)

    def _format_weight(self, value):
        """Format sesuai pengaturan division, mis. +00055.500"""
        sign = "+" if value >= 0 else "-"
        abs_val = abs(value)
        # Tentukan desimal berdasarkan min_div
        decimals = max(0, -int(round(
            __import__('math').log10(self.min_div)
        ))) if self.min_div < 1 else 0
        # field width based on max magnitude (integer digits) + decimal point + decimals
        int_digits = len(str(int(self.max_weight)))
        width = int_digits + 1 + decimals
        formatted = f"{abs_val:0{width}.{decimals}f}"
        return sign + formatted

    def _build_frame(self, stable, gross, net):
        """Buat frame serial sesuai Format 1 AD-4328."""
        h1 = "ST" if stable else "UN"
        h2 = "GS"
        w  = self._format_weight(gross)
        return f"{h1},{h2},{w}{self.unit}\r\n"

    def _simulate_loop(self):
        """Thread: simulasi settling timbangan ke target baru."""
        while self._running:
            with self._lock:
                target  = self._target
                current = self._current
                tare    = self._tare

            # Settling: gerakkan current mendekati target dengan noise
            diff    = target - current
            step    = diff * 0.6 + random.gauss(0, self.noise)
            current = self._round_to_div(current + step)
            # Clamp current within physical bounds (no negative readings)
            if current < 0.0:
                current = 0.0
            if current > self.max_weight:
                current = self.max_weight
            stable  = abs(diff) < self.min_div * 2

            with self._lock:
                self._current = current
                self._stable  = stable

            gross = current
            # Compute net and ensure it's not negative (user requested non-negative values)
            net   = self._round_to_div(current - tare)
            if net < 0.0:
                net = 0.0
            frame = self._build_frame(stable, gross, net)
            self._queue.put({
                "raw"    : frame.strip(),
                "stable" : stable,
                "gross"  : gross,
                "net"    : net,
                "tare"   : tare,
                "unit"   : self.unit,
                "ts"     : datetime.now(),
            })
            time.sleep(0.1)   # AD-4328: ~10 readings/sec (manual spec)

    def start(self):
        self._running = True
        t = threading.Thread(target=self._simulate_loop, daemon=True)
        t.start()

    def stop(self):
        self._running = False

    def place_load(self, weight=None):
        """Letakkan beban (random jika weight=None)."""
        if weight is None:
            # generate with simulator division precision
            raw = random.uniform(self.min_weight, self.max_weight)
            weight = self._round_to_div(raw)
        with self._lock:
            self._target = weight
        return weight

    def remove_load(self):
        """Angkat beban."""
        with self._lock:
            self._target = 0.0

    def tare(self):
        """Tekan tombol TARE."""
        with self._lock:
            self._tare = self._current

    def zero(self):
        """Tekan tombol ZERO."""
        with self._lock:
            self._tare   = 0.0
            self._target = 0.0

    def read(self, timeout=5.0):
        """Baca satu frame dari buffer serial (blocking)."""
        try:
            return self._queue.get(timeout=timeout)
        except queue.Empty:
            return None

    def read_stable(self, timeout=10.0):
        """Tunggu hingga baca data STABLE."""
        deadline = time.time() + timeout
        while time.time() < deadline:
            data = self.read(timeout=0.5)
            if data and data["stable"]:
                return data
        return None
