// Public-site interactivity: vanilla TypeScript only. No dashboard dependencies are loaded here.
// Every component is progressive: the server-rendered HTML is readable without JavaScript.
import { sampleDscr } from './site/dscr';

const reducedMotion = (): boolean => typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

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
  window.matchMedia?.('(min-width: 1280px)').addEventListener?.('change', (m) => { if (m.matches) setOpen(false); });
}

export interface TabsController { select(index: number, focus?: boolean): void; readonly index: number; readonly count: number }

/** WAI-ARIA tabs with automatic activation (arrow keys, Home/End). */
export function initTabs(root: HTMLElement, onChange?: (index: number) => void): TabsController | null {
  const tabs = Array.from(root.querySelectorAll<HTMLButtonElement>('[role="tab"]')).filter((t) => t.closest('[data-tabs]') === root);
  const panels = tabs.map((t) => root.ownerDocument.getElementById(t.getAttribute('aria-controls') || ''));
  if (!tabs.length || panels.some((p) => !p)) return null;
  let current = Math.max(0, tabs.findIndex((t) => t.getAttribute('aria-selected') === 'true'));
  const select = (index: number, focus = false) => {
    const next = (index + tabs.length) % tabs.length;
    tabs.forEach((t, i) => {
      const active = i === next;
      t.setAttribute('aria-selected', String(active));
      t.tabIndex = active ? 0 : -1;
      panels[i]!.hidden = !active;
      panels[i]!.classList.toggle('is-active', active);
    });
    if (focus) tabs[next].focus();
    const changed = next !== current;
    current = next;
    root.dataset.activeIndex = String(next);
    if (changed || onChange) onChange?.(next);
  };
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => select(i));
    tab.addEventListener('keydown', (e: KeyboardEvent) => {
      const vertical = tab.closest('[role="tablist"]')?.getAttribute('aria-orientation') === 'vertical';
      const prev = vertical ? ['ArrowUp', 'ArrowLeft'] : ['ArrowLeft'];
      const nextKeys = vertical ? ['ArrowDown', 'ArrowRight'] : ['ArrowRight'];
      let target: number | null = null;
      if (nextKeys.includes(e.key)) target = i + 1;
      else if (prev.includes(e.key)) target = i - 1;
      else if (e.key === 'Home') target = 0;
      else if (e.key === 'End') target = tabs.length - 1;
      if (target === null) return;
      e.preventDefault();
      select(target, true);
    });
  });
  root.classList.add('tabs-ready');
  select(current);
  return { select, get index() { return current; }, get count() { return tabs.length; } };
}

export function initWalkthrough(root: HTMLElement): TabsController | null {
  const prev = root.querySelector<HTMLButtonElement>('[data-walk-prev]');
  const next = root.querySelector<HTMLButtonElement>('[data-walk-next]');
  const status = root.querySelector<HTMLElement>('[data-walk-status]');
  const bar = root.querySelector<HTMLElement>('[data-walk-progress]');
  let ctl: TabsController | null = null;
  const update = (i: number) => {
    const count = ctl?.count ?? 4;
    if (status) status.textContent = `Step ${i + 1} of ${count}`;
    if (bar) { bar.style.setProperty('--progress', String((i + 1) / count)); bar.setAttribute('aria-valuenow', String(i + 1)); }
    if (prev) prev.disabled = i === 0;
    if (next) next.disabled = i === count - 1;
  };
  ctl = initTabs(root, update);
  if (!ctl) return null;
  update(ctl.index);
  prev?.addEventListener('click', () => ctl!.index > 0 && ctl!.select(ctl!.index - 1));
  next?.addEventListener('click', () => ctl!.index < ctl!.count - 1 && ctl!.select(ctl!.index + 1));
  return ctl;
}

/** "See How It Works": scroll to the walkthrough and activate (and focus) step 1. */
export function initWalkthroughStart(doc: Document, ctl: TabsController | null): void {
  doc.querySelectorAll<HTMLAnchorElement>('[data-walkthrough-start]').forEach((a) => a.addEventListener('click', (e) => {
    const section = doc.getElementById('walkthrough');
    if (!section || !ctl) return;
    e.preventDefault();
    ctl.select(0);
    section.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
    doc.querySelector<HTMLElement>('#walkthrough [role="tab"]')?.focus({ preventScroll: true });
    history.replaceState?.(null, '', '#walkthrough');
  }));
}

export function initDscr(root: HTMLElement): void {
  const rent = root.querySelector<HTMLInputElement>('[data-dscr-rent]');
  const payment = root.querySelector<HTMLInputElement>('[data-dscr-payment]');
  const result = root.querySelector<HTMLElement>('[data-dscr-result]');
  const equation = root.querySelector<HTMLElement>('[data-dscr-equation]');
  const explanation = root.querySelector<HTMLElement>('[data-dscr-explanation]');
  if (!rent || !payment || !result || !equation || !explanation) return;
  const setError = (input: HTMLInputElement, message?: string) => {
    const el = root.querySelector<HTMLElement>(`#${input.id}-error`);
    if (el) el.textContent = message ?? '';
    if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
  };
  const run = () => {
    const out = sampleDscr(rent.value, payment.value);
    if (out.ok) {
      setError(rent); setError(payment);
      result.textContent = out.dscr;
      equation.textContent = out.equation;
      explanation.textContent = out.explanation;
      root.classList.remove('has-error');
    } else {
      setError(rent, out.errors.rent); setError(payment, out.errors.payment);
      result.textContent = '—';
      equation.textContent = 'Check the highlighted amount to see the example.';
      explanation.textContent = '';
      root.classList.add('has-error');
    }
    root.classList.remove('is-updated'); void root.offsetWidth; root.classList.add('is-updated');
  };
  root.addEventListener('submit', (e) => { e.preventDefault(); run(); });
  rent.addEventListener('input', run);
  payment.addEventListener('input', run);
  root.querySelector<HTMLButtonElement>('[data-dscr-reset]')?.addEventListener('click', () => { rent.value = '3,300'; payment.value = '3,000'; run(); rent.focus(); });
}

export function initCarousel(root: HTMLElement): void {
  const slides = Array.from(root.querySelectorAll<HTMLElement>('[data-slide]'));
  const status = root.querySelector<HTMLElement>('[data-carousel-status]');
  if (slides.length < 2 || !root.querySelector('[data-carousel-next]')) return;
  let i = 0;
  const show = (n: number) => {
    i = (n + slides.length) % slides.length;
    slides.forEach((s, k) => { s.hidden = k !== i; });
    if (status) status.textContent = `Review ${i + 1} of ${slides.length}`;
  };
  root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => show(i - 1));
  root.querySelector('[data-carousel-next]')?.addEventListener('click', () => show(i + 1));
  root.classList.add('carousel-ready');
  show(0);
}

/** Gentle reveal on first entry. Content is visible by default; it is only hidden once this runs. */
export function initReveal(doc: Document = document): void {
  if (reducedMotion() || typeof IntersectionObserver === 'undefined') return;
  const items = Array.from(doc.querySelectorAll<HTMLElement>('[data-reveal]'));
  if (!items.length) return;
  const io = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (en.isIntersecting) { en.target.classList.add('is-revealed'); io.unobserve(en.target); }
  }), { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  items.forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) el.classList.add('is-revealed');
    else io.observe(el);
  });
  doc.documentElement.classList.add('reveal-on');
}

export function initCallingCode(root: HTMLElement): void {
  const select = root.querySelector<HTMLSelectElement>('select[name="phone_country"]');
  const button = root.querySelector<HTMLButtonElement>('.phone-prefix-btn');
  const menu = root.querySelector<HTMLElement>('.phone-menu');
  const search = menu?.querySelector<HTMLInputElement>('input[type="search"]');
  const list = menu?.querySelector<HTMLElement>('[role="listbox"]');
  if (!select || !button || !menu || !search || !list) return;

  const options = Array.from(select.options).map((option) => ({
    iso: option.value,
    name: option.dataset.name || option.textContent || option.value,
    dial: option.dataset.dial || '',
    flag: option.dataset.flag || '',
  }));
  let active = -1;

  const rows = () => Array.from(list.querySelectorAll<HTMLElement>('[role="option"]'));
  const paintButton = () => {
    const current = options.find((option) => option.iso === select.value) || options[0];
    if (!current) return;
    const flag = button.querySelector<HTMLElement>('[data-flag]');
    const dial = button.querySelector<HTMLElement>('[data-dial]');
    if (flag) flag.textContent = current.flag;
    if (dial) dial.textContent = `+${current.dial}`;
    button.setAttribute('aria-label', `Country code, ${current.name} +${current.dial}`);
  };
  const render = (query = '') => {
    const q = query.trim().toLowerCase();
    const visible = options.filter((option) => q === '' || `${option.name} ${option.iso} +${option.dial} ${option.dial}`.toLowerCase().includes(q));
    list.replaceChildren();
    visible.forEach((option) => {
      const item = document.createElement('li');
      item.setAttribute('role', 'option');
      item.dataset.iso = option.iso;
      item.id = `phone-country-${option.iso}`;
      item.setAttribute('aria-selected', String(option.iso === select.value));
      const flag = document.createElement('span');
      flag.setAttribute('aria-hidden', 'true');
      flag.textContent = option.flag;
      const name = document.createElement('span');
      name.textContent = option.name;
      const dial = document.createElement('span');
      dial.className = 'phone-dial';
      dial.textContent = `+${option.dial}`;
      item.append(flag, name, dial);
      list.appendChild(item);
    });
    if (!visible.length) {
      const empty = document.createElement('li');
      empty.className = 'phone-menu-empty';
      empty.textContent = 'No matching country';
      list.appendChild(empty);
    }
    const rendered = rows();
    active = visible.findIndex((option) => option.iso === select.value);
    rendered.forEach((row, index) => row.classList.toggle('is-active', index === active));
    if (active >= 0 && rendered[active]) button.setAttribute('aria-activedescendant', rendered[active].id);
    else button.removeAttribute('aria-activedescendant');
  };
  const close = (focusButton = false) => {
    menu.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    button.removeAttribute('aria-activedescendant');
    if (focusButton) button.focus();
  };
  const open = () => {
    menu.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    search.value = '';
    render('');
    search.focus();
    const current = rows()[active];
    current?.scrollIntoView({ block: 'nearest' });
  };
  const choose = (iso: string) => {
    select.value = iso;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    paintButton();
    close(true);
  };
  const move = (index: number) => {
    const rendered = rows();
    if (!rendered.length) return;
    active = (index + rendered.length) % rendered.length;
    rendered.forEach((row, i) => row.classList.toggle('is-active', i === active));
    button.setAttribute('aria-activedescendant', rendered[active].id);
    rendered[active].scrollIntoView({ block: 'nearest' });
  };

  button.addEventListener('click', () => { if (menu.hidden) open(); else close(); });
  search.addEventListener('input', () => render(search.value));
  search.addEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); move(active + 1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); move(active < 0 ? rows().length - 1 : active - 1); }
    else if (event.key === 'Home') { event.preventDefault(); move(0); }
    else if (event.key === 'End') { event.preventDefault(); move(rows().length - 1); }
    else if (event.key === 'Enter') {
      event.preventDefault();
      const rendered = rows();
      const iso = (active >= 0 ? rendered[active] : rendered[0])?.dataset.iso;
      if (iso) choose(iso);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      close(true);
    }
  });
  list.addEventListener('pointerdown', (event) => {
    const item = (event.target as HTMLElement).closest<HTMLElement>('[role="option"]');
    if (!item?.dataset.iso) return;
    event.preventDefault();
    choose(item.dataset.iso);
  });
  root.addEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === 'Escape' && !menu.hidden) { event.preventDefault(); close(true); }
  });
  document.addEventListener('pointerdown', (event) => {
    if (!root.contains(event.target as Node)) close();
  });

  paintButton();
  root.classList.add('phone-ready');
}

export function initSite(doc: Document = document): void {
  initMobileMenu(doc);
  doc.querySelectorAll<HTMLElement>('[data-tabs="preview"]').forEach((el) => initTabs(el));
  let walk: TabsController | null = null;
  doc.querySelectorAll<HTMLElement>('[data-tabs="walkthrough"]').forEach((el) => { walk = initWalkthrough(el); });
  initWalkthroughStart(doc, walk);
  doc.querySelectorAll<HTMLElement>('[data-dscr]').forEach(initDscr);
  doc.querySelectorAll<HTMLElement>('[data-carousel]').forEach(initCarousel);
  doc.querySelectorAll<HTMLElement>('[data-calling-code]').forEach(initCallingCode);
  initReveal(doc);
  doc.documentElement.classList.add('site-ready');
}

if (typeof document !== 'undefined' && !(globalThis as { __SITE_NO_AUTO_INIT__?: boolean }).__SITE_NO_AUTO_INIT__) {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initSite());
  else initSite();
}
