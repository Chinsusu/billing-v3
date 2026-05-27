@csrf
<label>Provider<input name="provider" value="{{ old('provider', $bankIntegration->provider ?: 'private_bank') }}" required></label>
<label>Name<input name="name" value="{{ old('name', $bankIntegration->name) }}" required></label>
<label>Base URL<input name="base_url" value="{{ old('base_url', $bankIntegration->base_url) }}" placeholder="https://bank.example.test" required></label>
<label>Transactions Path<input name="transactions_path" value="{{ old('transactions_path', $bankIntegration->transactions_path ?: '/api/transactions') }}" required></label>
<label>Account Number<input name="account_number" value="{{ old('account_number', $bankIntegration->account_number) }}"></label>
<label>
    <input type="hidden" name="enabled" value="0">
    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $bankIntegration->enabled ?? true))>
    Enabled
</label>
<label>API Key
    <input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $bankIntegration->api_key_last_four ? 'Configured ...'.$bankIntegration->api_key_last_four : 'Enter API key' }}">
</label>
<label>Webhook Secret
    <input type="password" name="webhook_secret" autocomplete="new-password" placeholder="{{ $bankIntegration->webhook_secret_last_four ? 'Configured ...'.$bankIntegration->webhook_secret_last_four : 'Enter webhook secret' }}">
</label>
<p><button type="submit">Save Bank Integration</button></p>
