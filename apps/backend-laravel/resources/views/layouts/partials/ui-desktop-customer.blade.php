<style>
    /* customer-desktop-foundation */
    @media (min-width: 1024px) {
        body.app-shell--customer {
            --customer-desktop-content-max: 1360px;
            --customer-desktop-gutter: 32px;
        }

        body.app-shell--customer .navbar {
            padding-left: var(--customer-desktop-gutter);
            padding-right: var(--customer-desktop-gutter);
        }

        body.app-shell--customer .content-wrapper {
            max-width: var(--customer-desktop-content-max);
            padding: 32px var(--customer-desktop-gutter) 24px;
        }

        body.app-shell--customer .page-header {
            align-items: center;
            margin-bottom: 22px;
        }

        body.app-shell--customer .page-subtitle {
            max-width: 760px;
        }

        body.app-shell--customer .stat-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        body.app-shell--customer .stat-card {
            min-height: 112px;
            padding: 18px;
        }

        body.app-shell--customer .stat-card__value {
            font-size: 1.45rem;
        }

        body.app-shell--customer .quick-action-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        body.app-shell--customer .recent-grid {
            align-items: start;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        body.app-shell--customer .recent-grid .panel {
            min-height: 168px;
        }

        body.app-shell--customer .product-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        body.app-shell--customer .product-card {
            min-height: 250px;
        }

        body.app-shell--customer .activity-item {
            align-items: flex-start;
        }

        body.app-shell--customer .activity-amount {
            min-width: 96px;
        }
    }

    @media (min-width: 1280px) {
        body.app-shell--customer .stat-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    @media (min-width: 1700px) {
        body.app-shell--customer .recent-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
</style>
