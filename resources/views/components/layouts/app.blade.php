<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $siteCompanyName = \App\Models\Setting::get('company_name', 'سرور ERP');
        $siteSubtitle = \App\Models\Setting::get('company_subtitle', 'لإدارة المبيعات والمخزون والتوزيع');
    @endphp
    <title>{{ $title ?? $siteCompanyName }} | {{ $siteCompanyName }} - {{ $siteSubtitle }}</title>
    
    <!-- Theme & Sidebar Early Initialization (Zero-lag / Anti-flicker) -->
    <script>
        (function() {
            try {
                const localPref = localStorage.getItem('theme');
                const userPref = "{{ auth()->user()->theme_preference ?? '' }}";
                const theme = localPref || userPref || 'dark';
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }

                const isPos = {{ request()->routeIs('invoices.create') ? 'true' : 'false' }};
                const isCollapsed = isPos || (localStorage.getItem('sidebar_collapsed') === 'true');
                if (isCollapsed) {
                    document.documentElement.classList.add('sidebar-collapsed');
                } else {
                    document.documentElement.classList.remove('sidebar-collapsed');
                }
            } catch (e) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Google Fonts: Cairo & Tajawal -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Favicon & PWA Icons -->
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $siteCompanyName }}">

    <!-- Flatpickr (Modern Date Picker with Arabic Locale) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>

    <!-- Tailwind CSS CDN & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'Tajawal', 'sans-serif'],
                        tajawal: ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        dark: {
                            800: '#1e293b',
                            850: '#172033',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        };
    </script>
    
    <style>
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        [x-cloak] { display: none !important; }
        
        /* Instant Zero-flicker Sidebar (Zero Layout Shifts & Zero Font Resizing) */
        @media (min-width: 1024px) {
            html.sidebar-collapsed aside#main-sidebar {
                width: 6rem !important; /* w-24 */
            }
            html:not(.sidebar-collapsed) aside#main-sidebar {
                width: 18rem !important; /* w-72 */
            }
            
            /* Desktop Collapsed state rules */
            html.sidebar-collapsed .sidebar-text-full {
                display: none !important;
            }
            html.sidebar-collapsed .sidebar-text-mini {
                display: block !important;
            }
            html.sidebar-collapsed .sidebar-nav-item {
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 0.5rem 0.25rem !important;
                gap: 0.25rem !important;
                text-align: center !important;
                border-radius: 1rem !important;
            }
            html.sidebar-collapsed .sidebar-pos-btn {
                flex-direction: column !important;
                justify-content: center !important;
                padding: 0.5rem 0.25rem !important;
                gap: 0.25rem !important;
                text-align: center !important;
            }
            html.sidebar-collapsed .sidebar-divider-full {
                display: none !important;
            }
            html.sidebar-collapsed .sidebar-divider-mini {
                display: block !important;
            }
            html.sidebar-collapsed .sidebar-user-info {
                display: none !important;
            }
            html.sidebar-collapsed .sidebar-user-link {
                justify-content: center !important;
                width: 100% !important;
            }
            html.sidebar-collapsed .sidebar-logout-btn {
                display: none !important;
            }

            /* Desktop Expanded state rules */
            html:not(.sidebar-collapsed) .sidebar-text-full {
                display: block !important;
            }
            html:not(.sidebar-collapsed) .sidebar-text-mini {
                display: none !important;
            }
            html:not(.sidebar-collapsed) .sidebar-nav-item {
                flex-direction: row !important;
                align-items: center !important;
                padding: 0.625rem 0.75rem !important;
                gap: 0.75rem !important;
                text-align: right !important;
                border-radius: 0.75rem !important;
            }
            html:not(.sidebar-collapsed) .sidebar-pos-btn {
                flex-direction: row !important;
                justify-content: center !important;
                padding: 0.75rem 1rem !important;
                gap: 0.5rem !important;
            }
            html:not(.sidebar-collapsed) .sidebar-divider-full {
                display: block !important;
            }
            html:not(.sidebar-collapsed) .sidebar-divider-mini {
                display: none !important;
            }
            html:not(.sidebar-collapsed) .sidebar-user-info {
                display: block !important;
            }
            html:not(.sidebar-collapsed) .sidebar-user-link {
                gap: 0.625rem !important;
            }
            html:not(.sidebar-collapsed) .sidebar-logout-btn {
                display: block !important;
            }
        }

        /* Mobile drawer rules */
        @media (max-width: 1023px) {
            .sidebar-text-full {
                display: block !important;
            }
            .sidebar-text-mini {
                display: none !important;
            }
            .sidebar-nav-item {
                flex-direction: row !important;
                align-items: center !important;
                padding: 0.625rem 0.75rem !important;
                gap: 0.75rem !important;
                text-align: right !important;
                border-radius: 0.75rem !important;
            }
            .sidebar-pos-btn {
                flex-direction: row !important;
                justify-content: center !important;
                padding: 0.75rem 1rem !important;
                gap: 0.5rem !important;
            }
            .sidebar-divider-full {
                display: block !important;
            }
            .sidebar-divider-mini {
                display: none !important;
            }
            .sidebar-user-info {
                display: block !important;
            }
            .sidebar-user-link {
                gap: 0.625rem !important;
            }
            .sidebar-logout-btn {
                display: block !important;
            }
        }

        aside#main-sidebar.has-transition {
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        html.dark ::-webkit-scrollbar-track {
            background: #0f172a;
        }
        html:not(.dark) ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        html.dark ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* 🚀 Top Page Loading Progress Bar Animation */
        #top-loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            z-index: 999999;
            pointer-events: none;
            width: 0%;
            background: linear-gradient(90deg, #d97706, #f59e0b, #10b981, #d97706);
            background-size: 200% 100%;
            transition: width 0.25s ease, opacity 0.3s ease;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.7);
        }
        #top-loading-bar.active {
            opacity: 1;
            animation: progressGlow 1.2s ease infinite;
        }
        @keyframes progressGlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* 📅 Custom Flatpickr Arabic & Dark Theme Styling */
        .flatpickr-calendar {
            font-family: 'Cairo', 'Tajawal', sans-serif !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
            direction: rtl !important;
            padding: 10px !important;
            width: 310px !important;
        }
        html.dark .flatpickr-calendar {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
            color: #f8fafc !important;
        }
        html:not(.dark) .flatpickr-calendar {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important;
        }
        .flatpickr-months {
            padding: 4px 0 !important;
        }
        .flatpickr-months .flatpickr-month {
            height: 38px !important;
        }
        html.dark .flatpickr-month,
        html.dark .flatpickr-weekdays,
        html.dark span.flatpickr-weekday {
            background: #0f172a !important;
            color: #94a3b8 !important;
            fill: #f8fafc !important;
            font-weight: bold !important;
        }
        .flatpickr-current-month {
            font-size: 110% !important;
            padding-top: 4px !important;
        }
        .flatpickr-current-month .cur-month {
            font-weight: 800 !important;
            margin: 0 4px !important;
        }
        html.dark .flatpickr-current-month input.cur-year {
            color: #f8fafc !important;
            font-weight: 800 !important;
        }
        .flatpickr-monthDropdown-months {
            background: #1e293b !important;
            color: #f8fafc !important;
            border-radius: 0.5rem !important;
            padding: 2px 6px !important;
            font-weight: bold !important;
        }
        .flatpickr-monthDropdown-months option {
            background-color: #0f172a !important;
            color: #f8fafc !important;
        }
        .flatpickr-prev-month, .flatpickr-next-month {
            padding: 6px !important;
            border-radius: 0.5rem !important;
        }
        html.dark .flatpickr-prev-month, html.dark .flatpickr-next-month {
            color: #f8fafc !important;
            fill: #f8fafc !important;
        }
        .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg {
            fill: #f59e0b !important;
        }
        .flatpickr-day {
            color: inherit !important;
            border-radius: 0.6rem !important;
            font-weight: 600 !important;
            height: 36px !important;
            line-height: 36px !important;
            margin: 2px 0 !important;
        }
        html.dark .flatpickr-day {
            color: #e2e8f0 !important;
        }
        html.dark .flatpickr-day:hover,
        html.dark .flatpickr-day:focus {
            background: #1e293b !important;
            border-color: #475569 !important;
        }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #d97706 !important;
            border-color: #d97706 !important;
            color: #ffffff !important;
            font-weight: 900 !important;
            border-radius: 0.6rem !important;
        }
        .flatpickr-day.today {
            border-color: #f59e0b !important;
            font-weight: 800 !important;
        }
        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.prevMonthDay,
        .flatpickr-day.nextMonthDay {
            color: #475569 !important;
            opacity: 0.4 !important;
        }

        /* 🖨️ Universal High-End A4 Print Optimization */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 10mm 15mm 10mm;
            }
            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Cairo', 'Tajawal', sans-serif !important;
                font-size: 11pt !important;
                line-height: 1.3 !important;
                overflow: visible !important;
                height: auto !important;
            }
            /* Hide all non-printable UI elements */
            aside#main-sidebar,
            header,
            #top-loading-bar,
            .sidebar-nav-item,
            button,
            .no-print,
            [onclick*="print"],
            [onclick*="window.print"],
            .pwa-install,
            .swal2-container,
            [wire\:loading],
            input[type="button"],
            input[type="submit"] {
                display: none !important;
            }
            /* Reset container widths and remove background colors / dark theme overrides */
            main, .main-content-container, .container {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
                border: none !important;
            }
            /* High-contrast crisp tables for paper */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
            th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                border: 1.5px solid #000000 !important;
                font-weight: 900 !important;
                padding: 6px 8px !important;
                text-align: center !important;
            }
            td {
                border: 1px solid #333333 !important;
                color: #000000 !important;
                padding: 5px 8px !important;
                background: #ffffff !important;
            }
            tr:nth-child(even) td {
                background: #f8fafc !important;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-shadow: none !important;
                text-shadow: none !important;
            }
            .print-only {
                display: block !important;
            }
        }
        @media not print {
            .print-only {
                display: none !important;
            }
        }
    </style>
    @livewireStyles
</head>
<body class="h-full bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex overflow-hidden selection:bg-amber-500 selection:text-white" 
      x-data="{ 
          sidebarOpen: false, 
          sidebarCollapsed: {{ request()->routeIs('invoices.create') ? 'true' : "(localStorage.getItem('sidebar_collapsed') === 'true')" }},
          hasTransition: false,
          toggleSidebar() {
              this.hasTransition = true;
              this.sidebarCollapsed = !this.sidebarCollapsed;
              localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
              if (this.sidebarCollapsed) {
                  document.documentElement.classList.add('sidebar-collapsed');
              } else {
                  document.documentElement.classList.remove('sidebar-collapsed');
              }
          }
      }">

    <!-- 🌟 Top Page Loading Progress Bar -->
    <div id="top-loading-bar"></div>

    <!-- ⚡ Floating Livewire Action Loading Badge -->
    <div wire:loading class="fixed bottom-5 left-5 z-[99999] bg-white/95 dark:bg-slate-900/95 border border-amber-500/50 text-amber-600 dark:text-amber-300 px-4 py-2.5 rounded-2xl shadow-2xl shadow-slate-400/30 dark:shadow-black/90 flex items-center gap-3 text-xs font-bold font-tajawal backdrop-blur-md">
        <svg class="animate-spin h-4 w-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>جاري التحميل والمعالجة...</span>
    </div>

    @auth
        @php
            $currentStore = auth()->user()?->getCurrentStore();
            $availableStores = auth()->user()?->hasRole('admin') 
                ? \App\Models\Store::where('is_active', true)->orderBy('is_main', 'desc')->get()
                : auth()->user()?->stores()->where('is_active', true)->get();
            if ($availableStores && $availableStores->isEmpty() && $currentStore) {
                $availableStores = collect([$currentStore]);
            }

            $shiftService = app(\App\Services\ShiftService::class);
            $activeShift = $shiftService->getActiveShift(storeId: $currentStore?->id);
            $isOverdueShift = false;
            $shiftDurationHours = 0;
            if ($activeShift && $activeShift->opened_at) {
                $shiftDurationHours = now()->diffInHours($activeShift->opened_at);
                if ($activeShift->opened_at->diffInDays(now()) >= 1 || $shiftDurationHours >= 24) {
                    $isOverdueShift = true;
                }
            }
        @endphp

        <!-- Mobile sidebar backdrop -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"></div>

        <!-- Sidebar Navigation (Collapsible - Zero Flickering) -->
        <aside 
            id="main-sidebar"
            :class="{
                'translate-x-0': sidebarOpen,
                'translate-x-full lg:translate-x-0': !sidebarOpen,
                'has-transition': hasTransition
            }"
            class="w-72 fixed lg:static inset-y-0 right-0 z-50 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex flex-col shadow-xl lg:shadow-none select-none shrink-0"
        >
            <!-- Brand Header -->
            <div class="h-16 px-2.5 flex items-center justify-between border-b border-slate-200 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-900/50 backdrop-blur-md overflow-hidden">
                <div class="flex items-center gap-2 min-w-0">
                    <a href="{{ route('dashboard') }}" title="{{ $siteCompanyName }}" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 p-1 flex items-center justify-center shadow-md shadow-amber-500/10 border border-slate-200 dark:border-slate-700 shrink-0">
                        <img src="{{ asset('logo.png') }}" alt="سرور POS" class="w-full h-full object-contain">
                    </a>
                    <div class="sidebar-text-full truncate min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h1 class="font-extrabold text-sm tracking-tight text-slate-900 dark:text-white font-tajawal truncate">
                                {{ $siteCompanyName }}
                            </h1>
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-400 font-mono font-black border border-amber-500/30">v1.2.5</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $siteSubtitle }}</p>
                    </div>
                </div>

                <div class="flex items-center shrink-0">
                    <!-- Desktop Collapse/Expand Toggle Button -->
                    <button 
                        @click="toggleSidebar()" 
                        type="button"
                        class="hidden lg:flex p-1.5 rounded-xl text-slate-400 hover:text-amber-600 hover:bg-amber-500/10 dark:hover:bg-slate-800 transition-all cursor-pointer"
                        :title="sidebarCollapsed ? 'توسيع القائمة الجانبية' : 'تصغير القائمة الجانبية'"
                    >
                        <svg class="w-5 h-5 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                        </svg>
                    </button>

                    <!-- Mobile Close Button -->
                    <button @click="sidebarOpen = false" class="lg:hidden p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Quick POS Button (pos.access) -->
            @can('pos.access')
            <div class="p-2 border-b border-slate-200 dark:border-slate-800/60">
                <a href="{{ route('invoices.create') }}" 
                   title="فاتورة بيع جديدة (F2)"
                   class="w-full flex items-center sidebar-pos-btn bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-500 hover:to-amber-500 text-white font-bold rounded-2xl shadow-lg shadow-amber-600/30 transition-all duration-200 active:scale-95 group font-tajawal cursor-pointer"
                >
                    <svg class="w-6 h-6 shrink-0 transition-transform group-hover:rotate-90 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    <span class="sidebar-text-full truncate text-sm font-bold">فاتورة بيع جديدة (F2)</span>
                    <span class="sidebar-text-mini text-[10px] font-black truncate max-w-full">بيع جديد</span>
                </a>
            </div>
            @endcan

            <!-- Nav Links -->
            <nav class="flex-1 px-2 py-2.5 space-y-1.5 overflow-y-auto">
                <!-- لوحة التحكم -->
                <div>
                    <a href="{{ route('dashboard') }}" 
                       title="لوحة التحكم (Dashboard)"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('dashboard') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">لوحة التحكم (Dashboard)</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الرئيسية</span>
                    </a>
                </div>

                <!-- المبيعات والفواتير -->
                @if(auth()->user()?->can('invoices.view') || auth()->user()?->can('daily_journal.view') || auth()->user()?->can('customers.manage'))
                <div class="sidebar-divider-full pt-3 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 truncate">المبيعات والفواتير</div>
                <div class="sidebar-divider-mini my-1.5 border-t border-slate-200 dark:border-slate-800/80 mx-1"></div>

                @can('invoices.view')
                <div>
                    <a href="{{ route('invoices.index') }}" 
                       title="فواتير المبيعات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('invoices.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">فواتير المبيعات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">المبيعات</span>
                    </a>
                </div>
                @endcan

                @can('daily_journal.view')
                <div>
                    <a href="{{ route('daily.journal') }}" 
                       title="اليومية وحركة الدرج"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('daily.journal') || request()->routeIs('shifts.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <div class="relative shrink-0">
                            <svg class="w-6 h-6 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            @if($activeShift)
                            <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full {{ $isOverdueShift ? 'bg-rose-500 animate-ping' : 'bg-emerald-500 animate-pulse' }}"></span>
                            @endif
                        </div>
                        <div class="sidebar-text-full flex-1 flex items-center justify-between min-w-0">
                            <span class="truncate text-sm font-semibold">اليومية وحركة الدرج</span>
                            @if($activeShift)
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md font-bold {{ $isOverdueShift ? 'bg-rose-500 text-white animate-pulse' : 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' }}">
                                {{ $isOverdueShift ? 'متأخرة (+24h)' : 'مفتوحة' }}
                            </span>
                            @endif
                        </div>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">اليومية</span>
                    </a>
                </div>
                @endcan

                @can('customers.manage')
                <div>
                    <a href="{{ route('customers.index') }}" 
                       title="العملاء والحسابات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('customers.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">العملاء والحسابات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">العملاء</span>
                    </a>
                </div>
                @endcan
                @endif

                <!-- المخزون والفروع والتوزيع -->
                @if(auth()->user()?->can('items.view') || auth()->user()?->can('transfers.view') || auth()->user()?->can('stores.manage') || auth()->user()?->can('purchases.view') || auth()->user()?->can('suppliers.manage'))
                <div class="sidebar-divider-full pt-3 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 truncate">المخزون والفروع والتوزيع</div>
                <div class="sidebar-divider-mini my-1.5 border-t border-slate-200 dark:border-slate-800/80 mx-1"></div>

                @can('items.view')
                <div>
                    <a href="{{ route('items.index') }}" 
                       title="الأصناف والأسعار العامة"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('items.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">الأصناف والأسعار</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الأصناف</span>
                    </a>
                </div>

                <div>
                    <a href="{{ route('store-stocks') }}" 
                       title="جرد وأأسعار الفروع"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('store-stocks') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">جرد وأسعار الفروع</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">جرد الفروع</span>
                    </a>
                </div>
                @endcan

                @can('transfers.view')
                <div>
                    <a href="{{ route('stock-transfers') }}" 
                       title="أذونات التحويل والشحن"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('stock-transfers*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">أذونات التحويل والشحن</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">التحويلات</span>
                    </a>
                </div>
                @endcan

                @can('stores.manage')
                <div>
                    <a href="{{ route('stores') }}" 
                       title="الفروع وعربات التوزيع"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('stores') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">الفروع وعربات التوزيع</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الفروع</span>
                    </a>
                </div>
                @endcan

                @can('purchases.view')
                <div>
                    <a href="{{ route('purchases.index') }}" 
                       title="فواتير المشتريات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('purchases.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">فواتير المشتريات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">المشتريات</span>
                    </a>
                </div>
                @endcan

                @can('suppliers.manage')
                <div>
                    <a href="{{ route('suppliers.index') }}" 
                       title="الموردون والشركات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('suppliers.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">الموردون والشركات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الموردون</span>
                    </a>
                </div>
                @endcan
                @endif

                <!-- المرتجعات والمصروفات والتقارير -->
                @if(auth()->user()?->can('expenses.manage') || auth()->user()?->can('returns.manage') || auth()->user()?->can('reports.view'))
                <div class="sidebar-divider-full pt-3 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 truncate">المرتجعات والمصروفات والتقارير</div>
                <div class="sidebar-divider-mini my-1.5 border-t border-slate-200 dark:border-slate-800/80 mx-1"></div>

                @can('expenses.manage')
                <div>
                    <a href="{{ route('expenses.index') }}" 
                       title="المصروفات والنثريات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('expenses.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">المصروفات والنثريات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">المصروفات</span>
                    </a>
                </div>
                @endcan

                @can('returns.manage')
                <div>
                    <a href="{{ route('returns.index') }}" 
                       title="سجل المرتجعات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('returns.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">سجل المرتجعات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">المرتجعات</span>
                    </a>
                </div>
                @endcan

                @can('reports.view')
                <div>
                    <a href="{{ route('reports.index') }}" 
                       title="التقارير المالية والأرباح"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('reports.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">التقارير المالية والأرباح</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">التقارير</span>
                    </a>
                </div>
                @endcan
                @endif

                <!-- إدارة النظام والمستخدمين -->
                @if(auth()->user()?->can('roles.manage') || auth()->user()?->can('trash.access') || auth()->user()?->can('logs.view'))
                <div class="sidebar-divider-full pt-3 pb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 truncate">إدارة النظام والمستخدمين</div>
                <div class="sidebar-divider-mini my-1.5 border-t border-slate-200 dark:border-slate-800/80 mx-1"></div>

                @can('roles.manage')
                <div>
                    <a href="{{ route('users.index') }}" 
                       title="المستخدمون والكاشير"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('users.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">المستخدمون والكاشير</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">المستخدمين</span>
                    </a>
                </div>

                <div>
                    <a href="{{ route('roles.index') }}" 
                       title="الأدوار ومجموعات الصلاحيات"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('roles.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">الأدوار والصلاحيات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الصلاحيات</span>
                    </a>
                </div>
                @endcan

                @can('logs.view')
                <div>
                    <a href="{{ route('activity-logs.index') }}" 
                       title="سجل العمليات والرقابة"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('activity-logs.*') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">سجل العمليات والرقابة</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الرقابة</span>
                    </a>
                </div>
                @endcan

                @if(auth()->user()?->hasRole('admin'))
                <div>
                    <a href="/pulse" target="_blank"
                       title="مراقبة أداء وسرعة السيرفر (Pulse)"
                       class="flex sidebar-nav-item transition-all text-purple-600 dark:text-purple-400 hover:bg-purple-500/10 hover:text-purple-700 dark:hover:text-purple-300"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">مراقبة السيرفر Pulse</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-purple-600 dark:text-purple-400">Pulse</span>
                    </a>
                </div>
                @endif

                @can('trash.access')
                <div>
                    <a href="{{ route('trash.index') }}" 
                       title="سلة المحذوفات المركزية"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('trash.index') ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/40 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400' }}"
                    >
                        <svg class="w-6 h-6 text-rose-500 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">سلة المحذوفات</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-rose-600 dark:text-rose-400">المحذوفات</span>
                    </a>
                </div>
                @endcan
                @endif

                @if(auth()->user()?->hasRole('admin'))
                <div>
                    <a href="{{ route('settings.index') }}" 
                       title="إعدادات النظام والطباعة والنسخ الاحتياطي"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('settings.index') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">إعدادات النظام والطباعة</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الإعدادات</span>
                    </a>
                </div>
                @endif

                <div>
                    <a href="{{ route('profile') }}" 
                       title="الملف الشخصي والأمان"
                       class="flex sidebar-nav-item transition-all {{ request()->routeIs('profile') ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <svg class="w-6 h-6 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span class="sidebar-text-full truncate text-sm font-semibold">الملف الشخصي والأمان</span>
                        <span class="sidebar-text-mini text-[10.5px] font-bold truncate max-w-full text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">الحساب</span>
                    </a>
                </div>

                <!-- PWA Mobile Install Button (Clean SVG Icon) -->
                <div class="pt-1.5">
                    <button onclick="triggerPwaInstall()" type="button" 
                            title="تثبيت التطبيق (PWA)"
                            class="w-full flex items-center sidebar-nav-item bg-gradient-to-r from-amber-600/15 via-amber-500/20 to-amber-600/15 hover:from-amber-600/30 hover:to-amber-500/30 text-amber-600 dark:text-amber-400 font-bold rounded-xl text-xs border border-amber-500/30 shadow-sm transition-all cursor-pointer"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span class="sidebar-text-full truncate">تثبيت التطبيق (PWA)</span>
                        <span class="sidebar-text-mini text-[10px] font-black truncate max-w-full">تثبيت PWA</span>
                    </button>
                </div>
            </nav>

            <!-- User Info & Logout -->
            <div class="p-2.5 border-t border-slate-200 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-900/60 flex items-center justify-between overflow-hidden">
                <a href="{{ route('profile') }}" title="{{ auth()->user()->name ?? 'مستخدم' }}" class="flex items-center sidebar-user-link min-w-0 hover:opacity-80 transition-opacity">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-600 dark:text-amber-300 flex items-center justify-center font-bold text-xs shrink-0 shadow-inner">
                        {{ mb_substr(auth()->user()->name ?? 'م', 0, 1) }}
                    </div>
                    <div class="sidebar-user-info min-w-0 truncate">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ auth()->user()->name ?? 'مستخدم' }}</p>
                        <p class="text-[10px] text-amber-600 dark:text-amber-400/80 truncate font-semibold">
                            @if(auth()->user()->hasRole('admin')) المدير العام
                            @elseif(auth()->user()->hasRole('cashier')) كاشير
                            @elseif(auth()->user()->hasRole('storekeeper')) أمين مخزن
                            @elseif(auth()->user()->hasRole('accountant')) محاسب
                            @else مستخدم @endif
                        </p>
                    </div>
                </a>

                <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-btn">
                    @csrf
                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors cursor-pointer" title="تسجيل الخروج">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Top App Bar -->
            <header class="h-16 bg-white/90 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 lg:px-8 shrink-0 z-30 shadow-sm">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger -->
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    <!-- Desktop Collapse Switcher in Header -->
                    <button 
                        @click="toggleSidebar()" 
                        type="button" 
                        class="hidden lg:flex p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-amber-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        :title="sidebarCollapsed ? 'توسيع القائمة' : 'تصغير القائمة'"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                        </svg>
                    </button>

                    <div class="text-sm font-semibold text-slate-600 dark:text-slate-300 hidden sm:flex items-center gap-2">
                        <span>📅 {{ now()->translatedFormat('l, d F Y') }}</span>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <span class="text-amber-600 dark:text-amber-400 font-mono" x-data="{ time: new Date().toLocaleTimeString('ar-EG') }" x-init="setInterval(() => time = new Date().toLocaleTimeString('ar-EG'), 1000)" x-text="time"></span>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2 sm:gap-3" x-data="{ userMenuOpen: false }">
                    
                    <!-- 🟢 / 🚨 Shift / Daily Journal Status Button in Header -->
                    @if(auth()->user()?->can('daily_journal.view') || auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('cashier'))
                        @if($activeShift)
                            @if($isOverdueShift)
                            <a href="{{ route('daily.journal') }}" 
                               class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/50 text-rose-700 dark:text-rose-300 font-bold text-xs shadow-md shadow-rose-500/20 animate-pulse transition-all cursor-pointer shrink-0"
                               title="تحذير: الوردية واليومية مفتوحة منذ أكثر من 24 ساعة! اضغط لتقفيل اليومية ومطابقة الدرج">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                                <span class="font-mono">🚨 اليومية متأخرة (+24h)</span>
                                <span class="hidden sm:inline text-[10px] bg-rose-600 text-white px-1.5 py-0.5 rounded-md font-black">تقفيل</span>
                            </a>
                            @else
                            <a href="{{ route('daily.journal') }}" 
                               class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 font-bold text-xs transition-all cursor-pointer shrink-0"
                               title="اليومية مفتوحة وشغالة حالياً (#{{ $activeShift->shift_number }}) - اضغط لمتابعة الدرج أو التقفيل">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="hidden xs:inline">اليومية شغالة</span>
                                <span class="text-[10px] bg-emerald-600/20 text-emerald-800 dark:text-emerald-300 font-mono font-black px-1.5 py-0.5 rounded">#{{ $activeShift->shift_number }}</span>
                            </a>
                            @endif
                        @else
                            <a href="{{ route('daily.journal') }}" 
                               class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-amber-500/10 hover:border-amber-500/30 text-slate-600 dark:text-slate-300 hover:text-amber-600 dark:hover:text-amber-400 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-all cursor-pointer shrink-0"
                               title="الوردية مغلقة حالياً - اضغط لفتح يومية جديدة">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span class="hidden xs:inline">الوردية مغلقة</span>
                                <span class="text-[10px] text-amber-600 dark:text-amber-400 font-black">+ فتح</span>
                            </a>
                        @endif
                    @endif

                    <!-- 🏬 Store / Van Switcher in Header -->
                    @if($currentStore)
                    <div class="relative" x-data="{ storeMenuOpen: false }">
                        <button
                            @click="storeMenuOpen = !storeMenuOpen"
                            type="button"
                            class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 font-bold text-xs transition-all cursor-pointer shrink-0 max-w-[130px] sm:max-w-none"
                            title="تبديل الفرع أو عربية التوزيع النشطة"
                        >
                            <span>
                                @if($currentStore->type === 'wholesale_van') 🚚 @elseif($currentStore->type === 'main_warehouse') 🏢 @else 🏬 @endif
                            </span>
                            <span class="truncate max-w-[100px] sm:max-w-[140px]">{{ $currentStore->name }}</span>
                            @if($availableStores && $availableStores->count() > 1)
                            <span class="text-[10px] text-emerald-500">▼</span>
                            @endif
                        </button>

                        @if($availableStores && $availableStores->count() > 1)
                        <div
                            x-show="storeMenuOpen"
                            @click.away="storeMenuOpen = false"
                            x-cloak
                            class="absolute left-0 mt-2 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl py-2 z-50 divide-y divide-slate-100 dark:divide-slate-800/80 font-sans"
                        >
                            <div class="px-3 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                الفرع / عربية التوزيع النشطة:
                            </div>
                            <div class="py-1 max-h-60 overflow-y-auto">
                                @foreach($availableStores as $stOption)
                                <button
                                    type="button"
                                    onclick="switchGlobalStore({{ $stOption->id }})"
                                    class="w-full text-right flex items-center justify-between px-3 py-2 text-xs transition-colors cursor-pointer {{ (int)$currentStore->id === (int)$stOption->id ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-black' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}"
                                >
                                    <div class="flex items-center gap-2 truncate">
                                        <span>@if($stOption->type === 'wholesale_van') 🚚 @elseif($stOption->type === 'main_warehouse') 🏢 @else 🏬 @endif</span>
                                        <span class="truncate">{{ $stOption->name }}</span>
                                    </div>
                                    @if((int)$currentStore->id === (int)$stOption->id)
                                    <span class="text-xs text-emerald-600 font-black">✓</span>
                                    @endif
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- 🔔 Live Notification Center -->
                    <livewire:notification-center />

                    <!-- 🏷️ System Version Badge (Always Visible in Header) -->
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 font-mono text-xs font-black shadow-sm shrink-0">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>v1.2.5 • لايف</span>
                    </div>

                    <!-- ☀️ / 🌙 Theme Toggle Button -->
                    <button
                        type="button"
                        onclick="toggleAppTheme()"
                        class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/90 dark:hover:bg-slate-700/80 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition-all cursor-pointer shadow-sm active:scale-95 shrink-0"
                        title="تبديل الوضع النهاري / الليلي"
                    >
                        <span class="dark:hidden flex items-center gap-1 text-slate-800 font-bold">
                            <span class="text-sm">🌙</span>
                            <span class="hidden sm:inline">الوضع الليلي</span>
                        </span>
                        <span class="hidden dark:flex items-center gap-1 text-amber-400 font-bold">
                            <span class="text-sm">☀️</span>
                            <span class="hidden sm:inline">الوضع النهاري</span>
                        </span>
                    </button>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button
                            @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-1.5 sm:gap-2.5 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700/60 text-xs transition-colors cursor-pointer text-slate-800 dark:text-slate-200 max-w-[140px] sm:max-w-none"
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                            <span class="font-bold truncate">{{ auth()->user()->name ?? 'المدير العام' }}</span>
                            <span class="text-slate-400 text-[10px] shrink-0">▼</span>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            x-show="userMenuOpen"
                            @click.away="userMenuOpen = false"
                            x-cloak
                            class="absolute left-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl py-2 z-50 divide-y divide-slate-100 dark:divide-slate-800/80 font-sans"
                        >
                            <div class="px-4 py-2 text-xs">
                                <p class="font-bold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono" dir="ltr">{{ auth()->user()->phone ?? auth()->user()->email }}</p>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors">
                                    <span>⚙️</span>
                                    <span>الملف الشخصي والأمان</span>
                                </a>
                                @can('roles.manage')
                                <a href="{{ route('users.index') }}" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors">
                                    <span>👥</span>
                                    <span>إدارة المستخدمين والصلاحيات</span>
                                </a>
                                <a href="{{ route('roles.index') }}" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors">
                                    <span>🛡️</span>
                                    <span>مصفوفة الصلاحيات والأدوار</span>
                                </a>
                                @endcan
                                @can('logs.view')
                                <a href="{{ route('activity-logs.index') }}" class="flex items-center gap-2 px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition-colors">
                                    <span>📜</span>
                                    <span>سجل العمليات والرقابة</span>
                                </a>
                                @endcan
                            </div>
                            <div class="pt-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-xs text-rose-500 hover:bg-rose-500/10 transition-colors cursor-pointer text-right font-bold">
                                        <span>🚪</span>
                                        <span>تسجيل الخروج</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 🚨 Overdue Shift Global Alert Banner (> 24 Hours) -->
            @if($isOverdueShift && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('cashier') || auth()->user()?->can('daily_journal.view')))
            <div class="bg-gradient-to-r from-rose-700 via-rose-600 to-rose-700 text-white px-4 py-2.5 shadow-md flex flex-col sm:flex-row items-center justify-between gap-2 text-xs font-bold shrink-0 z-20 border-b border-rose-800 animate-pulse">
                <div class="flex items-center gap-2 text-center sm:text-right">
                    <span class="text-base">🚨</span>
                    <span>
                        <strong>إنذار تقفيل اليومية:</strong> الوردية رقم (<span class="font-mono underline">{{ $activeShift->shift_number }}</span>) مفتوحة منذ أكثر من 24 ساعة (منذ {{ $activeShift->opened_at->translatedFormat('l d F - h:i A') }}). يرجى مراجعة الخزينة وتقفيل اليومية ومطابقة العهدة!
                    </span>
                </div>
                <a href="{{ route('daily.journal') }}" class="px-3.5 py-1.5 bg-white text-rose-700 hover:bg-rose-100 rounded-xl text-xs font-black transition-all shadow shrink-0 cursor-pointer">
                    الانتقال لتقفيل اليومية الآن ←
                </a>
            </div>
            @endif

            <!-- Dynamic Body Page Content -->
            <main class="flex-1 overflow-y-auto bg-slate-100 dark:bg-slate-950 p-4 lg:p-6 text-slate-800 dark:text-slate-100 flex flex-col justify-between">
                <div class="flex-1">
                    {{ $slot }}
                </div>

                <!-- 🌟 System Footer with Version -->
                <footer class="mt-8 pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-mono font-black border border-emerald-500/30">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            الإصدار: v1.2.5 • لايف
                        </span>
                        <span class="font-bold">نظام سرور كوفي ERP</span>
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono">
                        Server Active • {{ date('Y-m-d') }}
                    </div>
                </footer>
            </main>
        </div>
    @else
        <!-- Guest View (Full screen Login) -->
        <main class="flex-1 min-h-screen bg-slate-100 dark:bg-slate-950">
            {{ $slot }}
        </main>
    @endauth

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-start',
            showConfirmButton: false,
            timer: 2800,
            timerProgressBar: true,
            background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
            color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
            customClass: {
                popup: 'border border-slate-300 dark:border-slate-700 shadow-2xl rounded-2xl font-sans'
            },
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });

        // 🏬 Global Fast Store Switcher
        window.switchGlobalStore = function(storeId) {
            fetch('{{ route('store.switch') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ store_id: storeId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                }
            })
            .catch(err => console.error('Store switch error:', err));
        };

        // Flash session messages on load
        @if (session()->has('success'))
            document.addEventListener('DOMContentLoaded', () => {
                Toast.fire({
                    icon: 'success',
                    title: '{{ session('success') }}'
                });
            });
        @endif

        @if (session()->has('error'))
            document.addEventListener('DOMContentLoaded', () => {
                Toast.fire({
                    icon: 'error',
                    title: '{{ session('error') }}'
                });
            });
        @endif

        // Dynamic Livewire event listeners
        window.addEventListener('swal:toast', event => {
            const detail = Array.isArray(event.detail) ? event.detail[0] : (event.detail || {});
            Toast.fire({
                icon: detail.icon || detail.type || 'success',
                title: detail.title || detail.message || 'تمت العملية بنجاح'
            });
        });

        window.addEventListener('swal:alert', event => {
            const detail = Array.isArray(event.detail) ? event.detail[0] : (event.detail || {});
            Swal.fire({
                icon: detail.icon || detail.type || 'info',
                title: detail.title || 'إشعار',
                text: detail.text || detail.message || '',
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#d97706',
                background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
                customClass: {
                    popup: 'border border-slate-300 dark:border-slate-700 shadow-2xl rounded-2xl font-sans'
                }
            });
        });

        // ⌨️ Global Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            // F2: Open New Invoice (POS) from any screen
            if (e.key === 'F2') {
                e.preventDefault();
                if (window.location.pathname !== '/invoices/create') {
                    window.location.href = "{{ route('invoices.create') }}";
                } else {
                    const searchInput = document.querySelector('input[placeholder*="ابحث"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
            }
        });

        // ☀️ / 🌙 Theme Toggle Controller (Instant + Backend Persistence)
        window.toggleAppTheme = function() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';

            if (newTheme === 'dark') {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }

            try {
                localStorage.setItem('theme', newTheme);
            } catch(e) {}

            // Save to logged-in user in database
            fetch("{{ route('theme.toggle') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ theme: newTheme })
            }).catch(e => console.log('Theme sync error', e));
        };

        function applyStoredTheme() {
            try {
                const stored = localStorage.getItem('theme');
                if (stored === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (stored === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch(e) {}
        }

        window.addEventListener('livewire:navigated', applyStoredTheme);

        window.addEventListener('theme-changed', event => {
            const theme = typeof event.detail === 'string' ? event.detail : (event.detail?.[0] || 'dark');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            try {
                localStorage.setItem('theme', theme);
            } catch(e) {}
        });

        // 🚀 Smooth Top Progress Bar Controller
        const topLoader = document.getElementById('top-loading-bar');
        let loaderTimer;

        function startTopLoader() {
            if (!topLoader) return;
            topLoader.classList.add('active');
            topLoader.style.width = '30%';
            topLoader.style.opacity = '1';
            clearTimeout(loaderTimer);
            loaderTimer = setTimeout(() => {
                topLoader.style.width = '75%';
            }, 180);
        }

        function finishTopLoader() {
            if (!topLoader) return;
            topLoader.style.width = '100%';
            setTimeout(() => {
                topLoader.style.opacity = '0';
                setTimeout(() => {
                    topLoader.classList.remove('active');
                    topLoader.style.width = '0%';
                }, 300);
            }, 150);
        }

        // Hook to page navigation and Livewire commits
        window.addEventListener('beforeunload', () => startTopLoader());

        document.addEventListener('livewire:navigating', () => startTopLoader());
        document.addEventListener('livewire:navigated', () => finishTopLoader());

        document.addEventListener('livewire:init', () => {
            if (window.Livewire) {
                Livewire.hook('commit', ({ succeed, fail }) => {
                    startTopLoader();
                    succeed(() => finishTopLoader());
                    fail(() => finishTopLoader());
                });
            }
        });
    </script>

    <!-- 📲 PWA Install Guide Modal -->
    <div id="pwa-guide-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-md p-6 space-y-4 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('logo.png') }}" class="w-10 h-10 object-contain rounded-xl p-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700" alt="لوجو">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-base font-tajawal">تثبيت تطبيق {{ $siteCompanyName }}</h3>
                        <p class="text-xs text-slate-500">على الشاشة الرئيسية للهاتف أو الكمبيوتر</p>
                    </div>
                </div>
                <button onclick="closePwaModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-bold p-1">✕</button>
            </div>

            <div class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
                <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 space-y-1">
                    <p class="font-bold text-amber-800 dark:text-amber-300 text-sm">📱 لهواتف الأندرويد (Chrome):</p>
                    <p>انقر على زر <strong>"تثبيت التطبيق الآن"</strong> بالأسفل، أو اضغط على قائمة المتصفح (⋮) ثم اختر <strong>"تثبيت التطبيق"</strong> أو <strong>"إضافة إلى الشاشة الرئيسية"</strong>.</p>
                </div>

                <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                    <p class="font-bold text-slate-900 dark:text-white text-sm">🍏 لهواتف الآيفون (Safari):</p>
                    <p>1. اضغط على زر المشاركة بالأسفل <strong>(⎋ Share)</strong> في شريط متصفح سفاري.</p>
                    <p>2. مرر للأسفل واختر <strong>"إضافة إلى الشاشة الرئيسية ➕ (Add to Home Screen)"</strong>.</p>
                </div>

                <div class="p-3 rounded-2xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-1">
                    <p class="font-bold text-slate-900 dark:text-white text-sm">💻 لأجهزة الكمبيوتر (Chrome / Edge):</p>
                    <p>اضغط على أيقونة التثبيت <strong>(⊕ أو 🖥️)</strong> الموجودة في نهاية شريط العنوان بالمتصفح لتثبيته كتطبيق مستقل.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <button onclick="closePwaModal()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl text-xs hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">إغلاق</button>
                <button id="pwa-prompt-btn" onclick="executeNativeInstall()" class="px-5 py-2.5 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-bold rounded-xl text-xs shadow-lg shadow-amber-500/20 cursor-pointer">📲 تثبيت التطبيق الآن</button>
            </div>
        </div>
    </div>

    <script>
        // 📲 PWA Service Worker & Install Prompt Controller
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('PWA beforeinstallprompt ready');
        });

        window.triggerPwaInstall = function() {
            if (deferredPrompt) {
                window.executeNativeInstall();
            } else {
                const modal = document.getElementById('pwa-guide-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.remove('hidden');
                }
            }
        };

        window.executeNativeInstall = async function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                window.closePwaModal();
            } else {
                const modal = document.getElementById('pwa-guide-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.remove('hidden');
                }
            }
        };

        window.closePwaModal = function() {
            const modal = document.getElementById('pwa-guide-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.add('hidden');
            }
        };

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset("sw.js") }}?v={{ time() }}').then(reg => {
                    console.log('SW Registered successfully:', reg.scope);
                }).catch(err => {
                    console.log('SW Registration error:', err);
                });
            });
        }
    </script>
    @livewireScripts
</body>
</html>
