const fs = require('fs');

async function main() {
  const publicRoutes = [
    { name: 'home', url: 'http://127.0.0.1:8001/' },
    { name: 'about', url: 'http://127.0.0.1:8001/about' },
    { name: 'music', url: 'http://127.0.0.1:8001/music' },
    { name: 'store', url: 'http://127.0.0.1:8001/store' },
    { name: 'product_tee', url: 'http://127.0.0.1:8001/store/prince-byron-t-shirt' },
    { name: 'cart', url: 'http://127.0.0.1:8001/cart' },
    { name: 'checkout', url: 'http://127.0.0.1:8001/checkout' },
    { name: 'booking', url: 'http://127.0.0.1:8001/booking' },
    { name: 'guest_login', url: 'http://127.0.0.1:8001/login' },
    { name: 'admin_preview', url: 'http://127.0.0.1:8001/admin/preview' },
    { name: 'admin_login', url: 'http://127.0.0.1:8001/admin/login' }
  ];

  for (const r of publicRoutes) {
    try {
      const res = await fetch(r.url);
      const html = await res.text();
      fs.writeFileSync(`core/public/snapshots/${r.name}.html`, html);
      console.log(`Updated core/public/snapshots/${r.name}.html`);
    } catch (e) {
      console.error(`Failed ${r.name}:`, e.message);
    }
  }
}

main();
