{{-- Applies the saved theme before first paint (design system §9): light, dark or system (default).
     Inline so there is no flash; carries the CSP nonce. localStorage is a per-browser convenience. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var choice = 'system';
        try { choice = localStorage.getItem('theme') || 'system'; } catch (e) {}
        var dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = choice;
    })();
</script>
