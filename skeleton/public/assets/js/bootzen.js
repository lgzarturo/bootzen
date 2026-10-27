/**
 * bootzen.js: runtime de JavaScript de BootZen (módulo ES nativo, sin dependencias ni bundler).
 *
 * Es parte del framework: no lo edites, tu código va en app.js y en components/.
 *
 * - Componentes: <div data-component="contador" data-props='{"inicio": 0}'> monta
 *   components/contador.js, que exporta por defecto una función (elemento, props).
 *   Si esa función retorna otra función, se llama al quitar el elemento del DOM.
 * - request(url, opciones): fetch con JSON, cabecera CSRF y errores como RequestError.
 * - toast(mensaje, tipo): aviso temporal. Los elementos [data-flash] se muestran al cargar.
 * - Atributos declarativos:
 *   data-confirm="¿Seguro?"   pide confirmación antes de un clic o envío
 *   data-remote              envía el formulario con fetch (respuesta JSON: redirect, message)
 *   data-no-disable          no deshabilita los botones del formulario al enviarlo
 *   data-dismiss             quita el ancestro [data-dismissible] más cercano
 */

const componentsBase = new URL('./components/', import.meta.url);
const mounted = new WeakSet();
const cleanups = new WeakMap();
let started = false;

const TOAST_CLASSES = {
  info: 'rounded-lg bg-gray-900 px-4 py-3 text-sm text-white shadow-lg',
  success: 'rounded-lg bg-green-600 px-4 py-3 text-sm text-white shadow-lg',
  warning: 'rounded-lg bg-yellow-400 px-4 py-3 text-sm text-gray-900 shadow-lg',
  error: 'rounded-lg bg-red-600 px-4 py-3 text-sm text-white shadow-lg',
};

/**
 * Error de una petición hecha con request(): incluye el estado HTTP y el cuerpo de la respuesta.
 */
export class RequestError extends Error {
  constructor(message, status, data) {
    super(message);
    this.name = 'RequestError';
    this.status = status;
    this.data = data;
  }
}

/**
 * Token CSRF de <meta name="csrf-token">, o null si la página no lo tiene.
 */
export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
}

/**
 * fetch con valores por defecto para la app: JSON, cabecera AJAX y CSRF en métodos que modifican datos.
 *
 * @param {string} url
 * @param {{method?: string, data?: object|FormData, headers?: object}} options
 * @returns {Promise<any>} Cuerpo de la respuesta (JSON o texto)
 * @throws {RequestError} Si la respuesta no es 2xx
 */
export async function request(url, { method = 'GET', data, headers = {}, ...options } = {}) {
  method = method.toUpperCase();
  const init = {
    method,
    credentials: 'same-origin',
    ...options,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...headers },
  };

  const token = csrfToken();
  if (token && method !== 'GET' && method !== 'HEAD') {
    init.headers['X-CSRF-TOKEN'] = token;
  }

  if (method === 'GET' && data !== undefined) {
    const target = new URL(url, window.location.href);
    const entries = data instanceof FormData ? data : Object.entries(data);
    for (const [key, value] of entries) {
      target.searchParams.append(key, String(value));
    }
    url = target.toString();
  } else if (data instanceof FormData) {
    init.body = data;
  } else if (data !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(data);
  }

  const response = await fetch(url, init);
  const isJson = (response.headers.get('content-type') ?? '').includes('json');
  const body = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const message = (isJson && body && body.message) || response.statusText || `Error ${response.status}`;
    throw new RequestError(message, response.status, body);
  }

  return body;
}

/**
 * Muestra un aviso temporal en la esquina inferior derecha.
 *
 * @param {string} message
 * @param {'info'|'success'|'warning'|'error'} type
 * @returns {HTMLElement}
 */
export function toast(message, type = 'info', { timeout = 4000 } = {}) {
  let container = document.querySelector('[data-toasts]');
  if (!container) {
    container = document.createElement('div');
    container.setAttribute('data-toasts', '');
    container.className = 'fixed right-4 bottom-4 z-50 flex flex-col gap-2';
    document.body.append(container);
  }

  const element = document.createElement('div');
  element.setAttribute('role', type === 'error' ? 'alert' : 'status');
  element.className = TOAST_CLASSES[type] ?? TOAST_CLASSES.info;
  element.textContent = message;
  container.append(element);

  if (timeout > 0) {
    setTimeout(() => element.remove(), timeout);
  }

  return element;
}

/**
 * Monta los componentes [data-component] dentro de root (incluido root).
 *
 * @param {ParentNode} root
 */
export async function mount(root = document) {
  const elements = [];
  if (root instanceof Element && root.matches('[data-component]')) {
    elements.push(root);
  }
  root.querySelectorAll?.('[data-component]').forEach((element) => elements.push(element));

  await Promise.all(elements.map(mountElement));
}

async function mountElement(element) {
  if (mounted.has(element)) {
    return;
  }
  mounted.add(element);

  const name = element.dataset.component ?? '';
  if (!/^[a-z0-9][a-z0-9-]*(\/[a-z0-9][a-z0-9-]*)*$/.test(name)) {
    console.error(`[bootzen] Nombre de componente inválido: "${name}". Usa minúsculas, números y guiones.`);
    return;
  }

  try {
    const module = await import(new URL(`${name}.js`, componentsBase).href);
    if (typeof module.default !== 'function') {
      throw new Error(`components/${name}.js debe exportar por defecto una función (elemento, props).`);
    }

    const cleanup = await module.default(element, readProps(element));
    if (typeof cleanup === 'function') {
      cleanups.set(element, cleanup);
    }
  } catch (error) {
    element.setAttribute('data-component-error', '');
    console.error(`[bootzen] No se pudo montar el componente "${name}":`, error);
  }
}

function readProps(element) {
  if (!element.dataset.props) {
    return {};
  }

  try {
    return JSON.parse(element.dataset.props);
  } catch {
    console.error(`[bootzen] data-props inválido en "${element.dataset.component}": debe ser JSON.`);
    return {};
  }
}

function unmount(node) {
  if (!(node instanceof Element)) {
    return;
  }

  for (const element of [node, ...node.querySelectorAll('[data-component]')]) {
    const cleanup = cleanups.get(element);
    if (cleanup) {
      cleanups.delete(element);
      cleanup();
    }
  }
}

function setSubmitting(form, submitting) {
  form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]').forEach((button) => {
    button.disabled = submitting;
  });
  form.toggleAttribute('data-submitting', submitting);
}

async function submitRemote(form) {
  setSubmitting(form, true);

  try {
    const data = await request(form.action, {
      method: form.getAttribute('method') ?? 'GET',
      data: new FormData(form),
    });

    form.dispatchEvent(new CustomEvent('bootzen:success', { bubbles: true, detail: data }));

    if (data && typeof data === 'object' && data.redirect) {
      window.location.assign(data.redirect);
    } else if (data && typeof data === 'object' && data.message) {
      toast(data.message, 'success');
    }
  } catch (error) {
    form.dispatchEvent(new CustomEvent('bootzen:error', { bubbles: true, detail: error }));
    toast(error.message || 'Ocurrió un error inesperado.', 'error');
  } finally {
    setSubmitting(form, false);
  }
}

function onClick(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) {
    return;
  }

  const confirmable = target.closest('[data-confirm]');
  if (confirmable && !(confirmable instanceof HTMLFormElement) && !window.confirm(confirmable.dataset.confirm)) {
    event.preventDefault();
    event.stopImmediatePropagation();
    return;
  }

  if (target.closest('[data-dismiss]')) {
    target.closest('[data-dismissible]')?.remove();
  }
}

function onSubmit(event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
    return;
  }

  if (form.hasAttribute('data-remote')) {
    event.preventDefault();
    submitRemote(form);
    return;
  }

  if (!form.hasAttribute('data-no-disable')) {
    // En el siguiente tick: deshabilitar el botón ahora lo quitaría de los datos enviados
    setTimeout(() => setSubmitting(form, true), 0);
  }
}

function showFlashes() {
  document.querySelectorAll('[data-flash]').forEach((element) => {
    toast(element.textContent.trim(), element.dataset.type ?? 'info');
    element.remove();
  });
}

/**
 * Activa el runtime: comportamientos declarativos, avisos flash y componentes (también los que se
 * agreguen después al DOM). Llamarlo más de una vez no tiene efecto.
 */
export function start() {
  if (started) {
    return;
  }
  started = true;

  document.addEventListener('click', onClick, true);
  document.addEventListener('submit', onSubmit);
  showFlashes();
  mount(document);

  new MutationObserver((records) => {
    for (const record of records) {
      record.removedNodes.forEach(unmount);
      record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          mount(node);
        }
      });
    }
  }).observe(document.body, { childList: true, subtree: true });
}
