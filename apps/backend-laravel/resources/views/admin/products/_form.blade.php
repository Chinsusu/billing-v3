@php
    $providerOptions = old('provider_options', json_encode($product->provider_options ?? [], JSON_PRETTY_PRINT));
    $statusValue = old('status', $product->status ?: 'draft');
    $typeValue = old('type', $product->type ?: 'proxy');
    $lifecycleSourceValue = old('lifecycle_source', $product->lifecycle_source ?: 'local_policy');
    $lifecycleUnitValue = old('lifecycle_unit', $product->lifecycle_unit ?: 'day');
@endphp

@csrf

<div class="product-form-grid">
    <section class="product-form-section product-form-section--identity" aria-labelledby="product-identity-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">01</span>
            <div>
                <h2 class="product-form-section-title" id="product-identity-title">Product identity</h2>
                <p class="product-form-section-copy">Customer-facing catalog details and admin status.</p>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field" for="product-code">
                <span>Code</span>
                <input id="product-code" name="code" value="{{ old('code', $product->code) }}" required>
                <small class="field-help">Stable SKU used by API, orders, and reports.</small>
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
                <small class="field-help">Only active products appear in the customer catalog.</small>
            </label>

            <label class="product-form-field product-form-field--wide" for="product-description">
                <span>Description</span>
                <textarea id="product-description" name="description" rows="5">{{ old('description', $product->description) }}</textarea>
                <small class="field-help">Keep this concise; it is shown to customers before purchase.</small>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--pricing" aria-labelledby="product-pricing-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">02</span>
            <div>
                <h2 class="product-form-section-title" id="product-pricing-title">Pricing &amp; lifecycle</h2>
                <p class="product-form-section-copy">Charge amount and how service dates are calculated.</p>
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
                <small class="field-help">Fallback duration for reports and local day-based products.</small>
            </label>

            <label class="product-form-field" for="product-lifecycle-source">
                <span>Lifecycle Source</span>
                <select id="product-lifecycle-source" name="lifecycle_source" required>
                    @foreach (['local_policy' => 'Local Policy', 'provider_response' => 'Provider Response', 'provider_lookup' => 'Provider Lookup'] as $value => $label)
                        <option value="{{ $value }}" @selected($lifecycleSourceValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="field-help">Use provider lookup when dates must be fetched after external_id exists.</small>
            </label>

            <label class="product-form-field" for="product-lifecycle-unit">
                <span>Lifecycle Unit</span>
                <select id="product-lifecycle-unit" name="lifecycle_unit" required data-lifecycle-unit-select>
                    @foreach (['day' => 'Days', 'calendar_month' => 'Calendar Months'] as $value => $label)
                        <option value="{{ $value }}" @selected($lifecycleUnitValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-lifecycle-count">
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
                <p class="product-form-section-copy">Route this plan to a provider account, endpoint paths, and request options.</p>
            </div>
        </div>

        <div class="product-form-fields product-form-fields--three">
            <label class="product-form-field" for="product-provider-account">
                <span>Provider Account</span>
                <select id="product-provider-account" name="provider_account_id">
                    <option value="">Default sandbox</option>
                    @foreach ($providerAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('provider_account_id', $product->provider_account_id) === $account->id)>{{ $account->name }} ({{ $account->slug }})</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-provider-plan-code">
                <span>Provider Plan Code</span>
                <input id="product-provider-plan-code" name="provider_plan_code" value="{{ old('provider_plan_code', $product->provider_plan_code) }}" placeholder="A1">
            </label>

            <label class="product-form-field" for="product-provider-region">
                <span>Provider Region</span>
                <input id="product-provider-region" name="provider_region" value="{{ old('provider_region', $product->provider_region) }}" placeholder="sgp1">
            </label>

            <label class="product-form-field" for="product-provider-provision-path">
                <span>Provision Path</span>
                <input id="product-provider-provision-path" name="provider_provision_path" value="{{ old('provider_provision_path', $product->provider_provision_path) }}" placeholder="/api/provision">
            </label>

            <label class="product-form-field" for="product-provider-renew-path">
                <span>Renew Path</span>
                <input id="product-provider-renew-path" name="provider_renew_path" value="{{ old('provider_renew_path', $product->provider_renew_path) }}" placeholder="/api/services/{external_id}/renew">
            </label>

            <label class="product-form-field" for="product-provider-suspend-path">
                <span>Suspend Path</span>
                <input id="product-provider-suspend-path" name="provider_suspend_path" value="{{ old('provider_suspend_path', $product->provider_suspend_path) }}" placeholder="/api/services/{external_id}/suspend">
            </label>

            <label class="product-form-field" for="product-provider-cancel-path">
                <span>Cancel Path</span>
                <input id="product-provider-cancel-path" name="provider_cancel_path" value="{{ old('provider_cancel_path', $product->provider_cancel_path) }}" placeholder="/api/services/{external_id}/cancel">
            </label>

            <label class="product-form-field" for="product-provider-sync-path">
                <span>Sync Path</span>
                <input id="product-provider-sync-path" name="provider_sync_path" value="{{ old('provider_sync_path', $product->provider_sync_path) }}" placeholder="/api/services/{external_id}">
            </label>

            <label class="product-form-field product-form-field--wide" for="product-provider-options">
                <span>Provider Options</span>
                <textarea id="product-provider-options" name="provider_options" rows="6">{{ $providerOptions }}</textarea>
                <small class="field-help">JSON object merged into provider request templates.</small>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--renewal" aria-labelledby="product-renewal-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">04</span>
            <div>
                <h2 class="product-form-section-title" id="product-renewal-title">Auto-renew Policy</h2>
                <p class="product-form-section-copy">Controls renewal eligibility, lead time, and retry behavior.</p>
            </div>
        </div>

        <div class="product-form-fields">
            <input type="hidden" name="auto_renew_allowed" value="0">
            <label class="product-form-toggle product-form-field--wide" for="product-auto-renew-allowed">
                <input id="product-auto-renew-allowed" type="checkbox" name="auto_renew_allowed" value="1" @checked(old('auto_renew_allowed', $product->exists ? $product->auto_renew_allowed : true))>
                <span>
                    <strong>Allow auto-renewal</strong>
                    <small>Customers can renew this service automatically when wallet balance is enough.</small>
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

    <section class="product-form-section product-form-section--lifecycle" aria-labelledby="product-provider-lifecycle-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">05</span>
            <div>
                <h2 class="product-form-section-title" id="product-provider-lifecycle-title">Provider lifecycle response</h2>
                <p class="product-form-section-copy">Map provider date fields when lifecycle comes from API responses.</p>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field product-form-field--wide" for="product-provider-lifecycle-path">
                <span>Lifecycle Lookup Path</span>
                <input id="product-provider-lifecycle-path" name="provider_lifecycle_path" value="{{ old('provider_lifecycle_path', $product->provider_lifecycle_path) }}" placeholder="/api/services/{external_id}">
            </label>

            <label class="product-form-field" for="product-provider-ordered-path">
                <span>Ordered At Path</span>
                <input id="product-provider-ordered-path" name="provider_lifecycle_ordered_at_path" value="{{ old('provider_lifecycle_ordered_at_path', $product->provider_lifecycle_ordered_at_path) }}" placeholder="data.ordered_at">
            </label>

            <label class="product-form-field" for="product-provider-expires-path">
                <span>Expires At Path</span>
                <input id="product-provider-expires-path" name="provider_lifecycle_expires_at_path" value="{{ old('provider_lifecycle_expires_at_path', $product->provider_lifecycle_expires_at_path) }}" placeholder="data.expires_at">
            </label>

            <label class="product-form-field" for="product-provider-date-format">
                <span>Date Format</span>
                <select id="product-provider-date-format" name="provider_lifecycle_date_format" required>
                    @foreach (['iso8601' => 'ISO 8601', 'unix_seconds' => 'Unix Seconds', 'unix_ms' => 'Unix Milliseconds'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('provider_lifecycle_date_format', $product->provider_lifecycle_date_format ?: 'iso8601') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="product-provider-timezone">
                <span>Date Timezone</span>
                <input id="product-provider-timezone" name="provider_lifecycle_timezone" value="{{ old('provider_lifecycle_timezone', $product->provider_lifecycle_timezone ?: 'UTC') }}" placeholder="UTC">
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
            const unitSelect = form.querySelector('[data-lifecycle-unit-select]');
            const countInput = form.querySelector('[data-lifecycle-count-input]');
            const durationField = form.querySelector('[data-lifecycle-duration-field]');
            const durationInput = durationField?.querySelector('input[name="duration_days"]');

            if (!unitSelect || !durationField || !durationInput) {
                return;
            }

            const syncDurationVisibility = () => {
                const usesCalendarMonth = unitSelect.value === 'calendar_month';
                durationField.hidden = usesCalendarMonth;
                durationInput.disabled = usesCalendarMonth;
                durationInput.required = !usesCalendarMonth;

                if (usesCalendarMonth && (!durationInput.value || Number.parseInt(durationInput.value, 10) < 1)) {
                    const lifecycleCount = Math.max(1, Number.parseInt(countInput?.value || '1', 10) || 1);
                    durationInput.value = String(lifecycleCount * 30);
                }
            };

            unitSelect.addEventListener('change', () => {
                if (unitSelect.value === 'calendar_month' && countInput && (!countInput.value || countInput.value === '30')) {
                    countInput.value = '1';
                }

                syncDurationVisibility();
            });
            countInput?.addEventListener('input', syncDurationVisibility);
            syncDurationVisibility();
        });
    });
</script>
