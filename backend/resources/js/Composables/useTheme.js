import { ref } from 'vue';
import axios from 'axios';

export const PRESET_PALETTES = {
    amber: { hex: '#f59e0b', hover: '#d97706', rgb: '245, 158, 11', text: '#020617', name: 'الكهرمان / الذهبي الأصيل', icon: '🌟' },
    emerald: { hex: '#10b981', hover: '#059669', rgb: '16, 185, 129', text: '#ffffff', name: 'الأخضر الزمردي الملكي', icon: '🌿' },
    blue: { hex: '#3b82f6', hover: '#2563eb', rgb: '59, 130, 246', text: '#ffffff', name: 'الأزرق الملكي (Sapphire)', icon: '🔵' },
    purple: { hex: '#a855f7', hover: '#9333ea', rgb: '168, 85, 247', text: '#ffffff', name: 'البنفسجي الإمبراطوري', icon: '🟣' },
    rose: { hex: '#f43f5e', hover: '#e11d48', rgb: '244, 63, 94', text: '#ffffff', name: 'الياقوتي القرمزي (Ruby Rose)', icon: '🌹' },
    orange: { hex: '#f97316', hover: '#ea580c', rgb: '249, 115, 22', text: '#ffffff', name: 'البرتقالي الكلاسيكي (Warm Orange)', icon: '🟧' },
    teal: { hex: '#14b8a6', hover: '#0d9488', rgb: '20, 184, 166', text: '#ffffff', name: 'السماوي التركوازي (Ocean Teal)', icon: '🌊' },
    indigo: { hex: '#6366f1', hover: '#4f46e5', rgb: '99, 102, 241', text: '#ffffff', name: 'النيلي الداكن (Deep Indigo)', icon: '🌌' },
};

export function hexToRgb(hex) {
    let c = (hex || '#f59e0b').replace(/^#/, '');
    if (c.length === 3) c = c.split('').map(x => x + x).join('');
    const num = parseInt(c, 16) || 0;
    return {
        r: (num >> 16) & 255,
        g: (num >> 8) & 255,
        b: num & 255,
    };
}

export function adjustBrightness(hex, percent) {
    const { r, g, b } = hexToRgb(hex);
    const adjust = (val) => Math.min(255, Math.max(0, Math.round(val * (1 + percent / 100))));
    const nr = adjust(r);
    const ng = adjust(g);
    const nb = adjust(b);
    return `#${((1 << 24) + (nr << 16) + (ng << 8) + nb).toString(16).slice(1)}`;
}

export function getContrastTextColor(hex) {
    const { r, g, b } = hexToRgb(hex);
    // YIQ formula for high contrast readability
    const yiq = (r * 299 + g * 587 + b * 114) / 1000;
    return yiq >= 155 ? '#020617' : '#ffffff';
}

export function useTheme(defaultTheme = 'dark', defaultColor = 'amber') {
    const currentTheme = ref(localStorage.getItem('theme_preference') || defaultTheme);
    const currentColor = ref(localStorage.getItem('system_theme_color') || defaultColor);

    const applyTheme = (theme) => {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
            if (document.body) {
                document.body.classList.add('dark');
                document.body.classList.remove('light');
            }
        } else {
            document.documentElement.classList.add('light');
            document.documentElement.classList.remove('dark');
            if (document.body) {
                document.body.classList.add('light');
                document.body.classList.remove('dark');
            }
        }
    };

    const applyColorTheme = (color) => {
        if (!color) return;
        currentColor.value = color;

        let hex, hoverHex, rgbStr, textColor;

        if (PRESET_PALETTES[color]) {
            const preset = PRESET_PALETTES[color];
            hex = preset.hex;
            hoverHex = preset.hover;
            rgbStr = preset.rgb;
            textColor = preset.text;
            document.documentElement.setAttribute('data-theme-color', color);
            if (document.body) document.body.setAttribute('data-theme-color', color);
        } else {
            // Custom Hex Color (e.g. #00d2d3)
            hex = color.startsWith('#') ? color : `#${color}`;
            hoverHex = adjustBrightness(hex, -15);
            const { r, g, b } = hexToRgb(hex);
            rgbStr = `${r}, ${g}, ${b}`;
            textColor = getContrastTextColor(hex);
            document.documentElement.setAttribute('data-theme-color', 'custom');
            if (document.body) document.body.setAttribute('data-theme-color', 'custom');
        }

        // Dynamically inject CSS variables directly into root for 100% immediate effect everywhere
        const root = document.documentElement;
        root.style.setProperty('--color-primary', hex);
        root.style.setProperty('--color-primary-hover', hoverHex);
        root.style.setProperty('--color-primary-rgb', rgbStr);
        root.style.setProperty('--color-primary-light', `rgba(${rgbStr}, 0.15)`);
        root.style.setProperty('--color-primary-border', `rgba(${rgbStr}, 0.35)`);
        root.style.setProperty('--color-primary-text', textColor);

        if (document.body) {
            document.body.style.setProperty('--color-primary', hex);
            document.body.style.setProperty('--color-primary-hover', hoverHex);
            document.body.style.setProperty('--color-primary-rgb', rgbStr);
            document.body.style.setProperty('--color-primary-light', `rgba(${rgbStr}, 0.15)`);
            document.body.style.setProperty('--color-primary-border', `rgba(${rgbStr}, 0.35)`);
            document.body.style.setProperty('--color-primary-text', textColor);
        }

        try {
            localStorage.setItem('system_theme_color', color);
        } catch (e) {}
    };

    const toggleTheme = () => {
        currentTheme.value = currentTheme.value === 'dark' ? 'light' : 'dark';
        applyTheme(currentTheme.value);
        try {
            localStorage.setItem('theme_preference', currentTheme.value);
        } catch (e) {}

        // Async sync with backend (0 UI latency)
        try {
            axios.post('/theme-toggle', { theme: currentTheme.value });
        } catch (e) {}
    };

    const initTheme = (initialColor) => {
        applyTheme(currentTheme.value);
        const colorToApply = initialColor || currentColor.value || defaultColor;
        applyColorTheme(colorToApply);
    };

    return {
        currentTheme,
        currentColor,
        toggleTheme,
        applyColorTheme,
        initTheme,
        applyTheme,
    };
}
