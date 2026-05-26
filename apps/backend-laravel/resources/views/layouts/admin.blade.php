<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Billing Admin' }}</title>
    <!-- Public Sans from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
    
    <script>
        // Apply theme immediately to avoid flash of light mode
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <style>
        :root {
            --font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            
            /* Light Theme */
            --bg-body: #f8f7fa;
            --bg-card: #ffffff;
            --bg-sidebar: #2f3349;
            --text-color: #5d596c;
            --text-heading: #444050;
            --text-muted: #a8aaae;
            --border-color: #dbdade;
            --shadow: 0px 4px 18px 0px rgba(75, 70, 92, 0.1);
            
            /* Spatie/Laravel variables mapped to Vuexy theme HSL style */
            --primary: 115, 103, 240;      /* #7367F0 */
            --secondary: 128, 131, 144;    /* #808390 */
            --success: 40, 199, 111;       /* #28C76F */
            --info: 0, 207, 232;           /* #00CFE8 */
            --warning: 255, 159, 67;       /* #FF9F43 */
            --danger: 234, 84, 85;         /* #EA5455 */
            --primary-rgb: 115, 103, 240;
        }

        [data-theme="dark"] {
            /* Dark Theme */
            --bg-body: #25293c;
            --bg-card: #2f3349;
            --bg-sidebar: #2f3349;
            --text-color: #cfcde4;
            --text-heading: #e1e0ec;
            --text-muted: #7983bb;
            --border-color: #434968;
            --shadow: 0px 4px 18px 0px rgba(15, 10, 30, 0.25);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background: var(--bg-body);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            transition: background 0.15s ease-in-out, color 0.15s ease-in-out;
        }

        /* Layout Structure */
        .app-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar - Dark theme by default (Vuexy standard) */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            color: #dbdade;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, 0.1);
            transition: transform 0.25s ease-in-out;
        }

        .sidebar-brand {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(219, 218, 222, 0.12);
        }

        .sidebar-logo {
            width: 32px;
            height: 32px;
            background: rgb(var(--primary));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .sidebar-title {
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0;
            color: #fff;
        }

        .sidebar-badge {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .sidebar-menu {
            flex-grow: 1;
            padding: 16px 14px;
            overflow-y: auto;
        }

        .sidebar-section-header {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #7983bb;
            margin: 18px 0 8px 10px;
            letter-spacing: 0;
        }

        .sidebar-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            color: #b0acc5;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 3px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-menu-item svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            opacity: 0.85;
            transition: transform 0.15s ease-in-out;
            flex-shrink: 0;
        }

        .sidebar-menu-item:hover {
            background: rgba(255, 255, 255, 0.04);
            color: #fff;
        }

        .sidebar-menu-item:hover svg {
            transform: translateX(2px);
        }

        .sidebar-menu-item.active {
            background: rgb(var(--primary));
            color: #fff;
            box-shadow: 0px 2px 6px 0px rgba(234, 84, 85, 0.3);
        }

        /* Main Container */
        .main-container {
            flex-grow: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: calc(100% - 260px);
            transition: margin-left 0.25s ease-in-out, width 0.25s ease-in-out;
        }

        /* Top Navbar */
        .navbar {
            height: 64px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: var(--shadow);
            transition: background 0.15s ease-in-out, border 0.15s ease-in-out;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-toggle-btn {
            display: none;
            background: transparent;
            border: 0;
            color: var(--text-color);
            cursor: pointer;
        }

        .navbar-toggle-btn svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .navbar-page-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* Theme Toggle Button */
        .theme-toggle-btn {
            background: transparent;
            border: 0;
            color: var(--text-color);
            cursor: pointer;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease-in-out;
        }

        .theme-toggle-btn:hover {
            background: rgba(var(--secondary), 0.1);
        }

        .theme-toggle-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* User Impersonation banner */
        .impersonation-banner {
            background: rgba(255, 159, 67, 0.1);
            border: 1px solid rgb(255, 159, 67);
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: rgb(255, 159, 67);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .impersonation-banner button {
            background: rgb(255, 159, 67);
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: opacity 0.15s ease-in-out;
        }

        .impersonation-banner button:hover {
            opacity: 0.9;
        }

        /* Content Container */
        .content-wrapper {
            flex-grow: 1;
            padding: 24px;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        /* Vuexy Cards / Panels */
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            transition: background 0.15s ease-in-out, border 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .panel:hover {
            box-shadow: 0px 6px 20px 0px rgba(75, 70, 92, 0.15);
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--text-heading);
            font-weight: 600;
            margin-bottom: 16px;
            letter-spacing: 0;
        }

        h1 { font-size: 1.75rem; }
        h2 { font-size: 1.35rem; }
        h3 { font-size: 1.15rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .grid .panel {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .grid .panel strong {
            font-size: 1.5rem;
            color: rgb(var(--primary));
            font-weight: 700;
            display: block;
            margin-bottom: 4px;
        }

        /* Form styling */
        label {
            display: block;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-heading);
            margin-top: 16px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 14px;
            background: var(--bg-card);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.15s ease-in-out;
        }

        input:focus, select:focus, textarea:focus {
            border-color: rgb(var(--primary));
            box-shadow: 0px 0px 0px 3px rgba(var(--primary-rgb), 0.15);
        }

        /* Buttons */
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgb(var(--primary));
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0px 2px 4px 0px rgba(var(--primary-rgb), 0.2);
            transition: all 0.15s ease-in-out;
        }

        button:hover, .button:hover {
            background: rgba(var(--primary-rgb), 0.85);
            box-shadow: 0px 4px 12px 0px rgba(var(--primary-rgb), 0.3);
            transform: translateY(-1px);
        }

        button:active, .button:active {
            transform: translateY(0);
        }

        .button.secondary {
            background: rgb(var(--secondary));
            box-shadow: 0px 2px 4px 0px rgba(128, 131, 144, 0.2);
        }

        .button.secondary:hover {
            background: rgba(128, 131, 144, 0.85);
            box-shadow: 0px 4px 12px 0px rgba(128, 131, 144, 0.3);
        }

        .button.danger {
            background: rgb(var(--danger));
            box-shadow: 0px 2px 4px 0px rgba(234, 84, 85, 0.2);
        }

        .button.danger:hover {
            background: rgba(234, 84, 85, 0.85);
            box-shadow: 0px 4px 12px 0px rgba(234, 84, 85, 0.3);
        }

        /* Links */
        a {
            color: rgb(var(--primary));
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }

        a:hover {
            color: rgba(var(--primary-rgb), 0.85);
            text-decoration: underline;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            margin-top: 16px;
            margin-bottom: 16px;
        }

        th {
            background: rgba(var(--secondary), 0.05);
            color: var(--text-heading);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0;
            border-bottom: 2px solid var(--border-color);
            padding: 12px 16px;
            text-align: left;
        }

        td {
            border-bottom: 1px solid var(--border-color);
            padding: 14px 16px;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        tr:hover td {
            background: rgba(var(--secondary), 0.02);
        }

        .error, .panel.error {
            color: rgb(var(--danger));
            border-color: rgb(var(--danger));
            background: rgba(var(--danger), 0.05);
        }

        .muted {
            color: var(--text-muted) !important;
        }

        /* Alerts and helper messages */
        .flash-status {
            background: rgba(var(--success), 0.1);
            border: 1px solid rgb(var(--success));
            color: rgb(var(--success));
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Footer */
        .footer {
            height: 60px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: auto;
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-260px);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-container {
                margin-left: 0;
                width: 100%;
            }

            .navbar-toggle-btn {
                display: block;
            }
        }
    </style>
    @include('layouts.partials.ui-foundation')
    @include('layouts.partials.ui-desktop-admin')
</head>
<body class="app-shell app-shell--admin app-shell--vuexy">
<div class="app-wrapper">
    <!-- Vuexy-like Vertical Sidebar (Admin) -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-mark">B3</div>
            <div class="sidebar-brand-copy">
                <div class="sidebar-kicker">Control Plane</div>
                <div class="sidebar-title">Billing v3</div>
            </div>
            <span class="sidebar-badge">Admin</span>
        </div>
        <div class="sidebar-menu">
            <a href="/admin" class="sidebar-menu-item {{ Request::is('admin') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm-1 16H6v-1h12v1zm0-3H6v-1h12v1zm0-3H6V8h12v5z"/></svg>
                <span>Admin Overview</span>
            </a>

            <div class="sidebar-section-header">Financials</div>
            <a href="/admin/invoices" class="sidebar-menu-item {{ Request::is('admin/invoices*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm0 16H5V5h14v14zM7 7h10v2H7zm0 4h10v2H7zm0 4h7v2H7z"/></svg>
                <span>Invoices</span>
            </a>
            <a href="/admin/payment-events" class="sidebar-menu-item {{ Request::is('admin/payment-events*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm1-13h-2v6h6v-2h-4V7z"/></svg>
                <span>Payment Events</span>
            </a>
            @can('renewals.view')
                <a href="/admin/renewals" class="sidebar-menu-item {{ Request::is('admin/renewals*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M19 12h-2v3h-3v2h5v-5zM7 9h3V7H5v5h2V9z"/><path d="M20 2H4c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zm0 18H4V4h16v16z"/></svg>
                    <span>Renewals</span>
                </a>
            @endcan
            @can('wallets.adjust')
                <a href="/admin/reports/billing" class="sidebar-menu-item {{ Request::is('admin/reports*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm-1 16H6V6h12v13z"/><path d="M8 8h3v2H8zm0 4h5v2H8zm0 3h8v2H8z"/></svg>
                    <span>Billing Reports</span>
                </a>
            @endcan

            <div class="sidebar-section-header">Resources</div>
            <a href="/admin/products" class="sidebar-menu-item {{ Request::is('admin/products*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2.02422L1.75 6.00022L12 9.97622L22.25 6.00022L12 2.02422ZM3.75 6.00022L12 2.78122L20.25 6.00022L12 9.21922L3.75 6.00022ZM1.75 8.00022V16.0002L12 19.9762L22.25 16.0002V8.00022L20.25 8.78122V15.2192L12 18.4382L3.75 15.2192V8.78122L1.75 8.00022Z"/></svg>
                <span>Products</span>
            </a>
            <a href="/admin/orders" class="sidebar-menu-item {{ Request::is('admin/orders*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span>Orders</span>
            </a>
            <a href="/admin/services" class="sidebar-menu-item {{ Request::is('admin/services*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span>Services</span>
            </a>

            <div class="sidebar-section-header">Integrations</div>
            <a href="/admin/bank-integrations" class="sidebar-menu-item {{ Request::is('admin/bank-integrations*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M21 19h-1V9a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10H3a1 1 0 0 0-1 1v2h20v-2a1 1 0 0 0-1-1zM6 19v-9h12v9H6zm14 2H4v-1h16v1z"/></svg>
                <span>Bank Integrations</span>
            </a>
            <a href="/admin/provisioning-provider-accounts" class="sidebar-menu-item {{ Request::is('admin/provisioning-provider-accounts*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
                <span>Provider Accounts</span>
            </a>

            <div class="sidebar-section-header">Queue &amp; Ops</div>
            <a href="/admin/provisioning-jobs" class="sidebar-menu-item {{ Request::is('admin/provisioning-jobs*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                <span>Provisioning Jobs</span>
            </a>
            <a href="/admin/provider-action-jobs" class="sidebar-menu-item {{ Request::is('admin/provider-action-jobs*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm-1 16H6V6h12v13z"/><path d="M8 8h3v2H8zm0 4h5v2H8zm0 3h8v2H8z"/></svg>
                <span>Provider Action Jobs</span>
            </a>
            <a href="/admin/ops-health" class="sidebar-menu-item {{ Request::is('admin/ops-health*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm1 16h-2v-2h2v2zm0-4h-2V7h2v7z"/></svg>
                <span>Ops Health</span>
            </a>
            <a href="/admin/ops-alert-events" class="sidebar-menu-item {{ Request::is('admin/ops-alert-events*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm1 16h-2v-2h2v2zm0-4h-2V7h2v7z"/></svg>
                <span>Ops Alerts</span>
            </a>
            <a href="/admin/scheduled-task-runs" class="sidebar-menu-item {{ Request::is('admin/scheduled-task-runs*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm1 16h-2v-2h2v2zm0-4h-2V7h2v7z"/></svg>
                <span>Task Runs</span>
            </a>

            <div class="sidebar-section-header">Security &amp; Support</div>
            <a href="/admin/customers" class="sidebar-menu-item {{ Request::is('admin/customers*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm0-4h-2V7h2v8z"/></svg>
                <span>Customer Base</span>
            </a>
            @can('users.view')
                <a href="/admin/users" class="sidebar-menu-item {{ Request::is('admin/users*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/><path d="M12 6c-1.93 0-3.5 1.57-3.5 3.5S10.07 13 12 13s3.5-1.57 3.5-3.5S13.93 6 12 6zm0 5c-.827 0-1.5-.673-1.5-1.5S11.173 8 12 8s1.5.673 1.5 1.5S12.827 11 12 11zm0 3c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-6 4c.22-1.35 4-2 6-2s5.78.65 6 2H6z"/></svg>
                    <span>Users &amp; Roles</span>
                </a>
            @endcan
            @can('audit_logs.view')
                <a href="/admin/audit-logs" class="sidebar-menu-item {{ Request::is('admin/audit-logs*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/><path d="M13 7h-2v6h6v-2h-4z"/></svg>
                    <span>Audit Logs</span>
                </a>
            @endcan
            @can('notifications.manage')
                <a href="/admin/notification-events" class="sidebar-menu-item {{ Request::is('admin/notification-events*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm0 16H5V5h14v14zM7 7h10v2H7zm0 4h10v2H7zm0 4h7v2H7z"/></svg>
                    <span>Notification Logs</span>
                </a>
                <a href="/admin/notification-templates" class="sidebar-menu-item {{ Request::is('admin/notification-templates*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm0 16H5V5h14v14zM7 7h10v2H7zm0 4h10v2H7zm0 4h7v2H7z"/></svg>
                    <span>Templates</span>
                </a>
            @endcan
            <a href="/admin/support-tickets" class="sidebar-menu-item {{ Request::is('admin/support-tickets*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.103 0-2 .897-2 2v18l4-4h14c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zm0 14H5.172L4 17.172V4h16v12zm-9-3h2v2h-2zm0-8h2v6h-2z"/></svg>
                <span>Support Tickets</span>
            </a>
        </div>
    </aside>

    <!-- Page Content Container -->
    <div class="main-container">
        <!-- Top Navbar -->
        <header class="navbar layout-navbar-floating">
            <div class="navbar-left">
                <button class="navbar-toggle-btn" id="sidebar-toggle" aria-label="Toggle Sidebar">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                </button>
                <div class="navbar-title-stack">
                    <span class="navbar-page-kicker">Admin Workspace</span>
                    <span class="navbar-page-title">{{ $title ?? 'Admin Controls' }}</span>
                </div>
                <form class="navbar-search" method="GET" action="/admin/customers" role="search">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 3a6.5 6.5 0 0 1 5.18 10.43l4.45 4.44-1.41 1.42-4.45-4.45A6.5 6.5 0 1 1 9.5 3zm0 2a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9z"/></svg>
                    <input type="search" name="search" value="{{ Request::is('admin/customers*') ? request('search') : '' }}" placeholder="Search customers" aria-label="Search customers">
                </form>
            </div>
            
            <div class="navbar-right">
                <!-- Return to Customer Dashboard -->
                <a href="/dashboard" class="button secondary button-compact button-soft">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    <span>Client</span>
                </a>

                <!-- Theme Toggler -->
                <button class="theme-toggle-btn icon-button" id="theme-toggle" title="Toggle Light/Dark Theme" aria-label="Toggle Light/Dark Theme">
                    <!-- Sun Icon (visible in dark mode) -->
                    <svg id="theme-sun-icon" viewBox="0 0 24 24" style="display: none;"><path d="M6.993 12c0 2.761 2.246 5.007 5.007 5.007s5.007-2.246 5.007-5.007S14.761 6.993 12 6.993 6.993 9.239 6.993 12zM12 8.993c1.658 0 3.007 1.349 3.007 3.007s-1.349 3.007-3.007 3.007S8.993 13.658 8.993 12s1.349-3.007 3.007-3.007zm0-6.993c-.553 0-1 .447-1 1v2c0 .553.447 1 1 1s1-.447 1-1v-2c0-.553-.447-1-1-1zm0 16c-.553 0-1 .447-1 1v2c0 .553.447 1 1 1s1-.447 1-1v-2c0-.553-.447-1-1-1zM3 11H1c-.553 0-1 .447-1 1s.447 1 1 1h2c.553 0 1-.447 1-1s-.447-1-1-1zm16 0h-2c-.553 0-1 .447-1 1s.447 1 1 1h2c.553 0 1-.447 1-1s-.447-1-1-1zM5.222 5.222c-.391-.391-1.024-.391-1.414 0s-.391 1.024 0 1.414l1.414 1.414c.391.391 1.024.391 1.414 0s.391-1.024 0-1.414L5.222 5.222zm12.164 12.164c-.391-.391-1.024-.391-1.414 0s-.391 1.024 0 1.414l1.414 1.414c.391.391 1.024.391 1.414 0s.391-1.024 0-1.414l-1.414-1.414zm0-12.164-1.414 1.414c-.391.391-.391 1.024 0 1.414s1.024.391 1.414 0l1.414-1.414c.391-.391.391-1.024 0-1.414s-1.024-.391-1.414 0zm-12.164 12.164-1.414 1.414c-.391.391-.391 1.024 0 1.414s1.024.391 1.414 0l1.414-1.414c.391-.391.391-1.024 0-1.414s-1.024-.391-1.414 0z"/></svg>
                    <!-- Moon Icon (visible in light mode) -->
                    <svg id="theme-moon-icon" viewBox="0 0 24 24"><path d="M12.122 18.55C7.932 18.55 4.53 15.15 4.53 10.96c0-2.31 1.02-4.47 2.8-5.96a1 1 0 0 0-.25-1.7 8.01 8.01 0 0 0-4.13.9 1 1 0 0 0-.32 1.41A11.022 11.022 0 0 0 11.232 22a11.042 11.042 0 0 0 10.15-6.72 1 1 0 0 0-1.29-1.29 8.026 8.026 0 0 1-7.97 4.56z"/></svg>
                </button>

                @auth
                    <div class="user-avatar" title="{{ auth()->user()->email }}">
                        <span class="user-avatar__initial">{{ strtoupper(substr(auth()->user()->email, 0, 1)) }}</span>
                        <span class="user-avatar__copy">
                            <strong>Admin</strong>
                            <small>{{ auth()->user()->email }}</small>
                        </span>
                    </div>
                    <form method="POST" action="/logout" style="display:inline">
                        @csrf
                        <button type="submit" class="button secondary button-compact">Logout</button>
                    </form>
                @endauth
            </div>
        </header>

        <!-- Main Content deck -->
        <main class="content-wrapper">
            <!-- Impersonation banner -->
            @if (session('impersonator_id'))
                <div class="impersonation-banner">
                    <div>
                        <strong>Impersonation Active:</strong> Currently logged in as <strong>{{ session('impersonated_user_email') }}</strong> (Admin: {{ session('impersonator_email') }}).
                    </div>
                    <form method="POST" action="/impersonation/stop" style="display:inline">
                        @csrf
                        <button type="submit">Stop Impersonation</button>
                    </form>
                </div>
            @endif

            <!-- Success/Status Banner -->
            @if (session('status'))
                <div class="flash-status">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Errors Banner -->
            @if ($errors->any())
                <div class="panel error">
                    <h4 style="margin-bottom: 8px; font-weight: 600;">Please review the following errors:</h4>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Blade Page Content -->
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="footer">
            <div>&copy; 2026 Billing Admin. All rights reserved.</div>
        </footer>
    </div>
</div>

<script>
    // Theme Toggle Handler
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeSunIcon = document.getElementById('theme-sun-icon');
    const themeMoonIcon = document.getElementById('theme-moon-icon');

    function updateToggleIcons(theme) {
        if (theme === 'dark') {
            themeSunIcon.style.display = 'block';
            themeMoonIcon.style.display = 'none';
        } else {
            themeSunIcon.style.display = 'none';
            themeMoonIcon.style.display = 'block';
        }
    }

    // Initialize toggle icons state
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateToggleIcons(currentTheme);

    themeToggleBtn.addEventListener('click', () => {
        const activeTheme = document.documentElement.getAttribute('data-theme');
        let newTheme = 'light';
        if (activeTheme === 'light') {
            newTheme = 'dark';
        }
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateToggleIcons(newTheme);
    });

    // Mobile Sidebar Drawer toggler
    const sidebarToggleBtn = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });

        // Close sidebar if clicked outside of it on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== sidebarToggleBtn) {
                sidebar.classList.remove('open');
            }
        });
    }
</script>
</body>
</html>
