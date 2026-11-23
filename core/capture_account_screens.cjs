const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
const outDir = "C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots";

const viewports = [
  { suffix: 'desktop', w: 1440, h: 900, isMobile: false },
  { suffix: 'tablet',  w: 768,  h: 1024, isMobile: false },
  { suffix: 'mobile',  w: 375,  h: 812,  isMobile: true }
];

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  const edge = spawn(edgePath, [
    '--headless',
    '--remote-debugging-port=9555',
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
        const res = await fetch('http://127.0.0.1:9555/json/new?about:blank', { method: 'PUT' });
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

    console.log('=== Capturing Unauthenticated Guest Account View ===');
    for (const vp of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });
      await send('Page.navigate', { url: 'http://127.0.0.1:8001/account' });
      await sleep(1500);
      const shot = await send('Page.captureScreenshot', { format: 'png' });
      const filename = `09_account_guest_${vp.suffix}.png`;
      const filepath = path.join(outDir, filename);
      fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
      console.log(`Captured: ${filename}`);
    }

    console.log('\n=== Authenticating as Customer (fan@princebyron.local) ===');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/login' });
    await sleep(1200);

    await send('Runtime.evaluate', {
      expression: `
        document.querySelector('#login-email').value = 'fan@princebyron.local';
        document.querySelector('#login-password').value = 'TemporaryFanPass2026!';
        document.querySelector('form[action*="login"]').submit();
      `
    });
    await sleep(2500);

    const authViews = [
      { name: '12_account_authenticated', url: 'http://127.0.0.1:8001/account' },
      { name: '13_order_receipt_pb2',      url: 'http://127.0.0.1:8001/account/orders/PB-202611-002' }
    ];

    for (const vp of viewports) {
      console.log(`Capturing Auth Views for ${vp.suffix}`);
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });

      for (const av of authViews) {
        await send('Page.navigate', { url: av.url });
        await sleep(1500);

        // Verify elements on page
        const evalRes = await send('Runtime.evaluate', {
          expression: `
            JSON.stringify({
              title: document.title,
              h1: document.querySelector('h1')?.innerText,
              accountGrid: !!document.querySelector('.account-grid'),
              vaultCards: document.querySelectorAll('.account-card').length,
              activeBadges: document.querySelectorAll('.badge-status-settled').length,
              streamButtons: Array.from(document.querySelectorAll('button')).filter(b => b.innerText.includes('Stream')).length,
              downloadLinks: Array.from(document.querySelectorAll('a')).filter(a => a.href.includes('/downloads/file/')).length,
              bodyScrollWidth: document.body.scrollWidth,
              windowInnerWidth: window.innerWidth
            })
          `
        });
        console.log(`  Eval [${av.name} @ ${vp.suffix}]:`, evalRes.result?.value);

        const shot = await send('Page.captureScreenshot', { format: 'png' });
        const filename = `${av.name}_${vp.suffix}.png`;
        const filepath = path.join(outDir, filename);
        fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
        console.log(`  Captured: ${filename} (${fs.statSync(filepath).size} bytes)`);
      }
    }

    console.log('\n=== All Account Screenshots Captured & Reconciled! ===');
    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
