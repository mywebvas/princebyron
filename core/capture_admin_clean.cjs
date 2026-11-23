const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
const outDir = "C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots";
const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'edge-admin-'));

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
    `--user-data-dir=${tempDir}`,
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

    if (!wsUrl) throw new Error('No WS URL');

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
        ws.send(JSON.stringify({ id: msgId, method, params }));
      });
    }

    await new Promise(r => ws.onopen = r);

    await send('Page.enable');
    await send('DOM.enable');
    await send('Runtime.enable');
    await send('Network.enable');

    console.log('Navigating to /admin/login in isolated user-data-dir...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/admin/login' });
    await sleep(2000);

    // Fill form and click sign in button
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
          const btn = document.querySelector('button[type="submit"]');
          if (btn) btn.click();
        }
      `
    });
    await sleep(4000);

    const check = await send('Runtime.evaluate', {
      expression: '({ url: window.location.href, title: document.title, heading: document.querySelector("h1")?.innerText })',
      returnByValue: true
    });
    console.log('Post-login status:', check.result.value);

    for (const vp of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });
      await sleep(1000);

      const shot = await send('Page.captureScreenshot', { format: 'png' });
      const filename = `14_admin_dashboard_real_${vp.suffix}.png`;
      const filepath = path.join(outDir, filename);
      fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
      console.log(`Captured: ${filename} (${fs.statSync(filepath).size} bytes)`);
    }

    ws.close();
  } finally {
    edge.kill();
    try { fs.rmSync(tempDir, { recursive: true, force: true }); } catch (e) {}
  }
}

run().catch(console.error);
