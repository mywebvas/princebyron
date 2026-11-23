(() => {
  const products = window.PRINCE_BYRON_PRODUCTS;
  const cart=[];
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const money=amount=>'$'+amount.toFixed(2);
  const toast=message=>{const node=$('.toast');node.textContent=message;node.classList.add('visible');clearTimeout(toast.timer);toast.timer=setTimeout(()=>node.classList.remove('visible'),3000);};
  const productGrid=$('#product-grid');
  let activeFilter='all';

  function renderProducts(){
    let list=products.filter(product=>activeFilter==='all'||product.type===activeFilter);
    const sort=$('#sort-products').value;
    if(sort==='low')list=[...list].sort((a,b)=>a.price-b.price);
    if(sort==='high')list=[...list].sort((a,b)=>b.price-a.price);
    productGrid.innerHTML=list.map(product=>{
      const swatches=product.colors.map((color,index)=>'<button type="button" class="swatch '+(index===0?'selected':'')+'" aria-label="'+product.colorNames[index]+'" aria-pressed="'+(index===0)+'" title="'+product.colorNames[index]+'" style="background:'+color+'" data-color="'+product.colorNames[index]+'" data-swatch="'+color+'"></button>').join('');
      const mark=['shirt','hoodie','cap','tote'].includes(product.visual)?'<span class="product-mark">PB</span>':product.visual==='print'?'<span class="product-mark">FAITH<br>IN THE<br>IN-BETWEEN</span>':product.visual==='bundle'?'<span class="product-mark">PB<br>✳</span>':'';
      const description=product.description||(product.type==='apparel'?'Soft cotton · Made to live in':'A small way to keep the message close');
      const variants=product.colors.length?'<div class="product-options"><div class="swatches" role="group" aria-label="Choose a colour">'+swatches+'</div>'+(product.type==='apparel'?'<select class="size-select" aria-label="Choose a size" data-size><option value="">Choose size</option><option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option><option value="2XL">2XL</option></select>':'<span class="size-select">One size</span>')+'</div>':'';
      return '<article class="product-card" data-product="'+product.id+'"><div class="product-art '+(product.visual==='tote'||product.visual==='print'?'paper':product.visual==='hoodie'?'olive':'')+'"><span class="product-badge">'+product.badge+'</span><div class="product-object object-'+product.visual+'" '+(product.colors.length?'style="--garment:'+product.colors[0]+'"':'')+'>'+mark+'</div></div><div class="product-info"><div><h3>'+product.title+'</h3><p>'+description+'</p><a class="product-detail-link" href="product.html?product='+encodeURIComponent(product.id)+'">View details <span>↗</span></a></div><span class="product-price">$'+product.price+'</span></div>'+variants+'<button type="button" class="add-product" data-add="'+product.id+'">ADD TO BAG · $'+product.price+'</button></article>';
    }).join('');
  }
  renderProducts();

  function updateCart(){
    const count=cart.reduce((sum,item)=>sum+item.qty,0);
    $$('.cart-count').forEach(node=>node.textContent=count);
    $('#cart-empty').hidden=Boolean(count);
    $('#cart-bottom').hidden=!count;
    $('#cart-items').innerHTML=cart.map((item,index)=>'<article class="cart-item"><div class="cart-thumb">PB</div><div><h3>'+item.title+'</h3><p>'+(item.option?item.option+' · ':'')+(item.color||'Signature')+' · '+money(item.price)+'</p><div class="quantity-control"><button type="button" aria-label="Decrease '+item.title+' quantity" data-qty="'+index+'" data-step="-1">−</button><span>'+item.qty+'</span><button type="button" aria-label="Increase '+item.title+' quantity" data-qty="'+index+'" data-step="1">+</button></div></div><strong>'+money(item.price*item.qty)+'</strong></article>').join('');
    $('#cart-subtotal').textContent=money(cart.reduce((sum,item)=>sum+item.price*item.qty,0));
  }
  function openPanel(panel){closePanels();$('#overlay').hidden=false;document.body.classList.add('locked');panel.hidden=false;panel.dataset.opened='true';panel.querySelector('button:not([hidden]),input,select')?.focus();}
  function closePanels(){
    $('#overlay').hidden=true;document.body.classList.remove('locked');
    $$('.drawer,.modal').forEach(panel=>{panel.hidden=true;delete panel.dataset.opened;});
  }
  function addItem(product,options={}){
    const signature=[product.id,options.option||'',options.color||''].join('|');
    const existing=cart.find(item=>item.signature===signature);
    if(existing)existing.qty++;
    else cart.push({...product,...options,qty:1,signature});
    updateCart();toast(product.title+' added to your demo bag.');
  }

  document.addEventListener('click',event=>{
    const filter=event.target.closest('[data-store-filter]');
    if(filter){activeFilter=filter.dataset.storeFilter;$$('.collection-tab').forEach(tab=>{const active=tab===filter;tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));});renderProducts();return;}
    const swatch=event.target.closest('[data-swatch]');
    if(swatch){const card=swatch.closest('.product-card');card.querySelectorAll('.swatch').forEach(item=>{const active=item===swatch;item.classList.toggle('selected',active);item.setAttribute('aria-pressed',String(active));});card.querySelector('.product-object').style.setProperty('--garment',swatch.dataset.swatch);return;}
    const add=event.target.closest('[data-add]');
    if(add){const product=products.find(item=>item.id===add.dataset.add);const card=add.closest('.product-card');const size=card.querySelector('[data-size]');if(size&&!size.value){size.focus();toast('Choose a size before adding this item.');return;}const color=card.querySelector('.swatch.selected')?.dataset.color;addItem(product,{option:size?.value||'',color:color||''});return;}
    if(event.target.closest('[data-show-bundles]')){activeFilter='bundles';$$('.collection-tab').forEach(tab=>{const active=tab.dataset.storeFilter==='bundles';tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));});renderProducts();$('#product-grid').scrollIntoView({behavior:'smooth',block:'start'});return;}
    const quantity=event.target.closest('[data-qty]');
    if(quantity){const index=Number(quantity.dataset.qty);if(!Number.isInteger(index)||!cart[index])return;cart[index].qty+=Number(quantity.dataset.step);if(cart[index].qty<1)cart.splice(index,1);updateCart();return;}
    if(event.target.closest('.cart-open')){openPanel($('#cart-drawer'));return;}
    if(event.target.closest('.close-drawer,[data-close-cart],.modal-close,#overlay')){closePanels();return;}
    if(event.target.closest('.checkout-button')){if(!cart.length){toast('Your bag is empty. Add an item to continue.');return;}window.startDemoCheckout(cart);return;}
    if(event.target.closest('.ribbon-close'))$('.demo-ribbon').remove();
  });
  $('#sort-products').addEventListener('change',renderProducts);
  $('#checkout-form').addEventListener('submit',event=>{event.preventDefault();if(!event.currentTarget.reportValidity())return;closePanels();event.currentTarget.reset();cart.splice(0,cart.length);updateCart();toast('Demo complete — no payment or order was created.');});
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closePanels();});
  updateCart();
})();
