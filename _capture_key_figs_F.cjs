/**
 * Capturas reales Fig 28, 32, 37, 38
 * Login vía POST de sesión (Inertia a veces no navega tras 302 en headless).
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '_real_captures');
fs.mkdirSync(OUT, { recursive: true });

const EMAIL = 'lorenaMamani@gmail.com';
const PASS = 'lorena1234';

async function forceLight(page) {
  await page.addInitScript(() => {
    try {
      localStorage.setItem('appearance', 'light');
      localStorage.setItem('theme', 'light');
      document.documentElement.classList.remove('dark');
    } catch (_) {}
  });
}

async function login(context, page) {
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(300);

  const csrf =
    (await page.locator('meta[name="csrf-token"]').getAttribute('content').catch(() => null)) ||
    (await page.evaluate(() => {
      const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
      return m ? decodeURIComponent(m[1]) : null;
    }));

  if (!csrf) throw new Error('No CSRF token');

  const resp = await context.request.post(`${BASE}/acceso`, {
    form: {
      email: EMAIL,
      password: PASS,
      _token: csrf,
    },
    headers: {
      'X-XSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'text/html, application/xhtml+xml',
      Referer: `${BASE}/acceso`,
    },
    maxRedirects: 0,
  });

  console.log('login POST', resp.status(), resp.headers()['location'] || '');

  // Asegurar cookies de sesión en el browser context
  await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(800);

  let url = page.url();
  console.log('after dashboard', url);

  if (url.includes('/acceso') || url.includes('/login')) {
    // fallback UI login
    await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await page.click('#acceso_rol_email');
    await page.fill('#acceso_rol_email', EMAIL);
    await page.click('#acceso_rol_password');
    await page.fill('#acceso_rol_password', PASS);
    await Promise.all([
      page.waitForURL((u) => !u.pathname.includes('/acceso'), { timeout: 20000 }).catch(() => null),
      page.click('form button[type="submit"]'),
    ]);
    if (page.url().includes('/acceso')) {
      await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' });
    }
    url = page.url();
    console.log('fallback url', url);
  }

  if (url.includes('/acceso') || url.includes('/login')) {
    await page.screenshot({ path: path.join(OUT, '_debug-login-fail.png'), fullPage: false });
    throw new Error('Login falló: ' + url);
  }

  await page.evaluate(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  }).catch(() => {});
}

async function ensureAuthed(page) {
  if (page.url().includes('/acceso') || page.url().includes('/login')) {
    throw new Error('sesión perdida en ' + page.url());
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    colorScheme: 'light',
  });
  const page = await context.newPage();
  await forceLight(page);
  await login(context, page);

  // Fig 28 — registrar pago
  let ok28 = false;
  for (const id of [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]) {
    await page.goto(`${BASE}/pagos/registrar/nuevo?afiliado_id=${id}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1400);
    await ensureAuthed(page);
    const body = await page.locator('body').innerText();
    if (/Registrar pago|Cobro|Deuda|Multa|Mora|Cuota|pendiente|Concepto/i.test(body)) {
      await page.screenshot({ path: path.join(OUT, 'fig28-pagos-create.png'), fullPage: false });
      console.log('OK fig28', id, page.url());
      ok28 = true;
      break;
    }
  }
  if (!ok28) {
    await page.goto(`${BASE}/pagos/registrar/nuevo`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    await ensureAuthed(page);
    await page.screenshot({ path: path.join(OUT, 'fig28-pagos-create.png'), fullPage: false });
    console.log('OK fig28 fallback', page.url());
  }

  // Fig 32 — recibo
  await page.goto(`${BASE}/pagos`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  await ensureAuthed(page);
  const candidates = page.locator('a[href^="/pagos/"]');
  const n = await candidates.count();
  let ok32 = false;
  for (let i = 0; i < n; i++) {
    const href = await candidates.nth(i).getAttribute('href');
    if (href && /^\/pagos\/\d+$/.test(href)) {
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(1200);
      const txt = await page.locator('body').innerText();
      if (/Recibo|Ingreso|Caja|Comprobante|Total|Bs/i.test(txt)) {
        await page.screenshot({ path: path.join(OUT, 'fig32-recibo.png'), fullPage: false });
        console.log('OK fig32', href);
        ok32 = true;
        break;
      }
    }
  }
  if (!ok32) throw new Error('No se encontró recibo para fig32');

  // Fig 37 — ingresos
  await page.goto(`${BASE}/reportes/ingresos`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1800);
  await ensureAuthed(page);
  console.log('ingresos url', page.url());
  await page.screenshot({ path: path.join(OUT, 'fig37-ingresos.png'), fullPage: false });
  console.log('OK fig37');

  // Fig 38 — deudas
  await page.goto(`${BASE}/reportes/deudas`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1800);
  await ensureAuthed(page);
  console.log('deudas url', page.url());
  await page.screenshot({ path: path.join(OUT, 'fig38-deudas.png'), fullPage: false });
  console.log('OK fig38');

  await browser.close();
  console.log('DONE');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
