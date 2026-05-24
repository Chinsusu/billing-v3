@extends('layouts.app', ['title' => 'Provisioning Jobs'])
@section('content')
<div class="panel">
    <h1>Provisioning Jobs</h1>
    <table>
        <thead><tr><th>Type</th><th>Reference</th><th>Product</th><th>Status</th><th>Attempts</th><th>Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($provisioningJobs as $job)
                <tr>
                    <td>{{ $job->type }}</td>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->payload['product']['code'] ?? '-' }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                    <td>
                        @if ($job->status === 'failed')
                            <form method="POST" action="/admin/provisioning-jobs/{{ $job->id }}/retry">
                                @csrf
                                <button type="submit">Retry</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No provisioning jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $provisioningJobs->links() }}
</div>
@endsection
