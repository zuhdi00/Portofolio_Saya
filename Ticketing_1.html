<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Ticketing System - PT. Supracor Sejahtera</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #667eea;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: #1e3c72;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .logo {
            display: inline-block;
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .logo-text {
            font-size: 36px;
            font-weight: bold;
            color: #dc2626;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-content {
            padding: 40px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3c72;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #2a5298;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .required {
            color: #dc2626;
        }

        input[type="text"],
        input[type="date"],
        input[type="email"],
        input[type="tel"],
        input[type="file"],
        select,
        textarea {
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2a5298;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            color: white;
        }

        .btn-generate {
            background: #667eea;
            flex: 1;
        }

        .btn-generate:hover {
            background: #5568d3;
        }

        .btn-submit {
            background: #10b981;
            flex: 2;
        }

        .btn-submit:hover {
            background: #059669;
        }

        .btn-print {
            background: #f59e0b;
            flex: 1;
        }

        .btn-print:hover {
            background: #d97706;
        }

        .btn-reset {
            background: #ef4444;
            flex: 1;
        }

        .btn-reset:hover {
            background: #dc2626;
        }

        .signature-box {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
        }

        .signature-box input {
            margin: 20px 0;
            text-align: center;
            width: 80%;
        }

        .signature-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }

        .signature-line {
            height: 60px;
            border-bottom: 1px solid #cbd5e1;
            margin: 10px auto;
            width: 80%;
        }

        .status-box {
            background: #fef3c7;
            padding: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .footer {
            background: #1f2937;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 12px;
        }

        .footer p {
            margin: 5px 0;
        }

        .ticket-display {
            background: #3b82f6;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 20px;
            display: none;
        }

        .success-message {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                max-width: 100%;
            }
            .button-group {
                display: none;
            }
            .btn-generate {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .form-content {
                padding: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-text">SPS</div>
            </div>
            <h1>PT. SUPRACOR SEJAHTERA</h1>
            <p>IT Support Request Form - Department EDP</p>
            <p style="font-size: 12px; margin-top: 5px;">Formulir Permintaan Bantuan IT</p>
        </div>

        <div class="form-content">
            <div id="successMessage" class="success-message">
                Ticket berhasil dibuat! Nomor ticket Anda telah digenerate.
            </div>

            <form id="ticketForm">
                <div class="section">
                    <div class="section-title">INFORMASI TICKET</div>
                    <div id="ticketDisplay" class="ticket-display"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nomor Ticket <span class="required">*</span></label>
                            <input type="text" id="ticketNumber" name="ticketNumber" readonly placeholder="Klik Generate Ticket">
                        </div>
                        <div class="form-group">
                            <label>Tanggal <span class="required">*</span></label>
                            <input type="date" id="date" name="date" required>
                        </div>
                    </div>
                    <button type="button" class="btn-generate" onclick="generateTicket()">
                        Generate Nomor Ticket
                    </button>
                </div>

                <div class="section">
                    <div class="section-title">INFORMASI USER</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required placeholder="Masukkan nama lengkap">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required placeholder="nama@supracor.com">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Departemen <span class="required">*</span></label>
                            <select id="department" name="department" required>
                                <option value="">-- Pilih Departemen --</option>
                                <option value="Accounting">Accounting</option>
                                <option value="Finance">Finance</option>
                                <option value="HRD">Human Resource Development</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Sales">Sales</option>
                                <option value="Production">Production</option>
                                <option value="Warehouse">Warehouse</option>
                                <option value="PPIC">PPIC</option>
                                <option value="QC">Quality Control</option>
                                <option value="Purchasing">Purchasing</option>
                                <option value="EDP">EDP/IT</option>
                                <option value="GA">General Affairs</option>
                                <option value="BOD">Board of Director</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>No. Extension</label>
                            <input type="tel" id="extension" name="extension" placeholder="Ext. 123">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Lokasi <span class="required">*</span></label>
                            <input type="text" id="location" name="location" required placeholder="Gedung / Lantai / Ruangan">
                        </div>
                        <div class="form-group">
                            <label>No. Telepon/HP</label>
                            <input type="tel" id="phone" name="phone" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">DETAIL PERMINTAAN</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori <span class="required">*</span></label>
                            <select id="category" name="category" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Hardware">Hardware (PC, Laptop, Mouse, Keyboard)</option>
                                <option value="Software">Software (Aplikasi, Program, Update)</option>
                                <option value="Network">Network / Internet</option>
                                <option value="Email">Email / Outlook</option>
                                <option value="Printer">Printer / Scanner</option>
                                <option value="Telephone">Telephone / PABX</option>
                                <option value="Access">User Access / Permission</option>
                                <option value="Application">Aplikasi / System Internal</option>
                                <option value="Database">Database</option>
                                <option value="Backup">Backup dan Recovery</option>
                                <option value="Other">Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Prioritas <span class="required">*</span></label>
                            <select id="priority" name="priority" required>
                                <option value="">-- Pilih Prioritas --</option>
                                <option value="Low">Low - Tidak Mendesak</option>
                                <option value="Medium">Medium - Normal</option>
                                <option value="High">High - Mendesak</option>
                                <option value="Critical">Critical - Sangat Mendesak</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subjek Permasalahan <span class="required">*</span></label>
                        <input type="text" id="subject" name="subject" required placeholder="Ringkasan singkat permasalahan">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi Detail <span class="required">*</span></label>
                        <textarea id="description" name="description" required placeholder="Jelaskan permasalahan secara detail:
- Kapan terjadi?
- Apa yang sedang dilakukan?
- Apakah ada pesan error?
- Sudah coba restart?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attachment / Screenshot (Optional)</label>
                        <input type="file" id="attachment" name="attachment" accept="image/*,.pdf,.doc,.docx" multiple>
                        <small style="color: #64748b; display: block; margin-top: 5px;">Upload screenshot atau file pendukung (Max 5MB per file)</small>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">VERIFIKASI DAN TANDA TANGAN</div>
                    <div class="form-row">
                        <div class="signature-box">
                            <p style="font-weight: bold; margin-bottom: 10px;">Diminta Oleh (User)</p>
                            <input type="text" id="requestedBy" name="requestedBy" placeholder="Nama Lengkap">
                            <div class="signature-line"></div>
                            <p class="signature-label">Tanda Tangan dan Nama Jelas</p>
                        </div>
                        <div class="signature-box">
                            <p style="font-weight: bold; margin-bottom: 10px;">Diterima Oleh (IT Staff)</p>
                            <input type="text" id="receivedBy" name="receivedBy" placeholder="Nama IT Staff">
                            <div class="signature-line"></div>
                            <p class="signature-label">Tanda Tangan dan Nama Jelas</p>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">STATUS TICKET</div>
                    <div class="status-box">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>Status Saat Ini</label>
                            <select id="status" name="status">
                                <option value="Open">Open - Menunggu</option>
                                <option value="In Progress">In Progress - Sedang Dikerjakan</option>
                                <option value="Pending">Pending - Ditunda</option>
                                <option value="Resolved">Resolved - Selesai</option>
                                <option value="Closed">Closed - Ditutup</option>
                            </select>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #64748b; margin-top: 10px;">
                            <p><strong>Catatan:</strong></p>
                            <p>Form ini untuk keperluan internal</p>
                            <p>PT. Supracor Sejahtera</p>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">Submit Ticket</button>
                    <button type="button" class="btn-print" onclick="window.print()">Print Form</button>
                    <button type="button" class="btn-reset" onclick="resetForm()">Reset Form</button>
                </div>
            </form>
        </div>

        <div class="footer">
            <p><strong>2025 PT. SUPRACOR SEJAHTERA</strong></p>
            <p>Departemen EDP - IT Support | Contact: it.support@supracor.com</p>
            <p style="margin-top: 10px;">Field bertanda <span style="color: #dc2626;">*</span> wajib diisi</p>
        </div>
    </div>

    <script>
        document.getElementById('date').valueAsDate = new Date();

        function generateTicket() {
            var date = new Date();
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            var random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            var ticketNum = 'SPS-' + year + month + day + '-' + random;
            
            document.getElementById('ticketNumber').value = ticketNum;
            document.getElementById('ticketDisplay').textContent = 'Ticket Number: ' + ticketNum;
            document.getElementById('ticketDisplay').style.display = 'block';
            
            var successMsg = document.getElementById('successMessage');
            successMsg.style.display = 'block';
            setTimeout(function() {
                successMsg.style.display = 'none';
            }, 3000);
        }

        document.getElementById('ticketForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!document.getElementById('ticketNumber').value) {
                alert('Silakan generate nomor ticket terlebih dahulu!');
                return;
            }

            var formData = {
                ticketNumber: document.getElementById('ticketNumber').value,
                date: document.getElementById('date').value,
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                department: document.getElementById('department').value,
                extension: document.getElementById('extension').value,
                location: document.getElementById('location').value,
                phone: document.getElementById('phone').value,
                category: document.getElementById('category').value,
                priority: document.getElementById('priority').value,
                subject: document.getElementById('subject').value,
                description: document.getElementById('description').value,
                requestedBy: document.getElementById('requestedBy').value,
                receivedBy: document.getElementById('receivedBy').value,
                status: document.getElementById('status').value
            };

            console.log('Ticket Data:', formData);

            alert('Ticket berhasil disubmit!\n\nNomor Ticket: ' + formData.ticketNumber + '\n\nTim IT kami akan segera menindaklanjuti permintaan Anda. Terima kasih!');

            if (confirm('Apakah Anda ingin mencetak form ini?')) {
                window.print();
            }
        });

        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form? Semua data akan hilang.')) {
                document.getElementById('ticketForm').reset();
                document.getElementById('ticketNumber').value = '';
                document.getElementById('ticketDisplay').style.display = 'none';
                document.getElementById('date').valueAsDate = new Date();
            }
        }
    </script>
</body>
</html>