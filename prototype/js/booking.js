(() => {
  const $=selector=>document.querySelector(selector);
  const $$=selector=>[...document.querySelectorAll(selector)];
  const form=$('#booking-form');
  let step=1;
  const titleByStep=['Tell us about your gathering.','Let’s plan the details.','How can we reach you?'];

  function setStep(nextStep){
    step=Math.max(1,Math.min(3,nextStep));
    $$('.booking-pane').forEach(pane=>{const active=Number(pane.dataset.pane)===step;pane.classList.toggle('active',active);pane.hidden=!active;});
    $$('.step-dots i').forEach((dot,index)=>dot.classList.toggle('active',index<step));
    $('#step-count').textContent='STEP '+step+' OF 3';
    $('.back-step').hidden=step===1;$('.next-step').hidden=step===3;$('.submit-booking').hidden=step!==3;
    const line=step===1?'Tell us about':step===2?'Let’s plan':'How can we';const tail=step===1?'your gathering.':step===2?'the details.':'reach you?';
    const legend=$('.booking-pane.active legend');legend.textContent=step===1?'Tell us about the event':step===2?'Share the event details':'How can we reach you?';
    const heading=$('.booking-form-intro h2');heading.innerHTML=line+'<br><em>'+tail+'</em>';
  }
  function validateCurrentStep(){
    const required=$$('.booking-pane.active [required]');
    const invalid=required.find(field=>!field.checkValidity());
    if(invalid){invalid.reportValidity();invalid.focus();return false;}
    return true;
  }
  $('.next-step').addEventListener('click',()=>{if(validateCurrentStep())setStep(step+1);});
  $('.back-step').addEventListener('click',()=>setStep(step-1));
  form.addEventListener('submit',event=>{
    event.preventDefault();
    if(!validateCurrentStep()||!form.reportValidity())return;
    form.hidden=true;$('.booking-step-label').hidden=true;$('.booking-demo-note').hidden=true;$('#booking-success').hidden=false;$('#booking-success').focus();
  });
  $('#start-another').addEventListener('click',()=>{form.reset();form.hidden=false;$('.booking-step-label').hidden=false;$('.booking-demo-note').hidden=false;$('#booking-success').hidden=true;setStep(1);form.querySelector('select').focus();});
  const date=$('[name="eventDate"]');const tomorrow=new Date();tomorrow.setDate(tomorrow.getDate()+1);date.min=[tomorrow.getFullYear(),String(tomorrow.getMonth()+1).padStart(2,'0'),String(tomorrow.getDate()).padStart(2,'0')].join('-');
  $('.menu-toggle').addEventListener('click',event=>{const open=$('.main-nav').classList.toggle('open');event.currentTarget.setAttribute('aria-expanded',String(open));});
  $$('.main-nav a').forEach(link=>link.addEventListener('click',()=>{$('.main-nav').classList.remove('open');$('.menu-toggle').setAttribute('aria-expanded','false');}));
  $('.ribbon-close').addEventListener('click',()=>$('.demo-ribbon').remove());
  setStep(1);
})();
