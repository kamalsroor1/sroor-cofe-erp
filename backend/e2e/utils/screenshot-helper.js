import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '../../');
const e2eDir = path.resolve(rootDir, 'e2e');
const screenshotsBaseDir = path.resolve(e2eDir, 'screenshots');
const runMetaFile = path.resolve(e2eDir, '.run-meta.json');

const MAX_DAYS_RETENTION = 3;
const MAX_RUNS_PER_DAY = 10;

/**
 * Auto-cleans old day folders beyond the last 3 days
 */
function cleanupOldDays() {
    if (!fs.existsSync(screenshotsBaseDir)) return;

    try {
        const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        const dateDirs = fs.readdirSync(screenshotsBaseDir, { withFileTypes: true })
            .filter(d => d.isDirectory() && dateRegex.test(d.name))
            .map(d => d.name)
            .sort(); // Lexicographical sort works for YYYY-MM-DD

        while (dateDirs.length > MAX_DAYS_RETENTION) {
            const oldestDay = dateDirs.shift();
            const oldestDayPath = path.join(screenshotsBaseDir, oldestDay);
            try {
                fs.rmSync(oldestDayPath, { recursive: true, force: true });
                console.log(`🧹 Auto-cleanup: Removed oldest day screenshots folder: ${oldestDay}`);
            } catch (_) {}
        }
    } catch (_) {}
}

/**
 * Auto-cleans old run folders within a specific day if they exceed max runs
 * @param {string} dayDir 
 */
function cleanupOldRuns(dayDir) {
    if (!fs.existsSync(dayDir)) return;

    try {
        const runDirs = fs.readdirSync(dayDir, { withFileTypes: true })
            .filter(d => d.isDirectory() && d.name.startsWith('run-'))
            .map(d => ({
                name: d.name,
                path: path.join(dayDir, d.name),
                ctime: fs.statSync(path.join(dayDir, d.name)).ctimeMs,
            }))
            .sort((a, b) => a.ctime - b.ctime); // Oldest first

        // Keep at most MAX_RUNS_PER_DAY - 1 before adding the new run
        while (runDirs.length >= MAX_RUNS_PER_DAY) {
            const oldestRun = runDirs.shift();
            try {
                fs.rmSync(oldestRun.path, { recursive: true, force: true });
                console.log(`🧹 Auto-cleanup: Removed oldest run folder: ${oldestRun.name}`);
            } catch (_) {}
        }
    } catch (_) {}
}

/**
 * Initializes and determines the current run folder with auto-numbering (run-01, run-02, ...)
 */
export function getRunTimestamp() {
    if (process.env.E2E_RUN_ID && process.env.E2E_RUN_DATE) {
        return {
            dateStr: process.env.E2E_RUN_DATE,
            runId: process.env.E2E_RUN_ID,
        };
    }

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

    // Check if an active session run-meta was created recently (within last 3 minutes)
    if (fs.existsSync(runMetaFile)) {
        try {
            const data = JSON.parse(fs.readFileSync(runMetaFile, 'utf8'));
            if (data.dateStr === dateStr && data.runId) {
                const diff = Date.now() - (data.createdAt || 0);
                if (diff < 3 * 60 * 1000) {
                    process.env.E2E_RUN_DATE = data.dateStr;
                    process.env.E2E_RUN_ID = data.runId;
                    return { dateStr: data.dateStr, runId: data.runId };
                }
            }
        } catch (_) {}
    }

    // Ensure base directory and cleanup days
    fs.mkdirSync(screenshotsBaseDir, { recursive: true });
    cleanupOldDays();

    const todayDir = path.join(screenshotsBaseDir, dateStr);
    fs.mkdirSync(todayDir, { recursive: true });

    // Determine next run number
    cleanupOldRuns(todayDir);

    const existingRuns = fs.readdirSync(todayDir, { withFileTypes: true })
        .filter(d => d.isDirectory() && d.name.startsWith('run-'))
        .map(d => {
            const num = parseInt(d.name.replace('run-', ''), 10);
            return isNaN(num) ? 0 : num;
        });

    const nextRunNum = existingRuns.length > 0 ? Math.max(...existingRuns) + 1 : 1;
    const runId = `run-${pad(nextRunNum)}`;

    process.env.E2E_RUN_DATE = dateStr;
    process.env.E2E_RUN_ID = runId;

    try {
        fs.writeFileSync(runMetaFile, JSON.stringify({ dateStr, runId, createdAt: Date.now() }), 'utf8');
    } catch (_) {}

    return { dateStr, runId };
}

/**
 * Returns the fixed directory for the current test run (e2e/screenshots/[YYYY-MM-DD]/run-XX/)
 */
export function getScreenshotRunDir() {
    const { dateStr, runId } = getRunTimestamp();
    const runDir = path.join(screenshotsBaseDir, dateStr, runId);
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
