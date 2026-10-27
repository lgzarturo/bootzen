/**
 * Contador de ejemplo. Uso:
 * <div data-component="counter" data-props='{"start": 0}'>
 *   <button data-counter-increment>Sumar</button> <span data-counter-value>0</span>
 * </div>
 *
 * Un componente exporta por defecto una función (elemento, props). Si retorna una función,
 * BootZen la llama al quitar el elemento del DOM (para limpiar timers o listeners globales).
 */
export default function counter(element, { start = 0 }) {
  let value = start;
  const output = element.querySelector('[data-counter-value]');

  element.querySelector('[data-counter-increment]')?.addEventListener('click', () => {
    value += 1;
    output.textContent = String(value);
  });

  output.textContent = String(value);
}
