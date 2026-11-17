const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '_real_captures');
fs.mkdirSync(OUT, { recursive: true });

async function light(page) {
  await page.addInitScript(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  });
  await page.evaluate(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  }).catch(() => {});
}

async function login(page, email, pass) {
  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await light(page);
  await page.waitForSelector('#acceso_rol_email', { timeout: 20000 });
  await page.click('#acceso_rol_email');
  await page.fill('#acceso_rol_email', email);
  await page.click('#acceso_rol_password');
  await page.fill('#acceso_rol_password', pass);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForTimeout(1200);
  console.log('logged', email, page.url());
}

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });

  // Presidente: usuarios + roles
  await login(page, 'sergioNina@gmail.com', 'nina1234');
  await light(page);
  for (const [file, url] of [
    ['fig16-usuarios-create.png', '/usuarios/create'],
    ['fig17-usuarios.png', '/usuarios'],
    ['fig18-roles.png', '/roles'],
  ]) {
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
    await light(page);
    await page.waitForTimeout(1000);
    console.log(file, page.url());
    await page.screenshot({ path: path.join(OUT, file), fullPage: false });
  }

  // Secretaria: estado cuenta + lista trabajo
  await page.goto(`${BASE}/acceso/cambiar`, { waitUntil: 'networkidle' }).catch(() => {});
  await login(page, 'lorenaMamani@gmail.com', 'lorena1234');
  await light(page);

  let okEc = false;
  for (let id = 1; id <= 80; id++) {
    const res = await page.goto(`${BASE}/pagos/estado-cuenta/${id}`, { waitUntil: 'domcontentloaded' });
    const status = res ? res.status() : 0;
    if (status >= 200 && status < 400 && !page.url().includes('acceso') && !page.url().includes('login')) {
      await light(page);
      await page.waitForTimeout(900);
      // evitar páginas de error
      const body = await page.locator('body').innerText().catch(() => '');
      if (/estado de cuenta|cuotas|multas|lote/i.test(body)) {
        await page.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png'), fullPage: false });
        console.log('fig34 OK lote', id);
        okEc = true;
        break;
      }
    }
  }
  if (!okEc) console.log('fig34 SKIP');

  await page.goto(`${BASE}/trabajos`, { waitUntil: 'networkidle' });
  await light(page);
  await page.waitForTimeout(800);
  const listaBtn = page.locator('a,button').filter({ hasText: /lista|asistencia|pasar lista/i }).first();
  if (await listaBtn.count()) {
    await listaBtn.click();
    await page.waitForLoadState('networkidle');
    await light(page);
    await page.waitForTimeout(1000);
  } else {
    const any = page.locator('a[href*="/trabajos/"]').first();
    if (await any.count()) {
      await any.click();
      await page.waitForLoadState('networkidle');
      await light(page);
      await page.waitForTimeout(1000);
    }
  }
  await page.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png'), fullPage: false });
  console.log('fig30', page.url());

  await browser.close();
  console.log('DONE missing');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
