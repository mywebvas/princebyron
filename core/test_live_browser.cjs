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

    if (!wsUrl) {
      console.error('Failed to get WebSocket debugger URL');
      return;
    }

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
    await send('Network.enable');

    await send('Emulation.setDeviceMetricsOverride', {
      width: 1440,
      height: 900,
      deviceScaleFactor: 1,
      mobile: false
    });

    console.log('1. Navigating to /login...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/login' });
    await sleep(1000);

    console.log('2. Submitting login with changeme-dev-only...');
    await send('Runtime.evaluate', {
      expression: `
        document.querySelector('#login-email').value = 'fan@princebyron.local';
        document.querySelector('#login-password').value = 'changeme-dev-only';
        document.querySelector('form[action*="login"]').submit();
      `
    });
    await sleep(2500);

    let evalRes = await send('Runtime.evaluate', {
      expression: '({ url: window.location.href, title: document.title, bodySnippet: document.body.innerText.substring(0, 300) })',
      returnByValue: true
    });
    console.log('Post-login result:', evalRes.result.value);

    console.log('3. Navigating to /account/orders/PB-202611-002...');
    await send('Page.navigate', { url: 'http://127.0.0.1:8001/account/orders/PB-202611-002' });
    await sleep(2000);

    evalRes = await send('Runtime.evaluate', {
      expression: `({
        url: window.location.href,
        title: document.title,
        heading: document.querySelector('.account-card h2, .account-card h3')?.innerText,
        rect: document.querySelector('.account-card')?.getBoundingClientRect(),
        visibleSnippet: document.querySelector('.account-card')?.innerText.substring(0, 300)
      })`,
      returnByValue: true
    });
    console.log('Order detail receipt:', evalRes.result.value);

    // Capture screenshot
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('C:\\Users\\autoc\\.gemini\\antigravity\\brain\\8a2227c0-57cb-44cc-874e-e2ba88e94508\\screenshots\\live_order_pb2_authenticated.png', Buffer.from(shot.data, 'base64'));
    console.log('Screenshot saved to live_order_pb2_authenticated.png');

    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
