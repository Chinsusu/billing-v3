@php
    $providerOptionsArray = is_array($product->provider_options ?? null) ? $product->provider_options : [];
    $providerOptions = old('provider_options', json_encode($providerOptionsArray, JSON_PRETTY_PRINT));
    $statusValue = old('status', $product->status ?: 'draft');
    $typeValue = old('type', $product->type ?: 'proxy');
    $lifecycleSourceValue = old('lifecycle_source', $product->lifecycle_source ?: 'local_policy');
    $lifecycleUnitValue = old('lifecycle_unit', $product->lifecycle_unit ?: 'day');
    $providerRoutes = old('provider_routes');
    if ($providerRoutes === null) {
        $providerRoutes = $product->providerRoutes?->map(fn ($route) => [
            'provider_account_id' => $route->provider_account_id,
            'enabled' => $route->enabled ? '1' : '0',
            'priority' => $route->priority,
            'weight' => $route->weight,
            'billing_group_id' => $route->billing_group_id,
            'node_selector_type' => $route->node_selector_type,
            'node_name' => $route->node_name,
            'options' => json_encode($route->options ?? [], JSON_PRETTY_PRINT),
        ])->values()->all() ?? [];
    }
    if ($providerRoutes === []) {
        $providerRoutes = [[]];
    }
    $selectedProviderAccountId = old('provider_account_id', $product->provider_account_id);
    $selectedProviderAccount = $selectedProviderAccountId ? $providerAccounts->firstWhere('id', $selectedProviderAccountId) : null;
    $hasCloudminiRoutes = collect($providerRoutes)->contains(fn ($route) => ($route['provider_account_id'] ?? '') !== '');
    $hasCloudminiOptions = collect(['kind', 'protocol', 'speed_limit_mbps', 'bandwidth_limit_mb', 'preferred_outbound_ip', 'reserve_capacity'])
        ->contains(fn ($key) => array_key_exists($key, $providerOptionsArray));
    $providerModeValue = old('provider_mode', ($hasCloudminiRoutes || $hasCloudminiOptions || $selectedProviderAccount?->driver === 'cloudmini_v3') ? 'cloudmini_v3' : ($selectedProviderAccount ? 'generic_http' : 'sandbox'));
@endphp

@csrf

<div class="product-form-grid">
    <section class="product-form-section product-form-section--identity" aria-labelledby="product-identity-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">01</span>
            <div>
                <h2 class="product-form-section-title" id="product-identity-title">Product identity</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field" for="product-code">
                <span>Code</span>
                <input id="product-code" name="code" value="{{ old('code', $product->code) }}" required>
            </label>

            <label class="product-form-field" for="product-name">
                <span>Name</span>
                <input id="product-name" name="name" value="{{ old('name', $product->name) }}" required>
            </label>

            <label class="product-form-field" for="product-type">
                <span>Type</span>
                <select id="product-type" name="type" required>
                    @foreach (['proxy' => 'Proxy', 'vps' => 'VPS'] as $value => $label)
                        <option value="{{ $value }}" @selected($typeValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-status">
                <span>Status</span>
                <select id="product-status" name="status" required>
                    @foreach (['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field product-form-field--wide" for="product-description">
                <span>Description</span>
                <textarea id="product-description" name="description" rows="5">{{ old('description', $product->description) }}</textarea>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--pricing" aria-labelledby="product-pricing-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">02</span>
            <div>
                <h2 class="product-form-section-title" id="product-pricing-title">Pricing &amp; lifecycle</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field" for="product-price-amount">
                <span>Price Amount</span>
                <input id="product-price-amount" type="number" name="price_amount" value="{{ old('price_amount', $product->price_amount) }}" min="1" inputmode="numeric" required>
            </label>

            <label class="product-form-field" for="product-currency">
                <span>Currency</span>
                <input id="product-currency" name="currency" value="{{ old('currency', $product->currency ?: 'VND') }}" maxlength="3" required>
            </label>

            <label class="product-form-field product-form-field--duration" for="product-duration-days" data-lifecycle-duration-field>
                <span>Duration Days</span>
                <input id="product-duration-days" type="number" name="duration_days" value="{{ old('duration_days', $product->duration_days ?: 30) }}" min="1" inputmode="numeric" required>
            </label>

            <label class="product-form-field" for="product-lifecycle-source" data-lifecycle-source-field>
                <span>Lifecycle Source</span>
                <select id="product-lifecycle-source" name="lifecycle_source" required data-lifecycle-source-select>
                    @foreach (['local_policy' => 'Local Policy', 'provider_response' => 'Provider Response', 'provider_lookup' => 'Provider Lookup'] as $value => $label)
                        <option value="{{ $value }}" @selected($lifecycleSourceValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-lifecycle-unit" data-lifecycle-unit-field>
                <span>Lifecycle Unit</span>
                <select id="product-lifecycle-unit" name="lifecycle_unit" required data-lifecycle-unit-select>
                    @foreach (['day' => 'Days', 'calendar_month' => 'Calendar Months'] as $value => $label)
                        <option value="{{ $value }}" @selected($lifecycleUnitValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-lifecycle-count" data-lifecycle-count-field>
                <span>Lifecycle Count</span>
                <input id="product-lifecycle-count" type="number" name="lifecycle_count" value="{{ old('lifecycle_count', $product->lifecycle_count ?: $product->duration_days ?: 30) }}" min="1" inputmode="numeric" required data-lifecycle-count-input>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--provider product-form-section--wide" aria-labelledby="product-provider-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">03</span>
            <div>
                <h2 class="product-form-section-title" id="product-provider-title">Provisioning provider</h2>
            </div>
        </div>

        <div class="product-form-fields product-form-fields--three">
            <label class="product-form-field" for="product-provider-mode">
                <span>Provider Type</span>
                <select id="product-provider-mode" data-product-provider-mode-select>
                    @foreach (['sandbox' => 'Sandbox', 'generic_http' => 'Generic HTTP', 'cloudmini_v3' => 'Cloudmini V3'] as $value => $label)
                        <option value="{{ $value }}" @selected($providerModeValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-provider-account" data-product-provider-legacy-field>
                <span>Provider Account</span>
                <select id="product-provider-account" name="provider_account_id">
                    <option value="">Default sandbox</option>
                    @foreach ($providerAccounts->where('driver', '!=', 'cloudmini_v3') as $account)
                        <option value="{{ $account->id }}" @selected(old('provider_account_id', $product->provider_account_id) === $account->id)>{{ $account->name }} ({{ $account->slug }})</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-provider-plan-code" data-product-provider-legacy-field>
                <span>Provider Plan Code</span>
                <input id="product-provider-plan-code" name="provider_plan_code" value="{{ old('provider_plan_code', $product->provider_plan_code) }}" placeholder="A1">
            </label>

            <label class="product-form-field" for="product-provider-region" data-product-provider-legacy-field>
                <span>Provider Region</span>
                <input id="product-provider-region" name="provider_region" value="{{ old('provider_region', $product->provider_region) }}" placeholder="sgp1">
            </label>

            <label class="product-form-field" for="product-provider-provision-path" data-product-provider-legacy-field>
                <span>Provision Path</span>
                <input id="product-provider-provision-path" name="provider_provision_path" value="{{ old('provider_provision_path', $product->provider_provision_path) }}" placeholder="/api/provision">
            </label>

            <label class="product-form-field" for="product-provider-renew-path" data-product-provider-legacy-field>
                <span>Renew Path</span>
                <input id="product-provider-renew-path" name="provider_renew_path" value="{{ old('provider_renew_path', $product->provider_renew_path) }}" placeholder="/api/services/{external_id}/renew">
            </label>

            <label class="product-form-field" for="product-provider-suspend-path" data-product-provider-legacy-field>
                <span>Suspend Path</span>
                <input id="product-provider-suspend-path" name="provider_suspend_path" value="{{ old('provider_suspend_path', $product->provider_suspend_path) }}" placeholder="/api/services/{external_id}/suspend">
            </label>

            <label class="product-form-field" for="product-provider-cancel-path" data-product-provider-legacy-field>
                <span>Cancel Path</span>
                <input id="product-provider-cancel-path" name="provider_cancel_path" value="{{ old('provider_cancel_path', $product->provider_cancel_path) }}" placeholder="/api/services/{external_id}/cancel">
            </label>

            <label class="product-form-field" for="product-provider-sync-path" data-product-provider-legacy-field>
                <span>Sync Path</span>
                <input id="product-provider-sync-path" name="provider_sync_path" value="{{ old('provider_sync_path', $product->provider_sync_path) }}" placeholder="/api/services/{external_id}">
            </label>

            <label class="product-form-field" for="product-cloudmini-kind" data-product-provider-cloudmini-field>
                <span>Cloudmini Kind</span>
                <select id="product-cloudmini-kind" name="cloudmini_options[kind]">
                    @foreach (['ipv4_dc' => 'IPv4 Datacenter', 'residential' => 'Residential'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('cloudmini_options.kind', $providerOptionsArray['kind'] ?? 'ipv4_dc') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-cloudmini-protocol" data-product-provider-cloudmini-field>
                <span>Cloudmini Protocol</span>
                <input id="product-cloudmini-protocol" name="cloudmini_options[protocol]" value="{{ old('cloudmini_options.protocol', $providerOptionsArray['protocol'] ?? 'default') }}" placeholder="default">
            </label>

            <label class="product-form-field" for="product-cloudmini-speed" data-product-provider-cloudmini-field>
                <span>Speed Limit Mbps</span>
                <input id="product-cloudmini-speed" type="number" name="cloudmini_options[speed_limit_mbps]" value="{{ old('cloudmini_options.speed_limit_mbps', $providerOptionsArray['speed_limit_mbps'] ?? '') }}" min="1">
            </label>

            <label class="product-form-field" for="product-cloudmini-bandwidth" data-product-provider-cloudmini-field>
                <span>Bandwidth Limit MB</span>
                <input id="product-cloudmini-bandwidth" type="number" name="cloudmini_options[bandwidth_limit_mb]" value="{{ old('cloudmini_options.bandwidth_limit_mb', $providerOptionsArray['bandwidth_limit_mb'] ?? '') }}" min="0">
            </label>

            <label class="product-form-field" for="product-cloudmini-outbound-ip" data-product-provider-cloudmini-field>
                <span>Preferred Outbound IP</span>
                <input id="product-cloudmini-outbound-ip" name="cloudmini_options[preferred_outbound_ip]" value="{{ old('cloudmini_options.preferred_outbound_ip', $providerOptionsArray['preferred_outbound_ip'] ?? '') }}">
            </label>

            <input type="hidden" name="cloudmini_options[reserve_capacity]" value="0" data-product-provider-cloudmini-control>
            <label class="product-form-toggle" for="product-cloudmini-reserve-capacity" data-product-provider-cloudmini-field>
                <input id="product-cloudmini-reserve-capacity" type="checkbox" name="cloudmini_options[reserve_capacity]" value="1" @checked(old('cloudmini_options.reserve_capacity', $providerOptionsArray['reserve_capacity'] ?? false))>
                <span>
                    <strong>Reserve Capacity</strong>
                </span>
            </label>

            <label class="product-form-field product-form-field--wide" for="product-provider-options" data-product-provider-legacy-field>
                <span>Advanced Provider Options</span>
                <textarea id="product-provider-options" name="provider_options" rows="6">{{ $providerOptions }}</textarea>
            </label>

            @foreach ($providerRoutes as $index => $route)
                <div class="product-form-field product-form-field--wide" data-product-provider-cloudmini-field>
                    <span>Cloudmini Route {{ $index + 1 }}</span>
                    <div class="product-form-fields product-form-fields--three">
                        <label class="product-form-field" for="product-provider-route-account-{{ $index }}">
                            <span>Cloudmini Account</span>
                            <select id="product-provider-route-account-{{ $index }}" name="provider_routes[{{ $index }}][provider_account_id]">
                                <option value="">No route</option>
                                @foreach ($providerAccounts->where('driver', 'cloudmini_v3') as $account)
                                    <option value="{{ $account->id }}" @selected(($route['provider_account_id'] ?? '') === $account->id)>{{ $account->name }} ({{ $account->slug }})</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="product-form-field" for="product-provider-route-group-{{ $index }}">
                            <span>Billing Group ID</span>
                            <input id="product-provider-route-group-{{ $index }}" name="provider_routes[{{ $index }}][billing_group_id]" value="{{ $route['billing_group_id'] ?? '' }}" placeholder="vn-residential">
                        </label>

                        <label class="product-form-field" for="product-provider-route-enabled-{{ $index }}">
                            <span>Enabled</span>
                            <select id="product-provider-route-enabled-{{ $index }}" name="provider_routes[{{ $index }}][enabled]">
                                <option value="1" @selected(($route['enabled'] ?? '1') === '1')>Enabled</option>
                                <option value="0" @selected(($route['enabled'] ?? '1') === '0')>Disabled</option>
                            </select>
                        </label>

                        <label class="product-form-field" for="product-provider-route-priority-{{ $index }}">
                            <span>Priority</span>
                            <input id="product-provider-route-priority-{{ $index }}" type="number" name="provider_routes[{{ $index }}][priority]" value="{{ $route['priority'] ?? 100 }}" min="1">
                        </label>

                        <label class="product-form-field" for="product-provider-route-weight-{{ $index }}">
                            <span>Weight</span>
                            <input id="product-provider-route-weight-{{ $index }}" type="number" name="provider_routes[{{ $index }}][weight]" value="{{ $route['weight'] ?? 100 }}" min="1">
                        </label>

                        <label class="product-form-field" for="product-provider-route-node-selector-{{ $index }}">
                            <span>Node Selector</span>
                            <select id="product-provider-route-node-selector-{{ $index }}" name="provider_routes[{{ $index }}][node_selector_type]">
                                @foreach (['auto' => 'Auto', 'node_name' => 'Node Name'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($route['node_selector_type'] ?? 'auto') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="product-form-field" for="product-provider-route-node-name-{{ $index }}">
                            <span>Node Name</span>
                            <input id="product-provider-route-node-name-{{ $index }}" name="provider_routes[{{ $index }}][node_name]" value="{{ $route['node_name'] ?? '' }}" placeholder="node-hcm-01">
                        </label>

                        <label class="product-form-field product-form-field--wide" for="product-provider-route-options-{{ $index }}">
                            <span>Route Options</span>
                            <textarea id="product-provider-route-options-{{ $index }}" name="provider_routes[{{ $index }}][options]" rows="4">{{ $route['options'] ?? '' }}</textarea>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="product-form-section product-form-section--renewal" aria-labelledby="product-renewal-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">04</span>
            <div>
                <h2 class="product-form-section-title" id="product-renewal-title">Auto-renew Policy</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <input type="hidden" name="auto_renew_allowed" value="0">
            <label class="product-form-toggle product-form-field--wide" for="product-auto-renew-allowed">
                <input id="product-auto-renew-allowed" type="checkbox" name="auto_renew_allowed" value="1" @checked(old('auto_renew_allowed', $product->exists ? $product->auto_renew_allowed : true))>
                <span>
                    <strong>Allow auto-renewal</strong>
                </span>
            </label>

            <label class="product-form-field" for="product-auto-renew-window">
                <span>Renewal Window Hours</span>
                <input id="product-auto-renew-window" type="number" name="auto_renew_window_hours" value="{{ old('auto_renew_window_hours', $product->auto_renew_window_hours ?: 24) }}" min="1" max="24" inputmode="numeric" required>
            </label>

            <label class="product-form-field" for="product-auto-renew-delay">
                <span>Retry Delay Minutes</span>
                <input id="product-auto-renew-delay" type="number" name="auto_renew_retry_delay_minutes" value="{{ old('auto_renew_retry_delay_minutes', $product->auto_renew_retry_delay_minutes ?: 60) }}" min="5" max="10080" inputmode="numeric" required>
            </label>

            <label class="product-form-field" for="product-auto-renew-attempts">
                <span>Renewal Max Attempts</span>
                <input id="product-auto-renew-attempts" type="number" name="auto_renew_max_attempts" value="{{ old('auto_renew_max_attempts', $product->auto_renew_max_attempts ?: 3) }}" min="1" max="20" inputmode="numeric" required>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--lifecycle" aria-labelledby="product-provider-lifecycle-title" data-product-provider-lifecycle-response-section>
        <div class="product-form-section-header">
            <span class="product-form-section-index">05</span>
            <div>
                <h2 class="product-form-section-title" id="product-provider-lifecycle-title">Provider lifecycle response</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field product-form-field--wide" for="product-provider-lifecycle-path" data-provider-lookup-field>
                <span>Lifecycle Lookup Path</span>
                <input id="product-provider-lifecycle-path" name="provider_lifecycle_path" value="{{ old('provider_lifecycle_path', $product->provider_lifecycle_path) }}" placeholder="/api/services/{external_id}" data-required-when-enabled="true">
            </label>

            <label class="product-form-field" for="product-provider-ordered-path" data-provider-response-field>
                <span>Ordered At Path</span>
                <input id="product-provider-ordered-path" name="provider_lifecycle_ordered_at_path" value="{{ old('provider_lifecycle_ordered_at_path', $product->provider_lifecycle_ordered_at_path) }}" placeholder="data.ordered_at" data-required-when-enabled="true">
            </label>

            <label class="product-form-field" for="product-provider-expires-path" data-provider-response-field>
                <span>Expires At Path</span>
                <input id="product-provider-expires-path" name="provider_lifecycle_expires_at_path" value="{{ old('provider_lifecycle_expires_at_path', $product->provider_lifecycle_expires_at_path) }}" placeholder="data.expires_at" data-required-when-enabled="true">
            </label>

            <label class="product-form-field" for="product-provider-date-format" data-provider-response-field>
                <span>Date Format</span>
                <select id="product-provider-date-format" name="provider_lifecycle_date_format" required>
                    @foreach (['iso8601' => 'ISO 8601', 'unix_seconds' => 'Unix Seconds', 'unix_ms' => 'Unix Milliseconds'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('provider_lifecycle_date_format', $product->provider_lifecycle_date_format ?: 'iso8601') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-provider-timezone" data-provider-response-field>
                <span>Date Timezone</span>
                <input id="product-provider-timezone" name="provider_lifecycle_timezone" value="{{ old('provider_lifecycle_timezone', $product->provider_lifecycle_timezone ?: 'UTC') }}" placeholder="UTC" data-required-when-enabled="true">
            </label>
        </div>
    </section>
</div>

<div class="product-form-actions">
    <a class="button secondary button-soft" href="/admin/products">Cancel</a>
    <button type="submit">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
        <span>{{ $product->exists ? 'Save Changes' : 'Create Product' }}</span>
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.product-form-shell').forEach((form) => {
            const sourceSelect = form.querySelector('[data-lifecycle-source-select]');
            const sourceField = form.querySelector('[data-lifecycle-source-field]');
            const unitSelect = form.querySelector('[data-lifecycle-unit-select]');
            const unitField = form.querySelector('[data-lifecycle-unit-field]');
            const countInput = form.querySelector('[data-lifecycle-count-input]');
            const countField = form.querySelector('[data-lifecycle-count-field]');
            const durationField = form.querySelector('[data-lifecycle-duration-field]');
            const durationInput = durationField?.querySelector('input[name="duration_days"]');
            const providerResponseFields = Array.from(form.querySelectorAll('[data-provider-response-field]'));
            const providerLookupFields = Array.from(form.querySelectorAll('[data-provider-lookup-field]'));
            const providerModeSelect = form.querySelector('[data-product-provider-mode-select]');
            const providerLegacyFields = Array.from(form.querySelectorAll('[data-product-provider-legacy-field]'));
            const providerCloudminiFields = Array.from(form.querySelectorAll('[data-product-provider-cloudmini-field]'));
            const providerCloudminiControls = Array.from(form.querySelectorAll('[data-product-provider-cloudmini-control]'));
            const providerLifecycleResponseSection = form.querySelector('[data-product-provider-lifecycle-response-section]');

            if (!sourceSelect || !unitSelect || !durationField || !durationInput) {
                return;
            }

            const controlsIn = (field) => Array.from(field.querySelectorAll('input, select, textarea'));
            const setFieldDisabled = (field, disabled) => {
                if (!field) {
                    return;
                }

                field.classList.toggle('is-disabled', disabled);
                field.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                controlsIn(field).forEach((control) => {
                    control.disabled = disabled;
                    control.required = disabled ? false : control.hasAttribute('data-required-when-enabled');
                });
            };
            const setFieldsDisabled = (fields, disabled) => fields.forEach((field) => setFieldDisabled(field, disabled));
            const setFieldsHiddenAndDisabled = (fields, hidden) => fields.forEach((field) => {
                field.hidden = hidden;
                setFieldDisabled(field, hidden);
            });
            const setControlsDisabled = (controls, disabled) => controls.forEach((control) => {
                control.disabled = disabled;
                control.required = disabled ? false : control.hasAttribute('data-required-when-enabled');
            });
            const rememberRequiredState = (field) => {
                if (!field) {
                    return;
                }

                controlsIn(field).forEach((control) => {
                    if (control.required) {
                        control.setAttribute('data-required-when-enabled', 'true');
                    }
                });
            };

            [sourceField, unitField, countField, durationField, ...providerResponseFields, ...providerLookupFields, ...providerLegacyFields, ...providerCloudminiFields].forEach(rememberRequiredState);

            const syncLifecycleControls = () => {
                const usesCloudmini = providerModeSelect?.value === 'cloudmini_v3';
                let usesLocalPolicy = sourceSelect.value === 'local_policy';
                const usesCalendarMonth = unitSelect.value === 'calendar_month';

                if (usesCloudmini && sourceSelect.value !== 'local_policy') {
                    sourceSelect.value = 'local_policy';
                    usesLocalPolicy = true;
                }

                if (sourceField) {
                    sourceField.hidden = usesCloudmini;
                    setFieldDisabled(sourceField, usesCloudmini);
                }

                setFieldDisabled(unitField, !usesLocalPolicy);
                setFieldDisabled(durationField, !usesLocalPolicy || usesCalendarMonth);
                setFieldDisabled(countField, !usesLocalPolicy || !usesCalendarMonth);
                setFieldsDisabled(providerResponseFields, usesLocalPolicy);
                setFieldsDisabled(providerLookupFields, sourceSelect.value !== 'provider_lookup');
                if (providerLifecycleResponseSection) {
                    providerLifecycleResponseSection.hidden = usesCloudmini;
                    setFieldsDisabled(providerResponseFields, usesCloudmini || usesLocalPolicy);
                    setFieldsDisabled(providerLookupFields, usesCloudmini || sourceSelect.value !== 'provider_lookup');
                }

                if (usesCalendarMonth && (!durationInput.value || Number.parseInt(durationInput.value, 10) < 1)) {
                    const lifecycleCount = Math.max(1, Number.parseInt(countInput?.value || '1', 10) || 1);
                    durationInput.value = String(lifecycleCount * 30);
                }

                if (usesLocalPolicy && !usesCalendarMonth && countInput) {
                    countInput.value = durationInput.value || countInput.value || '30';
                }
            };

            const syncProviderModeControls = () => {
                if (!providerModeSelect) {
                    return;
                }

                const mode = providerModeSelect.value;
                setFieldsHiddenAndDisabled(providerLegacyFields, mode !== 'generic_http');
                setFieldsHiddenAndDisabled(providerCloudminiFields, mode !== 'cloudmini_v3');
                setControlsDisabled(providerCloudminiControls, mode !== 'cloudmini_v3');
                syncLifecycleControls();
            };

            unitSelect.addEventListener('change', () => {
                if (unitSelect.value === 'calendar_month' && countInput && (!countInput.value || countInput.value === '30')) {
                    countInput.value = '1';
                }

                syncLifecycleControls();
            });
            sourceSelect.addEventListener('change', syncLifecycleControls);
            providerModeSelect?.addEventListener('change', syncProviderModeControls);
            durationInput.addEventListener('input', syncLifecycleControls);
            countInput?.addEventListener('input', syncLifecycleControls);
            syncProviderModeControls();
        });
    });
</script>
