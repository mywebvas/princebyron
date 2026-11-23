(() => {
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const money=value=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(value);
  const merch=window.PRINCE_BYRON_PRODUCTS||[];
  const songs=[
    {title:'Free',price:0},
    {title:'Purify Me',price:1.29},
    {title:'I Always Win',price:1.29},
    {title:'Toduyun',price:1.29},
    {title:'Follow Me',price:1.29}
  ];
  let cart=[];
  let step=1;

  function resolveItem(raw){
    if(!raw||typeof raw!=='object'||!Number.isInteger(Number(raw.quantity))||Number(raw.quantity)<1||Number(raw.quantity)>99)return null;
    const quantity=Number(raw.quantity);
    const product=merch.find(item=>item.id===raw.id);
    if(product){
      const size=String(raw.size||'');
      if(product.type==='apparel'&&!['S','M','L','XL','2XL'].includes(size))return null;
      const color=product.colorNames?.includes(raw.color)?raw.color:product.colorNames?.[0]||'';
      return {id:product.id,title:product.title,type:'merch',price:product.price,quantity,size,color,format:''};
    }
    const trackMatch=/^track-(\d)$/.exec(String(raw.id));
    if(trackMatch){
      const index=Number(trackMatch[1]);const song=songs[index];if(!song)return null;
      const format=raw.format==='WAV'?'WAV':raw.format==='MP3'?'MP3':String(raw.color||'').includes('WAV')?'MP3 + WAV':'MP3';
      return {id:String(raw.id),title:song.title,type:'digital',price:song.price,quantity,size:'',color:'',format};
    }
    if(raw.id==='album'||raw.id==='album-demo'){const format=raw.format==='WAV'?'WAV':raw.format==='MP3'?'MP3':String(raw.color||'').includes('WAV')?'MP3 + WAV':'MP3';return {id:'album',title:'For the in-between · digital collection',type:'digital',price:12,quantity,size:'',color:'',format};}
    return null;
  }
  function makeLine(item){
    const row=document.createElement('article');row.className='checkout-line';
    const art=document.createElement('span');art.className='checkout-line-art';art.textContent=item.type==='digital'?'♪':'PB';
    const copy=document.createElement('div');const title=document.createElement('h3');title.textContent=item.title;
    const details=[item.quantity+' ×',item.size?'Size '+item.size:'',item.color,item.format].filter(Boolean).join(' · ');
    const meta=document.createElement('p');meta.textContent=details;copy.append(title,meta);
    const amount=document.createElement('strong');amount.textContent=money(item.price*item.quantity);row.append(art,copy,amount);return row;
  }
  function displayItems(target){target.replaceChildren(...cart.map(makeLine));}
  function setStep(next){
    step=Math.max(1,Math.min(3,next));
    $$('.checkout-step').forEach(pane=>{const active=Number(pane.dataset.checkoutStep)===step;pane.hidden=!active;pane.classList.toggle('active',active);});
    $$('.checkout-progress>div').forEach((node,index)=>{node.classList.toggle('active',index+1===step);node.classList.toggle('done',index+1<step);});
  }
  function validateStep(){
    if(step===2&&!cart.some(item=>item.type==='merch'))return true;
    const required=$$('.checkout-step.active [required]');
    for(const field of required){if(!field.checkValidity()){field.reportValidity();field.focus();return false;}}
    return true;
  }
  function showEmpty(){ $('#checkout-layout').hidden=true;$('#checkout-empty').hidden=false;$('#checkout-success').hidden=true; }
  function initialize(){
    const queryItems=new URLSearchParams(window.location.search).get('items');
    const isSample=queryItems===null;
    let raw=isSample?[
      {id:'tee',quantity:1,size:'M',color:'Olive'},
      {id:'track-1',quantity:1,format:'MP3'}
    ]:[];
    if(!isSample){try{raw=JSON.parse(queryItems||'[]');}catch{}}
    if(!Array.isArray(raw)||raw.length===0||raw.length>40){showEmpty();return;}
    const normalized=raw.map(resolveItem);if(normalized.some(item=>!item)){showEmpty();return;}
    cart=normalized;
    const hasMerch=cart.some(item=>item.type==='merch');
    $('#delivery-form').hidden=!hasMerch;$('#digital-only-note').hidden=hasMerch;
    displayItems($('#checkout-items'));displayItems($('#checkout-review-items'));
    const subtotal=cart.reduce((sum,item)=>sum+item.price*item.quantity,0);
    $('#checkout-subtotal').textContent=money(subtotal);
    $('#checkout-review-totals').innerHTML='<div class="demo-subtotal"><span>Sample subtotal (USD)</span><strong>'+money(subtotal)+'</strong></div><div><span>Shipping</span><span>Not calculated</span></div><div><span>Tax</span><span>Not calculated</span></div>';
    $('#sample-cart-note').hidden=!isSample;
    $('#checkout-layout').hidden=false;$('#checkout-empty').hidden=true;$('#checkout-success').hidden=true;setStep(1);
  }

  $$('.checkout-next').forEach(button=>button.addEventListener('click',()=>{if(validateStep())setStep(step+1);}));
  $$('.checkout-back-step').forEach(button=>button.addEventListener('click',()=>setStep(step-1)));
  $('#checkout-form').addEventListener('submit',event=>{
    event.preventDefault();if(!validateStep())return;
    $('#checkout-layout').hidden=true;$('#checkout-success').hidden=false;$('#checkout-success').focus();event.currentTarget.reset();cart=[];
  });
  $('#checkout-back-to-bag').addEventListener('click',()=>{
    const parent=new URL('.',window.location.href).href;
    if(document.referrer&&document.referrer.startsWith(parent))window.history.back();
    else window.location.href='store.html';
  });
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
  initialize();
})();
