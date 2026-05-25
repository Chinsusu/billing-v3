@csrf
<label>Code<input name="code" value="{{ old('code', $product->code) }}" required></label>
<label>Name<input name="name" value="{{ old('name', $product->name) }}" required></label>
<label>Type
    <select name="type" required>
        @foreach (['proxy' => 'Proxy', 'vps' => 'VPS'] as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $product->type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Status
    <select name="status" required>
        @foreach (['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $product->status ?: 'draft') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Price Amount<input type="number" name="price_amount" value="{{ old('price_amount', $product->price_amount) }}" min="1" required></label>
<label>Currency<input name="currency" value="{{ old('currency', $product->currency ?: 'VND') }}" maxlength="3" required></label>
<label>Duration Days<input type="number" name="duration_days" value="{{ old('duration_days', $product->duration_days ?: 30) }}" min="1" required></label>
<fieldset>
    <legend>Auto-renew Policy</legend>
    <input type="hidden" name="auto_renew_allowed" value="0">
    <label><input type="checkbox" name="auto_renew_allowed" value="1" @checked(old('auto_renew_allowed', $product->exists ? $product->auto_renew_allowed : true))> Allow auto-renewal</label>
    <label>Renewal Window Hours<input type="number" name="auto_renew_window_hours" value="{{ old('auto_renew_window_hours', $product->auto_renew_window_hours ?: 24) }}" min="1" max="24" required></label>
    <label>Retry Delay Minutes<input type="number" name="auto_renew_retry_delay_minutes" value="{{ old('auto_renew_retry_delay_minutes', $product->auto_renew_retry_delay_minutes ?: 60) }}" min="5" max="10080" required></label>
    <label>Renewal Max Attempts<input type="number" name="auto_renew_max_attempts" value="{{ old('auto_renew_max_attempts', $product->auto_renew_max_attempts ?: 3) }}" min="1" max="20" required></label>
</fieldset>
<label>Lifecycle Source
    <select name="lifecycle_source" required>
        @foreach (['local_policy' => 'Local Policy', 'provider_response' => 'Provider Response', 'provider_lookup' => 'Provider Lookup'] as $value => $label)
            <option value="{{ $value }}" @selected(old('lifecycle_source', $product->lifecycle_source ?: 'local_policy') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Lifecycle Unit
    <select name="lifecycle_unit" required>
        @foreach (['day' => 'Days', 'calendar_month' => 'Calendar Months'] as $value => $label)
            <option value="{{ $value }}" @selected(old('lifecycle_unit', $product->lifecycle_unit ?: 'day') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Lifecycle Count<input type="number" name="lifecycle_count" value="{{ old('lifecycle_count', $product->lifecycle_count ?: $product->duration_days ?: 30) }}" min="1" required></label>
<label>Description<textarea name="description">{{ old('description', $product->description) }}</textarea></label>
<label>Provider Account
    <select name="provider_account_id">
        <option value="">Default sandbox</option>
        @foreach ($providerAccounts as $account)
            <option value="{{ $account->id }}" @selected(old('provider_account_id', $product->provider_account_id) === $account->id)>{{ $account->name }} ({{ $account->slug }})</option>
        @endforeach
    </select>
</label>
<label>Provider Plan Code<input name="provider_plan_code" value="{{ old('provider_plan_code', $product->provider_plan_code) }}" placeholder="A1"></label>
<label>Provider Region<input name="provider_region" value="{{ old('provider_region', $product->provider_region) }}" placeholder="sgp1"></label>
<label>Provider Provision Path<input name="provider_provision_path" value="{{ old('provider_provision_path', $product->provider_provision_path) }}" placeholder="/api/provision"></label>
<label>Provider Renew Path<input name="provider_renew_path" value="{{ old('provider_renew_path', $product->provider_renew_path) }}" placeholder="/api/services/{external_id}/renew"></label>
<label>Provider Suspend Path<input name="provider_suspend_path" value="{{ old('provider_suspend_path', $product->provider_suspend_path) }}" placeholder="/api/services/{external_id}/suspend"></label>
<label>Provider Cancel Path<input name="provider_cancel_path" value="{{ old('provider_cancel_path', $product->provider_cancel_path) }}" placeholder="/api/services/{external_id}/cancel"></label>
<label>Provider Sync Path<input name="provider_sync_path" value="{{ old('provider_sync_path', $product->provider_sync_path) }}" placeholder="/api/services/{external_id}"></label>
<label>Provider Lifecycle Path<input name="provider_lifecycle_path" value="{{ old('provider_lifecycle_path', $product->provider_lifecycle_path) }}" placeholder="/api/services/{external_id}"></label>
<label>Provider Ordered At Path<input name="provider_lifecycle_ordered_at_path" value="{{ old('provider_lifecycle_ordered_at_path', $product->provider_lifecycle_ordered_at_path) }}" placeholder="data.ordered_at"></label>
<label>Provider Expires At Path<input name="provider_lifecycle_expires_at_path" value="{{ old('provider_lifecycle_expires_at_path', $product->provider_lifecycle_expires_at_path) }}" placeholder="data.expires_at"></label>
<label>Provider Date Format
    <select name="provider_lifecycle_date_format" required>
        @foreach (['iso8601' => 'ISO 8601', 'unix_seconds' => 'Unix Seconds', 'unix_ms' => 'Unix Milliseconds'] as $value => $label)
            <option value="{{ $value }}" @selected(old('provider_lifecycle_date_format', $product->provider_lifecycle_date_format ?: 'iso8601') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Provider Date Timezone<input name="provider_lifecycle_timezone" value="{{ old('provider_lifecycle_timezone', $product->provider_lifecycle_timezone ?: 'UTC') }}" placeholder="UTC"></label>
<label>Provider Options<textarea name="provider_options">{{ old('provider_options', json_encode($product->provider_options ?? [], JSON_PRETTY_PRINT)) }}</textarea></label>
<p><button type="submit">Save Product</button></p>
