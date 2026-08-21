import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '../../');
const e2eDir = path.resolve(rootDir, 'e2e');
const screenshotsBaseDir = path.resolve(e2eDir, 'screenshots');
const runMetaFile = path.resolve(e2eDir, '.run-meta.json');

/**
 * Returns the fixed run timestamp for the current session (YYYY-MM-DD and HH-mm-ss)
 */
export function getRunTimestamp() {
    if (process.env.E2E_RUN_TIMESTAMP && process.env.E2E_RUN_DATE) {
        return {
            dateStr: process.env.E2E_RUN_DATE,
            timeStr: process.env.E2E_RUN_TIMESTAMP,
        };
    }

    if (fs.existsSync(runMetaFile)) {
        try {
            const data = JSON.parse(fs.readFileSync(runMetaFile, 'utf8'));
            if (data.dateStr && data.timeStr) {
                // If created less than 2 hours ago, reuse
                const diff = Date.now() - (data.createdAt || 0);
                if (diff < 2 * 60 * 60 * 1000) {
                    process.env.E2E_RUN_DATE = data.dateStr;
                    process.env.E2E_RUN_TIMESTAMP = data.timeStr;
                    return { dateStr: data.dateStr, timeStr: data.timeStr };
                }
            }
        } catch (_) {}
    }

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    const timeStr = `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

    process.env.E2E_RUN_DATE = dateStr;
    process.env.E2E_RUN_TIMESTAMP = timeStr;

    try {
        fs.mkdirSync(e2eDir, { recursive: true });
        fs.writeFileSync(runMetaFile, JSON.stringify({ dateStr, timeStr, createdAt: Date.now() }), 'utf8');
    } catch (_) {}

    return { dateStr, timeStr };
}

/**
 * Returns the fixed directory for the current test run
 */
export function getScreenshotRunDir() {
    const { dateStr, timeStr } = getRunTimestamp();
    const runDir = path.join(screenshotsBaseDir, dateStr, timeStr);
    if (!fs.existsSync(runDir)) {
        fs.mkdirSync(runDir, { recursive: true });
    }
    return runDir;
}

/**
 * Returns the target screenshot file path
 * @param {string} moduleName - Module directory name (e.g. 'pos', 'customers')
 * @param {string} viewportType - 'desktop' or 'mobile'
 * @param {string} filename - e.g. '01-dashboard.png' or '02-pos-modal-open.png'
 */
export function getScreenshotPath(moduleName, viewportType, filename) {
    const runDir = getScreenshotRunDir();
    const targetDir = path.join(runDir, moduleName, viewportType);
    if (!fs.existsSync(targetDir)) {
        fs.mkdirSync(targetDir, { recursive: true });
    }
    return path.join(targetDir, filename);
}
