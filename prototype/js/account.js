(() => {
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const toast=message=>{const node=$('.toast');node.textContent=message;node.classList.add('visible');clearTimeout(toast.timer);toast.timer=setTimeout(()=>node.classList.remove('visible'),3200);};
  $('#signin-form').addEventListener('submit',event=>{event.preventDefault();if(!event.currentTarget.reportValidity())return;event.currentTarget.reset();toast('Sign-in preview only. No account or session was created.');});
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
})();
