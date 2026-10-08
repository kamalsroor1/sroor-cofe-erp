'use strict';

// APP-5: raw byte delivery to a printer queue (used for the ESC/POS drawer kick).
// The printer name and payload must never be interpolated into a shell/script string.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const raw = require(path.join(__dirname, '..', 'src', 'hardware', 'rawPrinter.js'));

const KICK = Buffer.from([0x1b, 0x70, 0x00, 0x19, 0xfa]);
const EVIL = 'XP-80"; Remove-Item C:\\ -Recurse; $x="';

function decodeEncodedCommand(args) {
    const index = args.indexOf('-EncodedCommand');
    assert.ok(index >= 0, 'expected -EncodedCommand');
    return Buffer.from(args[index + 1], 'base64').toString('utf16le');
}

test('isValidPrinterName accepts normal queue names and rejects junk', () => {
    assert.equal(raw.isValidPrinterName('XP-80C'), true);
    assert.equal(raw.isValidPrinterName('\\\\SHOP-PC\\POS 80 (طابعة الكاشير)'), true);
    assert.equal(raw.isValidPrinterName(''), false);
    assert.equal(raw.isValidPrinterName('   '), false);
    assert.equal(raw.isValidPrinterName(null), false);
    assert.equal(raw.isValidPrinterName(42), false);
    assert.equal(raw.isValidPrinterName('a\nb'), false);
    assert.equal(raw.isValidPrinterName('a\u0000b'), false);
    assert.equal(raw.isValidPrinterName('x'.repeat(257)), false);
});

test('windows command passes printer and data through env, never inside the script', () => {
    const cmd = raw.buildRawPrintCommand('win32', EVIL, KICK, { systemRoot: 'C:\\Windows' });
    assert.equal(cmd.file.toLowerCase(), 'c:\\windows\\system32\\windowspowershell\\v1.0\\powershell.exe');
    assert.ok(cmd.args.includes('-NoProfile'));
    assert.ok(cmd.args.includes('-NonInteractive'));
    const script = decodeEncodedCommand(cmd.args);
    assert.ok(!script.includes(EVIL), 'printer name leaked into the script');
    assert.match(script, /winspool\.drv/);
    assert.match(script, /"RAW"/);
    assert.match(script, /\$env:SROOR_RAW_PRINTER/);
    assert.match(script, /\$env:SROOR_RAW_DATA/);
    assert.equal(cmd.env.SROOR_RAW_PRINTER, EVIL);
    assert.equal(Buffer.from(cmd.env.SROOR_RAW_DATA, 'base64').toString('hex'), KICK.toString('hex'));
    assert.ok(cmd.args.every((a) => !a.includes(EVIL)));
});

test('posix command uses lp -o raw with the bytes on stdin', () => {
    const cmd = raw.buildRawPrintCommand('linux', 'XP-80', KICK);
    assert.equal(cmd.file, 'lp');
    assert.deepEqual(cmd.args, ['-d', 'XP-80', '-o', 'raw']);
    assert.ok(Buffer.isBuffer(cmd.input));
    assert.equal(cmd.input.toString('hex'), KICK.toString('hex'));
});

test('sendRaw rejects an invalid printer name without running anything', async () => {
    let ran = false;
    const res = await raw.sendRaw('', KICK, {
        run: async () => {
            ran = true;
        },
    });
    assert.deepEqual(res, { success: false, error: 'invalid_printer' });
    assert.equal(ran, false);
});

test('sendRaw rejects empty or oversized payloads', async () => {
    const run = async () => ({ code: 0 });
    assert.equal((await raw.sendRaw('XP-80', Buffer.alloc(0), { run })).error, 'invalid_payload');
    assert.equal((await raw.sendRaw('XP-80', 'not a buffer', { run })).error, 'invalid_payload');
    assert.equal((await raw.sendRaw('XP-80', Buffer.alloc(70 * 1024), { run })).error, 'invalid_payload');
});

test('sendRaw reports success when the spooler command exits 0', async () => {
    let seen = null;
    const res = await raw.sendRaw('XP-80', KICK, {
        platform: 'win32',
        systemRoot: 'C:\\Windows',
        run: async (cmd) => {
            seen = cmd;
            return { code: 0 };
        },
    });
    assert.deepEqual(res, { success: true });
    assert.equal(seen.env.SROOR_RAW_PRINTER, 'XP-80');
});

test('sendRaw maps a failing or throwing spooler command to raw_print_failed', async () => {
    const failed = await raw.sendRaw('XP-80', KICK, { platform: 'linux', run: async () => ({ code: 1 }) });
    assert.deepEqual(failed, { success: false, error: 'raw_print_failed' });
    const thrown = await raw.sendRaw('XP-80', KICK, {
        platform: 'linux',
        run: async () => {
            throw new Error('spawn ENOENT');
        },
    });
    assert.deepEqual(thrown, { success: false, error: 'raw_print_failed' });
});
