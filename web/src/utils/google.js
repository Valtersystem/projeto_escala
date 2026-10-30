// Login com Google (Google Identity Services). Sem VITE_GOOGLE_CLIENT_ID, o botão nem aparece.

export const clienteGoogle = () => import.meta.env?.VITE_GOOGLE_CLIENT_ID || '';

const SCRIPT = 'https://accounts.google.com/gsi/client';

/** Carrega o script do Google uma vez e devolve `google.accounts.id`. */
export function carregarGoogle() {
  return new Promise((resolve, reject) => {
    if (window.google?.accounts?.id) return resolve(window.google.accounts.id);

    const script = document.createElement('script');
    script.src = SCRIPT;
    script.async = true;
    script.onload = () => (window.google?.accounts?.id ? resolve(window.google.accounts.id) : reject(new Error('Google indisponível')));
    script.onerror = () => reject(new Error('Google indisponível'));
    document.head.appendChild(script);
  });
}
