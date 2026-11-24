const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function capture(url, outPath, width, height, isMobile = false) {
  const edge = spawn(edgePath, [
    '--headless',
    '--remote-debugging-port=9222',
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
        const res = await fetch('http://127.0.0.1:9222/json/new?about:blank', { method: 'PUT' });
        const json = await res.json();
        if (json.webSocketDebuggerUrl) {
          wsUrl = json.webSocketDebuggerUrl;
          break;
        }
      } catch (e) {}
    }

    if (!wsUrl) throw new Error('Could not connect to CDP WebSocket');

    const ws = new WebSocket(wsUrl);
    await new Promise(r => ws.onopen = r);

    let msgId = 1;
    function send(method, params = {}) {
      return new Promise((resolve) => {
        const id = msgId++;
        const handler = (event) => {
          const data = JSON.parse(event.data);
          if (data.id === id) {
            ws.removeEventListener('message', handler);
            resolve(data.result);
          }
        };
        ws.addEventListener('message', handler);
        ws.send(JSON.stringify({ id, method, params }));
      });
    }

    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', {
      width,
      height,
      deviceScaleFactor: 1,
      mobile: isMobile
    });

    await send('Page.navigate', { url });

    // Wait for page to finish loading
    await sleep(1500);

    const shot = await send('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: false
    });

    fs.writeFileSync(outPath, Buffer.from(shot.data, 'base64'));
    console.log(`Saved screenshot to ${outPath} (${width}x${height}, mobile: ${isMobile})`);

    ws.close();
  } finally {
    edge.kill();
  }
}

async function run() {
  const args = process.argv.slice(2);
  const url = args[0] || 'http://127.0.0.1:8001/account';
  const outPath = args[1] || 'core/test_mobile_cdp.png';
  const width = parseInt(args[2] || '375', 10);
  const height = parseInt(args[3] || '812', 10);
  const isMobile = args[4] === 'true';

  await capture(url, outPath, width, height, isMobile);
}

run().catch(console.error);
