<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#13201C">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (($appContext ?? null) === 'central')
    {{-- IDEN-1.11: platform-console shell (admin host only); contract for the SPA boot. --}}
    <meta name="app-context" content="central">
    {{-- Platform-console host(s), only on the console itself (resources/js/helpers/platformHosts.js). --}}
    <meta name="admin-domains" content="{{ implode(',', \App\Http\Middleware\EnsureCentralContext::adminHosts()) }}">
    @endif
    {{-- Central (workspace-hub) hosts from config, admin hosts excluded (helpers/platformHosts.js). --}}
    <meta name="central-domains" content="{{ implode(',', \App\Support\PlatformHosts::publicCentralDomains()) }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    @php
        // BRND-1: the shell is rendered before tenancy is initialised (the SPA sends X-Tenant on
        // API calls only), so the shop name is not available here: the platform brand is shown.
        $platformBrand = app(\App\Services\Branding\PlatformBranding::class)->get();
    @endphp
    <title>{{ $platformBrand->name }}</title>

    <!-- Early Anti-Flicker Theme Script -->
    <script @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
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

                const storedColor = localStorage.getItem('system_theme_color') || 'ibraa';
                const presets = {
                    ibraa: '#0F6E5E', amber: '#f59e0b', emerald: '#10b981', blue: '#3b82f6', purple: '#a855f7',
                    rose: '#f43f5e', orange: '#f97316', teal: '#14b8a6', indigo: '#6366f1'
                };
                if (presets[storedColor]) {
                    document.documentElement.setAttribute('data-theme-color', storedColor);
                    document.documentElement.style.setProperty('--color-primary', presets[storedColor]);
                } else if (storedColor.startsWith('#')) {
                    document.documentElement.setAttribute('data-theme-color', 'custom');
                    document.documentElement.style.setProperty('--color-primary', storedColor);
                } else {
                    document.documentElement.setAttribute('data-theme-color', 'ibraa');
                }
            } catch (e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Google Arabic Fonts: IBM Plex Sans Arabic (body), Reem Kufi (logo wordmark), Cairo & Tajawal (fallbacks) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Reem+Kufi:wght@500;600;700&family=Cairo:wght@300;400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Favicon & PWA Manifest -->
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    {{-- Dynamic manifest (routes/web.php): public/manifest.json is a static file the web server
         would serve instead of the /manifest.json route, so the shell points at /app.webmanifest. --}}
    <link rel="manifest" href="{{ url('/app.webmanifest') }}">

    <!-- Injected Global Translations & System Context -->
    <script @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
        @php
            $translationsAction = app(\App\Actions\System\GetTranslationsAction::class);
            $locale = app()->getLocale() ?: 'ar';
            $initialTranslations = $translationsAction->execute($locale);
        @endphp
        window.spaTranslations = {!! json_encode($initialTranslations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
        window.spaLocale = '{{ $locale }}';
    </script>

    <!-- Vite Scripts (Vue 3 Pure SPA) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
        * {
            font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-[var(--color-primary)] selection:text-white min-h-screen overflow-x-hidden font-sans transition-colors duration-200">
    <div id="app">
        <!-- Instant Branded Pre-Vue Splash Screen (Hidden in Electron to avoid double splash) -->
        <div id="pre-vue-splash" dir="rtl">
            <svg class="ps-mark" viewBox="0 0 100 100" fill="none" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="{{ $platformBrand->name }}">
                <g class="ps-settle">
                    <path class="ps-arc" d="M50 14 A36 36 0 1 1 20 30" stroke-width="9" pathLength="100"></path>
                    <polyline class="ps-head" points="12,22 20,30 30,24" stroke-width="7"></polyline>
                    <polyline class="ps-check" points="36,51 46,61 66,40" stroke-width="7" pathLength="100"></polyline>
                </g>
            </svg>
            <h1 class="ps-name">{{ $platformBrand->name }}</h1>
            <p class="ps-tagline">{{ __('common.splash.subtitle') }}</p>
            <div class="ps-bar"><div class="ps-bar-fill"></div></div>
            <style @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
                #pre-vue-splash {
                    --ps-bg: #13201C; --ps-arc: #4CC3A8; --ps-check: #EEF3F0; --ps-text: #EEF3F0; --ps-muted: #8FA79E; --ps-track: #22332D;
                    position: fixed; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
                    background: var(--ps-bg); color: var(--ps-text); z-index: 99999;
                    font-family: 'IBM Plex Sans Arabic', 'Cairo', 'Tajawal', sans-serif;
                }
                html.light #pre-vue-splash {
                    --ps-bg: #F5F7F3; --ps-arc: #0F6E5E; --ps-check: #15241F; --ps-text: #15241F; --ps-muted: #5B6F67; --ps-track: #DCE4DF;
                }
                #pre-vue-splash .ps-mark { width: 96px; height: 96px; margin-bottom: 20px; }
                #pre-vue-splash .ps-arc, #pre-vue-splash .ps-head { stroke: var(--ps-arc); }
                #pre-vue-splash .ps-check { stroke: var(--ps-check); }
                #pre-vue-splash .ps-arc, #pre-vue-splash .ps-check { stroke-dasharray: 100; }
                #pre-vue-splash .ps-arc { animation: ps-draw 1.1s cubic-bezier(.65, 0, .35, 1) both, ps-show .01s linear both; }
                #pre-vue-splash .ps-head { transform-box: fill-box; transform-origin: center; animation: ps-pop .25s ease-out 1.05s both; }
                #pre-vue-splash .ps-check { animation: ps-draw .45s cubic-bezier(.65, 0, .35, 1) 1.35s both, ps-show .01s linear 1.35s both; }
                #pre-vue-splash .ps-settle { transform-box: view-box; transform-origin: 50px 50px; animation: ps-settle .45s ease-in-out 1.85s both; }
                #pre-vue-splash .ps-name { font-family: 'Reem Kufi', 'IBM Plex Sans Arabic', sans-serif; font-size: 32px; font-weight: 600; margin: 0 0 6px 0; line-height: 1.2; color: var(--ps-text); }
                #pre-vue-splash .ps-tagline { font-size: 12px; color: var(--ps-muted); margin: 0 0 24px 0; font-weight: 500; text-align: center; padding: 0 16px; }
                #pre-vue-splash .ps-bar { width: 160px; height: 3px; background: var(--ps-track); border-radius: 999px; overflow: hidden; position: relative; }
                #pre-vue-splash .ps-bar-fill { position: absolute; top: 0; left: 0; height: 100%; width: 50%; background: var(--ps-arc); border-radius: 999px; animation: ps-loader 1.5s infinite ease-in-out; }
                @keyframes ps-draw { from { stroke-dashoffset: 100; } to { stroke-dashoffset: 0; } }
                @keyframes ps-show { from { opacity: 0; } to { opacity: 1; } }
                @keyframes ps-pop { 0% { opacity: 0; transform: scale(.4); } 60% { opacity: 1; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }
                @keyframes ps-settle { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.07); } }
                @keyframes ps-loader {
                    0% { transform: translateX(-100%); }
                    50% { transform: translateX(100%); }
                    100% { transform: translateX(300%); }
                }
                @media (prefers-reduced-motion: reduce) {
                    #pre-vue-splash .ps-arc, #pre-vue-splash .ps-head, #pre-vue-splash .ps-check, #pre-vue-splash .ps-settle, #pre-vue-splash .ps-bar-fill { animation: none; }
                    #pre-vue-splash .ps-arc, #pre-vue-splash .ps-check { stroke-dasharray: none; }
                }
            </style>
            <script @if (\Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" @endif>
                if (window.electronAPI || navigator.userAgent.includes('Electron')) {
                    document.getElementById('pre-vue-splash').style.display = 'none';
                }
            </script>
        </div>
    </div>
</body>
</html>
