-- Recommended indexes for stbtotalv1 report performance
-- Run these in your SQL Server by a DBA or with appropriate permissions.

-- If dTanggal is stored as datetime/datetime2, index it directly:
CREATE INDEX IX_tbSC_dTanggal ON dbo.tbSC(dTanggal);

-- If dTanggal is stored as string and CONVERT(date,dTanggal) is used, prefer adding a computed persisted column and index it:
-- ALTER TABLE dbo.tbSC ADD dTanggalDate AS CONVERT(date, dTanggal) PERSISTED;
-- CREATE INDEX IX_tbSC_dTanggalDate ON dbo.tbSC(dTanggalDate);

-- Join keys
CREATE INDEX IX_tbStbBJ_cNoSc ON dbo.tbStbBJ(cNoSc);
CREATE INDEX IX_tbSRJDtl_cNoScDtl ON dbo.tbSRJDtl(cNoScDtl);
CREATE INDEX IX_tbSRJ_cNoSRJ ON dbo.tbSRJ(cNoSRJ);

-- If queries filter by status or other frequent predicates, consider including those columns
-- Example: include cStatus on tbSC to speed WHERE/ORDER/SELECT
-- CREATE INDEX IX_tbSC_dTanggal_cStatus ON dbo.tbSC(dTanggal) INCLUDE (cStatus);

-- Notes:
-- - Test each index for impact on write performance and disk space.
-- - If tables are very large, create indexes during maintenance windows.
-- - Consider statistics updates and index maintenance (REBUILD/REORGANIZE).
