export function hexToRgb(hex) {
    if (!hex) return '245, 158, 11';
    hex = hex.replace('#', '').trim();
    if (hex.length === 3) {
        hex = hex.split('').map(c => c + c).join('');
    }
    if (hex.length !== 6) return '245, 158, 11';
    const num = parseInt(hex, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return `${r}, ${g}, ${b}`;
}

export function adjustColorBrightness(hex, percent) {
    if (!hex) return '#d97706';
    hex = hex.replace('#', '').trim();
    if (hex.length === 3) {
        hex = hex.split('').map(c => c + c).join('');
    }
    if (hex.length !== 6) return '#d97706';
    let num = parseInt(hex, 16);
    let r = (num >> 16) + Math.round(255 * (percent / 100));
    let g = ((num >> 8) & 0x00FF) + Math.round(255 * (percent / 100));
    let b = (num & 0x0000FF) + Math.round(255 * (percent / 100));
    r = Math.min(255, Math.max(0, r));
    g = Math.min(255, Math.max(0, g));
    b = Math.min(255, Math.max(0, b));
    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

export function applyThemeColor(color) {
    if (!color) return;
    localStorage.setItem('system_theme_color', color);

    const presets = {
        amber: { hex: '#f59e0b', hover: '#d97706' },
        emerald: { hex: '#10b981', hover: '#059669' },
        blue: { hex: '#3b82f6', hover: '#2563eb' },
        purple: { hex: '#a855f7', hover: '#9333ea' },
        rose: { hex: '#f43f5e', hover: '#e11d48' },
        orange: { hex: '#f97316', hover: '#ea580c' },
        teal: { hex: '#14b8a6', hover: '#0d9488' },
        indigo: { hex: '#6366f1', hover: '#4f46e5' },
    };

    let primaryHex, hoverHex;

    if (presets[color]) {
        document.documentElement.setAttribute('data-theme-color', color);
        primaryHex = presets[color].hex;
        hoverHex = presets[color].hover;
    } else {
        document.documentElement.setAttribute('data-theme-color', 'custom');
        primaryHex = color.startsWith('#') ? color : `#${color}`;
        hoverHex = adjustColorBrightness(primaryHex, -15);
    }

    const rgb = hexToRgb(primaryHex);
    document.documentElement.style.setProperty('--color-primary', primaryHex);
    document.documentElement.style.setProperty('--color-primary-hover', hoverHex);
    document.documentElement.style.setProperty('--color-primary-rgb', rgb);
    document.documentElement.style.setProperty('--color-primary-light', `rgba(${rgb}, 0.15)`);
    document.documentElement.style.setProperty('--color-primary-border', `rgba(${rgb}, 0.35)`);
}
