const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

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
  await page.locator('#acceso_rol_email').fill('lorenaMamani@gmail.com');
  await page.locator('#acceso_rol_password').fill('lorena1234');
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForTimeout(2000);

  // Lote 1 ya se usó en estado de cuenta
  await page.goto(`${BASE}/pagos/registrar/nuevo?afiliado_id=1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  console.log('cobro url', page.url());

  await page.screenshot({ path: path.join(OUT, 'fig28-pagos-create.png'), fullPage: false });
  await page.screenshot({ path: path.join(OUT, 'fig31-pagos-create2.png'), fullPage: false });
  console.log('OK fig28/fig31 cobro con afiliado');

  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
