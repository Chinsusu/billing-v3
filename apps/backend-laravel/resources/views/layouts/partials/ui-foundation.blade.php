<style>
    body.app-shell {
        --primary-rgb: var(--primary);
        --surface-soft: #f4f5f9;
        --surface-subtle: #fafafa;
        --border-subtle: #ecebf1;
        --text-strong: var(--text-heading);
        overflow-x: hidden;
    }

    body.app-shell--customer {
        --primary: 115, 103, 240;
        --primary-rgb: 115, 103, 240;
    }

    body.app-shell--admin {
        --primary: 37, 99, 235;
        --primary-rgb: 37, 99, 235;
        --bg-sidebar: #252a3d;
    }

    h1, h2, h3, h4, h5, h6, label, th {
        letter-spacing: 0;
    }

    .content-wrapper {
        max-width: 1240px;
    }

    .panel {
        max-width: 100%;
    }

    .panel:hover {
        box-shadow: var(--shadow);
    }

    .page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .page-header h1 {
        margin-bottom: 6px;
    }

    .page-eyebrow {
        color: rgb(var(--primary));
        font-size: 0.78rem;
        font-weight: 700;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .page-subtitle {
        color: var(--text-muted);
        margin: 0;
        max-width: 720px;
    }

    .page-header-actions,
    .action-row,
    .action-group {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .stat-grid,
    .ops-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: var(--shadow);
        color: var(--text-color);
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 126px;
        padding: 20px;
        text-decoration: none;
    }

    .stat-card:hover {
        text-decoration: none;
    }

    .stat-card__value {
        color: rgb(var(--primary));
        font-size: 1.55rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .stat-card__label {
        color: var(--text-heading);
        font-weight: 600;
    }

    .stat-card__meta {
        color: var(--text-muted);
        font-size: 0.88rem;
        line-height: 1.45;
    }

    .stat-card--success .stat-card__value {
        color: rgb(var(--success));
    }

    .stat-card--warning .stat-card__value {
        color: rgb(var(--warning));
    }

    .stat-card--danger .stat-card__value {
        color: rgb(var(--danger));
    }

    .quick-action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-top: 18px;
    }

    .quick-action {
        align-items: flex-start;
        background: var(--surface-subtle);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        color: var(--text-heading);
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 14px;
        text-decoration: none;
    }

    .quick-action:hover {
        border-color: rgba(var(--primary-rgb), 0.35);
        color: rgb(var(--primary));
        text-decoration: none;
    }

    .quick-action span {
        color: var(--text-muted);
        font-size: 0.84rem;
    }

    .recent-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 20px;
    }

    .activity-list {
        display: grid;
        gap: 10px;
        margin-top: 14px;
    }

    .activity-item {
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding: 10px 0;
    }

    .activity-item:first-child {
        padding-top: 0;
    }

    .activity-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .activity-main {
        display: grid;
        gap: 6px;
        min-width: 0;
    }

    .activity-main a {
        overflow-wrap: anywhere;
    }

    .activity-main .status-badge {
        justify-self: start;
    }

    .activity-meta {
        color: var(--text-muted);
        font-size: 0.86rem;
    }

    .activity-amount {
        color: var(--text-heading);
        flex: 0 0 auto;
        font-weight: 700;
        text-align: right;
    }

    .form-section,
    .filter-bar {
        display: grid;
        gap: 14px;
    }

    .filter-bar {
        grid-template-columns: minmax(220px, 1fr) auto;
        align-items: end;
    }

    .field-help {
        color: var(--text-muted);
        font-size: 0.88rem;
        margin-top: 4px;
    }

    button, .button {
        gap: 8px;
        min-height: 38px;
        white-space: nowrap;
    }

    button.danger,
    .button.danger {
        background: rgb(var(--danger));
        box-shadow: 0px 2px 4px 0px rgba(234, 84, 85, 0.2);
    }

    button.danger:hover,
    .button.danger:hover {
        background: rgba(234, 84, 85, 0.85);
    }

    .data-table {
        margin-top: 16px;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .data-table table {
        margin: 0;
        min-width: 720px;
    }

    .data-table--compact table {
        min-width: 100%;
    }

    .panel > table {
        min-width: 720px;
    }

    .table-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .status-badge {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1;
        min-height: 24px;
        padding: 6px 10px;
    }

    .status-badge--neutral {
        background: rgba(var(--secondary), 0.12);
        color: rgb(var(--secondary));
    }

    .status-badge--primary {
        background: rgba(var(--primary-rgb), 0.12);
        color: rgb(var(--primary));
    }

    .status-badge--success {
        background: rgba(var(--success), 0.12);
        color: rgb(var(--success));
    }

    .status-badge--warning {
        background: rgba(var(--warning), 0.14);
        color: #a85b00;
    }

    .status-badge--danger {
        background: rgba(var(--danger), 0.12);
        color: rgb(var(--danger));
    }

    .empty-state {
        border: 1px dashed var(--border-color);
        border-radius: 8px;
        color: var(--text-muted);
        padding: 24px;
        text-align: center;
    }

    .empty-state strong {
        color: var(--text-heading);
        display: block;
        margin-bottom: 6px;
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
    }

    .product-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-height: 230px;
        padding: 22px;
    }

    .product-card__meta {
        color: var(--text-muted);
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        line-height: 1.4;
    }

    .product-card__price {
        color: var(--text-heading);
        font-size: 1.2rem;
        font-weight: 700;
    }

    .product-card__actions {
        margin-top: auto;
    }

    .footer {
        min-height: 60px;
        padding: 10px 20px;
        text-align: center;
    }

    .footer div {
        max-width: 100%;
        overflow-wrap: anywhere;
    }

    @media (max-width: 768px) {
        .navbar {
            gap: 10px;
            height: auto;
            min-height: 64px;
            padding: 8px 14px;
        }

        .navbar-left {
            min-width: 0;
        }

        .navbar-page-title {
            font-size: 1rem;
            line-height: 1.2;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
        }

        .navbar-right {
            gap: 8px;
            margin-left: auto;
        }

        .navbar-right > span {
            display: none;
        }

        .theme-toggle-btn {
            flex: 0 0 auto;
            height: 36px;
            width: 36px;
        }

        .content-wrapper {
            padding: 24px 24px 16px;
        }

        .page-header {
            flex-direction: column;
            gap: 12px;
        }

        .page-header-actions,
        .action-row,
        .action-group {
            width: 100%;
        }

        .filter-bar {
            grid-template-columns: 1fr;
        }

        .panel {
            overflow-x: auto;
            padding: 20px;
        }

        .panel > table {
            margin-bottom: 0;
        }

        .stat-grid,
        .ops-dashboard-grid,
        .recent-grid,
        .product-grid,
        .quick-action-grid {
            grid-template-columns: 1fr;
        }

        .footer {
            height: auto;
            min-height: 56px;
        }
    }

    @media (max-width: 420px) {
        .navbar-page-title {
            display: none;
        }

        .navbar-right .button span {
            display: none;
        }

        .content-wrapper {
            padding-left: 24px;
            padding-right: 24px;
        }

        .button,
        button {
            padding-left: 14px;
            padding-right: 14px;
        }
    }
</style>
