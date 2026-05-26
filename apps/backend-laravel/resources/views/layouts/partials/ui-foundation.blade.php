<style>
    body.app-shell {
        --primary-rgb: var(--primary);
        --surface-soft: #f8f7fa;
        --surface-subtle: #ffffff;
        --surface-elevated: rgba(255, 255, 255, 0.94);
        --border-subtle: rgba(219, 218, 222, 0.72);
        --shadow-navbar: 0 0.25rem 1.125rem rgba(75, 70, 92, 0.12);
        --shadow-card: 0 0.25rem 1.125rem rgba(75, 70, 92, 0.08);
        --shadow-card-hover: 0 0.5rem 1.5rem rgba(75, 70, 92, 0.13);
        --text-strong: var(--text-heading);
        overflow-x: hidden;
    }

    [data-theme="dark"] body.app-shell {
        --surface-soft: #25293c;
        --surface-subtle: #2f3349;
        --surface-elevated: rgba(47, 51, 73, 0.94);
        --border-subtle: rgba(67, 73, 104, 0.88);
        --shadow-navbar: 0 0.25rem 1.125rem rgba(15, 10, 30, 0.32);
        --shadow-card: 0 0.25rem 1.125rem rgba(15, 10, 30, 0.24);
        --shadow-card-hover: 0 0.5rem 1.5rem rgba(15, 10, 30, 0.34);
    }

    body.app-shell--customer {
        --primary: 115, 103, 240;
        --primary-rgb: 115, 103, 240;
    }

    body.app-shell--admin {
        --primary: 115, 103, 240;
        --primary-rgb: 115, 103, 240;
        --bg-sidebar: #2f3349;
    }

    body.app-shell--vuexy {
        background:
            radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.08), transparent 28rem),
            var(--bg-body);
        color: var(--text-color);
    }

    body.app-shell--vuexy .sidebar {
        border-right: 1px solid rgba(219, 218, 222, 0.08);
        box-shadow: 0 0.125rem 0.375rem rgba(15, 10, 30, 0.16);
    }

    body.app-shell--vuexy .sidebar-brand {
        gap: 12px;
        min-height: 76px;
        padding: 18px;
    }

    .sidebar-brand-mark {
        align-items: center;
        background: rgb(var(--primary));
        border-radius: 8px;
        box-shadow: 0 0.25rem 0.75rem rgba(var(--primary-rgb), 0.3);
        color: #fff;
        display: inline-flex;
        flex: 0 0 36px;
        font-size: 0.88rem;
        font-weight: 700;
        height: 36px;
        justify-content: center;
        line-height: 1;
        width: 36px;
    }

    .sidebar-brand-copy {
        display: grid;
        gap: 2px;
        min-width: 0;
    }

    .sidebar-kicker {
        color: #7983bb;
        font-size: 0.68rem;
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    body.app-shell--vuexy .sidebar-title {
        font-size: 1.08rem;
        line-height: 1.2;
    }

    body.app-shell--vuexy .sidebar-badge {
        margin-left: auto;
    }

    body.app-shell--vuexy .sidebar-menu {
        scrollbar-color: rgba(219, 218, 222, 0.24) transparent;
    }

    body.app-shell--vuexy .sidebar-menu-item {
        border-radius: 6px;
        min-height: 38px;
        position: relative;
    }

    body.app-shell--vuexy .sidebar-menu-item.active {
        background: linear-gradient(118deg, rgb(var(--primary)), rgba(var(--primary-rgb), 0.82));
        box-shadow: 0 0.125rem 0.375rem rgba(var(--primary-rgb), 0.35);
    }

    body.app-shell--vuexy .layout-navbar-floating {
        backdrop-filter: blur(10px);
        background: var(--surface-elevated);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        box-shadow: var(--shadow-navbar);
        min-height: 64px;
    }

    body.app-shell--vuexy .navbar-left {
        flex: 1 1 auto;
        min-width: 0;
    }

    .navbar-title-stack {
        display: grid;
        flex: 0 0 auto;
        gap: 2px;
        min-width: 150px;
    }

    .navbar-page-kicker {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    body.app-shell--vuexy .navbar-page-title {
        color: var(--text-heading);
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.2;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .navbar-search {
        align-items: center;
        background: var(--surface-soft);
        border: 1px solid transparent;
        border-radius: 8px;
        display: flex;
        flex: 1 1 380px;
        gap: 8px;
        max-width: 430px;
        min-width: 240px;
        padding: 0 12px;
        transition: all 0.15s ease-in-out;
    }

    .navbar-search:focus-within {
        background: var(--bg-card);
        border-color: rgba(var(--primary-rgb), 0.38);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.12);
    }

    .navbar-search svg {
        color: var(--text-muted);
        fill: currentColor;
        flex: 0 0 18px;
        height: 18px;
        width: 18px;
    }

    .navbar-search input {
        background: transparent;
        border: 0;
        box-shadow: none;
        color: var(--text-color);
        font-size: 0.88rem;
        min-height: 40px;
        padding: 0;
    }

    .navbar-search input:focus {
        border: 0;
        box-shadow: none;
    }

    body.app-shell--vuexy .navbar-right {
        gap: 10px;
    }

    .icon-button,
    .theme-toggle-btn.icon-button {
        background: var(--surface-soft);
        border: 1px solid transparent;
        border-radius: 8px;
        color: var(--text-color);
        height: 40px;
        width: 40px;
    }

    .icon-button:hover,
    .theme-toggle-btn.icon-button:hover {
        background: rgba(var(--primary-rgb), 0.1);
        color: rgb(var(--primary));
        transform: translateY(-1px);
    }

    .button svg,
    button svg {
        fill: currentColor;
        flex: 0 0 16px;
        height: 16px;
        width: 16px;
    }

    .button-compact,
    button.button-compact {
        min-height: 38px;
        padding: 8px 12px;
    }

    .button-soft,
    button.button-soft {
        background: rgba(var(--primary-rgb), 0.1);
        box-shadow: none;
        color: rgb(var(--primary));
    }

    .button-soft:hover,
    button.button-soft:hover {
        background: rgba(var(--primary-rgb), 0.16);
        box-shadow: none;
        color: rgb(var(--primary));
    }

    .user-avatar {
        align-items: center;
        background: var(--surface-soft);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        display: inline-flex;
        gap: 10px;
        min-height: 42px;
        max-width: 260px;
        padding: 5px 10px 5px 6px;
    }

    .user-avatar__initial {
        align-items: center;
        background: rgba(var(--primary-rgb), 0.14);
        border-radius: 8px;
        color: rgb(var(--primary));
        display: inline-flex;
        flex: 0 0 30px;
        font-size: 0.82rem;
        font-weight: 800;
        height: 30px;
        justify-content: center;
        width: 30px;
    }

    .user-avatar__copy {
        display: grid;
        gap: 1px;
        min-width: 0;
    }

    .user-avatar__copy strong {
        color: var(--text-heading);
        font-size: 0.78rem;
        line-height: 1;
    }

    .user-avatar__copy small {
        color: var(--text-muted);
        display: block;
        font-size: 0.74rem;
        line-height: 1.2;
        max-width: 178px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    h1, h2, h3, h4, h5, h6, label, th {
        letter-spacing: 0;
    }

    .content-wrapper {
        max-width: 1240px;
    }

    .panel {
        max-width: 100%;
        border-color: var(--border-subtle);
        box-shadow: var(--shadow-card);
    }

    .panel:hover {
        box-shadow: var(--shadow-card-hover);
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
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        box-shadow: var(--shadow-card);
        color: var(--text-color);
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 126px;
        padding: 20px;
        position: relative;
        text-decoration: none;
    }

    .stat-card::before {
        background: rgba(var(--primary-rgb), 0.12);
        border-radius: 7px;
        content: "";
        height: 34px;
        position: absolute;
        right: 16px;
        top: 16px;
        width: 34px;
    }

    .stat-card:hover {
        border-color: rgba(var(--primary-rgb), 0.24);
        box-shadow: var(--shadow-card-hover);
        transform: translateY(-1px);
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

    .stat-card--success::before {
        background: rgba(var(--success), 0.13);
    }

    .stat-card--warning .stat-card__value {
        color: rgb(var(--warning));
    }

    .stat-card--warning::before {
        background: rgba(var(--warning), 0.16);
    }

    .stat-card--danger .stat-card__value {
        color: rgb(var(--danger));
    }

    .stat-card--danger::before {
        background: rgba(var(--danger), 0.13);
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
        position: relative;
        text-decoration: none;
    }

    .quick-action::before {
        background: rgba(var(--primary-rgb), 0.16);
        border-radius: 999px;
        content: "";
        height: 8px;
        position: absolute;
        right: 14px;
        top: 14px;
        width: 8px;
    }

    .quick-action:hover {
        background: rgba(var(--primary-rgb), 0.05);
        border-color: rgba(var(--primary-rgb), 0.35);
        color: rgb(var(--primary));
        text-decoration: none;
        transform: translateY(-1px);
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

    .data-table,
    .panel:has(> table) {
        border-radius: 8px;
    }

    table {
        font-variant-numeric: tabular-nums;
    }

    th {
        background: rgba(var(--secondary), 0.08);
        border-bottom: 1px solid var(--border-subtle);
        color: var(--text-heading);
    }

    td {
        border-bottom-color: var(--border-subtle);
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
        background: var(--surface-soft);
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
        .navbar-search {
            display: none;
        }

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

        .user-avatar__copy {
            display: none;
        }

        .user-avatar {
            padding-right: 6px;
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
