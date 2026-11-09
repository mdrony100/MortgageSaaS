// Minimal public-site interactivity. No dashboard dependencies are loaded here.
export function initMobileMenu(root: Document = document): void {
  const button = root.querySelector<HTMLButtonElement>('[data-menu-button]');
  const nav = root.querySelector<HTMLElement>('[data-mobile-nav]');
  if (!button || !nav) return;
  const setOpen = (open: boolean, focusButton = false) => {
    button.setAttribute('aria-expanded', String(open));
    nav.hidden = !open;
    if (open) nav.querySelector<HTMLAnchorElement>('a')?.focus();
    else if (focusButton) button.focus();
  };
  button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
  root.addEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') setOpen(false, true);
  });
  window.matchMedia?.('(min-width: 1024px)').addEventListener?.('change', (m) => { if (m.matches) setOpen(false); });
}

if (typeof document !== 'undefined') initMobileMenu();
