const { spawn } = require('child_process');
const fs = require('fs');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  const edge = spawn(edgePath, [
    '--headless',
    '--remote-debugging-port=9333',
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
        const res = await fetch('http://127.0.0.1:9333/json/new?about:blank', { method: 'PUT' });
        const json = await res.json();
        if (json.webSocketDebuggerUrl) {
          wsUrl = json.webSocketDebuggerUrl;
          break;
        }
      } catch (e) {}
    }

    if (!wsUrl) throw new Error('No WS URL');

    const ws = new WebSocket(wsUrl);
    let id = 1;
    function send(method, params = {}) {
      return new Promise((resolve) => {
        const msgId = id++;
        const handler = (event) => {
          const msg = JSON.parse(event.data);
          if (msg.id === msgId) {
            ws.removeEventListener('message', handler);
            resolve(msg.result);
          }
        };
        ws.addEventListener('message', handler);
        ws.send(JSON.stringify({ id: msgId, method, params }));
      });
    }

    await new Promise((resolve) => ws.onopen = resolve);

    await send('Page.enable');
    await send('DOM.enable');
    await send('Runtime.enable');

    await send('Emulation.setDeviceMetricsOverride', {
      width: 1440,
      height: 900,
      deviceScaleFactor: 1,
      mobile: false
    });

    console.log('Navigating to /admin/login...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/admin/login' });
    await sleep(2000);

    // Filament uses Livewire. Let's set value and trigger input & change events
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

    let evalRes = await send('Runtime.evaluate', {
      expression: '({ url: window.location.href, title: document.title, heading: document.querySelector("h1")?.innerText, bodySnippet: document.body.innerText.substring(0, 400) })',
      returnByValue: true
    });
    console.log('Filament admin dashboard result:', evalRes.result.value);

    const shot = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots\\live_admin_dashboard_real.png', Buffer.from(shot.data, 'base64'));
    console.log('Screenshot saved to live_admin_dashboard_real.png');

    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
