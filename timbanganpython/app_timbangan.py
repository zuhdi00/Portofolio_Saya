import sys
import os
import threading
import time
from datetime import datetime

from PyQt5 import QtWidgets, uic, QtCore, QtGui
from PyQt5.QtCore import QTimer, QDate
from PyQt5.QtWidgets import QMessageBox

from ad4328_simulator import AD4328Simulator
from label_generator import generate_label_sheet


# Output directory for generated PDFs (relative to project)
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "outputs")


def make_kode(index: int) -> str:
    now = datetime.now()
    return f"T-{now.strftime('%y%m%d')}{index:03d}"


# Prepare UI class and patch QLayout behavior before creating dialogs
UI_PATH = os.path.join(os.path.dirname(__file__), "form_timbangan.ui")
# Patch QLayout.setContentsMargins to accept QRect/QMargins when generated
# setupUi tries to pass a QRect (tooling mismatch).
_orig_setContentsMargins = QtWidgets.QLayout.setContentsMargins
def _patched_setContentsMargins(self, *args):
    if len(args) == 1:
        arg = args[0]
        if isinstance(arg, QtCore.QRect):
            _orig_setContentsMargins(self, arg.left(), arg.top(), arg.right(), arg.bottom())
            return
        if hasattr(QtCore, 'QMargins') and isinstance(arg, QtCore.QMargins):
            _orig_setContentsMargins(self, arg.left(), arg.top(), arg.right(), arg.bottom())
            return
    _orig_setContentsMargins(self, *args)
QtWidgets.QLayout.setContentsMargins = _patched_setContentsMargins

Ui_Form, Base = uic.loadUiType(UI_PATH)


class FormTimbangan(Base, Ui_Form):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setupUi(self)
        # Enable standard window Minimize/Maximize buttons on title bar
        self.setWindowFlags(self.windowFlags()
                    | QtCore.Qt.WindowMinimizeButtonHint
                    | QtCore.Qt.WindowMaximizeButtonHint
                    | QtCore.Qt.WindowCloseButtonHint)

        # Set application/window icon if available
        try:
            icon_path = os.path.join(os.path.dirname(__file__), 'SPS_Logo1.png')
            if os.path.exists(icon_path):
                ico = QtGui.QIcon(icon_path)
                if not ico.isNull():
                    self.setWindowIcon(ico)
        except Exception:
            pass

        # Simulator (3-decimal precision, wide range)
        self.sim = AD4328Simulator(min_weight=0.001, max_weight=999.999,
                       min_div=0.001, unit="kg", noise=0.05)
        self.sim.start()

        # State
        self.current_data = None
        self.records = []

        # Timer: poll simulator
        self.timer = QTimer(self)
        self.timer.timeout.connect(self.poll_simulator)
        self.timer.start(150)

        # Connect buttons
        self.btnTambah.clicked.connect(self.on_tambah)
        self.btnCetak.clicked.connect(self.on_cetak)
        self.btnSimpan.clicked.connect(self.on_simpan)

        # Init UI defaults
        self.dateEdit.setDate(QDate.currentDate())
        self.txtKode.setText(make_kode(1))

    def poll_simulator(self):
        # Pull all available frames quickly
        while True:
            data = self.sim.read(timeout=0.01)
            if not data:
                break
            self.current_data = data

        if self.current_data:
            gross = self.current_data.get("gross", 0.0)
            unit = self.current_data.get("unit", "kg")
            # Display with three decimals and comma as decimal separator
            display = f"{gross:0,.3f}".replace(',', '#').replace('.', ',').replace('#', '.')
            self.lblBeratValue.setText(display)
            self.lblBeratUnit.setText(unit)

    def get_selected_alas(self):
        if getattr(self, 'radioKayu', None) and self.radioKayu.isChecked():
            return 'Kayu'
        if getattr(self, 'radioTroli', None) and self.radioTroli.isChecked():
            return 'Troli'
        if getattr(self, 'radioPallet', None) and self.radioPallet.isChecked():
            return 'Pallet'
        return 'Troli'

    def on_tambah(self):
        """Capture current stable reading as a record."""
        if not self.current_data:
            QMessageBox.warning(self, "Info", "Tidak ada data timbangan tersedia.")
            return

        if not self.current_data.get('stable', False):
            ret = QMessageBox.question(self, "Konfirmasi",
                                       "Berat belum stabil. Simpan tetap?",
                                       QMessageBox.Yes | QMessageBox.No)
            if ret != QMessageBox.Yes:
                return

        gross = round(self.current_data.get('gross', 0.0), 3)
        alas = self.get_selected_alas()
        tare_weights = {'Kayu': 2.5, 'Troli': 8.0, 'Pallet': 15.0}
        tare = round(tare_weights.get(alas, 0.0), 3)
        net = round(gross - tare, 3)
        if net < 0.0:
            net = 0.0

        idx = len(self.records) + 1
        kode = make_kode(idx)

        record = {
            'weight': gross,
            'tare': tare,
            'net': net,
            'unit': self.current_data.get('unit', 'kg'),
            'stable': self.current_data.get('stable', True),
            'tanggal': self.current_data.get('ts').strftime('%d/%m/%Y'),
            'kode': kode,
            'shift': 'A' if self.chkA.isChecked() else ('B' if self.chkB.isChecked() else 'C'),
            'alas': alas,
            'tipe': self.cmbTipe.currentText() if hasattr(self, 'cmbTipe') else 'Waste In',
            'keterangan': self.txtKeterangan.text() if hasattr(self, 'txtKeterangan') else '',
            'operator': 'Operator',
            'ts': self.current_data.get('ts'),
            'raw': self.current_data.get('raw')
        }

        self.records.append(record)

        # Update UI fields
        self.txtKode.setText(make_kode(len(self.records) + 1))
        QMessageBox.information(self, 'Tersimpan', f"Berat {gross:.3f} kg disimpan sebagai {kode}")

    def on_cetak(self):
        if not self.records:
            QMessageBox.warning(self, 'Info', 'Tidak ada record untuk dicetak.')
            return

        os.makedirs(OUTPUT_DIR, exist_ok=True)
        ts = datetime.now().strftime('%Y%m%d_%H%M%S')
        out = os.path.join(OUTPUT_DIR, f'label_timbangan_{ts}.pdf')

        try:
            generate_label_sheet(self.records, out, cols=2, rows=4)
            QMessageBox.information(self, 'Selesai', f'Label tersimpan: {out}')
        except Exception as e:
            QMessageBox.critical(self, 'Error', f'Gagal membuat PDF:\n{e}')

    def on_simpan(self):
        # Minimal: save records to CSV next to outputs
        if not self.records:
            QMessageBox.warning(self, 'Info', 'Tidak ada data untuk disimpan.')
            return
        os.makedirs(OUTPUT_DIR, exist_ok=True)
        csv_path = os.path.join(OUTPUT_DIR, f'records_{datetime.now().strftime("%Y%m%d_%H%M%S")}.csv')
        with open(csv_path, 'w', encoding='utf-8') as f:
            f.write('kode,tanggal,weight,tare,net,alas,tipe,keterangan\n')
            for r in self.records:
                f.write(f"{r['kode']},{r['tanggal']},{r['weight']},{r['tare']},{r['net']},{r['alas']},{r['tipe']},\"{r['keterangan']}\"\n")
        QMessageBox.information(self, 'Selesai', f'Data disimpan: {csv_path}')

    def closeEvent(self, event):
        try:
            self.sim.stop()
        except Exception:
            pass
        event.accept()


def main():
    app = QtWidgets.QApplication(sys.argv)
    # set application icon (taskbar / alt-tab)
    try:
        icon_path = os.path.join(os.path.dirname(__file__), 'SPS_Logo1.png')
        if os.path.exists(icon_path):
            app.setWindowIcon(QtGui.QIcon(icon_path))
    except Exception:
        pass
    w = FormTimbangan()
    w.show()
    sys.exit(app.exec_())


if __name__ == '__main__':
    main()
