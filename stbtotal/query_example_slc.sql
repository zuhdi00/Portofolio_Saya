-- ============================================================================

DECLARE @slc NVARCHAR(50) = 'SLC/2606/00599';

-- 1. Master Order (tbSC)
SELECT 'A. MASTER ORDER (tbSC)' AS [Section]
SELECT 
    cNoSc, 
    dTanggal,
    cNama AS [Customer],
    cJenis AS [Item],
    nQty AS [Order Qty],
    cStatus,
    nToleransi
FROM tbSC
WHERE cNoSc = @slc;

-- 2. Total STB Bruto (tbStbBJ)
SELECT '' AS [Spacer];
SELECT 'B. TOTAL STB BRUTO (tbStbBJ)' AS [Section]
SELECT 
    cNoSc,
    COUNT(*) AS [Jumlah Record STB],
    SUM(nQty) AS [Total STB Qty],
    MIN(dTglBuat) AS [Tgl Dibuat Awal],
    MAX(dTglBuat) AS [Tgl Dibuat Akhir]
FROM tbStbBJ
WHERE cNoSc = @slc
GROUP BY cNoSc;

-- Detail setiap STB
SELECT '  → Detail Per STB:' AS [SubSection]
SELECT 
    cNoStbBJ AS [No STB],
    dTglBuat AS [Tgl Buat],
    nQty AS [Qty],
    cKeterangan AS [Keterangan]
FROM tbStbBJ
WHERE cNoSc = @slc
ORDER BY dTglBuat DESC;

-- 3. Stock Tersimpan (tbDtStockDtl)
SELECT '' AS [Spacer];
SELECT 'C. STOCK TERSIMPAN / DI-HOLD (tbDtStockDtl)' AS [Section]
SELECT 
    cNoSc,
    COUNT(*) AS [Jumlah Record],
    SUM(ISNULL(nStock, 0)) AS [Total Stock Hold],
    MAX(dTglEntry) AS [Tgl Entry]
FROM tbDtStockDtl
WHERE cNoSc = @slc
GROUP BY cNoSc;

-- Detail stock tersimpan
SELECT '  → Detail Stock Hold:' AS [SubSection]
SELECT 
    cNoBast,
    cRak,
    nStock AS [Stock Hold],
    dTglEntry
FROM tbDtStockDtl
WHERE cNoSc = @slc
ORDER BY dTglEntry DESC;

-- 4. Total Pengiriman dari SRJ (tbSRJ + tbSRJDtl)
SELECT '' AS [Spacer];
SELECT 'D. TOTAL PENGIRIMAN (tbSRJ + tbSRJDtl)' AS [Section]
SELECT 
    COALESCE(d.cNoScDtl, s.cNoSC) AS [SLC],
    COUNT(DISTINCT s.cNoSRJ) AS [Jumlah SRJ],
    SUM(d.nQty) AS [Total Qty Dikirim],
    MIN(s.dTglSRJ) AS [SRJ Awal],
    MAX(s.dTglSRJ) AS [SRJ Akhir]
FROM tbSRJ s
INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
WHERE s.cNoSC = @slc OR d.cNoScDtl = @slc
GROUP BY COALESCE(d.cNoScDtl, s.cNoSC);

-- Detail pengiriman per SRJ
SELECT '  → Detail Per SRJ:' AS [SubSection]
SELECT 
    s.cNoSRJ AS [No SRJ],
    d.cNoScDtl AS [SLC Detail],
    s.cNoSC AS [SLC Header],
    d.nQty AS [Qty Kirim],
    s.dTglSRJ AS [Tgl SRJ],
    s.cKeterangan
FROM tbSRJ s
INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
WHERE s.cNoSC = @slc OR d.cNoScDtl = @slc
ORDER BY s.dTglSRJ DESC;

-- ============================================================================
-- RINGKASAN PERHITUNGAN
-- ============================================================================
SELECT '' AS [Spacer];
SELECT 'E. RINGKASAN PERHITUNGAN FINAL' AS [Section];

WITH calculations AS (
    -- STB
    SELECT 
        @slc AS [SLC],
        ISNULL(SUM(s.nQty), 0) AS [STB_Bruto]
    FROM tbStbBJ s
    WHERE s.cNoSc = @slc
    
    -- Stock Hold
), stock_calc AS (
    SELECT 
        ISNULL(SUM(ISNULL(d.nStock, 0)), 0) AS [Stock_Hold]
    FROM tbDtStockDtl d
    WHERE d.cNoSc = @slc
    
    -- Pengiriman
), srj_calc AS (
    SELECT 
        ISNULL(SUM(d.nQty), 0) AS [Total_SRJ]
    FROM tbSRJ s
    INNER JOIN tbSRJDtl d ON s.cNoSRJ = d.cNoSRJ
    WHERE s.cNoSC = @slc OR d.cNoScDtl = @slc
    
    -- Order
), order_calc AS (
    SELECT 
        ISNULL(nQty, 0) AS [Order_Qty]
    FROM tbSC
    WHERE cNoSc = @slc
)

SELECT 
    -- Dari tbStbBJ
    (SELECT STB_Bruto FROM calculations) AS [① STB Bruto (tbStbBJ)],
    
    -- Dari tbDtStockDtl (dikurangi)
    (SELECT Stock_Hold FROM stock_calc) AS [② Stock Hold (tbDtStockDtl)],
    
    -- Hitungan: STB Aktif
    ((SELECT STB_Bruto FROM calculations) - (SELECT Stock_Hold FROM stock_calc)) AS [③ STB Aktif (① - ②)],
    
    -- Dari tbSRJ
    (SELECT Total_SRJ FROM srj_calc) AS [④ Total Pengiriman (tbSRJ)],
    
    -- Hitungan: Sisa STB
    (((SELECT STB_Bruto FROM calculations) - (SELECT Stock_Hold FROM stock_calc)) - (SELECT Total_SRJ FROM srj_calc)) AS [⑤ Sisa STB (③ - ④)],
    
    -- Dari tbSC
    (SELECT Order_Qty FROM order_calc) AS [⑥ Total Order (tbSC)],
    
    -- Hitungan: Sisa Order
    ((SELECT Order_Qty FROM order_calc) - ((SELECT STB_Bruto FROM calculations) - (SELECT Stock_Hold FROM stock_calc))) AS [⑦ Sisa Order (⑥ - ③)];

SELECT '' AS [Spacer];
SELECT 'PENJELASAN HASIL:' AS [Info];
SELECT '
① STB Bruto     = Semua STB yang pernah dibuat untuk SLC ini (dari tbStbBJ)
② Stock Hold    = STB yang disimpan/di-hold (tidak siap kirim)
③ STB Aktif     = STB yang siap dikirim (STB Bruto - Stock Hold)
④ Pengiriman    = Jumlah yang sudah dikirim (dari tbSRJ)
⑤ Sisa STB      = STB belum dikirim (STB Aktif - Pengiriman)
⑥ Order         = Total order/quota untuk SLC ini (dari tbSC.nQty)
⑦ Sisa Order    = Order yang belum ada STB (Order - STB Aktif)
' AS [Rumus];
