<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#020617">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title>{{ \App\Models\Setting::get('platform_name') ?: \App\Models\Setting::get('app_name') ?: config('app.name', 'منظومة ERP السحابية') }}</title>

    <!-- Early Anti-Flicker Theme Script -->
    <script>
        (function() {
            try {
                const storedTheme = localStorage.getItem('theme_preference') || 'dark';
                if (storedTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.add('light');
                    document.documentElement.classList.remove('dark');
                }

                const storedColor = localStorage.getItem('system_theme_color') || 'amber';
                const presets = {
                    amber: '#f59e0b', emerald: '#10b981', blue: '#3b82f6', purple: '#a855f7',
                    rose: '#f43f5e', orange: '#f97316', teal: '#14b8a6', indigo: '#6366f1'
                };
                if (presets[storedColor]) {
                    document.documentElement.setAttribute('data-theme-color', storedColor);
                    document.documentElement.style.setProperty('--color-primary', presets[storedColor]);
                } else if (storedColor.startsWith('#')) {
                    document.documentElement.setAttribute('data-theme-color', 'custom');
                    document.documentElement.style.setProperty('--color-primary', storedColor);
                } else {
                    document.documentElement.setAttribute('data-theme-color', 'amber');
                }
            } catch (e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Google Arabic Fonts (Cairo & Tajawal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Favicon & PWA Manifest -->
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- Injected Global Translations & System Context -->
    <script>
        @php
            $translationsAction = app(\App\Actions\System\GetTranslationsAction::class);
            $locale = app()->getLocale() ?: 'ar';
            $initialTranslations = $translationsAction->execute($locale);
        @endphp
        window.spaTranslations = {!! json_encode($initialTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
        window.spaLocale = '{{ $locale }}';
    </script>

    <!-- Vite Scripts (Vue 3 Pure SPA) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            font-family: 'Cairo', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            font-family: 'Cairo', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-amber-500 selection:text-white min-h-screen overflow-x-hidden font-sans transition-colors duration-200">
    <div id="app">
        <!-- Instant Branded Pre-Vue Splash Screen -->
        <div style="position: fixed; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #020617; color: #f8fafc; z-index: 99999; font-family: 'Cairo', 'Tajawal', sans-serif;" dir="rtl">
            <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(245, 158, 11, 0.35); margin-bottom: 20px;">
                <svg style="width: 40px; height: 40px; stroke: #020617; fill: none; stroke-width: 2.2;" viewBox="0 0 24 24">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
                    <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
                    <line x1="6" y1="2" x2="6" y2="4"></line>
                    <line x1="10" y1="2" x2="10" y2="4"></line>
                    <line x1="14" y1="2" x2="14" y2="4"></line>
                </svg>
            </div>
            <h1 style="font-size: 20px; font-weight: 900; margin: 0 0 6px 0; letter-spacing: -0.5px; color: #ffffff;">سرور كوفي ERP & POS</h1>
            <p style="font-size: 11px; color: #94a3b8; margin: 0 0 24px 0; font-weight: 600;">نظام الفواتير والمخزون ونقاط البيع السريعة</p>
            <div style="width: 180px; height: 4px; background: #1e293b; border-radius: 999px; overflow: hidden; position: relative;">
                <div style="position: absolute; top: 0; left: 0; height: 100%; width: 50%; background: linear-gradient(90deg, #f59e0b, #10b981); border-radius: 999px; animation: app-splash-loader 1.5s infinite ease-in-out;"></div>
            </div>
            <style>
                @keyframes app-splash-loader {
                    0% { transform: translateX(-100%); }
                    50% { transform: translateX(100%); }
                    100% { transform: translateX(300%); }
                }
            </style>
        </div>
    </div>
</body>
</html>
