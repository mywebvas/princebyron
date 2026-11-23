const http = require('http');

const urls = [
  "http://127.0.0.1:8001/",
  "http://127.0.0.1:8001/about",
  "http://127.0.0.1:8001/music",
  "http://127.0.0.1:8001/store",
  "http://127.0.0.1:8001/store/tee",
  "http://127.0.0.1:8001/cart",
  "http://127.0.0.1:8001/checkout",
  "http://127.0.0.1:8001/booking",
  "http://127.0.0.1:8001/login",
  "http://127.0.0.1:8001/account",
  "http://127.0.0.1:8001/account/orders/PB-202611-002",
  "http://127.0.0.1:8001/admin/login",
  "http://127.0.0.1:8001/admin/preview"
];

function check(u) {
  return new Promise((resolve) => {
    http.get(u, { headers: { 'Connection': 'close' } }, (res) => {
      console.log(res.statusCode, u, res.headers.location || '');
      res.resume();
      resolve();
    }).on('error', (e) => {
      console.error('ERR', u, e.message);
      resolve();
    });
  });
}

async function main() {
  for (const u of urls) {
    await check(u);
  }
}

main();
