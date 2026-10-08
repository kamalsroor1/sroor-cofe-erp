'use strict';

// Sends raw bytes (ESC/POS) to a printer queue, bypassing the driver's renderer.
// Windows: winspool WritePrinter with datatype RAW through a fixed PowerShell script.
// The printer name and payload travel in environment variables, never inside the script.
// Other platforms: CUPS `lp -o raw` with the bytes on stdin.

const path = require('path');
const { execFile } = require('child_process');

const MAX_PRINTER_NAME_LENGTH = 256;
const MAX_RAW_BYTES = 64 * 1024;
const DEFAULT_TIMEOUT_MS = 15000;

const WINDOWS_RAW_PRINT_SCRIPT = [
    "$ErrorActionPreference = 'Stop'",
    'Add-Type -TypeDefinition @"',
    'using System;',
    'using System.ComponentModel;',
    'using System.Runtime.InteropServices;',
    'public static class SroorRawPrinter {',
    '    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]',
    '    public class DocInfo {',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;',
    '    }',
    '    [DllImport("winspool.drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]',
    '    static extern bool OpenPrinter(string name, out IntPtr handle, IntPtr defaults);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    static extern bool ClosePrinter(IntPtr handle);',
    '    [DllImport("winspool.drv", EntryPoint = "StartDocPrinterW", SetLastError = true, CharSet = CharSet.Unicode)]',
    '    static extern int StartDocPrinter(IntPtr handle, int level, [In] DocInfo info);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    static extern bool EndDocPrinter(IntPtr handle);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    static extern bool StartPagePrinter(IntPtr handle);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    static extern bool EndPagePrinter(IntPtr handle);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    static extern bool WritePrinter(IntPtr handle, byte[] bytes, int count, out int written);',
    '    public static void Send(string printer, byte[] data) {',
    '        IntPtr handle;',
    '        if (!OpenPrinter(printer, out handle, IntPtr.Zero)) throw new Win32Exception(Marshal.GetLastWin32Error());',
    '        try {',
    '            DocInfo info = new DocInfo();',
    '            info.pDocName = "ESC/POS";',
    '            info.pDataType = "RAW";',
    '            if (StartDocPrinter(handle, 1, info) == 0) throw new Win32Exception(Marshal.GetLastWin32Error());',
    '            try {',
    '                if (!StartPagePrinter(handle)) throw new Win32Exception(Marshal.GetLastWin32Error());',
    '                int written;',
    '                bool ok = WritePrinter(handle, data, data.Length, out written);',
    '                EndPagePrinter(handle);',
    '                if (!ok || written != data.Length) throw new Win32Exception(Marshal.GetLastWin32Error());',
    '            } finally { EndDocPrinter(handle); }',
    '        } finally { ClosePrinter(handle); }',
    '    }',
    '}',
    '"@',
    '[SroorRawPrinter]::Send($env:SROOR_RAW_PRINTER, [Convert]::FromBase64String($env:SROOR_RAW_DATA))',
].join('\r\n');

function isValidPrinterName(name) {
    return (
        typeof name === 'string' &&
        name.trim() !== '' &&
        name.length <= MAX_PRINTER_NAME_LENGTH &&
        // eslint-disable-next-line no-control-regex -- rejecting control characters is the point
        !/[\u0000-\u001f\u007f]/.test(name)
    );
}

function buildRawPrintCommand(platform, printerName, bytes, { systemRoot } = {}) {
    if (platform === 'win32') {
        const root = systemRoot || process.env.SystemRoot || 'C:\\Windows';
        return {
            file: path.win32.join(root, 'System32', 'WindowsPowerShell', 'v1.0', 'powershell.exe'),
            args: [
                '-NoProfile',
                '-NonInteractive',
                '-EncodedCommand',
                Buffer.from(WINDOWS_RAW_PRINT_SCRIPT, 'utf16le').toString('base64'),
            ],
            env: {
                SROOR_RAW_PRINTER: printerName,
                SROOR_RAW_DATA: Buffer.from(bytes).toString('base64'),
            },
            input: null,
        };
    }
    return {
        file: 'lp',
        args: ['-d', printerName, '-o', 'raw'],
        env: {},
        input: Buffer.from(bytes),
    };
}

function defaultRun(cmd, timeoutMs) {
    return new Promise((resolve) => {
        const child = execFile(
            cmd.file,
            cmd.args,
            { env: { ...process.env, ...cmd.env }, windowsHide: true, timeout: timeoutMs },
            (error) => resolve({ code: error ? error.code || 1 : 0 })
        );
        if (cmd.input) {
            child.stdin.end(cmd.input);
        } else {
            child.stdin.end();
        }
    });
}

async function sendRaw(printerName, bytes, options = {}) {
    if (!isValidPrinterName(printerName)) {
        return { success: false, error: 'invalid_printer' };
    }
    if (!Buffer.isBuffer(bytes) || bytes.length === 0 || bytes.length > MAX_RAW_BYTES) {
        return { success: false, error: 'invalid_payload' };
    }
    const platform = options.platform || process.platform;
    const run = options.run || defaultRun;
    const cmd = buildRawPrintCommand(platform, printerName, bytes, { systemRoot: options.systemRoot });
    try {
        const result = await run(cmd, options.timeoutMs || DEFAULT_TIMEOUT_MS);
        return result && result.code === 0 ? { success: true } : { success: false, error: 'raw_print_failed' };
    } catch {
        return { success: false, error: 'raw_print_failed' };
    }
}

module.exports = { isValidPrinterName, buildRawPrintCommand, sendRaw, MAX_RAW_BYTES };
