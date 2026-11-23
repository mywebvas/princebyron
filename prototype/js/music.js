(() => {
  const songs = [
    {title:'Free', price:0, length:'3:42', note:'A reminder that grace makes room for every beginning.', tag:'FREE LISTEN'},
    {title:'Purify Me', price:1.29, length:'4:16', note:'A quiet prayer for a clear heart.', tag:'COLLECTION'},
    {title:'I Always Win', price:1.29, length:'3:58', note:'An anthem of hope through every season.', tag:'COLLECTION'},
    {title:'Toduyun', price:1.29, length:'4:31', note:'A joyful sound of gratitude.', tag:'COLLECTION'},
    {title:'Follow Me', price:1.29, length:'3:49', note:'A call to walk in faith, one day at a time.', tag:'COLLECTION'}
  ];
  const playlists=[[1,4],[2,4],[0,3]];
  const cart=[];
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const money=value=>'$'+value.toFixed(2);
  const toast=message=>{
    const element=$('.music-toast');element.textContent=message;element.classList.add('visible');
    clearTimeout(toast.timer);toast.timer=setTimeout(()=>element.classList.remove('visible'),3000);
  };
  const list=$('#catalog-track-list');

  function renderSongs(filter='all'){
    const items=songs.map((song,index)=>({...song,index})).filter(song=>filter==='all'||(filter==='free'?song.price===0:song.price>0));
    list.innerHTML=items.map(song=>'<article class="music-track-row"><span class="music-track-number">0'+(song.index+1)+'</span><div class="music-track-main"><strong>'+song.title+'</strong><small>'+song.note+'</small></div><span class="music-track-tag">'+song.tag+'</span><span class="music-track-length">'+song.length+'</span><span class="music-track-price">'+(song.price?money(song.price):'Free')+'</span><div class="music-track-actions"><button class="music-circle" aria-label="Preview '+song.title+'" data-play="'+song.index+'">▶</button>'+(song.price?'<button class="music-buy" data-buy="'+song.index+'">GET '+money(song.price)+'</button>':'<button class="music-buy" data-free-download>FREE · MP3/WAV</button>')+'</div></article>').join('');
  }
  renderSongs();
  $$('.catalog-filter').forEach(button=>button.addEventListener('click',()=>{
    $$('.catalog-filter').forEach(item=>item.classList.toggle('active',item===button));
    renderSongs(button.dataset.filter);
  }));

  function openPanel(panel){
    closePanels();$('#music-overlay').hidden=false;document.body.classList.add('locked');panel.hidden=false;
    panel.querySelector('button,input')?.focus();
  }
  function closePanels(){
    $('#music-overlay').hidden=true;document.body.classList.remove('locked');
    $$('.music-drawer,.music-checkout').forEach(panel=>panel.hidden=true);
  }
  function addToBag(item){
    const existing=cart.find(entry=>entry.id===item.id&&entry.format===item.format);
    if(existing)existing.quantity++;
    else cart.push({...item,quantity:1});
    renderBag();toast(item.title+' added to your demo music bag.');
  }
  function renderBag(){
    const count=cart.reduce((sum,item)=>sum+item.quantity,0);
    $$('.music-bag-count').forEach(element=>element.textContent=count);
    $('#music-bag-empty').style.display=count?'none':'';
    $('#music-bag-bottom').style.display=count?'':'none';
    $('#music-bag-items').innerHTML=cart.map((item,index)=>'<article class="music-bag-line"><span class="music-bag-thumb">PB</span><div><strong>'+item.title+'</strong><small>'+item.format+' · '+money(item.price)+(item.quantity>1?' · Qty '+item.quantity:'')+'</small><button class="music-bag-remove" data-remove="'+index+'">Remove</button></div><strong>'+money(item.price*item.quantity)+'</strong></article>').join('');
    $('#music-bag-total').textContent=money(cart.reduce((sum,item)=>sum+item.price*item.quantity,0));
  }
  function play(index){
    const song=songs[Number(index)];if(!song)return;
    $('#playing-title').textContent=song.title;
    $('.music-player-control').dataset.active='true';
    $('.music-player-control').textContent='Ⅱ';
    $('.music-player-control').setAttribute('aria-label','Pause preview');
    $('#music-progress-fill').style.width='13%';
    $('#music-player').classList.add('visible');
    toast('Previewing “'+song.title+'” — no audio source is connected.');
  }

  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-play]');
    if(trigger){play(trigger.dataset.play);return;}
    const buy=event.target.closest('[data-buy]');
    if(buy){
      const song=songs[Number(buy.dataset.buy)];
      addToBag({id:'track-'+buy.dataset.buy,title:song.title+' · digital track',price:song.price,format:$('#format-choice').value});
      return;
    }
    if(event.target.closest('[data-free-download]')){toast('Free download is a demo. No audio file is included.');return;}
    const playlist=event.target.closest('[data-playlist]');
    if(playlist){play(playlists[Number(playlist.dataset.playlist)][0]);return;}
    const remove=event.target.closest('[data-remove]');
    if(remove){cart.splice(Number(remove.dataset.remove),1);renderBag();return;}
  });
  $('[data-buy-album]').addEventListener('click',()=>{
    addToBag({id:'album',title:'For the in-between · digital collection',price:12,format:$('#format-choice').value});
    openPanel($('#music-bag'));
  });
  $('.music-bag-open').addEventListener('click',()=>openPanel($('#music-bag')));
  $('.music-drawer-close').addEventListener('click',closePanels);
  $('[data-close-music-bag]').addEventListener('click',closePanels);
  $('#music-overlay').addEventListener('click',closePanels);
  $('.music-checkout-open').addEventListener('click',()=>window.startDemoCheckout(cart));
  $('.music-checkout-close').addEventListener('click',closePanels);
  $('#music-checkout-form').addEventListener('submit',event=>{
    event.preventDefault();if(!event.currentTarget.reportValidity())return;
    closePanels();event.currentTarget.reset();cart.splice(0,cart.length);renderBag();
    toast('Demo complete — no payment or order was created.');
  });
  $('.music-player-control').addEventListener('click',event=>{
    const active=event.currentTarget.dataset.active==='true';
    event.currentTarget.dataset.active=String(!active);
    event.currentTarget.textContent=active?'▶':'Ⅱ';
    event.currentTarget.setAttribute('aria-label',active?'Play preview':'Pause preview');
    toast(active?'Preview paused.':'Preview playing (simulated).');
  });
  $('.music-player-close').addEventListener('click',()=>$('#music-player').classList.remove('visible'));
  $('.menu-toggle').addEventListener('click',event=>{
    const open=$('.main-nav').classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded',String(open));
  });
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{
    $('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');
  }));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closePanels();});
  renderBag();
})();
