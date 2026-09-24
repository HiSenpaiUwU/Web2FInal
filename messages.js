document.addEventListener('DOMContentLoaded',()=>{
  if(!location.pathname.endsWith('/messages.php'))return;
  let last=Date.now();
  setInterval(()=>{
    const active=document.activeElement;
    if(active&&['TEXTAREA','INPUT'].includes(active.tagName))return;
    if(Date.now()-last<12000)return;
    last=Date.now();
    location.reload();
  },12000);
});
