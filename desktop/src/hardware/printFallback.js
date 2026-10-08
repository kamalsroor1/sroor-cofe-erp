'use strict';

// Print orchestration with fallbacks. Pure: the actual print/dialog/PDF steps are injected.
//   1. silent print to the requested printer (or the system default when none is set)
//   2. system print dialog: the cashier picks another printer or "Microsoft Print to PDF"
//   3. save the receipt as a PDF file
// Results carry machine codes only; the renderer translates them.

function findPrinter(printers, name) {
    return printers.find((p) => p && (p.name === name || p.displayName === name)) || null;
}

function resolveTargetPrinter(printers, requestedPrinter) {
    const list = Array.isArray(printers) ? printers : [];
    if (list.length === 0) return { printerName: null, reason: 'no_printer' };
    if (typeof requestedPrinter !== 'string' || requestedPrinter.trim() === '') {
        return { printerName: '', reason: null };
    }
    const match = findPrinter(list, requestedPrinter);
    return match ? { printerName: match.name, reason: null } : { printerName: null, reason: 'printer_not_found' };
}

function isCancelled(reason) {
    return typeof reason === 'string' && /cancel/i.test(reason);
}

async function attempt(step, ...args) {
    try {
        const result = await step(...args);
        return result && typeof result === 'object' ? result : { success: false };
    } catch (error) {
        return { success: false, error: error && error.message };
    }
}

async function printWithFallback(job, deps) {
    const printers = Array.isArray(job.printers) ? job.printers : [];
    const target = resolveTargetPrinter(printers, job.requestedPrinter);
    let reason = target.reason;

    if (target.printerName !== null) {
        const silent = await attempt(deps.printSilent, target.printerName);
        if (silent.success) {
            return { success: true, method: 'silent', printerName: target.printerName };
        }
        reason = 'print_failed';
    }

    if (job.allowInteractive === false) {
        return { success: false, error: reason };
    }

    if (printers.length > 0) {
        const dialog = await attempt(deps.printWithDialog);
        if (dialog.success) {
            return { success: true, method: 'dialog', fallbackReason: reason };
        }
        if (dialog.cancelled || isCancelled(dialog.error)) {
            return { success: false, error: 'print_cancelled', fallbackReason: reason };
        }
    }

    const pdf = await attempt(deps.saveAsPdf);
    if (pdf.success) {
        return { success: true, method: 'pdf', fallbackReason: reason, filePath: pdf.filePath };
    }
    if (pdf.cancelled) {
        return { success: false, error: 'print_cancelled', fallbackReason: reason };
    }
    return { success: false, error: 'pdf_failed', fallbackReason: reason };
}

module.exports = { findPrinter, resolveTargetPrinter, isCancelled, printWithFallback };
