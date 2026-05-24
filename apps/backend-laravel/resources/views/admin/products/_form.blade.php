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
<label>Provider Options<textarea name="provider_options">{{ old('provider_options', json_encode($product->provider_options ?? [], JSON_PRETTY_PRINT)) }}</textarea></label>
<p><button type="submit">Save Product</button></p>
