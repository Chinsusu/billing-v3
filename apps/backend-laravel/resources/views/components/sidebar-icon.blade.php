@props(['name'])

@php
    $icons = [
        'overview' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />',
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /><path d="M14 14h6.5M14 18h6.5" />',
        'invoices' => '<path d="M6 3h12v18l-2-1-2 1-2-1-2 1-2-1-2 1V3Z" /><path d="M9 8h6M9 12h6M9 16h4" />',
        'payment-events' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 10h18M7 15h4" />',
        'renewals' => '<path d="M20 11a8 8 0 0 0-14.7-4.4L4 8M4 4v4h4" /><path d="M4 13a8 8 0 0 0 14.7 4.4L20 16M20 20v-4h-4" />',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />',
        'products' => '<path d="M12 3 3.5 7.25 12 11.5l8.5-4.25L12 3Z" /><path d="M3.5 7.25v9.5L12 21l8.5-4.25v-9.5M12 11.5V21" />',
        'orders' => '<path d="M6 6h15l-2 8H8L6 3H3" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />',
        'services' => '<rect x="4" y="4" width="16" height="6" rx="2" /><rect x="4" y="14" width="16" height="6" rx="2" /><path d="M8 7h.01M8 17h.01M12 7h4M12 17h4" />',
        'wallet' => '<path d="M4 7.5V18a2 2 0 0 0 2 2h14V8H6a2 2 0 0 1 0-4h12v4" /><path d="M16 13.5h4" />',
        'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" />',
        'api-keys' => '<circle cx="7.5" cy="12.5" r="3.5" /><path d="M11 12.5h10M16 12.5v3M19 12.5v-3" />',
        'reseller-control' => '<rect x="3.5" y="7" width="17" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3.5 12h17M9 16h6" />',
        'admin-panel' => '<path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z" /><path d="m9.5 12 1.8 1.8L15.8 9" />',
        'bank-integrations' => '<path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M4 18h16M12 3l8 4H4l8-4Z" />',
        'provider-accounts' => '<path d="M6 18.5h11a4 4 0 0 0 .7-7.94A6 6 0 0 0 6.25 8.5 4.8 4.8 0 0 0 6 18.5Z" /><path d="M12 12v3M10.5 13.5h3" />',
        'provisioning-jobs' => '<rect x="3" y="4" width="6" height="6" rx="1.5" /><rect x="15" y="4" width="6" height="6" rx="1.5" /><rect x="9" y="15" width="6" height="6" rx="1.5" /><path d="M9 7h6M12 10v5" />',
        'provider-action-jobs' => '<path d="M8 6h12M8 12h12M8 18h12" /><path d="m3.5 6 1 1 2-2M3.5 12l1 1 2-2M3.5 18l1 1 2-2" />',
        'ops-health' => '<path d="M3 12h4l2-6 4 12 2-6h6" />',
        'ops-alerts' => '<path d="M12 3 22 20H2L12 3Z" /><path d="M12 9v5M12 17h.01" />',
        'task-runs' => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18M12 14v3l2 1" />',
        'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />',
        'users-roles' => '<path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z" /><circle cx="12" cy="10" r="2" /><path d="M8.5 16a4 4 0 0 1 7 0" />',
        'audit-logs' => '<path d="M9 4h6l1 2h3v15H5V6h3l1-2Z" /><path d="M9 11h6M9 15h6" />',
        'notification-logs' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4M18.5 3.5l2 2M5.5 3.5l-2 2" />',
        'templates' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z" /><path d="M14 3v6h6M8 13h8M8 17h5" />',
        'support-tickets' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z" /><path d="M8 9h8M8 13h5" />',
    ];

    $iconMarkup = $icons[$name] ?? $icons['overview'];
@endphp

<span {{ $attributes->merge(['class' => 'sidebar-menu-icon', 'data-menu-icon' => $name]) }} aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        {!! $iconMarkup !!}
    </svg>
</span>
