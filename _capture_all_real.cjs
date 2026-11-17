const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '_real_captures');
fs.mkdirSync(OUT, { recursive: true });

async function light(page) {
  await page.evaluate(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
  }).catch(() => {});
}

async function login(page, email, pass) {
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);
  await page.click('#acceso_rol_email');
  await page.fill('#acceso_rol_email', '');
  await page.type('#acceso_rol_email', email, { delay: 15 });
  await page.click('#acceso_rol_password');
  await page.fill('#acceso_rol_password', '');
  await page.type('#acceso_rol_password', pass, { delay: 15 });
  await page.waitForTimeout(200);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2500);
  await light(page);
  console.log('login', email, '=>', page.url());
  if (page.url().includes('acceso')) throw new Error('Login failed for ' + email);
}

async function snap(page, file, url) {
  await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, file), fullPage: false });
  console.log('OK', file, page.url());
}

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    colorScheme: 'light',
  });
  const page = await context.newPage();
  await page.addInitScript(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  });

  // Fig 15 login (sin sesión)
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await light(page);
  await page.waitForTimeout(700);
  await page.screenshot({ path: path.join(OUT, 'fig15-login.png'), fullPage: false });
  console.log('OK fig15-login.png');

  // Presidente: 16,17,18
  await login(page, 'sergioNina@gmail.com', 'nina1234');
  await snap(page, 'fig16-usuarios-create.png', '/usuarios/create');
  await snap(page, 'fig17-usuarios.png', '/usuarios');
  await snap(page, 'fig18-roles.png', '/roles');

  // Secretaria: resto (nueva sesión limpia)
  await context.clearCookies();
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await login(page, 'lorenaMamani@gmail.com', 'lorena1234');

  await snap(page, 'fig19-afiliados.png', '/afiliados');
  await snap(page, 'fig20-afiliaciones.png', '/afiliaciones');
  await snap(page, 'fig23-categorias.png', '/categorias');

  // Fig 24: editar primer servicio
  await page.goto(`${BASE}/servicios`, { waitUntil: 'networkidle' });
  await light(page);
  const edit = page.locator('a[href*="/servicios/"][href$="/edit"]').first();
  if (await edit.count()) {
    await edit.click();
    await page.waitForLoadState('networkidle');
  }
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, 'fig24-servicio-edit.png'), fullPage: false });
  console.log('OK fig24-servicio-edit.png', page.url());

  await snap(page, 'fig25-otb.png', '/otb');
  await snap(page, 'fig28-pagos-create.png', '/pagos/registrar/nuevo');
  await snap(page, 'fig29-trabajos.png', '/trabajos');
  await snap(page, 'fig31-pagos-create.png', '/pagos/registrar/nuevo');

  // Fig 30 lista
  await page.goto(`${BASE}/trabajos`, { waitUntil: 'networkidle' });
  await light(page);
  const lista = page.locator('a,button').filter({ hasText: /lista|asistencia/i }).first();
  if (await lista.count()) {
    await lista.click();
    await page.waitForLoadState('networkidle');
  } else {
    const a = page.locator('a[href*="/trabajos/"]').first();
    if (await a.count()) {
      await a.click();
      await page.waitForLoadState('networkidle');
    }
  }
  await light(page);
  await page.waitForTimeout(900);
  await page.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png'), fullPage: false });
  console.log('OK fig30', page.url());

  // Fig 32 recibo
  await page.goto(`${BASE}/pagos`, { waitUntil: 'networkidle' });
  await light(page);
  const pays = page.locator('a[href^="/pagos/"]');
  const pn = await pays.count();
  let gotRecibo = false;
  for (let i = 0; i < pn; i++) {
    const href = await pays.nth(i).getAttribute('href');
    if (href && /^\/pagos\/\d+$/.test(href)) {
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
      await light(page);
      await page.waitForTimeout(900);
      await page.screenshot({ path: path.join(OUT, 'fig32-recibo.png'), fullPage: false });
      console.log('OK fig32-recibo.png', href);
      gotRecibo = true;
      break;
    }
  }
  if (!gotRecibo) console.log('SKIP fig32');

  await snap(page, 'fig33-multas.png', '/multas');

  // Fig 34
  for (let id = 1; id <= 80; id++) {
    const res = await page.goto(`${BASE}/pagos/estado-cuenta/${id}`, { waitUntil: 'domcontentloaded' });
    if (!res || res.status() >= 400) continue;
    const t = await page.locator('body').innerText();
    if (/estado de cuenta/i.test(t)) {
      await light(page);
      await page.waitForTimeout(800);
      await page.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png'), fullPage: false });
      console.log('OK fig34', id);
      break;
    }
  }

  await snap(page, 'fig37-ingresos.png', '/reportes/ingresos');
  await snap(page, 'fig38-deudas.png', '/reportes/deudas');

  await browser.close();
  console.log('DONE ALL REAL');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
