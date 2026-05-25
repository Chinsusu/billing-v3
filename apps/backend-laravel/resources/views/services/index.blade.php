@extends('layouts.app', ['title' => 'Services'])
@section('content')
<div class="panel">
    <h1>Services</h1>
    <table>
        <thead><tr><th>Product</th><th>Type</th><th>Status</th><th>Expires</th><th>Auto-renew</th><th>Latest renewal</th><th></th></tr></thead>
        <tbody>
            @forelse ($services as $service)
                @php($latestAutoRenewalAttempt = $service->autoRenewalAttempts->first())
                <tr>
                    <td>{{ $service->product_name }}</td>
                    <td>{{ $service->product_type }}</td>
                    <td>{{ $service->status }}</td>
                    <td>{{ $service->expires_at?->format('Y-m-d') }}</td>
                    <td>
                        {{ $service->auto_renew_enabled ? 'Enabled' : 'Disabled' }}
                        @if ($service->product && ! $service->product->auto_renew_allowed)
                            <br><span class="muted">Unavailable by policy</span>
                        @endif
                    </td>
                    <td>
                        {{ $latestAutoRenewalAttempt?->status ?? '-' }}
                        @if ($latestAutoRenewalAttempt?->next_attempt_at)
                            <br><span class="muted">Next retry {{ $latestAutoRenewalAttempt->next_attempt_at->format('Y-m-d H:i') }}</span>
                        @endif
                    </td>
                    <td><a href="/services/{{ $service->id }}">Details</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No services yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
