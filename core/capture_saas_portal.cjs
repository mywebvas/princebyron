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
    '--remote-debugging-port=9777',
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
        const res = await fetch('http://127.0.0.1:9777/json/new?about:blank', { method: 'PUT' });
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
    await send('Runtime.enable');

    console.log('=== Step 1: Login as Customer (fan@princebyron.local) ===');
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

    console.log('=== Step 2: Capturing SaaS Customer Portal across Viewports ===');
    for (const vp of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.w,
        height: vp.h,
        deviceScaleFactor: 1,
        mobile: vp.isMobile
      });

      await send('Page.navigate', { url: 'http://127.0.0.1:8001/account' });
      await sleep(1500);

      // Verify DOM architecture
      const domCheck = await send('Runtime.evaluate', {
        expression: `
          JSON.stringify({
            saasPortal: !!document.querySelector('.saas-portal'),
            sidebarVisible: !!document.querySelector('.saas-sidebar'),
            topbarVisible: !!document.querySelector('.saas-topbar'),
            searchInput: !!document.querySelector('.saas-search-input'),
            profileTrigger: !!document.querySelector('.saas-profile-trigger'),
            h1: document.querySelector('h1')?.innerText,
            kpis: document.querySelectorAll('.saas-portal-content > div:nth-child(2) > div').length,
            songs: document.querySelectorAll('#library tbody tr').length,
            orders: document.querySelectorAll('#orders tbody tr').length,
            bodyScrollWidth: document.body.scrollWidth,
            windowWidth: window.innerWidth
          })
        `
      });
      console.log(`DOM Check [${vp.suffix}]:`, domCheck.result?.value);

      // Viewport Screenshot
      const shot = await send('Page.captureScreenshot', { format: 'png' });
      fs.writeFileSync(path.join(outDir, `12_account_authenticated_${vp.suffix}.png`), Buffer.from(shot.data, 'base64'));
      console.log(`  Saved: 12_account_authenticated_${vp.suffix}.png`);

      // Full Page Screenshot on Desktop
      if (vp.suffix === 'desktop') {
        const shotFull = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
        fs.writeFileSync(path.join(outDir, '12_account_full_desktop.png'), Buffer.from(shotFull.data, 'base64'));
        console.log('  Saved: 12_account_full_desktop.png');

        // Test expanding Orders Accordion
        await send('Runtime.evaluate', {
          expression: `document.querySelector('#orders .accordion-toggle-btn')?.click()`
        });
        await sleep(500);
        const shotOrdersOpen = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
        fs.writeFileSync(path.join(outDir, '12_account_orders_expanded_desktop.png'), Buffer.from(shotOrdersOpen.data, 'base64'));
        console.log('  Saved: 12_account_orders_expanded_desktop.png');

        // Test Profile Dropdown open state
        await send('Runtime.evaluate', {
          expression: `document.querySelector('.saas-profile-trigger').click()`
        });
        await sleep(400);
        const shotDropdown = await send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(outDir, '12_account_profile_dropdown_desktop.png'), Buffer.from(shotDropdown.data, 'base64'));
        console.log('  Saved: 12_account_profile_dropdown_desktop.png');

        // Close dropdown
        await send('Runtime.evaluate', {
          expression: `document.querySelector('.saas-profile-trigger').click()`
        });
      }

      // Also capture Order PB-202611-002 receipt
      await send('Page.navigate', { url: 'http://127.0.0.1:8001/account/orders/PB-202611-002' });
      await sleep(1500);
      const shotOrder = await send('Page.captureScreenshot', { format: 'png' });
      fs.writeFileSync(path.join(outDir, `13_order_receipt_pb2_${vp.suffix}.png`), Buffer.from(shotOrder.data, 'base64'));
      console.log(`  Saved: 13_order_receipt_pb2_${vp.suffix}.png`);
    }

    console.log('\n=== All SaaS Customer Portal Views Captured Successfully! ===');
    ws.close();
  } finally {
    edge.kill();
  }
}

run().catch(console.error);
