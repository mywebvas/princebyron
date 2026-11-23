const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
const outDir = "C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots";

if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

const viewports = [
  { suffix: 'desktop', w: 1440, h: 900, isMobile: false },
  { suffix: 'tablet',  w: 768,  h: 1024, isMobile: false },
  { suffix: 'mobile',  w: 375,  h: 812,  isMobile: true }
];

const publicViews = [
  { name: '01_home',            url: 'http://127.0.0.1:8001/' },
  { name: '02_about',           url: 'http://127.0.0.1:8001/about' },
  { name: '03_music',           url: 'http://127.0.0.1:8001/music' },
  { name: '04_store',           url: 'http://127.0.0.1:8001/store' },
  { name: '05_product',         url: 'http://127.0.0.1:8001/store/tee' },
  { name: '06_cart',            url: 'http://127.0.0.1:8001/cart' },
  { name: '07_checkout',        url: 'http://127.0.0.1:8001/checkout' },
  { name: '08_booking',         url: 'http://127.0.0.1:8001/booking' },
  { name: '09_account_guest',   url: 'http://127.0.0.1:8001/account' },
  { name: '10_admin_preview',   url: 'http://127.0.0.1:8001/admin/preview' },
  { name: '11_admin_login',     url: 'http://127.0.0.1:8001/admin/login' }
];

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  const edge = spawn(edgePath, [
    '--headless',
    '--remote-debugging-port=9444',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    'about:blank'
  ]);

  try {
    let wsUrl = null;
    for (let i = 0; i < 30; i++) {
      await sleep(200);
      try {
        const res = await fetch('http://127.0.0.1:9444/json/new?about:blank', { method: 'PUT' });
        const json = await res.json();
        if (json.webSocketDebuggerUrl) {
          wsUrl = json.webSocketDebuggerUrl;
          break;
        }
      } catch (e) {}
    }

    if (!wsUrl) throw new Error('Could not connect to Edge via CDP WebSocket');

    const ws = new WebSocket(wsUrl);
    let msgId = 1;
    function send(method, params = {}) {
      return new Promise((resolve) => {
        const id = msgId++;
        const handler = (event) => {
          const msg = JSON.parse(event.data);
          if (msg.id === id) {
            ws.removeEventListener('message', handler);
            resolve(msg.result);
          }
        };
        ws.addEventListener('message', handler);
        ws.send(JSON.stringify({ id, method, params }));
      });
    }

    await new Promise(r => ws.onopen = r);

    await send('Page.enable');
    await send('DOM.enable');
    await send('Runtime.enable');
    await send('Network.enable');

    console.log('=== Stage 3.1: Capturing Public & Unauthenticated Views ===');
    for (const vp of viewports) {
      console.log(`Setting viewport: ${vp.suffix} (${vp.w}x${vp.h})`);
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });

      for (const v of publicViews) {
        await send('Page.navigate', { url: v.url });
        await sleep(1200);
        const shot = await send('Page.captureScreenshot', { format: 'png' });
        const filename = `${v.name}_${vp.suffix}.png`;
        const filepath = path.join(outDir, filename);
        fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
        console.log(`  Captured: ${filename} (${fs.statSync(filepath).size} bytes)`);
      }
    }

    console.log('\n=== Stage 3.2: Authenticating as Customer (fan@princebyron.local) ===');
    // Ensure viewport is desktop for login
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/login' });
    await sleep(1000);
    await send('Runtime.evaluate', {
      expression: `
        document.querySelector('#login-email').value = 'fan@princebyron.local';
        document.querySelector('#login-password').value = 'TemporaryFanPass2026!';
        document.querySelector('form[action*="login"]').submit();
      `
    });
    await sleep(2500);

    const customerViews = [
      { name: '12_account_authenticated', url: 'http://127.0.0.1:8001/account' },
      { name: '13_order_receipt_pb2',      url: 'http://127.0.0.1:8001/account/orders/PB-202611-002' }
    ];

    for (const vp of viewports) {
      console.log(`Capturing Customer Auth: ${vp.suffix} (${vp.w}x${vp.h})`);
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });

      for (const cv of customerViews) {
        await send('Page.navigate', { url: cv.url });
        await sleep(1500);
        const shot = await send('Page.captureScreenshot', { format: 'png' });
        const filename = `${cv.name}_${vp.suffix}.png`;
        const filepath = path.join(outDir, filename);
        fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
        console.log(`  Captured: ${filename} (${fs.statSync(filepath).size} bytes)`);
      }
    }

    console.log('\n=== Stage 3.3: Authenticating as Owner into Live Filament CMS ===');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/admin/login' });
    await sleep(1500);

    await send('Runtime.evaluate', {
      expression: `
        const email = document.getElementById('form.email');
        const pass = document.getElementById('form.password');
        if (email && pass) {
          email.value = 'owner@princebyron.local';
          email.dispatchEvent(new Event('input', { bubbles: true }));
          email.dispatchEvent(new Event('change', { bubbles: true }));
          pass.value = 'TemporaryOwnerPass2026!';
          pass.dispatchEvent(new Event('input', { bubbles: true }));
          pass.dispatchEvent(new Event('change', { bubbles: true }));
          const form = document.getElementById('form');
          form.dispatchEvent(new Event('submit', { bubbles: true }));
        }
      `
    });
    await sleep(3500);

    const ownerViews = [
      { name: '14_admin_dashboard_real', url: 'http://127.0.0.1:8001/admin' }
    ];

    for (const vp of viewports) {
      console.log(`Capturing Owner Admin: ${vp.suffix} (${vp.w}x${vp.h})`);
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });

      for (const ov of ownerViews) {
        await send('Page.navigate', { url: ov.url });
        await sleep(2000);
        const shot = await send('Page.captureScreenshot', { format: 'png' });
        const filename = `${ov.name}_${vp.suffix}.png`;
        const filepath = path.join(outDir, filename);
        fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
        console.log(`  Captured: ${filename} (${fs.statSync(filepath).size} bytes)`);
      }
    }

    console.log('\n=== ALL 42 SCREENSHOTS CAPTURED SUCCESSFULLY! ===');
    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
