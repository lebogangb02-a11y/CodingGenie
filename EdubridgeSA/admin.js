// Dark mode toggle and small helpers
(() => {
  const toggle = document.getElementById('darkModeToggle');
  const applyMode = (enabled) => {
    document.body.classList.toggle('bg-dark', enabled);
    document.body.classList.toggle('text-white', enabled);
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('bg-dark', enabled);
  };
  if (toggle) {
    const saved = localStorage.getItem('darkMode') === '1';
    toggle.checked = saved; applyMode(saved);
    toggle.addEventListener('change', () => {
      localStorage.setItem('darkMode', toggle.checked ? '1' : '0');
      applyMode(toggle.checked);
    });
  }
})();

// Bulk selection helper
function toggleAll(source, name) {
  document.querySelectorAll(`input[name='${name}[]']`).forEach(cb => cb.checked = source.checked);
}