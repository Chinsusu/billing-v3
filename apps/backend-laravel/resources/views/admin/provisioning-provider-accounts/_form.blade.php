@php
    $driverValue = old('driver', $providerAccount->driver ?: 'sandbox');
    $authTypeValue = old('auth_type', $providerAccount->auth_type ?: 'none');
    $requestTemplate = old('request_template', json_encode($providerAccount->request_template ?? [], JSON_PRETTY_PRINT));
@endphp

@csrf

<div class="product-form-grid">
    <section class="product-form-section product-form-section--provider-identity" aria-labelledby="provider-account-identity-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">01</span>
            <div>
                <h2 class="product-form-section-title" id="provider-account-identity-title">Provider identity</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field" for="provider-account-slug">
                <span>Slug</span>
                <input id="provider-account-slug" name="slug" value="{{ old('slug', $providerAccount->slug) }}" required>
            </label>

            <label class="product-form-field" for="provider-account-name">
                <span>Name</span>
                <input id="provider-account-name" name="name" value="{{ old('name', $providerAccount->name) }}" required>
            </label>

            <label class="product-form-field" for="provider-account-driver">
                <span>Driver</span>
                <select id="provider-account-driver" name="driver" required data-provider-driver-select>
                    @foreach (['sandbox' => 'Sandbox', 'generic_http' => 'Generic HTTP', 'cloudmini_v3' => 'Cloudmini V3'] as $value => $label)
                        <option value="{{ $value }}" @selected($driverValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <input type="hidden" name="enabled" value="0">
            <label class="product-form-toggle" for="provider-account-enabled">
                <input id="provider-account-enabled" type="checkbox" name="enabled" value="1" @checked(old('enabled', $providerAccount->enabled ?? true))>
                <span>
                    <strong>Enabled</strong>
                </span>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--provider-http" aria-labelledby="provider-account-http-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">02</span>
            <div>
                <h2 class="product-form-section-title" id="provider-account-http-title">HTTP connection</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field product-form-field--wide" for="provider-account-base-url" data-provider-driver-field>
                <span>Base URL</span>
                <input id="provider-account-base-url" name="base_url" value="{{ old('base_url', $providerAccount->base_url) }}" placeholder="https://provider.example.test" data-required-when-enabled="true">
            </label>

            <label class="product-form-field" for="provider-account-provision-path" data-provider-driver-field>
                <span>Provision Path</span>
                <input id="provider-account-provision-path" name="provision_path" value="{{ old('provision_path', $providerAccount->provision_path) }}" placeholder="/api/provision" data-required-when-enabled="true">
            </label>

            <label class="product-form-field" for="provider-account-timeout-seconds">
                <span>Timeout Seconds</span>
                <input id="provider-account-timeout-seconds" type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $providerAccount->timeout_seconds ?: 15) }}" min="1" max="120" required>
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--provider-auth" aria-labelledby="provider-account-auth-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">03</span>
            <div>
                <h2 class="product-form-section-title" id="provider-account-auth-title">Authentication</h2>
            </div>
        </div>

        <div class="product-form-fields">
            <label class="product-form-field" for="provider-account-auth-type">
                <span>Auth Type</span>
                <select id="provider-account-auth-type" name="auth_type" required data-provider-auth-select>
                    @foreach (['none' => 'None', 'bearer' => 'Bearer', 'header' => 'Custom Header'] as $value => $label)
                        <option value="{{ $value }}" @selected($authTypeValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="product-form-field" for="provider-account-auth-header-name" data-provider-auth-header-field>
                <span>Auth Header Name</span>
                <input id="provider-account-auth-header-name" name="auth_header_name" value="{{ old('auth_header_name', $providerAccount->auth_header_name) }}" placeholder="X-API-Key" data-required-when-enabled="true">
            </label>

            <label class="product-form-field product-form-field--wide" for="provider-account-api-key" data-provider-api-key-field>
                <span>API Key</span>
                <input id="provider-account-api-key" type="password" name="api_key" autocomplete="new-password" placeholder="{{ $providerAccount->api_key_last_four ? 'Configured ...'.$providerAccount->api_key_last_four : 'Enter API key' }}">
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--provider-mapping product-form-section--wide" aria-labelledby="provider-account-mapping-title" data-provider-generic-mapping-section>
        <div class="product-form-section-header">
            <span class="product-form-section-index">04</span>
            <div>
                <h2 class="product-form-section-title" id="provider-account-mapping-title">Request &amp; response mapping</h2>
            </div>
        </div>

        <div class="product-form-fields product-form-fields--three">
            <label class="product-form-field product-form-field--wide" for="provider-account-request-template" data-provider-driver-field>
                <span>Request Template</span>
                <textarea id="provider-account-request-template" name="request_template" rows="6">{{ $requestTemplate }}</textarea>
            </label>

            <label class="product-form-field" for="provider-account-external-id-path">
                <span>External ID Path</span>
                <input id="provider-account-external-id-path" name="response_external_id_path" value="{{ old('response_external_id_path', $providerAccount->response_external_id_path ?: 'external_id') }}" required>
            </label>

            <label class="product-form-field" for="provider-account-status-path">
                <span>Status Path</span>
                <input id="provider-account-status-path" name="response_status_path" value="{{ old('response_status_path', $providerAccount->response_status_path ?: 'status') }}" required>
            </label>

            <label class="product-form-field" for="provider-account-config-path">
                <span>Config Path</span>
                <input id="provider-account-config-path" name="response_config_path" value="{{ old('response_config_path', $providerAccount->response_config_path) }}">
            </label>
        </div>
    </section>

    <section class="product-form-section product-form-section--provider-callbacks product-form-section--wide" aria-labelledby="provider-account-callback-title">
        <div class="product-form-section-header">
            <span class="product-form-section-index">05</span>
            <div>
                <h2 class="product-form-section-title" id="provider-account-callback-title">Provider callbacks</h2>
            </div>
        </div>

        <div class="product-form-fields product-form-fields--three">
            <label class="product-form-field product-form-field--wide" for="provider-account-callback-secret">
                <span>Callback Secret</span>
                <input id="provider-account-callback-secret" type="password" name="callback_secret" autocomplete="new-password" placeholder="{{ $providerAccount->callback_secret_last_four ? 'Configured ...'.$providerAccount->callback_secret_last_four : 'Enter callback secret' }}">
            </label>

            <label class="product-form-field" for="provider-account-callback-event-id-path">
                <span>Callback Event ID Path</span>
                <input id="provider-account-callback-event-id-path" name="callback_event_id_path" value="{{ old('callback_event_id_path', $providerAccount->callback_event_id_path ?: 'event_id') }}" required>
            </label>

            <label class="product-form-field" for="provider-account-callback-external-id-path">
                <span>Callback External ID Path</span>
                <input id="provider-account-callback-external-id-path" name="callback_external_id_path" value="{{ old('callback_external_id_path', $providerAccount->callback_external_id_path ?: 'external_id') }}" required>
            </label>

            <label class="product-form-field" for="provider-account-callback-action-path">
                <span>Callback Action Path</span>
                <input id="provider-account-callback-action-path" name="callback_action_path" value="{{ old('callback_action_path', $providerAccount->callback_action_path ?: 'action') }}" required>
            </label>

            <label class="product-form-field" for="provider-account-callback-status-path">
                <span>Callback Status Path</span>
                <input id="provider-account-callback-status-path" name="callback_status_path" value="{{ old('callback_status_path', $providerAccount->callback_status_path ?: 'status') }}" required>
            </label>
        </div>
    </section>
</div>

<div class="product-form-actions">
    <a class="button secondary button-soft" href="/admin/provisioning-provider-accounts">Cancel</a>
    <button type="submit">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
        <span>Save Provider Account</span>
    </button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.provider-account-form').forEach((form) => {
            const driverSelect = form.querySelector('[data-provider-driver-select]');
            const authSelect = form.querySelector('[data-provider-auth-select]');
            const driverFields = Array.from(form.querySelectorAll('[data-provider-driver-field]'));
            const genericMappingSection = form.querySelector('[data-provider-generic-mapping-section]');
            const authHeaderField = form.querySelector('[data-provider-auth-header-field]');
            const apiKeyField = form.querySelector('[data-provider-api-key-field]');

            if (!driverSelect || !authSelect) {
                return;
            }

            const controlsIn = (field) => Array.from(field?.querySelectorAll('input, select, textarea') || []);
            const rememberRequiredState = (field) => {
                controlsIn(field).forEach((control) => {
                    if (control.required || control.hasAttribute('data-required-when-enabled')) {
                        control.setAttribute('data-required-when-enabled', 'true');
                    }
                });
            };
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

            [...driverFields, genericMappingSection, authHeaderField, apiKeyField].forEach(rememberRequiredState);

            const syncProviderAccountControls = () => {
                const usesGenericHttp = driverSelect.value === 'generic_http';
                const usesCloudmini = driverSelect.value === 'cloudmini_v3';
                const usesCustomHeader = authSelect.value === 'header';
                const usesSecretAuth = authSelect.value !== 'none';

                if (genericMappingSection) {
                    genericMappingSection.hidden = usesCloudmini;
                    if (usesCloudmini) {
                        setFieldDisabled(genericMappingSection, true);
                    } else {
                        genericMappingSection.classList.remove('is-disabled');
                        genericMappingSection.setAttribute('aria-disabled', 'false');
                        controlsIn(genericMappingSection).forEach((control) => {
                            control.disabled = false;
                            control.required = control.hasAttribute('data-required-when-enabled');
                        });
                    }
                }
                driverFields.forEach((field) => setFieldDisabled(field, !usesGenericHttp));
                form.querySelectorAll('#provider-account-base-url').forEach((control) => {
                    const baseUrlField = control.closest('[data-provider-driver-field]');
                    const baseUrlDisabled = !(usesGenericHttp || usesCloudmini);
                    baseUrlField?.classList.toggle('is-disabled', baseUrlDisabled);
                    baseUrlField?.setAttribute('aria-disabled', baseUrlDisabled ? 'true' : 'false');
                    control.disabled = baseUrlDisabled;
                    control.required = ! baseUrlDisabled;
                });
                setFieldDisabled(authHeaderField, !usesCustomHeader);
                setFieldDisabled(apiKeyField, !usesSecretAuth);
            };

            driverSelect.addEventListener('change', () => {
                if (driverSelect.value === 'cloudmini_v3' && authSelect.value === 'none') {
                    authSelect.value = 'header';
                    const headerName = form.querySelector('#provider-account-auth-header-name');
                    if (headerName && headerName.value.trim() === '') {
                        headerName.value = 'X-API-Key';
                    }
                }

                syncProviderAccountControls();
            });
            authSelect.addEventListener('change', syncProviderAccountControls);
            syncProviderAccountControls();
        });
    });
</script>
