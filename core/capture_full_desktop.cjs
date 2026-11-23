const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
const outDir = "C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots";

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  const edge = spawn(edgePath, [
    '--headless',
    '--remote-debugging-port=9666',
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
        const res = await fetch('http://127.0.0.1:9666/json/new?about:blank', { method: 'PUT' });
        const json = await res.json();
        if (json.webSocketDebuggerUrl) {
          wsUrl = json.webSocketDebuggerUrl;
          break;
        }
      } catch (e) {}
    }

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

    // Authenticate
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
    await sleep(2000);

    await send('Page.navigate', { url: 'http://127.0.0.1:8001/account' });
    await sleep(1500);

    const shotFull = await send('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: true
    });
    fs.writeFileSync(path.join(outDir, '12_account_full_desktop.png'), Buffer.from(shotFull.data, 'base64'));
    console.log('Full desktop screenshot captured: 12_account_full_desktop.png');

    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
