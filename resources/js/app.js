// The theme toggle in the header: switch, and remember the choice.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) return;

    const dark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch {
        // Without storage the choice lasts for this page only.
    }
});
