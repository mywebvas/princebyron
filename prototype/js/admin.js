(() => {
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
})();
