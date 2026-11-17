/**
 * Capturas REALES del sistema (tema claro) para el documento.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '_real_captures');
const EMAIL = 'lorenaMamani@gmail.com';
const PASS = 'lorena1234';

const shots = [
  // Sprint 1
  { file: 'fig15-login.png', url: '/login', wait: 'form', full: false },
  { file: 'fig16-usuarios-create.png', url: '/usuarios/create', wait: 'h1, form', needPresidente: true },
  { file: 'fig17-usuarios.png', url: '/usuarios', wait: 'h1', needPresidente: true },
  { file: 'fig18-roles.png', url: '/roles', wait: 'h1', needPresidente: true },
  { file: 'fig19-afiliados.png', url: '/afiliados', wait: 'h1' },
  { file: 'fig20-afiliaciones.png', url: '/afiliaciones', wait: 'h1' },
  // Sprint 2
  { file: 'fig23-categorias.png', url: '/categorias', wait: 'h1' },
  { file: 'fig24-servicios.png', url: '/servicios', wait: 'h1' },
  { file: 'fig25-otb.png', url: '/otb', wait: 'h1' },
  // Sprint 3
  { file: 'fig28-pagos-create.png', url: '/pagos/registrar/nuevo', wait: 'h1, form' },
  { file: 'fig29-trabajos.png', url: '/trabajos', wait: 'h1' },
  { file: 'fig31-pagos-create2.png', url: '/pagos/registrar/nuevo', wait: 'h1, form' },
  { file: 'fig32-pagos.png', url: '/pagos', wait: 'h1' },
  { file: 'fig33-multas.png', url: '/multas', wait: 'h1' },
  // Sprint 4
  { file: 'fig37-ingresos.png', url: '/reportes/ingresos', wait: 'h1' },
  { file: 'fig38-deudas.png', url: '/reportes/deudas', wait: 'h1' },
];

async function forceLight(page) {
  await page.addInitScript(() => {
    try {
      localStorage.setItem('appearance', 'light');
      localStorage.setItem('theme', 'light');
      document.documentElement.classList.remove('dark');
      document.documentElement.style.colorScheme = 'light';
    } catch (_) {}
  });
  await page.evaluate(() => {
    localStorage.setItem('appearance', 'light');
    localStorage.setItem('theme', 'light');
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
  }).catch(() => {});
}

async function loginAcceso(page, email, password) {
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await forceLight(page);
  const emailInput = page.locator('#acceso_rol_email, input[name="email"]:visible').first();
  const passInput = page.locator('#acceso_rol_password, input[name="password"]:visible').first();
  await emailInput.click();
  await emailInput.fill(email);
  await passInput.click();
  await passInput.fill(password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.locator('button[type="submit"]:visible').click(),
  ]);
  await page.waitForTimeout(1500);
}

async function logout(page) {
  await page.goto(`${BASE}/acceso/cambiar`, { waitUntil: 'networkidle' }).catch(() => {});
  await page.waitForTimeout(500);
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForSelector('#acceso_rol_email', { timeout: 15000 });
}

async function loginPresidente(page) {
  await logout(page);
  await loginAcceso(page, 'sergioNina@gmail.com', 'nina1234');
}

async function loginSecretaria(page) {
  await logout(page);
  await loginAcceso(page, EMAIL, PASS);
}

async function shot(page, item) {
  const dest = path.join(OUT, item.file);
  if (item.url === '/login') {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await forceLight(page);
    await page.waitForTimeout(800);
    await page.screenshot({ path: dest, fullPage: false });
    console.log('OK', item.file);
    return;
  }
  await page.goto(`${BASE}${item.url}`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForTimeout(1200);
  // si redirige a verify-email, intentar marcar verified no es posible desde UI
  const url = page.url();
  if (url.includes('verify-email') || url.includes('login') || url.includes('acceso')) {
    console.log('SKIP/AUTH', item.file, '->', url);
    return;
  }
  await page.screenshot({ path: dest, fullPage: false });
  console.log('OK', item.file, url);
}

async function captureTrabajoLista(page) {
  await page.goto(`${BASE}/trabajos`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForTimeout(1000);
  const links = page.locator('a[href*="/trabajos/"]');
  const n = await links.count();
  for (let i = 0; i < n; i++) {
    const href = await links.nth(i).getAttribute('href');
    if (!href) continue;
    if (/lista|asistencia/i.test(href) || /\/trabajos\/\d+$/.test(href)) {
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
      await forceLight(page);
      await page.waitForTimeout(1000);
      await page.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png'), fullPage: false });
      console.log('OK fig30-trabajos-lista.png', href);
      return;
    }
  }
  // botón "Lista" / texto
  const btn = page.getByRole('link', { name: /lista|asistencia|pasar/i }).first();
  if (await btn.count()) {
    await btn.click();
    await page.waitForLoadState('networkidle');
    await forceLight(page);
    await page.waitForTimeout(1000);
  }
  await page.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png'), fullPage: false });
  console.log('OK fig30-trabajos-lista.png (fallback)');
}

async function captureEstadoCuenta(page) {
  await page.goto(`${BASE}/afiliados`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForTimeout(800);
  const links = page.locator('a[href^="/afiliados/"]');
  const n = await links.count();
  for (let i = 0; i < Math.min(n, 20); i++) {
    const href = await links.nth(i).getAttribute('href');
    if (!href || !/^\/afiliados\/\d+$/.test(href)) continue;
    await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
    await forceLight(page);
    await page.waitForTimeout(700);
    const ec = page.locator('a[href*="/pagos/estado-cuenta/"]');
    if (await ec.count()) {
      const ecHref = await ec.first().getAttribute('href');
      await page.goto(`${BASE}${ecHref}`, { waitUntil: 'networkidle' });
      await forceLight(page);
      await page.waitForTimeout(1000);
      await page.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png'), fullPage: false });
      console.log('OK fig34-estado-cuenta.png', ecHref);
      return;
    }
  }
  // último recurso: probar ids de lote 1..30
  for (let id = 1; id <= 30; id++) {
    const res = await page.goto(`${BASE}/pagos/estado-cuenta/${id}`, { waitUntil: 'networkidle' });
    if (res && res.ok()) {
      await forceLight(page);
      await page.waitForTimeout(800);
      if (!(page.url().includes('login') || page.url().includes('acceso'))) {
        await page.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png'), fullPage: false });
        console.log('OK fig34-estado-cuenta.png lote', id);
        return;
      }
    }
  }
  console.log('SKIP fig34-estado-cuenta.png');
}

async function captureRecibo(page) {
  await page.goto(`${BASE}/pagos`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForTimeout(800);
  const link = page.locator('a[href*="/pagos/"]').filter({ hasNotText: 'registrar' }).first();
  // better: table row link
  const show = page.locator('table a[href^="/pagos/"], a[href*="/pagos/"][href*="/"]').first();
  const candidates = page.locator('a[href^="/pagos/"]');
  const n = await candidates.count();
  for (let i = 0; i < n; i++) {
    const href = await candidates.nth(i).getAttribute('href');
    if (href && /^\/pagos\/\d+$/.test(href)) {
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
      await forceLight(page);
      await page.waitForTimeout(1000);
      await page.screenshot({ path: path.join(OUT, 'fig32-recibo.png'), fullPage: false });
      console.log('OK fig32-recibo.png', href);
      return;
    }
  }
  console.log('SKIP fig32-recibo.png');
}

async function captureServicioEdit(page) {
  await page.goto(`${BASE}/servicios`, { waitUntil: 'networkidle' });
  await forceLight(page);
  await page.waitForTimeout(800);
  const edit = page.locator('a[href*="/servicios/"][href*="/edit"]').first();
  if (await edit.count()) {
    await edit.click();
    await page.waitForLoadState('networkidle');
    await forceLight(page);
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(OUT, 'fig24-servicio-edit.png'), fullPage: false });
    console.log('OK fig24-servicio-edit.png');
  } else {
    // fallback list
    await page.screenshot({ path: path.join(OUT, 'fig24-servicio-edit.png'), fullPage: false });
    console.log('FALLBACK fig24-servicio-edit.png (lista)');
  }
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    colorScheme: 'light',
  });
  const page = await context.newPage();
  await forceLight(page);

  // Login figura 15 (login page) primero sin sesión
  await shot(page, shots[0]);

  // Secretaria para la mayoría
  await loginSecretaria(page);
  await forceLight(page);

  for (const item of shots) {
    if (item.file === 'fig15-login.png') continue;
    if (item.needPresidente) continue;
    if (item.file === 'fig24-servicios.png') {
      await captureServicioEdit(page);
      continue;
    }
    if (item.file === 'fig32-pagos.png') {
      await captureRecibo(page);
      // también lista recibos
      await page.goto(`${BASE}/pagos`, { waitUntil: 'networkidle' });
      await forceLight(page);
      await page.waitForTimeout(800);
      await page.screenshot({ path: path.join(OUT, 'fig32-pagos-lista.png'), fullPage: false });
      continue;
    }
    await shot(page, item);
  }

  await captureTrabajoLista(page);
  await captureEstadoCuenta(page);

  // Presidente: usuarios y roles
  await loginPresidente(page);
  await forceLight(page);
  for (const item of shots.filter((s) => s.needPresidente)) {
    await shot(page, item);
  }

  await browser.close();
  console.log('DONE captures in', OUT);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
