<?php
$dataFile = 'invoice_data.json';
$savedData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($dataFile, json_encode($_POST, JSON_PRETTY_PRINT));
    echo "<script>alert('Invoice Saved Successfully!'); window.location.href='invoice.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice Generator</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f2f7ff;
      padding: 20px;
    }

    .invoice-box {
      background: white;
      max-width: 1000px;
      margin: auto;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
      color: #333;
    }

    h1 {
      text-align: center;
      color: #246ee9;
    }

    input, textarea {
      width: 100%;
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 10px;
      margin-bottom: 10px;
      box-sizing: border-box;
    }

    label {
      font-weight: 600;
      display: block;
      margin-bottom: 4px;
      color: #333;
    }

    .two-col {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }

    .two-col > div {
      flex: 1 1 45%;
      min-width: 300px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    table, th, td {
      border: 1px solid #ddd;
    }

    th {
      background-color: #eef3fc;
      padding: 10px;
    }

    td {
      padding: 10px;
      text-align: center;
    }

    .add-item {
      margin-top: 10px;
      background-color: #246ee9;
      color: white;
      padding: 8px 12px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }

    .summary {
      text-align: right;
      margin-top: 20px;
      font-size: 18px;
    }

    .download-btn, .save-btn {
      background-color: #246ee9;
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 20px;
      display: inline-block;
    }

    .terms {
      margin-top: 30px;
      font-size: 14px;
      color: #555;
      background-color: #f9f9f9;
      padding: 15px;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div class="invoice-box" id="invoice">
  <h1>INVOICE</h1>
  <form method="post" id="invoiceForm">
    <div class="two-col">
      <div>
        <label>Invoice No</label>
        <input type="text" name="invoice_no" value="<?= $savedData['invoice_no'] ?? 'INV-001' ?>">

        <label>Invoice Date</label>
        <input type="date" name="invoice_date" value="<?= $savedData['invoice_date'] ?? '' ?>">

        <label>Payable To (Your Business Name)</label>
        <input type="text" name="payable_to" value="<?= $savedData['payable_to'] ?? '' ?>">
      </div>

      <div>
        <label>Customer Name & Address</label>
        <textarea name="bill_to" rows="5"><?= $savedData['bill_to'] ?? '' ?></textarea>

        <label>Customer GSTIN</label>
        <input type="text" name="gstin" value="<?= $savedData['gstin'] ?? '' ?>">
      </div>
    </div>

    <table id="item-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Rate</th>
          <th>CGST %</th>
          <th>SGST %</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody id="items">
        <tr>
          <td><input type="text" name="items[][name]"></td>
          <td><input type="number" name="items[][qty]" value="1"></td>
          <td><input type="number" name="items[][rate]" value="0"></td>
          <td><input type="number" name="items[][cgst]" value="9"></td>
          <td><input type="number" name="items[][sgst]" value="9"></td>
          <td><input type="text" class="item-total" readonly></td>
        </tr>
      </tbody>
    </table>

    <button type="button" class="add-item" onclick="addRow()">+ Add Item</button>

    <div class="summary">
      <strong>Grand Total: ₹ <span id="grand-total">0.00</span></strong>
    </div>

    <div class="terms">
      <strong>Terms & Conditions:</strong><br>
      - Goods once sold will not be taken back or exchanged.<br>
      - Please make the payment within 15 days.<br>
      - Late payments will attract a 2% monthly interest.<br>
      - Warranty as per manufacturer policy only.<br>
      - Terms and conditions are fixed and cannot be altered.
    </div>

    <br>
    <button type="submit" class="save-btn">Save Invoice</button>
    <button type="button" class="download-btn" onclick="downloadPDF()">Download PDF</button>
  </form>
</div>

<script>
  function addRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td><input type="text" name="items[][name]"></td>
      <td><input type="number" name="items[][qty]" value="1"></td>
      <td><input type="number" name="items[][rate]" value="0"></td>
      <td><input type="number" name="items[][cgst]" value="9"></td>
      <td><input type="number" name="items[][sgst]" value="9"></td>
      <td><input type="text" class="item-total" readonly></td>
    `;
    document.getElementById('items').appendChild(row);
    attachListeners();
  }

  function attachListeners() {
    document.querySelectorAll('#item-table tbody tr').forEach(row => {
      const qty = row.querySelector('input[name$="[qty]"]');
      const rate = row.querySelector('input[name$="[rate]"]');
      const cgst = row.querySelector('input[name$="[cgst]"]');
      const sgst = row.querySelector('input[name$="[sgst]"]');
      const total = row.querySelector('.item-total');

      function updateAmount() {
        const q = parseFloat(qty.value) || 0;
        const r = parseFloat(rate.value) || 0;
        const cg = parseFloat(cgst.value) || 0;
        const sg = parseFloat(sgst.value) || 0;
        const base = q * r;
        const tax = base * ((cg + sg) / 100);
        total.value = (base + tax).toFixed(2);
        updateTotal();
      }

      qty.oninput = rate.oninput = cgst.oninput = sgst.oninput = updateAmount;
      updateAmount();
    });
  }

  function updateTotal() {
    let total = 0;
    document.querySelectorAll('.item-total').forEach(input => {
      total += parseFloat(input.value) || 0;
    });
    document.getElementById('grand-total').innerText = total.toFixed(2);
  }

  function downloadPDF() {
    const invoiceBox = document.getElementById("invoice");

    html2canvas(invoiceBox).then(canvas => {
      const imgData = canvas.toDataURL("image/png");
      const pdf = new jspdf.jsPDF("p", "pt", "a4");
      const imgProps = pdf.getImageProperties(imgData);
      const pdfWidth = pdf.internal.pageSize.getWidth();
      const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
      pdf.addImage(imgData, "PNG", 0, 0, pdfWidth, pdfHeight);
      pdf.save("invoice.pdf");
    });
  }

  attachListeners();
</script>

</body>
</html>
