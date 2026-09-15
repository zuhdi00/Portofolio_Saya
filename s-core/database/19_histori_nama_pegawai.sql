/* Tambah kolom nama_pegawai di histori_pegawai
   agar nama tetap tercatat walau data pegawai berubah/terhapus */
USE dbHR;
GO
IF COL_LENGTH('dbo.histori_pegawai','nama_pegawai') IS NULL
BEGIN
    ALTER TABLE dbo.histori_pegawai ADD nama_pegawai NVARCHAR(128) NULL;
    PRINT '>> kolom nama_pegawai dibuat';
END
ELSE PRINT '>> nama_pegawai sudah ada';
GO

/* isi nama untuk histori yang sudah ada (dari tabel pegawai) */
UPDATE h
SET h.nama_pegawai = p.nama_peg
FROM dbo.histori_pegawai h
JOIN dbo.pegawai p ON p.id_peg = h.pegawai_id
WHERE h.nama_pegawai IS NULL;
PRINT '>> nama pegawai lama diisi';
GO
