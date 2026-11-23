(() => {
  const tracks = [
    { title: 'Free', kind: 'single', price: 0, length: '3:42', tag: 'FREE LISTEN', note: 'A reminder that grace makes room for every beginning.' },
    { title: 'Purify Me', kind: 'collection', price: 1.29, length: '4:16', tag: 'COLLECTION', note: 'A quiet prayer for a clear heart.' },
    { title: 'I Always Win', kind: 'collection', price: 1.29, length: '3:58', tag: 'COLLECTION', note: 'An anthem of hope through every season.' },
    { title: 'Toduyun', kind: 'collection', price: 1.29, length: '4:31', tag: 'COLLECTION', note: 'A joyful sound of gratitude.' },
    { title: 'Follow Me', kind: 'collection', price: 1.29, length: '3:49', tag: 'COLLECTION', note: 'A call to walk in faith, one day at a time.' }
  ];
  const products = window.PRINCE_BYRON_PRODUCTS;
  const cart=[];
  const $=(selector,root=document)=>root.querySelector(selector);
  const $$=(selector,root=document)=>[...root.querySelectorAll(selector)];
  const money=amount=>'$'+amount.toFixed(2);
  const showToast=message=>{const toast=$('.toast');toast.textContent=message;toast.classList.add('visible');clearTimeout(showToast.timer);showToast.timer=setTimeout(()=>toast.classList.remove('visible'),3000);};

  const trackList=$('#track-list');
  function renderTracks(filter='all'){
    const filtered=tracks.filter(track=>filter==='all'||(filter==='free'?track.price===0:track.kind===filter));
    trackList.innerHTML=filtered.map(track=>{
      const index=tracks.indexOf(track);
      return '<article class="track-row"><span class="track-number">0'+(index+1)+'</span><div class="track-name">'+track.title+'<small>'+track.note+'</small></div><span class="track-tag">'+track.tag+'</span><span class="track-length">'+track.length+'</span><span class="track-price">'+(track.price?'$'+track.price.toFixed(2):'Free')+'</span><div class="track-actions"><button class="circle-action" aria-label="Preview '+track.title+'" data-play="'+index+'">▶</button>'+(track.price?'<button class="circle-action" aria-label="Get '+track.title+'" data-track-buy="'+index+'">＋</button>':'<button class="circle-action" aria-label="Download Free formats" data-download="Free">↓</button>')+'</div></article>';
    }).join('')||'<p>No songs in this collection yet.</p>';
  }
  renderTracks();
  $$('.music-filters button').forEach(button=>button.addEventListener('click',()=>{
    $$('.music-filters button').forEach(chip=>chip.classList.remove('active'));
    button.classList.add('active');renderTracks(button.dataset.musicFilter);
  }));

  const productGrid=$('#product-grid');
  function renderProducts(filter='all'){
    let list=products.filter(product=>filter==='all'||product.type===filter);
    const sort=$('#sort-products').value;
    if(sort==='low')list=[...list].sort((a,b)=>a.price-b.price);
    if(sort==='high')list=[...list].sort((a,b)=>b.price-a.price);
    productGrid.innerHTML=list.map(product=>{
      const swatches=product.colors.map((color,i)=>'<button class="swatch '+(i===0?'selected':'')+'" aria-label="'+product.colorNames[i]+'" title="'+product.colorNames[i]+'" style="background:'+color+'" data-color="'+product.colorNames[i]+'" data-swatch="'+color+'"></button>').join('');
      let mark='';
      if(['shirt','hoodie','cap','tote'].includes(product.visual))mark='<span class="product-mark">PB</span>';
      if(product.visual==='print')mark='<span class="product-mark">FAITH<br>IN THE<br>IN-BETWEEN</span>';
      if(product.visual==='bundle')mark='<span class="product-mark">PB<br>✳</span>';
      const description=product.description||(product.type==='apparel'?'Soft cotton · Made to live in':'A small way to keep the message close');
      return '<article class="product-card" data-product="'+product.id+'"><div class="product-art '+(product.visual==='tote'||product.visual==='print'?'paper':product.visual==='hoodie'?'olive':'')+'"><span class="product-badge">'+product.badge+'</span><div class="product-object object-'+product.visual+'" '+(product.colors.length?'style="--garment:'+product.colors[0]+'"':'')+'>'+mark+'</div></div><div class="product-info"><div><h3>'+product.title+'</h3><p>'+description+'</p><a class="product-detail-link" href="product.html?product='+encodeURIComponent(product.id)+'">View details <span>↗</span></a></div><span class="product-price">$'+product.price+'</span></div>'+(product.colors.length?'<div class="product-options"><div class="swatches" aria-label="Choose a colour">'+swatches+'</div>'+(product.type==='apparel'?'<select class="size-select" aria-label="Choose a size"><option value="S">Size: S</option><option value="M">Size: M</option><option value="L">Size: L</option><option value="XL">Size: XL</option><option value="2XL">Size: 2XL</option></select>':'<span class="size-select">One size</span>')+'</div>':'')+'<button class="add-product" data-add="'+product.id+'">ADD TO BAG · $'+product.price+'</button></article>';
    }).join('');
  }
  renderProducts();
  let activeStoreFilter='all';
  $$('.collection-tab').forEach(button=>button.addEventListener('click',()=>{
    $$('.collection-tab').forEach(tab=>tab.classList.remove('active'));
    button.classList.add('active');activeStoreFilter=button.dataset.storeFilter;renderProducts(activeStoreFilter);
  }));
  $('#sort-products').addEventListener('change',()=>renderProducts(activeStoreFilter));

  function updateCart(){
    const count=cart.reduce((sum,item)=>sum+item.qty,0);
    $$('.cart-count').forEach(node=>node.textContent=count);
    $('#cart-empty').style.display=count?'none':'';
    $('#cart-bottom').style.display=count?'':'none';
    $('#cart-items').innerHTML=cart.map((item,index)=>'<article class="cart-item"><div class="cart-thumb">'+(item.visual==='bundle'?'PB ✳':'PB')+'</div><div><h3>'+item.title+'</h3><p>'+(item.option?item.option+' · ':'')+(item.color||'Signature')+' · '+money(item.price)+'</p><div class="quantity-control"><button aria-label="Decrease quantity" data-qty="'+index+'" data-step="-1">−</button><span>'+item.qty+'</span><button aria-label="Increase quantity" data-qty="'+index+'" data-step="1">+</button></div></div><strong>'+money(item.price*item.qty)+'</strong></article>').join('');
    $('#cart-subtotal').textContent=money(cart.reduce((sum,item)=>sum+item.price*item.qty,0));
  }
  function addItem(id,options={}){
    const product=products.find(item=>item.id===id);if(!product)return;
    const signature=[id,options.option||'',options.color||''].join('|');
    const existing=cart.find(item=>item.signature===signature);
    if(existing)existing.qty++;
    else cart.push({...product,...options,qty:1,signature});
    updateCart();showToast(product.title+' added to your demo bag.');
  }

  const playerBar=$('#player-bar');
  function playTrack(index){
    const track=tracks[Number(index)];if(!track)return;
    $('.player-play').dataset.playing='true';$('.player-play').textContent='Ⅱ';
    $('#player-title').textContent=track.title;playerBar.classList.add('visible');
    $('#progress-fill').style.width='12%';
    showToast('Previewing “'+track.title+'” — demo player, no audio source connected.');
  }
  function openPanel(panel){
    closePanels();$('#overlay').hidden=false;document.body.classList.add('locked');
    const element=panel==='cart'?$('#cart-drawer'):$('#'+panel+'-modal');
    element.hidden=false;element.dataset.opened='true';
    $('button:not([hidden]),input,select',element)?.focus();
  }
  function closePanels(){
    $('#overlay').hidden=true;document.body.classList.remove('locked');
    $$('.drawer,.modal').forEach(panel=>{panel.hidden=true;delete panel.dataset.opened;});
  }
  $('.cart-open').addEventListener('click',()=>openPanel('cart'));
  $('[data-close-cart]').addEventListener('click',closePanels);
  $('.close-drawer').addEventListener('click',closePanels);
  $('#overlay').addEventListener('click',closePanels);
  $$('.modal-close').forEach(button=>button.addEventListener('click',closePanels));
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closePanels();});
  $$('[data-open]').forEach(button=>button.addEventListener('click',()=>openPanel(button.dataset.open)));
  document.addEventListener('click',event=>{
    const play=event.target.closest('[data-play]');
    if(play){playTrack(play.dataset.play);return;}
    const buy=event.target.closest('[data-track-buy]');
    if(buy){const track=tracks[Number(buy.dataset.trackBuy)];cart.push({id:'track-'+buy.dataset.trackBuy,title:track.title+' · digital track',price:track.price,visual:'track',color:'MP3 + WAV demo',qty:1,signature:'track-'+buy.dataset.trackBuy});updateCart();showToast('Digital track added to your demo bag.');return;}
    const download=event.target.closest('[data-download]');
    if(download){showToast('Download is simulated. The prototype contains no audio files.');return;}
    const product=event.target.closest('[data-add]');
    if(product){
      const id=product.dataset.add;
      if(id==='tee-cap'){cart.push({id:'tee-cap',title:'Everyday Faith Set',price:35,visual:'bundle',color:'Bundle',qty:1,signature:'tee-cap|bundle'});updateCart();showToast('Everyday Faith Set added to your demo bag.');return;}
      const card=product.closest('.product-card');const color=$('.swatch.selected',card);const size=$('.size-select',card);
      addItem(id,{color:color?.dataset.color||'Signature',option:size?.value||''});return;
    }
    const quantity=event.target.closest('[data-qty]');
    if(quantity){const index=Number(quantity.dataset.qty);cart[index].qty+=Number(quantity.dataset.step);if(cart[index].qty<=0)cart.splice(index,1);updateCart();}
  });
  productGrid.addEventListener('click',event=>{
    const swatch=event.target.closest('.swatch');if(!swatch)return;
    const card=swatch.closest('.product-card');$$('.swatch',card).forEach(el=>el.classList.remove('selected'));swatch.classList.add('selected');
    const object=$('.product-object',card);if(object)object.style.background='linear-gradient(110deg,'+swatch.dataset.swatch+',#eee5d8 49%,'+swatch.dataset.swatch+')';
  });
  $('[data-play="0"]').addEventListener('click',()=>playTrack(0));
  $('[data-buy-album]').addEventListener('click',()=>{
    cart.push({id:'album-demo',title:'Faith for the in-between · digital collection',price:12,visual:'album',color:'MP3 + WAV demo',qty:1,signature:'album-demo'});
    updateCart();openPanel('cart');
  });
  $('.player-play').addEventListener('click',event=>{
    const playing=event.currentTarget.dataset.playing==='true';event.currentTarget.dataset.playing=String(!playing);
    event.currentTarget.textContent=playing?'▶':'Ⅱ';showToast(playing?'Preview paused.':'Preview playing (simulated).');
  });
  $('.player-close').addEventListener('click',()=>playerBar.classList.remove('visible'));
  $('#newsletter-form').addEventListener('submit',event=>{event.preventDefault();showToast('Thank you — newsletter signup is a demo and no message was sent.');event.currentTarget.reset();});

  let bookingStep=1;const bookingForm=$('#booking-form');
  const setBookingStep=step=>{
    bookingStep=step;
    $$('.booking-pane').forEach(pane=>pane.classList.toggle('active',Number(pane.dataset.pane)===step));
    $$('.step-dots i').forEach((dot,index)=>dot.classList.toggle('active',index<step));
    $('.step-count').textContent='STEP '+step+' OF 3';
    $('.back-step').hidden=step===1;$('.next-step').hidden=step===3;$('.submit-booking').hidden=step!==3;
    $('#booking-title').innerHTML=step===1?'Tell us about<br><em>your gathering.</em>':step===2?'Let’s plan<br><em>the details.</em>':'How can we<br><em>reach you?</em>';
  };
  $('.next-step').addEventListener('click',()=>{
    const fields=$$('.booking-pane.active input,.booking-pane.active select,.booking-pane.active textarea').filter(field=>field.required);
    const invalid=fields.find(field=>!field.checkValidity());
    if(invalid){invalid.reportValidity();return;}
    setBookingStep(Math.min(3,bookingStep+1));
  });
  $('.back-step').addEventListener('click',()=>setBookingStep(Math.max(1,bookingStep-1)));
  bookingForm.addEventListener('submit',event=>{event.preventDefault();if(!bookingForm.reportValidity())return;closePanels();bookingForm.reset();setBookingStep(1);showToast('Your demo enquiry is ready. No booking request was sent.');});
  const tomorrow=new Date();tomorrow.setDate(tomorrow.getDate()+1);
  $('[name="eventDate"]').min=[tomorrow.getFullYear(),String(tomorrow.getMonth()+1).padStart(2,'0'),String(tomorrow.getDate()).padStart(2,'0')].join('-');

  $('.checkout-button').addEventListener('click',()=>{
    window.startDemoCheckout(cart);
  });
  $('#checkout-form').addEventListener('submit',event=>{event.preventDefault();if(!event.currentTarget.reportValidity())return;closePanels();showToast('Demo complete — no payment or order was created.');cart.splice(0,cart.length);updateCart();event.currentTarget.reset();});
  $('.menu-toggle').addEventListener('click',event=>{const nav=$('.main-nav');const open=nav.classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('[data-show-bundles]').addEventListener('click',()=>{
    activeStoreFilter='bundles';
    $$('.collection-tab').forEach(tab=>tab.classList.toggle('active',tab.dataset.storeFilter==='bundles'));
    renderProducts('bundles');$('#product-grid').scrollIntoView({behavior:'smooth',block:'start'});
  });
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
  updateCart();
})();
