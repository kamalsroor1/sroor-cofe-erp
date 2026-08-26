const fs = require('fs');
const path = require('path');
const { app } = require('electron');

class SettingsStore {
    constructor() {
        this.configFilePath = null;
        this.defaults = {
            serverUrl: '',
            tenantId: '',
            centralUrl: 'https://baraa-solutions.com',
            thermalPrinterName: '',
            paperWidth: '80mm',
            autoOpenDrawerOnCash: true,
            kioskMode: false,
            windowBounds: { width: 1400, height: 900, isMaximized: true },
            theme: 'dark'
        };
        this.settings = null;
    }

    getConfigFilePath() {
        if (!this.configFilePath) {
            try {
                const userDataPath = app.getPath('userData');
                this.configFilePath = path.join(userDataPath, 'sroor-desktop-config.json');
            } catch (e) {
                // Fallback before app ready
                this.configFilePath = path.join(process.cwd(), 'sroor-desktop-config.json');
            }
        }
        return this.configFilePath;
    }

    loadSettings() {
        if (this.settings) return this.settings;
        try {
            const filePath = this.getConfigFilePath();
            if (fs.existsSync(filePath)) {
                const data = fs.readFileSync(filePath, 'utf8');
                this.settings = { ...this.defaults, ...JSON.parse(data) };
                return this.settings;
            }
        } catch (e) {
            console.error('[SettingsStore] Failed to read config file, using defaults:', e);
        }
        this.settings = { ...this.defaults };
        return this.settings;
    }

    saveSettings(newSettings) {
        try {
            const filePath = this.getConfigFilePath();
            this.settings = { ...this.loadSettings(), ...newSettings };
            fs.writeFileSync(filePath, JSON.stringify(this.settings, null, 2), 'utf8');
            return { success: true, settings: this.settings };
        } catch (e) {
            console.error('[SettingsStore] Failed to save config file:', e);
            return { success: false, error: e.message };
        }
    }

    get(key) {
        const current = this.loadSettings();
        return current[key] !== undefined ? current[key] : this.defaults[key];
    }

    set(key, value) {
        const current = this.loadSettings();
        current[key] = value;
        return this.saveSettings(current);
    }
}

module.exports = new SettingsStore();
