@extends('layouts.app', ['title' => 'Ops Health'])
@section('content')
<div class="panel">
    <h1>Ops Health</h1>
    <div class="grid">
        <div class="panel">
            <strong>{{ $provisioningQueue['status'] }}</strong><br>
            Provisioning Queue<br>
            pending: {{ $provisioningQueue['pending'] }}<br>
            processing: {{ $provisioningQueue['processing'] }}<br>
            failed: {{ $provisioningQueue['failed'] }}
        </div>
        <div class="panel">
            <strong>{{ $providerActionQueue['status'] }}</strong><br>
            Provider Action Queue<br>
            pending: {{ $providerActionQueue['pending'] }}<br>
            processing: {{ $providerActionQueue['processing'] }}<br>
            failed: {{ $providerActionQueue['failed'] }}
        </div>
        <div class="panel">
            <strong>{{ $overdueActiveServiceCount }}</strong><br>
            Overdue Active Services
        </div>
        <div class="panel">
            <strong>{{ $enabledBankIntegrationCount }}</strong><br>
            Enabled Bank Integrations
        </div>
    </div>
</div>

<div class="panel">
    <h2>Scheduled Tasks</h2>
    <table>
        <thead><tr><th>Task</th><th>Health</th><th>Last Run</th><th>Duration</th><th>Exit</th><th>Message</th></tr></thead>
        <tbody>
            @foreach ($tasks as $task => $health)
                <tr>
                    <td>{{ $task }}</td>
                    <td>{{ $health['status'] }}</td>
                    <td>{{ $health['lastRun']?->started_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $health['lastRun']?->duration_ms ?? '-' }}</td>
                    <td>{{ $health['lastRun']?->exit_code ?? '-' }}</td>
                    <td>{{ $health['message'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
