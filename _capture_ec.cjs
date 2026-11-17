const { chromium } = require('playwright');
const path = require('path');
const OUT = path.join(__dirname, '_real_captures');

(async () => {
  const b = await chromium.launch({ headless: true, channel: 'chrome' });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 }, colorScheme: 'light' });
  await p.addInitScript(() => {
    localStorage.setItem('appearance', 'light');
    document.documentElement.classList.remove('dark');
  });

  await p.goto('http://127.0.0.1:8000/acceso', { waitUntil: 'networkidle' });
  await p.click('#acceso_rol_email');
  await p.fill('#acceso_rol_email', 'lorenaMamani@gmail.com');
  await p.click('#acceso_rol_password');
  await p.fill('#acceso_rol_password', 'lorena1234');
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}),
    p.click('button[type="submit"]'),
  ]);
  console.log('logged', p.url());

  let ok = false;
  for (let id = 1; id <= 120; id++) {
    const res = await p.goto(`http://127.0.0.1:8000/pagos/estado-cuenta/${id}`, {
      waitUntil: 'domcontentloaded',
    });
    if (!res || res.status() >= 400) continue;
    if (/acceso|login/i.test(p.url())) continue;
    const t = await p.locator('body').innerText();
    if (/estado de cuenta|cuotas|saldo/i.test(t) && !/Not Found|403|Forbidden/i.test(t)) {
      await p.evaluate(() => document.documentElement.classList.remove('dark'));
      await p.waitForTimeout(800);
      await p.screenshot({ path: path.join(OUT, 'fig34-estado-cuenta.png') });
      console.log('fig34 OK', id);
      ok = true;
      break;
    }
  }
  if (!ok) console.log('fig34 FAIL');

  await p.goto('http://127.0.0.1:8000/trabajos', { waitUntil: 'networkidle' });
  await p.evaluate(() => document.documentElement.classList.remove('dark'));
  const btn = p.locator('a,button').filter({ hasText: /lista|asistencia/i }).first();
  if (await btn.count()) {
    await btn.click();
    await p.waitForLoadState('networkidle');
  } else {
    const a = p.locator('a[href*="/trabajos/"]').first();
    if (await a.count()) {
      await a.click();
      await p.waitForLoadState('networkidle');
    }
  }
  await p.evaluate(() => document.documentElement.classList.remove('dark'));
  await p.waitForTimeout(800);
  await p.screenshot({ path: path.join(OUT, 'fig30-trabajos-lista.png') });
  console.log('fig30', p.url());

  await b.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
