@extends('layouts.admin', ['title' => 'Provisioning Jobs'])
@section('content')
<div class="panel">
    <h1>Provisioning Jobs</h1>
    <table>
        <thead><tr><th>Type</th><th>Reference</th><th>Product</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($provisioningJobs as $job)
                <tr>
                    <td>{{ $job->type }}</td>
                    <td><a href="/admin/provisioning-jobs/{{ $job->id }}">{{ $job->idempotency_key }}</a></td>
                    <td>{{ $job->payload['product']['code'] ?? '-' }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                    <td>
                        <a class="button secondary" href="/admin/provisioning-jobs/{{ $job->id }}">View</a>
                        @if ($job->status === 'failed')
                            <form method="POST" action="/admin/provisioning-jobs/{{ $job->id }}/retry">
                                @csrf
                                <button type="submit">Retry</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No provisioning jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $provisioningJobs->links() }}
</div>
@endsection
