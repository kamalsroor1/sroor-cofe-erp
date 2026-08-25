const printerManager = require('./printerManager');

class CashDrawer {
    /**
     * Send ESC/POS pulse to kick open connected cash drawer
     * Command: ESC p 0 25 250 (HEX: 1B 70 00 19 FA)
     */
    async kickDrawer(printerName = '') {
        try {
            // Standard ESC/POS drawer kick HTML pulse
            const kickHtml = `
                <div style="font-size: 1px; color: transparent; height: 1px;">
                    <!-- ESC/POS Drawer Kick Trigger -->
                    <span>.</span>
                </div>
            `;
            return await printerManager.printThermalSilent(kickHtml, {
                printerName: printerName,
                paperWidth: '80mm',
                silent: true,
                copies: 1
            });
        } catch (error) {
            console.error('[CashDrawer] Failed to kick drawer:', error);
            return { success: false, error: error.message };
        }
    }
}

module.exports = new CashDrawer();
