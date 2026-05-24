@extends('layouts.app', ['title' => 'Provisioning Jobs'])
@section('content')
<div class="panel">
    <h1>Provisioning Jobs</h1>
    <table>
        <thead><tr><th>Type</th><th>Reference</th><th>Product</th><th>Status</th><th>Attempts</th></tr></thead>
        <tbody>
            @forelse ($provisioningJobs as $job)
                <tr>
                    <td>{{ $job->type }}</td>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->payload['product']['code'] ?? '-' }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No provisioning jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $provisioningJobs->links() }}
</div>
@endsection
