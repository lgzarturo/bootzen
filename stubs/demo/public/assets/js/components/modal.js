/**
 * Modal con <dialog>. Uso en la vista: <x-modal id="borrar-3"> y un botón data-modal-open="borrar-3".
 * Se cierra con cualquier elemento data-modal-close, con Escape o con un clic fuera del contenido.
 */
export default function modal(dialog) {
  const open = (event) => {
    if (event.target.closest(`[data-modal-open="${dialog.id}"]`)) {
      event.preventDefault();
      dialog.showModal();
    }
  };

  const close = (event) => {
    if (event.target.closest('[data-modal-close]') || event.target === dialog) {
      dialog.close();
    }
  };

  document.addEventListener('click', open);
  dialog.addEventListener('click', close);

  return () => document.removeEventListener('click', open);
}
