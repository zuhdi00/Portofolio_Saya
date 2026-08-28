/* ============================================================================
   PT SUPRACOR SEJAHTERA — LENGKAPI PATOKAN STOK + PERBAIKI PERHITUNGAN RETUR
   Dibuat : 05 Agustus 2026

   TEMUAN DARI HASIL UJI KEMARIN
   1. Patokan Excel baru mencakup sheet "BOX" saja. Sheet "PART+LAYER" berisi
      101 baris dengan saldo akhir 289.406 pc / 16.936,24 kg yang belum masuk.
      Inilah penyebab sebagian besar stok minus.
   2. vwReturnSrj ternyata punya kolom cNoSc dan dTgl sendiri. Selama ini retur
      dihitung lewat join ke tbSRJDtl, padahal satu surat jalan punya banyak
      baris detail — akibatnya qty retur TERGANDA sebanyak jumlah baris detail.
      Sekarang dibaca langsung dari view-nya, dan disaring pakai tanggal retur
      yang sebenarnya (dTgl), bukan tanggal surat jalan.

   Patokan setelah file ini:  BOX 776.500 + PART/LAYER 289.406 = 1.065.906 pc
   ============================================================================ */

USE dbSopanusa;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 1 — TANDAI ASAL DATA
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.tbStokGudangExcel', 'cKategori') IS NULL
    ALTER TABLE dbo.tbStokGudangExcel ADD cKategori VARCHAR(20) NULL;
GO
UPDATE dbo.tbStokGudangExcel SET cKategori = 'BOX' WHERE cKategori IS NULL;
GO

/* Satu NO. OP bisa punya stok di BOX sekaligus di PART+LAYER — ada 11 kasus,
   antara lain 2607/00940 dan 2607/00294. Keduanya stok nyata dan harus
   dijumlahkan, jadi primary key diubah jadi gabungan nomor + kategori. */
ALTER TABLE dbo.tbStokGudangExcel ALTER COLUMN cKategori VARCHAR(20) NOT NULL;
GO
IF EXISTS (SELECT 1 FROM sys.key_constraints
           WHERE name = 'PK_tbStokGudangExcel' AND parent_object_id = OBJECT_ID('dbo.tbStokGudangExcel'))
    ALTER TABLE dbo.tbStokGudangExcel DROP CONSTRAINT PK_tbStokGudangExcel;
GO
ALTER TABLE dbo.tbStokGudangExcel
    ADD CONSTRAINT PK_tbStokGudangExcel PRIMARY KEY (cNoSc, cKategori);
GO

-- Bersihkan dulu kalau file ini pernah dijalankan
DELETE FROM dbo.tbStokGudangExcel WHERE cKategori = 'PART+LAYER';
GO

/* ---------------------------------------------------------------------------
   LANGKAH 2 — TAMBAH PATOKAN DARI SHEET PART+LAYER
   --------------------------------------------------------------------------- */
INSERT INTO dbo.tbStokGudangExcel (cNoSc,cNoOpExcel,cNoScDb,cKategori,cNama,cNamabrg,dProduksiAkhir,nStokAwalPc,nStokAwalKg,nStokAkhirPc,nStokAkhirKg,dCutOff) VALUES
(N'2607/00164',N'2607/00164',N'SLC/2607/00164',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT',N'LAYER - KARTON BOX + LAYER PRINT 520 X 343 X 335 MM SW PRINT ADK','2025-02-25',10367,936.55478,10367,936.55478,'2026-08-03'),
(N'2607/00679',N'2607/00679',N'SLC/2607/00679',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT',N'LAYER KARTON BOX + LAYER PRINT 540 X 355 X 340 MM PRINT ADK','2024-12-13',6,0.58308,6,0.58308,'2026-08-03'),
(N'2607/00678',N'2607/00678',N'SLC/2607/00678',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT',N'LAYER - KARTON BOX + LAYER PRINT 600 X 400 X 335 MM PRINT ADK','2024-12-14',20,2.4396,20,2.4396,'2026-08-03'),
(N'2604/01097',N'2604/01097',N'SLC/2604/01097',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT',N'LAYER-KARTON BOX + LAYER PRINT 660 X 440 X 340 PRINT ADK',NULL,1560,234.2028,1560,234.2028,'2026-08-03'),
(N'2607/00677',N'2607/00677',N'SLC/2607/00677',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT/PRIMAYUDHA',N'LAYER PRINT 520 X 343 X 335 MM SW LOGO SRITEX','2024-12-07',1930,174.3562,1930,174.3562,'2026-08-03'),
(N'2605/00818',N'2605/00818',N'SLC/2605/00818',N'PART+LAYER',N'AGUNG JAMI'', CAHAYA HARAPAN, CV',N'LAYER Q JAMI 240 ML (NEW), BOX','2024-12-16',240,7.8816,240,7.8816,'2026-08-03'),
(N'2607/00574',N'2607/00574',N'SLC/2607/00574',N'PART+LAYER',N'ALBEXON JAYA SUKSES MAKMUR',N'PART 2L1 - TOPLES JD 1/4 RED','2024-12-11',4040,99.7072,4040,99.7072,'2026-08-03'),
(N'2607/00940',N'2607/00940',N'SLC/2607/00940',N'PART+LAYER',N'ALBEXON JAYA SUKSES MAKMUR, CV',N'PARTISI 2L1-TOPLES JD 3/4 DIAMOND',NULL,1700,66.504,3200,125.184,'2026-08-03'),
(N'2604/01185',N'2604/01185',N'SLC/2604/01185',N'PART+LAYER',N'APAC INTI CORPORA PT',N'LAYER POLOS 680 X 500 MM','2024-08-09',50,7.14,50,7.14,'2026-08-03'),
(N'2607/00150',N'2607/00150',N'SLC/2607/00150',N'PART+LAYER',N'ARGA KENCANA MULIA, CV',N'PART PENDEK-DOC SRF NEW LOGO (BOTTOM)',NULL,200,23.511,200,23.511,'2026-08-03'),
(N'2606/00774',N'2606/00774',N'SLC/2606/00774',N'PART+LAYER',N'BAMBANG HERMANTO',N'PART PANJANG BODY BOX SSF GOLD','2024-12-23',300,34.941,300,34.941,'2026-08-03'),
(N'2603/00599',N'2603/00599',N'SLC/2603/00599',N'PART+LAYER',N'BAYER INDONESIA, PT',N'LAYER - CASE - N 40 X 250 G (WATER REPALENT)','2024-12-09',4964,295.7064,4964,295.7064,'2026-08-03'),
(N'2603/00597',N'2603/00597',N'SLC/2603/00597',N'PART+LAYER',N'BAYER INDONESIA, PT',N'PART PANJANG + PART PENDEK CASE - N 12 X 1 KG 490 X 390 X 335 MM AP (WATER REPALENT)','2023-11-24',1120,128.352,1120,128.352,'2026-08-03'),
(N'2603/00600',N'2603/00600',N'SLC/2603/00600',N'PART+LAYER',N'BAYER INDONESIA, PT',N'part 1L1 - CASE - N 20 X 500 G (WATER REPALENT)',NULL,720,76.9104,720,76.9104,'2026-08-03'),
(N'2602/01006',N'2602/01006',N'SLC/2602/01006',N'PART+LAYER',N'BERKAH SUMBER TIRTA, PT',N'LAYER BOX AMDK BST 220 ML NEW 2','2024-12-12',880,28.3888,880,28.3888,'2026-08-03'),
(N'2606/00779',N'2606/00779',N'SLC/2606/00779',N'PART+LAYER',N'BERLINE FARM INDONESIA , CV',N'Part PANJANG- BODY BOX DKSP NEW PLATINUM','2025-02-15',4500,524.115,4500,524.115,'2026-08-03'),
(N'2607/00834',N'2607/00834',N'SLC/2607/00834',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER','2025-01-22',4000,96.24,4000,96.24,'2026-08-03'),
(N'2603/00937',N'2603/00937',N'SLC/2603/00937',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'layer - KARTON ROSALINDA SARDINES IN VO 125 G',NULL,953,22.92918,953,22.92918,'2026-08-03'),
(N'2603/00935',N'2603/00935',N'SLC/2603/00935',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'layer',NULL,18800,452.328,18800,452.328,'2026-08-03'),
(N'2603/00945',N'2603/00945',N'SLC/2603/00945',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,10200,245.412,10200,245.412,'2026-08-03'),
(N'2604/00248',N'2604/00248',N'SLC/2604/00248',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,640,15.9872,640,15.9872,'2026-08-03'),
(N'2604/00993',N'2604/00993',N'SLC/2604/00993',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,9000,216.54,9000,216.54,'2026-08-03'),
(N'2606/01142-P0101',N'2606/01142-P0101',N'SLC/2606/01142-P0101',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,10000,0.0,10000,240.6,'2026-08-03'),
(N'2606/01150-P0101',N'2606/01150-P0101',N'SLC/2606/01150-P0101',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,240,0.0,240,5.7744,'2026-08-03'),
(N'2607/00833',N'2607/00833',N'SLC/2607/00833',N'PART+LAYER',N'BLUE OCEAN FOODS INDONESIA , PT',N'LAYER',NULL,100,2.691,100,2.691,'2026-08-03'),
(N'2607/00161',N'2607/00161',N'SLC/2607/00161',N'PART+LAYER',N'CHAROEN POKPHAND JAYA FARM.PT',N'PART PANJANG DOC CP707 (BOTTOM)','2024-12-10',1100,130.939,1100,130.939,'2026-08-03'),
(N'2606/00856',N'2606/00856',N'SLC/2606/00856',N'PART+LAYER',N'DINAMIKA MEGATAMA CITRA.PT',N'PART PANJANG BOX DOC PRINT (BODY)','2024-12-21',1900,195.67,1900,195.67,'2026-08-03'),
(N'2607/00358',N'2607/00358',N'SLC/2607/00358',N'PART+LAYER',N'DUA JAYA SEJAHTERA, CV',N'LAYER',NULL,220,0.0,220,7.3326,'2026-08-03'),
(N'2603/00382',N'2603/00382',N'SLC/2603/00382',N'PART+LAYER',N'DUNIA KIMIA JAYA, PT',N'LAYER HIJAU 25 KG,','2024-08-19',940,62.369,940,62.369,'2026-08-03'),
(N'2511/00050',N'2511/00050',N'SLC/2511/00050',N'PART+LAYER',N'DUTA KEMAS PACKINDO, PT',N'PART 3L2',NULL,8700,134.096,8700,134.096,'2026-08-03'),
(N'2606/00135',N'2606/00135',N'SLC/2606/00135',N'PART+LAYER',N'ELODA MITRA, PT',N'LAYER CREASING-5553102023 BOX BUN MINI TRAY','2023-09-04',5608,351.29372,5608,351.29372,'2026-08-03'),
(N'2607/00743',N'2607/00743',N'SLC/2607/00743',N'PART+LAYER',N'ELODA MITRA, PT',N'LAYER BESAR- 553102020 BOX ROTI 108','2024-12-16',18230,591.1061,18230,591.1061,'2026-08-03'),
(N'2605/01143',N'2605/01143',N'SLC/2605/01143',N'PART+LAYER',N'ELYA SARI DEWI',N'PARTISI - 5L3',NULL,20,0.0,20,4.5244,'2026-08-03'),
(N'2606/01108',N'2606/01108',N'SLC/2606/01108',N'PART+LAYER',N'EMBROITEX JAYA, PT',N'LAYER 1 SET 150 X 1060 (berat set)','2025-02-26',1740,483.0588,3475,964.7295,'2026-08-03'),
(N'2606/01213',N'2606/01213',N'SLC/2606/01213',N'PART+LAYER',N'ENVIRONEER, PT',N'LAYER GELAIR AB4 + 2 LYR UK 290 X 290 X 110 MM','2024-05-08',80,5.4576,80,5.4576,'2026-08-03'),
(N'2602/00492',N'2602/00492',N'SLC/2602/00492',N'PART+LAYER',N'ETIKA DAIRIES INDONESIA, PT',N'PART PANJANG-CB PB DC 1 KG LOKAL (PT. EDI VS PT. EBI) + PARTISI','2023-08-09',3400,250.036,3400,250.036,'2026-08-03'),
(N'2506/00187',N'2506/00187',N'SLC/2506/00187',N'PART+LAYER',N'ETIKA DAIRIES INDONESIA, PT',N'PART PENDEK-CB PB DC 1 KG LOKAL (PT. EDI VS PT. EBI) + PARTISI','2023-08-10',4750,247.9975,4750,247.9975,'2026-08-03'),
(N'2607/00099',N'2607/00099',N'SLC/2607/00099',N'PART+LAYER',N'ETIKA DAIRIES INDONESIA, PT',N'PART PANJANG- CB PB DC 2.5 KG LOKAL (PT. EDI VS PT. EBI) + PARTISI','2025-03-25',11560,622.506,36520,2036.586,'2026-08-03'),
(N'2607/00083',N'2607/00083',N'SLC/2607/00083',N'PART+LAYER',N'EVANDER SEBASTIAN THOMAS',N'PART PENDEK',NULL,100,0.0,100,11.7555,'2026-08-03'),
(N'2508/00221',N'2508/00221',N'SLC/2508/00221',N'PART+LAYER',N'EXCELLENCE QUALITIES YARN. PT',N'LAYER PRINTING EQY, 460 X 460 X 335','2024-10-17',400,34.78,400,34.78,'2026-08-03');
GO

INSERT INTO dbo.tbStokGudangExcel (cNoSc,cNoOpExcel,cNoScDb,cKategori,cNama,cNamabrg,dProduksiAkhir,nStokAwalPc,nStokAwalKg,nStokAkhirPc,nStokAkhirKg,dCutOff) VALUES
(N'2606/00357',N'2606/00357',N'SLC/2606/00357',N'PART+LAYER',N'GRAMAR JAYA. PT',N'LAYER',NULL,236,0.0,236,11.8944,'2026-08-03'),
(N'2607/01093',N'2607/01093',N'SLC/2607/01093',N'PART+LAYER',N'GREATCHEMINDO SATRIA PUTRAMAS, CV',N'LAYER - KARDUS KALENG CKETZ','2025-01-23',2900,93.032,2900,93.032,'2026-08-03'),
(N'2603/00368',N'2603/00368',N'SLC/2603/00368',N'PART+LAYER',N'HERU SANTOSO',N'part Panjang- DOC SUMBER UNGGAS JAYA ABADI - (BOTTOM)','2024-05-31',1000,117.565,1000,117.565,'2026-08-03'),
(N'2606/00087',N'2606/00087',N'SLC/2606/00087',N'PART+LAYER',N'INDO JAYA PRATAMA. CV',N'LAYER',NULL,660,0.0,660,15.8796,'2026-08-03'),
(N'2601/00926',N'2601/00926',N'SLC/2601/00926',N'PART+LAYER',N'INDO JAYA PRATAMA. CV',N'LAYER AFL POLOS',NULL,4500,107.415,4500,107.415,'2026-08-03'),
(N'2605/00916',N'2605/00916',N'SLC/2605/00916',N'PART+LAYER',N'INDRAMUKTI SEGARA, PT',N'layer- SP.  SINTI 30 X 200 G (MERAH) - NEW MD + LAYER',NULL,240,5.3856,240,5.3856,'2026-08-03'),
(N'2604/01134',N'2604/01134',N'SLC/2604/01134',N'PART+LAYER',N'JANU PUTRA ABADI, PT',N'PART PANJANG-DOC PRINT JANU (JP 354) - BODY (BOTTOM)',NULL,200,23.511,200,23.511,'2026-08-03'),
(N'2606/00336',N'2606/00336',N'SLC/2606/00336',N'PART+LAYER',N'KARYA HIJAU NUSA PT',N'LAYER - BOX 500 X 90 X 210 MM','2024-12-21',200,3.66,200,3.66,'2026-08-03'),
(N'2501/01039',N'2501/01039',N'SLC/2501/01039',N'PART+LAYER',N'KEMUNING SARI TEMBAKAU, PT',N'LAYER - BOTTOM','2024-07-01',196,22.5302,196,22.5302,'2026-08-03'),
(N'2607/00294',N'2607/00294',N'SLC/2607/00294',N'PART+LAYER',N'KEONG NUSANTARA ABADI, PT',N'PART 1L2 MY JELLY SK 14 GR X 30 CUPS X 12','2024-06-11',580,71.92,580,71.92,'2026-08-03'),
(N'2607/00694',N'2607/00694',N'SLC/2607/00694',N'PART+LAYER',N'KEONG NUSANTARA ABADI, PT',N'PART 1L1 MY JELLY SK 14 GR X 5 CUPS X 60 BAGS','2025-02-21',13380,1240.0584,15120,1401.3216,'2026-08-03'),
(N'2607/00295',N'2607/00295',N'SLC/2607/00295',N'PART+LAYER',N'KEONG NUSANTARA ABADI, PT',N'layer- MY JELLY SK 14 GR X 5 CUPS X 60 BAGS + LAYER',NULL,6000,212.16,6000,212.16,'2026-08-03'),
(N'2606/01087',N'2606/01087',N'SLC/2606/01087',N'PART+LAYER',N'KEONG NUSANTARA ABADI, PT',N'SEKAT',NULL,20,0.0,20,0.5352,'2026-08-03'),
(N'2604/01350',N'2604/01350',N'SLC/2604/01350',N'PART+LAYER',N'KOPERASI PESANTREN LATANSA PONDOK MODERN GONTOR',N'LAYER',NULL,530,17.4052,530,17.4052,'2026-08-03'),
(N'2605/01089',N'2605/01089',N'SLC/2605/01089',N'PART+LAYER',N'MAAN GHODAQO SHIDDIQ LESTARI, PT',N'LAYER - MAAQO 240 ML NEW DESIGN','2025-03-04',365,11.9866,365,11.9866,'2026-08-03'),
(N'2602/00732',N'2602/00732',N'SLC/2602/00732',N'PART+LAYER',N'MAYANGSARI.PT',N'LAYER TOP',NULL,3000,360.0,3000,360.0,'2026-08-03'),
(N'2607/00053',N'2607/00053',N'SLC/2607/00053',N'PART+LAYER',N'MISSOURI, CV',N'PART PANJANG-BOX DOC HM 623 (BOTTOM)',NULL,-400,-47.022,-400,-47.022,'2026-08-03'),
(N'2606/00460',N'2606/00460',N'SLC/2606/00460',N'PART+LAYER',N'MUH. RIZKI MAULANA',N'LAYER-VA 2',NULL,60,0.0,60,1.9704,'2026-08-03'),
(N'2601/00089',N'2601/00089',N'SLC/2601/00089',N'PART+LAYER',N'PETRO KIMIA KAYAKU, PT',N'INNER - BODY CASE SATURN-D 2 KG U/ 20 KG + PARTISI','2024-12-05',1780,245.284,1780,245.284,'2026-08-03'),
(N'2602/00523',N'2602/00523',N'SLC/2602/00523',N'PART+LAYER',N'PETRO KIMIA KAYAKU, PT',N'LAYER BOX KAYABAS 250 + 100 ML TOPI DALAM DISPLAY',NULL,2400,217.176,2400,217.176,'2026-08-03'),
(N'2512/00424',N'2512/00424',N'SLC/2512/00424',N'PART+LAYER',N'PETRO KIMIA KAYAKU, PT',N'INNER BODY CASE DIAZINON 10 GR',NULL,110,16.2767,110,16.2767,'2026-08-03'),
(N'2605/00927',N'2605/00927',N'SLC/2605/00927',N'PART+LAYER',N'PETRO KIMIA KAYAKU, PT',N'LY  DIAZINON 10 GR',NULL,320,12.4736,320,12.4736,'2026-08-03'),
(N'2607/00749',N'2607/00749',N'SLC/2607/00749',N'PART+LAYER',N'PURA BARUTAMA, PT',N'PART PANJANG 1L1-BOX NUTRILON 400 GR + DEVIDER TYPE A2 - DC','2024-12-11',380,50.2246,380,50.2246,'2026-08-03'),
(N'2607/00488',N'2607/00488',N'SLC/2607/00488',N'PART+LAYER',N'PUTRA JADI JAYA',N'LAYER',NULL,160,33.7616,160,33.7616,'2026-08-03'),
(N'2605/00628',N'2605/00628',N'SLC/2605/00628',N'PART+LAYER',N'PUTRI NATALIA ANGGRAENI',N'LAYER',NULL,312,14.59188,312,14.59188,'2026-08-03'),
(N'2603/00979',N'2603/00979',N'SLC/2603/00979',N'PART+LAYER',N'R SUGENG W',N'PART PANJANG',NULL,200,13.759,200,13.759,'2026-08-03'),
(N'2605/01176',N'2605/01176',N'SLC/2605/01176',N'PART+LAYER',N'SATWA UTAMA RAYA,PT',N'PART-PANJANG DOC SR707 (BOTTOM)','2023-07-08',700,82.2885,700,82.2885,'2026-08-03'),
(N'2603/00613',N'2603/00613',N'SLC/2603/00613',N'PART+LAYER',N'SIANTAR TOP TBK, PT',N'DUS LAYER GEMEZ EXTRA POLYBAG DAIN DAISO','2024-12-23',50,5.141,50,5.141,'2026-08-03'),
(N'2601/00561',N'2601/00561',N'SLC/2601/00561',N'PART+LAYER',N'SIANTAR TOP TBK, PT',N'LAYER GEMEZ ENAAK EXTRA CHICKEN BBQ 30 GR POLYBAG 5IN1 EXPORT DAIN KOREA',NULL,2600,192.634,2600,192.634,'2026-08-03'),
(N'2605/00433',N'2605/00433',N'SLC/2605/00433',N'PART+LAYER',N'SIANTAR TOP TBK, PT',N'LAYER GEMEZ ENAAK SMOKED BBQ JOIN SPICY LEVEL 3 POLYBAG 4IN1 DAIN KOREA',NULL,150,6.0855,150,6.0855,'2026-08-03'),
(N'2510/00571',N'2510/00571',N'SLC/2510/00571',N'PART+LAYER',N'SINAR KEMASAN TERANG, PT',N'SEKAT LAYER X 4 (CREASING)-TUMBU',NULL,1200,251.328,1200,251.328,'2026-08-03'),
(N'2601/00930',N'2601/00930',N'SLC/2601/00930',N'PART+LAYER',N'SUKSES MITRA INDO,PT',N'PART 6L3-B1004 & B1004G CANGKIR DAN TUTUP',NULL,884,160.33992,884,160.33992,'2026-08-03'),
(N'2604/00471',N'2604/00471',N'SLC/2604/00471',N'PART+LAYER',N'SUMBER MUTIARA SAMUDRA. PT',N'LAYER',NULL,2420,73.5438,2420,73.5438,'2026-08-03'),
(N'2604/01451',N'2604/01451',N'SLC/2604/01451',N'PART+LAYER',N'SUMBER MUTIARA SAMUDRA. PT',N'LAYER',NULL,21100,507.666,23800,572.628,'2026-08-03'),
(N'2507/00445',N'2507/00445',N'SLC/2507/00445',N'PART+LAYER',N'SUMBERTAMAN KERAMIKAINDUSTRI, PT',N'layer polos -OB ADL 2130 J ISI 48','2024-12-21',900,75.906,900,75.906,'2026-08-03'),
(N'2606/01274',N'2606/01274',N'SLC/2606/01274',N'PART+LAYER',N'WONOKOYO JAYA CORPORINDO, PT',N'PART PENDEK- BODY BOX WONCHICK SNI BROILER NEW','2022-08-10',650,69.472,650,69.472,'2026-08-03'),
(N'2606/00090',N'2606/00090',N'SLC/2606/00090',N'PART+LAYER',N'WONOKOYO JAYA CORPORINDO, PT',N'PART PANJANG-BODY BOX LAYER JANTAN SNI',NULL,800,98.472,800,98.472,'2026-08-03'),
(N'2607/01170',N'2607/01170',N'SLC/2607/01170',N'PART+LAYER',N'ADIKENCANA MAHKOTABUANA, PT',N'LAYER UK. 63CM X 113CM 3 PLY',NULL,3000,0.0,3000,923.7,'2026-08-03'),
(N'2502/00617',N'2502/00617',N'SLC/2502/00617',N'PART+LAYER',N'WONOKOYO/ LITA YULITA',N'LAYER SINGLE FACE (EF)',NULL,8100,377.784,8100,377.784,'2026-08-03'),
(N'2411/00902',N'2411/00902',N'SLC/2411/00902',N'PART+LAYER',N'MIFTAKHU ROHMAD',N'LAYER PUYUH (TOP) POLOS',NULL,14210,696.8584,14210,696.8584,'2026-08-03');
GO

INSERT INTO dbo.tbStokGudangExcel (cNoSc,cNoOpExcel,cNoScDb,cKategori,cNama,cNamabrg,dProduksiAkhir,nStokAwalPc,nStokAwalKg,nStokAkhirPc,nStokAkhirKg,dCutOff) VALUES
(N'2505/00936',N'2505/00936',N'SLC/2505/00936',N'PART+LAYER',N'HERU SANTOSO',N'LAYER EF 630 X 470','2024-05-31',15400,1288.21,15400,1288.21,'2026-08-03');
GO

-- Kosongkan cNoScDb yang tidak ada di tbStbBJ supaya ketahuan
UPDATE e SET e.cNoScDb = NULL
FROM   dbo.tbStokGudangExcel e
WHERE  e.cKategori = 'PART+LAYER'
  AND  NOT EXISTS (SELECT 1 FROM dbo.tbStbBJ b WHERE RTRIM(b.cNoSc) = e.cNoScDb);
GO

-- 2a. HASIL. Target: BOX 291 baris / 776.500 pc, PART+LAYER 81 baris / 289.406 pc
SELECT cKategori,
       COUNT(*)                                             AS baris,
       SUM(CASE WHEN cNoScDb IS NOT NULL THEN 1 ELSE 0 END) AS ketemu,
       SUM(CASE WHEN cNoScDb IS NULL     THEN 1 ELSE 0 END) AS belum_ketemu,
       SUM(nStokAkhirPc)                                    AS total_pc,
       SUM(nStokAkhirKg)                                    AS total_kg
FROM   dbo.tbStokGudangExcel
GROUP  BY cKategori
ORDER  BY cKategori;

-- 2b. Yang belum ketemu di database, untuk dicek gudang
SELECT cKategori, cNoOpExcel, cNama, cNamabrg, nStokAkhirPc
FROM   dbo.tbStokGudangExcel WHERE cNoScDb IS NULL ORDER BY nStokAkhirPc DESC;
GO

-- 2c. NO. OP yang punya stok di dua kategori sekaligus (seharusnya 11 baris).
--     Prosedur menjumlahkan keduanya, jadi ini bukan duplikat yang perlu dibuang.
SELECT cNoSc, COUNT(*) AS jml_kategori, SUM(nStokAkhirPc) AS total_pc,
       MAX(CASE WHEN cKategori = 'BOX'        THEN nStokAkhirPc END) AS pc_box,
       MAX(CASE WHEN cKategori = 'PART+LAYER' THEN nStokAkhirPc END) AS pc_part
FROM   dbo.tbStokGudangExcel
GROUP  BY cNoSc HAVING COUNT(*) > 1
ORDER  BY total_pc DESC;
GO

/* ---------------------------------------------------------------------------
   LANGKAH 3 — PERBAIKI PERHITUNGAN RETUR DI PROSEDUR
   Hanya blok retur yang berubah. Bagian lain sama persis.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.spRefreshStokGudang') IS NOT NULL DROP PROCEDURE dbo.spRefreshStokGudang;
GO

CREATE PROCEDURE dbo.spRefreshStokGudang
    @Sumber    VARCHAR(30) = 'MANUAL',
    @HariTrend INT         = 30
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;   -- tidak mengunci input gudang

    DECLARE @Mulai DATETIME = GETDATE(), @Id INT, @CutOff DATE;
    INSERT INTO dbo.tbStokGudangLog (dMulai, cStatus, cSumber) VALUES (@Mulai, 'JALAN', @Sumber);
    SET @Id = SCOPE_IDENTITY();

    BEGIN TRY
        SELECT @CutOff = MAX(dCutOff) FROM dbo.tbStokGudangExcel;
        IF @CutOff IS NULL
            THROW 50010, 'tbStokGudangExcel kosong. Isi dulu saldo gudang dari file Excel.', 1;

        CREATE TABLE #agg (cNoSc VARCHAR(30) PRIMARY KEY, nStok INT NOT NULL DEFAULT 0);

        /* 1. Saldo awal dari file Excel gudang (patokan utama) */
        INSERT INTO #agg (cNoSc, nStok)
        SELECT RTRIM(cNoScDb), SUM(nStokAkhirPc)
        FROM   dbo.tbStokGudangExcel
        WHERE  cNoScDb IS NOT NULL
        GROUP  BY RTRIM(cNoScDb);

        /* 2. NO. OP baru yang mulai berproduksi setelah cut-off */
        INSERT INTO #agg (cNoSc, nStok)
        SELECT DISTINCT RTRIM(b.cNoSc), 0
        FROM   dbo.tbStbBJ b
        WHERE  b.dTanggal > @CutOff
          AND  b.cNoSc IS NOT NULL AND LTRIM(RTRIM(b.cNoSc)) <> ''
          AND  NOT EXISTS (SELECT 1 FROM #agg a WHERE a.cNoSc = RTRIM(b.cNoSc));

        /* 3. Tambah setoran barang jadi setelah cut-off */
        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nQty,0)) AS q
                    FROM   dbo.tbStbBJ WHERE dTanggal > @CutOff
                    GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.cNoSc;

        /* 4. Kurangi pengiriman setelah cut-off */
        UPDATE a SET a.nStok = a.nStok - x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(COALESCE(d.cNoScDtl, s.cNoSC)) AS sc, SUM(ISNULL(d.nQty,0)) AS q
                    FROM   dbo.tbSRJ s
                    INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = s.cNoSRJ
                    WHERE  s.dTanggal > @CutOff
                    GROUP  BY RTRIM(COALESCE(d.cNoScDtl, s.cNoSC))) x ON x.sc = a.cNoSc;

        /* 5. Tambah retur setelah cut-off.
              Dibaca LANGSUNG dari vwReturnSrj memakai kolom cNoSc dan dTgl
              miliknya sendiri. Versi lama menjoin ke tbSRJDtl, padahal satu
              surat jalan punya banyak baris detail, sehingga qty retur
              terganda sebanyak jumlah baris detail surat jalan itu. */
        UPDATE a SET a.nStok = a.nStok + x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(rv.cNoSc) AS sc, SUM(ISNULL(rv.nQty,0)) AS q
                    FROM   dbo.vwReturnSrj rv
                    WHERE  rv.dTgl > @CutOff
                      AND  rv.cNoSc IS NOT NULL AND LTRIM(RTRIM(rv.cNoSc)) <> ''
                    GROUP  BY RTRIM(rv.cNoSc)) x ON x.sc = a.cNoSc;

        /* 6. Kurangi penyesuaian modul gudang setelah cut-off */
        UPDATE a SET a.nStok = a.nStok - x.q
        FROM   #agg a
        INNER JOIN (SELECT RTRIM(cNoSc) AS sc, SUM(ISNULL(nStock,0)) AS q
                    FROM   dbo.tbDtStockDtl WHERE userdate > @CutOff
                    GROUP  BY RTRIM(cNoSc)) x ON x.sc = a.cNoSc;

        /* 7. Buang yang habis. Stok minus dipertahankan sebagai penanda
              data yang perlu diperiksa gudang. */
        DELETE FROM #agg WHERE nStok = 0;

        BEGIN TRANSACTION;

        TRUNCATE TABLE dbo.tbStokGudangSnap;

        INSERT INTO dbo.tbStokGudangSnap
              (cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel)
        SELECT cNoSc, cKodeCust, cNama, cNamabrg, cNoMC, cNamaSales, cType, cRak,
               nBerat, dTglStbAkhir, nUmur, nStokPc, nStokKg, cKeterangan, lDariExcel
        FROM (
            SELECT LEFT(a.cNoSc, 30)                                            AS cNoSc,
                   LEFT(ISNULL(d.cKodeCust, ''), 50)                            AS cKodeCust,
                   LEFT(ISNULL(NULLIF(d.cNama, ''), ISNULL(e.cNama, '')), 300)   AS cNama,
                   LEFT(ISNULL(NULLIF(d.cNamabrg, ''), ISNULL(e.cNamabrg,'')), 500) AS cNamabrg,
                   LEFT(ISNULL(d.cNoMC, ''), 100)      AS cNoMC,
                   LEFT(ISNULL(d.cNamaSales, ''), 200) AS cNamaSales,
                   LEFT(ISNULL(d.cType, ''), 100)      AS cType,
                   LEFT(ISNULL(d.cRak, ''), 100)       AS cRak,
                   ISNULL(d.nberat, 0)                                          AS nBerat,
                   CAST(d.dTanggal AS DATE)                                     AS dTglStbAkhir,
                   DATEDIFF(day, ISNULL(d.dTanggal, e.dProduksiAkhir), CAST(GETDATE() AS DATE)) AS nUmur,
                   a.nStok                                                      AS nStokPc,
                   CAST(a.nStok * ISNULL(d.nberat, 0) AS DECIMAL(18,3))         AS nStokKg,
                   LEFT(e.cKeterangan, 255)                                     AS cKeterangan,
                   CASE WHEN e.cNoSc IS NULL THEN 0 ELSE 1 END                  AS lDariExcel,
                   ROW_NUMBER() OVER (PARTITION BY a.cNoSc ORDER BY (SELECT 1)) AS rn
            FROM      #agg a
            OUTER APPLY (
                SELECT TOP 1 x.cNoSc, x.cNama, x.cNamabrg, x.cKeterangan, x.dProduksiAkhir
                FROM   dbo.tbStokGudangExcel x
                WHERE  RTRIM(x.cNoScDb) = a.cNoSc
                ORDER  BY x.nStokAkhirPc DESC
            ) e
            OUTER APPLY (
                SELECT TOP 1 s.cKodeCust, s.cNama, s.cNamabrg, s.cNoMC, s.cNamaSales,
                             s.cType, s.cRak, s.nberat, s.dTanggal
                FROM   dbo.tbStbBJ s
                WHERE  s.cNoSc = a.cNoSc
                ORDER  BY s.dTanggal DESC, s.cNoSTB DESC
            ) d
        ) src
        WHERE src.rn = 1;

        /* 8. Mutasi harian untuk grafik dashboard */
        DECLARE @Awal  DATE = DATEADD(day, -(@HariTrend - 1), CAST(GETDATE() AS DATE));
        DECLARE @Akhir DATE = DATEADD(day, 1, CAST(GETDATE() AS DATE));

        TRUNCATE TABLE dbo.tbStokGudangMutasi;

        ;WITH berat_sc AS (
            SELECT RTRIM(cNoSc) AS cNoSc, MAX(ISNULL(nberat,0)) AS nberat
            FROM   dbo.tbStbBJ WHERE dTanggal >= DATEADD(year, -2, @Awal)
            GROUP  BY RTRIM(cNoSc)
        ),
        stb_h AS (
            SELECT CAST(dTanggal AS DATE) AS d,
                   SUM(ISNULL(nQty,0)) AS pc, SUM(ISNULL(nQtyKg,0)) AS kg
            FROM   dbo.tbStbBJ
            WHERE  dTanggal >= @Awal AND dTanggal < @Akhir
            GROUP  BY CAST(dTanggal AS DATE)
        ),
        krm_h AS (
            SELECT CAST(s.dTanggal AS DATE) AS d,
                   SUM(ISNULL(dt.nQty,0)) AS pc,
                   SUM(ISNULL(dt.nQty,0) * ISNULL(bs.nberat,0)) AS kg
            FROM   dbo.tbSRJ s
            INNER JOIN dbo.tbSRJDtl dt ON dt.cNoSRJ = s.cNoSRJ
            LEFT  JOIN berat_sc     bs ON bs.cNoSc  = RTRIM(COALESCE(dt.cNoScDtl, s.cNoSC))
            WHERE  s.dTanggal >= @Awal AND s.dTanggal < @Akhir
            GROUP  BY CAST(s.dTanggal AS DATE)
        ),
        kalender AS (
            SELECT @Awal AS d
            UNION ALL SELECT DATEADD(day, 1, d) FROM kalender WHERE d < CAST(GETDATE() AS DATE)
        )
        INSERT INTO dbo.tbStokGudangMutasi (dTanggal, nStbPc, nStbKg, nKirimPc, nKirimKg)
        SELECT k.d, ISNULL(sh.pc,0), CAST(ISNULL(sh.kg,0) AS DECIMAL(18,3)),
                    ISNULL(kh.pc,0), CAST(ISNULL(kh.kg,0) AS DECIMAL(18,3))
        FROM      kalender k
        LEFT JOIN stb_h sh ON sh.d = k.d
        LEFT JOIN krm_h kh ON kh.d = k.d
        OPTION (MAXRECURSION 366);

        COMMIT TRANSACTION;

        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               nJmlOp   = (SELECT COUNT(*) FROM dbo.tbStokGudangSnap),
               nTotalPc = (SELECT ISNULL(SUM(nStokPc),0) FROM dbo.tbStokGudangSnap),
               cStatus  = 'SUKSES',
               cPesan   = 'Cut-off saldo Excel: ' + CONVERT(VARCHAR(10), @CutOff, 23)
        WHERE  nId = @Id;

        DROP TABLE #agg;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        UPDATE dbo.tbStokGudangLog
        SET    dSelesai = GETDATE(),
               nDetik   = DATEDIFF(second, @Mulai, GETDATE()),
               cStatus  = 'GAGAL',
               cPesan   = ERROR_MESSAGE()
        WHERE  nId = @Id;
        THROW;
    END CATCH
END
GO

/* ---------------------------------------------------------------------------
   LANGKAH 4 — JALANKAN & PERIKSA
   --------------------------------------------------------------------------- */
EXEC dbo.spRefreshStokGudang @Sumber = 'PATOKANLENGKAP';
GO

-- 4a. Harus SUKSES. nTotalPc sekarang di kisaran 1.065.906 + produksi baru.
SELECT TOP 5 nId, dMulai, nDetik, nJmlOp, nTotalPc, cStatus, cSumber, cPesan
FROM   dbo.tbStokGudangLog ORDER BY nId DESC;

-- 4b. Stok minus harus jauh berkurang dari 17
SELECT COUNT(*) AS jml_op,
       SUM(CASE WHEN nStokPc > 0 THEN nStokPc ELSE 0 END) AS total_pc,
       SUM(CASE WHEN nStokPc < 0 THEN 1 ELSE 0 END)       AS op_negatif,
       SUM(CASE WHEN nStokPc < 0 THEN nStokPc ELSE 0 END) AS pc_negatif
FROM   dbo.tbStokGudangSnap;

-- 4c. Sisa stok minus + penjelasannya, untuk ditindaklanjuti gudang
SELECT s.cNoSc, s.cNama, s.cNamabrg, s.nStokPc,
       ISNULL(e.cKategori, 'TIDAK ADA DI EXCEL') AS asal_patokan,
       ISNULL(e.nStokAkhirPc, 0)                 AS saldo_excel,
       k.kirim_stlh_cutoff, b.stb_stlh_cutoff
FROM   dbo.tbStokGudangSnap s
LEFT JOIN dbo.tbStokGudangExcel e ON e.cNoScDb = s.cNoSc
OUTER APPLY (SELECT SUM(ISNULL(d.nQty,0)) AS kirim_stlh_cutoff
             FROM dbo.tbSRJ j INNER JOIN dbo.tbSRJDtl d ON d.cNoSRJ = j.cNoSRJ
             WHERE RTRIM(COALESCE(d.cNoScDtl, j.cNoSC)) = s.cNoSc
               AND j.dTanggal > '2026-08-03') k
OUTER APPLY (SELECT SUM(ISNULL(nQty,0)) AS stb_stlh_cutoff
             FROM dbo.tbStbBJ WHERE RTRIM(cNoSc) = s.cNoSc
               AND dTanggal > '2026-08-03') b
WHERE  s.nStokPc < 0
ORDER  BY s.nStokPc;
GO

/* ---------------------------------------------------------------------------
   CATATAN — STOK MINUS YANG MASIH TERSISA
   Sisa minus biasanya barang yang keluar sebelum 03 Agustus tapi surat
   jalannya baru dibuat tanggal 04-05 Agustus, atau kategori barang yang
   memang tidak dicatat di file Excel gudang (contoh: item "SHEET - ...").
   Ini soal pencatatan, bukan soal query. Daftar dari 4c bisa langsung
   diserahkan ke gudang untuk dicocokkan fisik.
   --------------------------------------------------------------------------- */
