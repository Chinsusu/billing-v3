<script>
    (() => {
        const bootRealtimeSearch = () => {
            document.querySelectorAll('[data-realtime-search]').forEach((picker) => {
                if (picker.dataset.realtimeSearchReady === 'true') {
                    return;
                }

                const search = picker.querySelector('[data-realtime-search-input]');
                const value = picker.querySelector('[data-realtime-search-value]');
                const optionPanel = picker.querySelector('[data-realtime-search-options]');
                const emptyState = picker.querySelector('[data-realtime-search-empty]');
                const options = Array.from(picker.querySelectorAll('[data-realtime-search-option]'));
                const form = picker.closest('form');
                const allowFreeText = picker.hasAttribute('data-realtime-search-free-text');
                let suppressNextFocus = false;

                if (!search || !value || !optionPanel) {
                    return;
                }

                picker.dataset.realtimeSearchReady = 'true';

                const normalize = (text) => (text || '').trim().toLowerCase();

                const setOpen = (open) => {
                    picker.classList.toggle('is-open', open);
                    search.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                const visibleOptions = () => options.filter((option) => !option.hidden);

                const markSelected = (selectedValue) => {
                    options.forEach((option) => {
                        option.setAttribute('aria-selected', option.dataset.value === selectedValue ? 'true' : 'false');
                    });
                };

                const selectOption = (option) => {
                    if (!option) {
                        return;
                    }

                    const nextValue = option.dataset.value || '';
                    search.value = option.dataset.label || nextValue;
                    value.value = nextValue;
                    markSelected(nextValue);
                    suppressNextFocus = true;
                    setOpen(false);
                    search.focus();
                };

                const filterOptions = () => {
                    const term = normalize(search.value);
                    let shown = 0;
                    let exactMatch = null;

                    options.forEach((option) => {
                        const matches = term === '' || normalize(option.dataset.search).includes(term);
                        option.hidden = !matches;
                        if (matches) {
                            shown += 1;
                        }
                        if (normalize(option.dataset.value) === term || normalize(option.dataset.label) === term) {
                            exactMatch = option;
                        }
                    });

                    if (emptyState) {
                        emptyState.hidden = shown !== 0;
                    }

                    if (exactMatch) {
                        value.value = exactMatch.dataset.value || '';
                        markSelected(value.value);
                    } else if (allowFreeText) {
                        value.value = search.value.trim();
                        markSelected('');
                    } else if (normalize(value.value) !== term) {
                        value.value = '';
                        markSelected('');
                    }

                    setOpen(true);
                };

                options.forEach((option) => {
                    option.addEventListener('click', () => selectOption(option));
                });

                search.addEventListener('focus', () => {
                    if (suppressNextFocus) {
                        suppressNextFocus = false;

                        return;
                    }

                    filterOptions();
                });
                search.addEventListener('input', filterOptions);
                search.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                        return;
                    }

                    if (event.key === 'Enter' && picker.classList.contains('is-open')) {
                        const firstVisible = visibleOptions()[0];
                        if (firstVisible) {
                            event.preventDefault();
                            selectOption(firstVisible);
                        }
                    }
                });

                form?.addEventListener('submit', () => {
                    if (allowFreeText) {
                        value.value = search.value.trim();
                    } else if (!value.value && search.value.trim() !== '') {
                        value.value = search.value.trim();
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!picker.contains(event.target)) {
                        setOpen(false);
                    }
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootRealtimeSearch);
        } else {
            bootRealtimeSearch();
        }
    })();
</script>
