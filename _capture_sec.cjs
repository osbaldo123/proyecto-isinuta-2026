const { chromium } = require('playwright');
const path = require('path');
const OUT = path.join(__dirname, '_real_captures');
const BASE = 'http://127.0.0.1:8000';

async function light(page) {
  await page.evaluate(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  }).catch(() => {});
}

async function login(page) {
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.click('#acceso_rol_email');
  await page.fill('#acceso_rol_email', '');
  await page.type('#acceso_rol_email', 'lorenaMamani@gmail.com', { delay: 15 });
  await page.click('#acceso_rol_password');
  await page.fill('#acceso_rol_password', '');
  await page.type('#acceso_rol_password', 'lorena1234', { delay: 15 });
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2500);
  await light(page);
  if (page.url().includes('acceso')) throw new Error('login fail');
  console.log('ok login', page.url());
}

async function snap(page, file, url) {
  await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, file), fullPage: false });
  console.log('OK', file);
}

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
  await page.addInitScript(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  });
  await login(page);

  await snap(page, 'fig19-afiliados.png', '/afiliados');
  await snap(page, 'fig20-afiliaciones.png', '/afiliaciones');
  await snap(page, 'fig23-categorias.png', '/categorias');

  await page.goto(`${BASE}/servicios`, { waitUntil: 'networkidle' });
  await light(page);
  const edit = page.locator('a[href*="/servicios/"][href$="/edit"]').first();
  if (await edit.count()) {
    await edit.click();
    await page.waitForLoadState('networkidle');
  }
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, 'fig24-servicio-edit.png') });
  console.log('OK fig24', page.url());

  await snap(page, 'fig25-otb.png', '/otb');
  await snap(page, 'fig28-pagos-create.png', '/pagos/registrar/nuevo');
  await snap(page, 'fig29-trabajos.png', '/trabajos');
  await snap(page, 'fig31-pagos-create.png', '/pagos/registrar/nuevo');

  await page.goto(`${BASE}/trabajos`, { waitUntil: 'networkidle' });
  await light(page);
  const lista = page.locator('a,button').filter({ hasText: /lista|asistencia/i }).first();
  if (await lista.count()) {
    await lista.click();
    await page.waitForLoadState('networkidle');
  }
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png') });
  console.log('OK fig30', page.url());

  await page.goto(`${BASE}/pagos`, { waitUntil: 'networkidle' });
  const pays = page.locator('a[href^="/pagos/"]');
  for (let i = 0; i < (await pays.count()); i++) {
    const href = await pays.nth(i).getAttribute('href');
    if (href && /^\/pagos\/\d+$/.test(href)) {
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
      await light(page);
      await page.waitForTimeout(900);
      await page.screenshot({ path: path.join(OUT, 'fig32-recibo.png') });
      console.log('OK fig32', href);
      break;
    }
  }

  await snap(page, 'fig33-multas.png', '/multas');

  for (let id = 1; id <= 80; id++) {
    const res = await page.goto(`${BASE}/pagos/estado-cuenta/${id}`, { waitUntil: 'domcontentloaded' });
    if (!res || res.status() >= 400) continue;
    const t = await page.locator('body').innerText();
    if (/estado de cuenta/i.test(t)) {
      await light(page);
      await page.waitForTimeout(800);
      await page.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png') });
      console.log('OK fig34', id);
      break;
    }
  }

  await snap(page, 'fig37-ingresos.png', '/reportes/ingresos');
  await snap(page, 'fig38-deudas.png', '/reportes/deudas');

  await browser.close();
  console.log('DONE SEC');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
