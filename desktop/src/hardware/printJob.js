'use strict';

// Validation of renderer print payloads + the thermal receipt HTML shell. Pure.

const { isValidPrinterName } = require('./rawPrinter');

const MAX_PRINT_HTML_LENGTH = 1024 * 1024;
const MAX_PRINT_COPIES = 5;
const PAPER_WIDTHS = ['58mm', '80mm'];

function pickPaperWidth(...candidates) {
    return candidates.find((w) => PAPER_WIDTHS.includes(w)) || '80mm';
}

function pickPrinterName(...candidates) {
    return candidates.find((name) => isValidPrinterName(name)) || '';
}

function normalizePrintJob(data, settings = {}) {
    if (
        !data ||
        typeof data !== 'object' ||
        Array.isArray(data) ||
        typeof data.html !== 'string' ||
        data.html.length > MAX_PRINT_HTML_LENGTH
    ) {
        return null;
    }
    const requestedCopies = Math.trunc(Number(data.copies));
    const copies = Number.isFinite(requestedCopies) ? Math.min(MAX_PRINT_COPIES, Math.max(1, requestedCopies)) : 1;
    return {
        html: data.html,
        printerName: pickPrinterName(data.printerName, settings.thermalPrinterName),
        paperWidth: pickPaperWidth(data.paperWidth, settings.paperWidth),
        copies,
    };
}

function buildReceiptDocument(receiptHtml, paperWidth) {
    const width = pickPaperWidth(paperWidth);
    const bodyWidth = width === '58mm' ? '48mm' : '72mm';
    return `<!DOCTYPE html>
<html dir="rtl">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; size: ${width} auto; }
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body { width: ${bodyWidth}; margin: 0 auto; padding: 4px 2px; font-size: 11px; color: #000; background: #fff; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 3px 1px; }
    .text-center { text-align: center; }
    .text-end { text-align: end; }
    .text-start { text-align: start; }
    .font-bold { font-weight: bold; }
    .border-b { border-bottom: 1px dashed #000; }
    .border-t { border-top: 1px dashed #000; }
    .my-1 { margin-top: 4px; margin-bottom: 4px; }
    .py-1 { padding-top: 4px; padding-bottom: 4px; }
</style>
</head>
<body>
${receiptHtml}
</body>
</html>`;
}

module.exports = {
    normalizePrintJob,
    buildReceiptDocument,
    MAX_PRINT_HTML_LENGTH,
    MAX_PRINT_COPIES,
    PAPER_WIDTHS,
};
