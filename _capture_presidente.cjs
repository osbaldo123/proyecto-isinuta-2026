const { chromium } = require('playwright');
const path = require('path');

const BASE = 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '_real_captures');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const page = await (
    await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' })
  ).newPage();

  await page.addInitScript(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  });

  await page.goto(`${BASE}/acceso`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);

  await page.locator('#acceso_rol_email').fill('sergioNina@gmail.com');
  await page.locator('#acceso_rol_password').fill('nina1234');
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForTimeout(2500);
  console.log('after login', page.url());

  for (const [file, url] of [
    ['fig16-usuarios-create.png', '/usuarios/create'],
    ['fig17-usuarios.png', '/usuarios'],
    ['fig18-roles.png', '/roles'],
  ]) {
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    console.log(file, page.url());
    if (page.url().includes('acceso') || page.url().includes('login')) {
      console.log('AUTH FAIL', file);
      continue;
    }
    await page.screenshot({ path: path.join(OUT, file), fullPage: false });
    console.log('OK', file);
  }

  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
