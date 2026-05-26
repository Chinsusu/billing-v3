@extends('layouts.admin', ['title' => 'Provider Action Jobs'])
@section('content')
<div class="panel">
    <h1>Provider Action Jobs</h1>
    <table>
        <thead><tr><th>Action</th><th>Reference</th><th>Service</th><th>Provider</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($providerActionJobs as $job)
                <tr>
                    <td>{{ $job->action }}</td>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->service?->product_name ?? '-' }}</td>
                    <td>{{ $job->providerAccount?->slug ?? '-' }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }} / {{ $job->max_attempts }}</td>
                    <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                    <td>
                        @if ($job->status === 'failed')
                            <form method="POST" action="/admin/provider-action-jobs/{{ $job->id }}/retry">
                                @csrf
                                <button type="submit">Retry</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">No provider action jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $providerActionJobs->links() }}
</div>
@endsection
