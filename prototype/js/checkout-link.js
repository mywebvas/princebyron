window.startDemoCheckout = function(items){
  if(!Array.isArray(items)||items.length===0)return;
  const checkoutItems=items.map(item=>({
    id:String(item.id||''),
    quantity:Number(item.qty??item.quantity??1),
    size:String(item.option||''),
    color:String(item.color||''),
    format:String(item.format||'')
  }));
  const url=new URL('checkout.html',window.location.href);
  url.searchParams.set('items',JSON.stringify(checkoutItems));
  window.location.assign(url.href);
};
