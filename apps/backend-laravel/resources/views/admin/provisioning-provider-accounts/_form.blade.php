@csrf
<label>Slug<input name="slug" value="{{ old('slug', $providerAccount->slug) }}" required></label>
<label>Name<input name="name" value="{{ old('name', $providerAccount->name) }}" required></label>
<label>Driver
    <select name="driver" required>
        @foreach (['sandbox' => 'Sandbox', 'generic_http' => 'Generic HTTP'] as $value => $label)
            <option value="{{ $value }}" @selected(old('driver', $providerAccount->driver ?: 'sandbox') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Base URL<input name="base_url" value="{{ old('base_url', $providerAccount->base_url) }}" placeholder="https://provider.example.test"></label>
<label>Provision Path<input name="provision_path" value="{{ old('provision_path', $providerAccount->provision_path) }}" placeholder="/api/provision"></label>
<label>Auth Type
    <select name="auth_type" required>
        @foreach (['none' => 'None', 'bearer' => 'Bearer', 'header' => 'Custom Header'] as $value => $label)
            <option value="{{ $value }}" @selected(old('auth_type', $providerAccount->auth_type ?: 'none') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Auth Header Name<input name="auth_header_name" value="{{ old('auth_header_name', $providerAccount->auth_header_name) }}" placeholder="X-API-Key"></label>
<label>API Key
    <input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $providerAccount->api_key_last_four ? 'Configured ...'.$providerAccount->api_key_last_four : 'Enter API key' }}">
</label>
<label>
    <input type="hidden" name="enabled" value="0">
    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $providerAccount->enabled ?? true))>
    Enabled
</label>
<label>Timeout Seconds<input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $providerAccount->timeout_seconds ?: 15) }}" min="1" max="120" required></label>
<label>Request Template<textarea name="request_template">{{ old('request_template', json_encode($providerAccount->request_template ?? [], JSON_PRETTY_PRINT)) }}</textarea></label>
<label>External ID Path<input name="response_external_id_path" value="{{ old('response_external_id_path', $providerAccount->response_external_id_path ?: 'external_id') }}" required></label>
<label>Status Path<input name="response_status_path" value="{{ old('response_status_path', $providerAccount->response_status_path ?: 'status') }}" required></label>
<label>Config Path<input name="response_config_path" value="{{ old('response_config_path', $providerAccount->response_config_path) }}"></label>
<h2>Provider Callbacks</h2>
<label>Callback Secret
    <input type="password" name="callback_secret" autocomplete="new-password" placeholder="{{ $providerAccount->callback_secret_last_four ? 'Configured ...'.$providerAccount->callback_secret_last_four : 'Enter callback secret' }}">
</label>
<label>Callback Event ID Path<input name="callback_event_id_path" value="{{ old('callback_event_id_path', $providerAccount->callback_event_id_path ?: 'event_id') }}" required></label>
<label>Callback External ID Path<input name="callback_external_id_path" value="{{ old('callback_external_id_path', $providerAccount->callback_external_id_path ?: 'external_id') }}" required></label>
<label>Callback Action Path<input name="callback_action_path" value="{{ old('callback_action_path', $providerAccount->callback_action_path ?: 'action') }}" required></label>
<label>Callback Status Path<input name="callback_status_path" value="{{ old('callback_status_path', $providerAccount->callback_status_path ?: 'status') }}" required></label>
<p><button type="submit">Save Provider Account</button></p>
