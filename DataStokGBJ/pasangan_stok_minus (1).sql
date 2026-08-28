/* ============================================================================
   PT SUPRACOR SEJAHTERA — STOK MINUS YANG PASANGANNYA ADA DI NO. SC LAIN
   Dibuat : 10 Agustus 2026

   LAPORAN DARI PIC
        SLC/2605/00397  INTERBAT  (S4029001) CARTON R  FLEXO-4      -900
        SLC/2606/00551  INTERBAT  (S4029001) CARTON R  FOLDER GLUE  +900

     Customer sama, item sama persis, jumlah sama persis, tanda berlawanan.

   PENJELASANNYA
     Barangnya tidak hilang dan hitungannya tidak salah. Surat jalan ditulis
     atas nomor SC yang berbeda dari nomor SC tempat stoknya tercatat.
     Di gudang barang itu satu tumpukan; petugas mengambil 900 pc dan
     mengirimnya, tercatat di SC 2605/00397 padahal saldonya di 2606/00551.

     Perhatikan kolom proses: FLEXO-4 dan FOLDER GLUE. Satu item yang sama
     dikerjakan lewat dua jalur produksi berbeda, sehingga terbit dua nomor SC.
     Gudang tidak memisahkan fisiknya, dan memang tidak perlu.

   CATATAN PENTING
     Aturan saling tutup yang sudah terpasang hanya berlaku ANTAR TIPE di
     dalam SATU nomor SC. Kasus ini beda nomor SC, jadi tidak tertangkap.

   File ini menghitung berapa banyak stok minus yang sebenarnya terjelaskan
   oleh pola ini. HANYA MEMBACA, tidak mengubah apa pun.
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — PERIKSA KASUS YANG DILAPORKAN
   --------------------------------------------------------------------------- */
SELECT s.cNoSc, s.cNama, s.cNamabrg, s.cType AS proses, s.cNoMC,
       s.dTglStbAkhir, s.nUmur, s.nStokPc, s.cStatusData
FROM   dbo.tbStokGudangSnap s
WHERE  s.cNoSc IN ('SLC/2605/00397', 'SLC/2606/00551');

-- Rincian mutasinya, supaya kelihatan dari mana angkanya
SELECT t.cNoSc, t.cKelompok, t.nSaldoAwal, t.nStb, t.nKirim, t.nRetur, t.nStokPc
FROM   dbo.tbStokSnapTipe t
WHERE  t.cNoSc IN ('SLC/2605/00397', 'SLC/2606/00551')
ORDER  BY t.cNoSc;

-- Surat jalan Agustus untuk kedua SC tersebut
SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS cNoSc, s.cNoSRJ,
       CONVERT(VARCHAR(10), s.dTanggal, 23)  AS tgl_dokumen,
       CONVERT(VARCHAR(10), s.dTglKirim, 23) AS tgl_kirim,
       d.cNoOp, d.nQty, s.cNama
FROM   dbo.tbSRJ s INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
WHERE  RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) IN ('SLC/2605/00397', 'SLC/2606/00551')
  AND  s.dTanggal > '2026-07-31'
ORDER  BY s.dTanggal;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — SEBERAPA UMUM POLA INI?
   Tiap NO. SC yang minus dicarikan pasangannya: customer sama, item sama,
   stok positif.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#p') IS NOT NULL DROP TABLE #p;

SELECT m.cNoSc          AS sc_minus,
       m.cNama,
       m.cNamabrg,
       m.nStokPc        AS stok_minus,
       ISNULL(p.pc, 0)  AS stok_positif_seitem,
       ISNULL(p.jml, 0) AS jml_sc_pasangan,
       CASE WHEN ISNULL(p.pc,0) >= -m.nStokPc THEN 'TERTUTUP PENUH'
            WHEN ISNULL(p.pc,0) > 0           THEN 'TERTUTUP SEBAGIAN'
            ELSE                                   'TIDAK ADA PASANGAN' END AS hasil
INTO   #p
FROM   dbo.tbStokGudangSnap m
OUTER APPLY (
    SELECT SUM(x.nStokPc) AS pc, COUNT(*) AS jml
    FROM   dbo.tbStokGudangSnap x
    WHERE  x.cNoSc <> m.cNoSc
      AND  x.nStokPc > 0
      AND  RTRIM(x.cNama)    = RTRIM(m.cNama)
      AND  RTRIM(x.cNamabrg) = RTRIM(m.cNamabrg)
) p
WHERE  m.nStokPc < 0;

-- 2a. RINGKASAN. Ini angka yang menentukan.
SELECT hasil, COUNT(*) AS jml_op,
       SUM(stok_minus) AS pc_minus,
       SUM(CASE WHEN stok_positif_seitem >= -stok_minus
                THEN -stok_minus ELSE stok_positif_seitem END) AS pc_yang_bisa_ditutup
FROM   #p GROUP BY hasil ORDER BY SUM(stok_minus);

-- 2b. Total
SELECT COUNT(*) AS total_op_minus,
       SUM(stok_minus) AS total_pc_minus,
       SUM(CASE WHEN stok_positif_seitem >= -stok_minus
                THEN -stok_minus ELSE stok_positif_seitem END) AS total_bisa_ditutup,
       SUM(stok_minus) + SUM(CASE WHEN stok_positif_seitem >= -stok_minus
                THEN -stok_minus ELSE stok_positif_seitem END) AS sisa_minus_sebenarnya
FROM   #p;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — DAFTAR PASANGANNYA, SEPERTI YANG DILIHAT PIC
   Berpasangan rapi seperti contoh INTERBAT tadi.
   --------------------------------------------------------------------------- */
SELECT TOP 60
       p.cNama                AS customer,
       p.cNamabrg             AS item,
       p.sc_minus,
       p.stok_minus,
       x.cNoSc                AS sc_positif,
       x.nStokPc              AS stok_positif,
       x.cType                AS proses_positif,
       p.hasil
FROM   #p p
INNER JOIN dbo.tbStokGudangSnap x
        ON  x.nStokPc > 0
        AND RTRIM(x.cNama)    = RTRIM(p.cNama)
        AND RTRIM(x.cNamabrg) = RTRIM(p.cNamabrg)
WHERE  p.hasil <> 'TIDAK ADA PASANGAN'
ORDER  BY p.stok_minus;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — YANG BENAR-BENAR TIDAK PUNYA PASANGAN
   Ini yang perlu dicek fisik oleh gudang, bukan soal penomoran.
   --------------------------------------------------------------------------- */
SELECT TOP 30 sc_minus, cNama, cNamabrg, stok_minus
FROM   #p WHERE hasil = 'TIDAK ADA PASANGAN' ORDER BY stok_minus;

DROP TABLE #p;
GO

/* ---------------------------------------------------------------------------
   CATATAN — TIGA PILIHAN PENANGANAN, PERLU DIPUTUSKAN BERSAMA

   A. DIBETULKAN DI SUMBERNYA
      Surat jalan diralat ke nomor SC yang benar lewat modul pengiriman.
      Paling bersih, tapi perlu kesediaan bagian pengiriman dan tidak selalu
      bisa untuk dokumen yang sudah diposting.

   B. DICATAT SEBAGAI KOREKSI BERPASANGAN
      Lewat tombol Koreksi di dashboard, jenis PINDAH NO. SC:
          SLC/2605/00397   +900   "Barang diambil dari stok 2606/00551"
          SLC/2606/00551   -900   "Barang dikirim atas SJ 2605/00397"
      Riwayatnya tercatat lengkap dengan nama pengisi. Cocok untuk kasus
      yang jumlahnya tidak banyak.

   C. DASHBOARD MENAMPILKAN PER CUSTOMER + ITEM
      Angka utamanya digabung, rincian per NO. SC tetap bisa dibuka.
      Paling sedikit pekerjaan manual, dan paling mendekati cara gudang
      menghitung fisik. Tapi menyembunyikan bahwa penomorannya tidak rapi.

   Angka dari Langkah 2 menentukan pilihan mana yang masuk akal. Kalau yang
   TERTUTUP PENUH jumlahnya banyak, pilihan C paling hemat tenaga.
   --------------------------------------------------------------------------- */
