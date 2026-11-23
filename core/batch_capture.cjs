const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const edgePath = "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe";
const artDir = "C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots";

const views = [
  { name: 'home', route: '/' },
  { name: 'about', route: '/about' },
  { name: 'music', route: '/music' },
  { name: 'store', route: '/store' },
  { name: 'product', route: '/store/tee' },
  { name: 'cart', route: '/cart' },
  { name: 'checkout', route: '/checkout' },
  { name: 'booking', route: '/booking' },
  { name: 'account', route: '/account' },
  { name: 'admin_preview', route: '/admin/preview' }
];

const viewports = [
  { suffix: 'desktop', w: 1440, h: 900, mobile: false },
  { suffix: 'tablet', w: 768, h: 1024, mobile: false },
  { suffix: 'mobile', w: 375, h: 812, mobile: true }
];

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function main() {
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

    if (!wsUrl) throw new Error('Could not connect to Edge via CDP WebSocket');

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

    for (const v of views) {
      const url = `http://127.0.0.1:8001${v.route}`;
      for (const vp of viewports) {
        await send('Emulation.setDeviceMetricsOverride', {
          width: vp.w,
          height: vp.h,
          deviceScaleFactor: 1,
          mobile: vp.mobile
        });

        await send('Page.navigate', { url });
        await sleep(1200);

        const shot = await send('Page.captureScreenshot', {
          format: 'png',
          captureBeyondViewport: false
        });

        const outName = `${v.name}_${vp.suffix}.png`;
        const outPath = path.join(artDir, outName);
        fs.writeFileSync(outPath, Buffer.from(shot.data, 'base64'));
        console.log(`[Captured] ${outName} (${vp.w}x${vp.h}, mobile: ${vp.mobile}) from ${url}`);
      }
    }

    ws.close();
  } finally {
    edge.kill();
  }
}

main().catch(console.error);
