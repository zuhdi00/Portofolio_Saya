# Verifikasi Data SLC: SLC/2606/00599

## Cara Menggunakan Query

1. Buka SQL Server Management Studio (SSMS)
2. Koneksikan ke database `dbSopanusa` di server `spsdmz2`
3. Buka file `query_example_slc.sql`
4. Jalankan query (F5)
5. Lihat hasil di tab "Results"

## Expected Output

### A. MASTER ORDER (tbSC)
```
cNoSc              | dTanggal   | Customer | Item    | Order Qty | cStatus | nToleransi
SLC/2606/00599     | 2026-06-23 | PT ABC   | Produk X| 1000      | OPEN    | 5
```
**Penjelasan**: SLC ini adalah order untuk 1000 unit dari PT ABC

---

### B. TOTAL STB BRUTO (tbStbBJ)

**Summary:**
```
cNoSc              | Jumlah Record | Total STB Qty | Tgl Dibuat Awal | Tgl Dibuat Akhir
SLC/2606/00599     | 5             | 1000          | 2026-06-20      | 2026-06-23
```

**Detail Per STB:**
```
No STB              | Tgl Buat   | Qty  | Keterangan
STB/2606/00001      | 2026-06-20 | 200  | Batch 1
STB/2606/00002      | 2026-06-21 | 300  | Batch 2
STB/2606/00003      | 2026-06-22 | 250  | Batch 3
STB/2606/00004      | 2026-06-23 | 150  | Batch 4
STB/2606/00005      | 2026-06-23 | 100  | Batch 5
```
**Penjelasan**: Total 1000 unit STB dibuat dalam 5 batch

---

### C. STOCK TERSIMPAN (tbDtStockDtl)

**Summary:**
```
cNoSc              | Jumlah Record | Total Stock Hold | Tgl Entry
SLC/2606/00599     | 2             | 50               | 2026-06-22
```

**Detail Stock Hold:**
```
No BAST      | Rak    | Stock Hold | Tgl Entry
BAST/001     | RAK-A1 | 30         | 2026-06-22
BAST/002     | RAK-B2 | 20         | 2026-06-23
```
**Penjelasan**: 50 unit sedang di-hold di gudang (belum siap kirim)

---

### D. TOTAL PENGIRIMAN (tbSRJ + tbSRJDtl)

**Summary:**
```
SLC         | Jumlah SRJ | Total Qty Dikirim | SRJ Awal   | SRJ Akhir
SLC/2606/00599 | 3       | 900               | 2026-06-21 | 2026-06-23
```

**Detail Per SRJ:**
```
No SRJ          | SLC Detail     | SLC Header     | Qty Kirim | Tgl SRJ    | Keterangan
SRJ/2606/0001   | SLC/2606/00599 | SLC/2606/00599 | 400       | 2026-06-21 | Pengiriman 1
SRJ/2606/0002   | SLC/2606/00599 | SLC/2606/00599 | 350       | 2026-06-22 | Pengiriman 2
SRJ/2606/0003   | SLC/2606/00599 | SLC/2606/00599 | 150       | 2026-06-23 | Pengiriman 3
```
**Penjelasan**: 900 unit sudah dikirim dalam 3 kali pengiriman

---

### E. RINGKASAN PERHITUNGAN FINAL

```
① STB Bruto          1000  (Dari tbStbBJ - total semua batch)
② Stock Hold           50  (Dari tbDtStockDtl - stock di-hold)
③ STB Aktif          950  (1000 - 50 = STB yang siap kirim)
④ Total Pengiriman   900  (Dari tbSRJ - sudah dikirim)
⑤ Sisa STB            50  (950 - 900 = STB belum dikirim)
⑥ Total Order       1000  (Dari tbSC.nQty - order/quota)
⑦ Sisa Order         50  (1000 - 950 = Order belum ada STB)
```

---

## Interpretasi Hasil

| Metrik | Nilai | Interpretasi |
|--------|-------|--------------|
| **STB Aktif** | 950 | Dari 1000 unit STB, ada 50 yang di-hold, jadi 950 siap kirim |
| **Sisa STB** | 50 | Ada 50 unit STB yang belum dikirim (masih tersimpan) |
| **Sisa Order** | 50 | Ada 50 unit order yang belum ada STB-nya (perlu dibuat STB baru) |

---

## Catatan Penting

### Hubungan Tabel:

```
tbSC (Master Order)
  ↓
  ├─→ tbStbBJ (STB yang dibuat)
  │     ↓
  │     └─→ tbDtStockDtl (Stock yang di-hold)
  │
  └─→ tbSRJ (Pengiriman)
        ↓
        └─→ tbSRJDtl (Detail pengiriman)
```

### Hubungan SLC di SRJ:
- **tbSRJ.cNoSC** = SLC di level header (biasanya dari order)
- **tbSRJDtl.cNoScDtl** = SLC di level detail (bisa berbeda dari header)
- **Query menggunakan COALESCE** untuk prioritas: gunakan `cNoScDtl` jika ada, kalau tidak ada gunakan `cNoSC`

### Validasi:
✅ **Order Terpenuhi** = Total Order ≤ STB Aktif  
⚠️ **Order Belum Terpenuhi** = Total Order > STB Aktif (ada Sisa Order)  
✅ **Semua Dikirim** = Sisa STB = 0  
⚠️ **Ada Sisa Kirim** = Sisa STB > 0
