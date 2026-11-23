(() => {
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const products=window.PRINCE_BYRON_PRODUCTS||[];
  const itemId=new URLSearchParams(window.location.search).get('product');
  const product=itemId?products.find(item=>item.id===itemId):products[0];
  const cart=[];
  const money=value=>'$'+value.toFixed(2);
  const toast=message=>{const node=$('.toast');node.textContent=message;node.classList.add('visible');clearTimeout(toast.timer);toast.timer=setTimeout(()=>node.classList.remove('visible'),3000);};
  const quantityInput=$('#product-quantity');
  const quantity=()=>{const value=Number(quantityInput.value);return Number.isInteger(value)?Math.max(1,Math.min(10,value)):1;};
  const updateAddTotal=()=>$('#product-add-total').textContent=product?money(product.price*quantity()):'';

  if(!product){$('#product-detail').hidden=true;$('#product-missing').hidden=false;}
  else{
    const detail=$('#product-detail');detail.hidden=false;$('#product-missing').hidden=true;
    const mark=['shirt','hoodie','cap','tote'].includes(product.visual)?'<span class="product-mark">PB</span>':product.visual==='print'?'<span class="product-mark">FAITH<br>IN THE<br>IN-BETWEEN</span>':product.visual==='bundle'?'<span class="product-mark">PB<br>✳</span>':'<span class="product-mark">✳</span>';
    $('#product-category').textContent=product.type==='apparel'?'Apparel':product.type==='bundles'?'Gift sets':'Accessories';
    $('#product-breadcrumb-title').textContent=product.title;$('#product-badge').textContent=product.badge;
    $('#product-title').textContent=product.title;$('#product-price').textContent=money(product.price);$('#product-description').textContent=product.description;
    document.title=product.title+' — Prince Byron Official Store';
    $('#product-about-copy').textContent=product.description;
    $('#product-spec-copy').textContent=product.type==='apparel'?'Sizes S–2XL are shown for this demo. A size chart, garment measurements, fabric and care information have not been provided and must be confirmed before a live listing.':product.type==='bundles'?product.description+' Bundle contents are illustrative and should be confirmed before launch.':'Colour choices are sample representations. Final dimensions, materials and finish should be confirmed before a live listing.';
    const art=$('#product-art');if(product.visual==='hoodie')art.classList.add('olive');if(product.visual==='tote'||product.visual==='print')art.classList.add('paper');
    const object=$('#product-object');object.className='product-object object-'+product.visual;object.innerHTML=mark;if(product.colors.length)object.style.setProperty('--garment',product.colors[0]);
    if(product.colors.length){
      $('#color-choice').hidden=false;$('#selected-color').textContent=product.colorNames[0];
      $('#product-swatches').innerHTML=product.colors.map((color,index)=>'<button class="product-swatch '+(index===0?'selected':'')+'" type="button" style="background:'+color+'" aria-label="'+product.colorNames[index]+'" aria-pressed="'+(index===0)+'" data-color-index="'+index+'"></button>').join('');
      const setColor=index=>{const color=product.colors[index];$('#selected-color').textContent=product.colorNames[index];object.style.setProperty('--garment',color);if(product.visual==='cap')object.style.background='linear-gradient(130deg,'+color+','+color+')';if(product.visual==='tote'){object.style.background=color;object.style.borderColor=color;}};
      setColor(0);
      $('#product-swatches').addEventListener('click',event=>{const swatch=event.target.closest('[data-color-index]');if(!swatch)return;const index=Number(swatch.dataset.colorIndex);$$('.product-swatch').forEach((button,i)=>{button.classList.toggle('selected',i===index);button.setAttribute('aria-pressed',String(i===index));});setColor(index);});
    }
    if(product.type==='apparel')$('#size-choice').hidden=false;
    else if(product.type==='accessories')$('#one-size').hidden=false;
    const related=products.filter(item=>item.id!==product.id).sort((a,b)=>Number(b.type===product.type)-Number(a.type===product.type)).slice(0,3);
    $('#related-grid').innerHTML=related.map(item=>{const mark=['shirt','hoodie','cap','tote'].includes(item.visual)?'<span class="product-mark">PB</span>':item.visual==='print'?'<span class="product-mark">FAITH<br>IN THE<br>IN-BETWEEN</span>':item.visual==='bundle'?'<span class="product-mark">PB<br>✳</span>':'<span class="product-mark">✳</span>';const artClass=item.visual==='hoodie'?'olive':item.visual==='tote'||item.visual==='print'?'paper':'';return '<article class="related-card"><a href="product.html?product='+encodeURIComponent(item.id)+'" aria-label="View '+item.title+'"><div class="product-art '+artClass+'"><div class="product-object object-'+item.visual+'" '+(item.colors.length?'style="--garment:'+item.colors[0]+'"':'')+'>'+mark+'</div></div><h3>'+item.title+'</h3></a><p>'+money(item.price)+' <span>USD</span></p></article>';}).join('');
    $('#related-products').hidden=false;updateAddTotal();
  }

  function renderCart(){
    const count=cart.reduce((sum,item)=>sum+item.qty,0);$$('.cart-count').forEach(node=>node.textContent=count);
    $('#cart-empty').hidden=count>0;$('#cart-bottom').hidden=count===0;
    $('#cart-items').innerHTML=cart.map((item,index)=>'<article class="cart-item"><div class="cart-thumb">PB</div><div><h3>'+item.title+'</h3><p>'+(item.option?'Size '+item.option+' · ':'')+(item.color||'Signature')+' · '+money(item.price)+'</p><div class="quantity-control"><button type="button" aria-label="Decrease '+item.title+' quantity" data-qty="'+index+'" data-step="-1">−</button><span>'+item.qty+'</span><button type="button" aria-label="Increase '+item.title+' quantity" data-qty="'+index+'" data-step="1">+</button></div></div><strong>'+money(item.price*item.qty)+'</strong></article>').join('');
    $('#cart-subtotal').textContent=money(cart.reduce((sum,item)=>sum+item.price*item.qty,0));
  }
  function closePanels(){$('#overlay').hidden=true;document.body.classList.remove('locked');$$('.drawer,.modal').forEach(panel=>{panel.hidden=true;delete panel.dataset.opened;});}
  function openPanel(panel){closePanels();$('#overlay').hidden=false;document.body.classList.add('locked');panel.hidden=false;panel.dataset.opened='true';panel.querySelector('button,input,select')?.focus();}
  function addToBag(){
    if(!product)return;
    const size=$('#size-choice-select');if(size&&!$('#size-choice').hidden&&!size.value){size.focus();toast('Choose a size before adding this item.');return;}
    quantityInput.value=quantity();updateAddTotal();
    const colorIndex=$('.product-swatch.selected')?.dataset.colorIndex;const color=colorIndex===undefined?'':product.colorNames[Number(colorIndex)];const option=size&&!$('#size-choice').hidden?size.value:'';const signature=[product.id,option,color].join('|');
    const existing=cart.find(item=>item.signature===signature);if(existing)existing.qty+=quantity();else cart.push({...product,option,color,qty:quantity(),signature});
    renderCart();toast(product.title+' added to your demo bag.');
  }

  $('#product-add').addEventListener('click',addToBag);
  quantityInput.addEventListener('input',()=>{const value=Number(quantityInput.value);if(Number.isInteger(value)&&value>=1&&value<=10)updateAddTotal();});
  quantityInput.addEventListener('change',()=>{quantityInput.value=quantity();updateAddTotal();});
  document.addEventListener('click',event=>{const control=event.target.closest('[data-product-quantity]');if(!control)return;quantityInput.value=Math.max(1,Math.min(10,quantity()+Number(control.dataset.productQuantity)));updateAddTotal();});
  $('.cart-open').addEventListener('click',()=>openPanel($('#cart-drawer')));
  $('.close-drawer').addEventListener('click',closePanels);$('#overlay').addEventListener('click',closePanels);$('.modal-close').addEventListener('click',closePanels);
  $('#cart-items').addEventListener('click',event=>{const button=event.target.closest('[data-qty]');if(!button)return;const index=Number(button.dataset.qty);if(!cart[index])return;cart[index].qty+=Number(button.dataset.step);if(cart[index].qty<1)cart.splice(index,1);renderCart();});
  $('.checkout-button').addEventListener('click',()=>{if(!cart.length)return;window.startDemoCheckout(cart);});
  $('#checkout-form').addEventListener('submit',event=>{event.preventDefault();if(!event.currentTarget.reportValidity())return;closePanels();event.currentTarget.reset();cart.splice(0,cart.length);renderCart();toast('Demo complete — no payment or order was created.');});
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());document.addEventListener('keydown',event=>{if(event.key==='Escape')closePanels();});
  renderCart();
})();
