<style>
    /* admin-desktop-foundation */
    @media (min-width: 1024px) {
        body.app-shell--admin {
            --admin-desktop-content-max: 1520px;
            --admin-desktop-gutter: 28px;
            --admin-desktop-sidebar-width: 272px;
            --admin-desktop-table-cell-x: 12px;
            --admin-desktop-table-cell-y: 9px;
        }

        body.app-shell--admin .sidebar {
            width: var(--admin-desktop-sidebar-width);
        }

        body.app-shell--admin .sidebar-brand {
            padding: 20px 18px;
        }

        body.app-shell--admin .sidebar-menu {
            padding: 12px;
        }

        body.app-shell--admin .sidebar-section-header {
            margin: 16px 0 7px 10px;
        }

        body.app-shell--admin .sidebar-menu-item {
            gap: 10px;
            margin-bottom: 2px;
            padding: 7px 12px;
        }

        body.app-shell--admin .main-container {
            margin-left: var(--admin-desktop-sidebar-width);
            width: calc(100% - var(--admin-desktop-sidebar-width));
        }

        body.app-shell--admin .navbar {
            height: 64px;
            padding-left: var(--admin-desktop-gutter);
            padding-right: var(--admin-desktop-gutter);
        }

        body.app-shell--admin .layout-navbar-floating {
            margin: 16px var(--admin-desktop-gutter) 0;
            padding-left: 18px;
            padding-right: 18px;
        }

        body.app-shell--admin .navbar-search {
            max-width: 520px;
        }

        body.app-shell--admin .content-wrapper {
            max-width: var(--admin-desktop-content-max);
            padding: 22px var(--admin-desktop-gutter) 18px;
        }

        body.app-shell--admin .panel {
            margin-bottom: 18px;
            padding: 20px;
        }

        body.app-shell--admin .page-header {
            align-items: center;
            margin-bottom: 20px;
        }

        body.app-shell--admin .page-header h1 {
            margin-bottom: 4px;
        }

        body.app-shell--admin h1 {
            font-size: 1.58rem;
        }

        body.app-shell--admin h2 {
            font-size: 1.18rem;
        }

        body.app-shell--admin .ops-dashboard-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        body.app-shell--admin .stat-card {
            min-height: 108px;
            padding: 16px;
        }

        body.app-shell--admin .stat-card::before {
            right: 14px;
            top: 14px;
        }

        body.app-shell--admin .stat-card__value {
            font-size: 1.42rem;
        }

        body.app-shell--admin .quick-action-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        body.app-shell--admin .quick-action {
            min-height: 82px;
            padding: 12px;
        }

        body.app-shell--admin .filter-bar {
            grid-template-columns: minmax(360px, 520px) max-content;
            gap: 12px;
        }

        body.app-shell--admin .filter-bar button {
            justify-self: start;
            min-width: 120px;
        }

        body.app-shell--admin input,
        body.app-shell--admin select,
        body.app-shell--admin textarea {
            font-size: 0.88rem;
            padding: 8px 12px;
        }

        body.app-shell--admin button,
        body.app-shell--admin .button {
            font-size: 0.84rem;
            min-height: 34px;
            padding: 8px 14px;
        }

        body.app-shell--admin .data-table {
            margin-top: 12px;
        }

        body.app-shell--admin .data-table table,
        body.app-shell--admin .panel > table {
            font-variant-numeric: tabular-nums;
            min-width: 960px;
        }

        body.app-shell--admin th {
            font-size: 0.72rem;
            padding: 9px var(--admin-desktop-table-cell-x);
        }

        body.app-shell--admin td {
            font-size: 0.84rem;
            padding: var(--admin-desktop-table-cell-y) var(--admin-desktop-table-cell-x);
        }

        body.app-shell--admin .table-actions {
            flex-wrap: nowrap;
            gap: 6px;
        }

        body.app-shell--admin .status-badge {
            font-size: 0.72rem;
            min-height: 22px;
            padding: 5px 8px;
        }

        body.app-shell--admin .footer {
            min-height: 48px;
        }
    }

    @media (min-width: 1280px) {
        body.app-shell--admin .ops-dashboard-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }
</style>
