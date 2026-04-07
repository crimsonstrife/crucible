<div class="d-flex align-items-center gap-1" aria-label="{{ __('Theme') }}">
    <button type="button"
            class="btn btn-sm btn-outline-secondary"
            onclick="window.__setTheme('light')"
            title="{{ __('Light mode') }}"
            aria-label="{{ __('Light mode') }}">
        <i class="fas fa-sun"></i>
    </button>
    <button type="button"
            class="btn btn-sm btn-outline-secondary"
            onclick="window.__setTheme('dark')"
            title="{{ __('Dark mode') }}"
            aria-label="{{ __('Dark mode') }}">
        <i class="fas fa-moon"></i>
    </button>
    <button type="button"
            class="btn btn-sm btn-outline-secondary"
            onclick="window.__setTheme('auto')"
            title="{{ __('System theme') }}"
            aria-label="{{ __('System theme') }}">
        <i class="fas fa-circle-half-stroke"></i>
    </button>
</div>
