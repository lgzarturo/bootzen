/**
 * Menú de navegación móvil (lo usa <x-navigation>). En pantallas chicas muestra u oculta los enlaces.
 */
export default function menu(element) {
  const toggle = element.querySelector('[data-menu-toggle]');
  const items = element.querySelector('[data-menu-items]');

  toggle?.addEventListener('click', () => {
    const open = items.classList.toggle('hidden') === false;
    items.classList.toggle('flex', open);
    items.classList.toggle('flex-col', open);
    toggle.setAttribute('aria-expanded', String(open));
  });
}
