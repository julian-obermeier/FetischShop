if('serviceWorker'in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js').catch(()=>{}));}
document.addEventListener('click',e=>{const b=e.target.closest('[data-confirm]');if(b&&!confirm(b.dataset.confirm))e.preventDefault();});
document.addEventListener('click',e=>{
  const menu=e.target.closest('[data-admin-menu]');
  if(menu){
    const sidebar=document.getElementById('admin-sidebar');
    if(sidebar)sidebar.classList.toggle('open');
    let backdrop=document.querySelector('.admin-sidebar-backdrop');
    if(!backdrop){backdrop=document.createElement('div');backdrop.className='admin-sidebar-backdrop';document.body.appendChild(backdrop);}
    backdrop.classList.toggle('open',sidebar&&sidebar.classList.contains('open'));
  }
  if(e.target.classList.contains('admin-sidebar-backdrop')){
    document.getElementById('admin-sidebar')?.classList.remove('open');
    e.target.classList.remove('open');
  }

  const copy=e.target.closest('[data-copy-target]');
  if(copy){
    const target=document.querySelector(copy.dataset.copyTarget);
    if(target){
      navigator.clipboard?.writeText(target.value||target.textContent||'').then(()=>{
        const old=copy.textContent;copy.textContent='Kopiert';setTimeout(()=>copy.textContent=old,1200);
      }).catch(()=>{});
    }
  }

  const addRow=e.target.closest('[data-add-row]');
  if(addRow){
    const template=document.getElementById(addRow.dataset.addRow);
    const target=document.querySelector(addRow.dataset.target||'');
    if(template&&target)target.appendChild(template.content.cloneNode(true));
  }

  const remove=e.target.closest('[data-remove-row]');
  if(remove){
    const row=remove.closest('[data-repeat-row]');
    if(row)row.remove();
  }
});

document.addEventListener('click',e=>{
  const menu=e.target.closest('[data-seller-menu]');
  if(menu){
    const sidebar=document.getElementById('seller-sidebar');
    if(sidebar)sidebar.classList.toggle('open');
    let backdrop=document.querySelector('.seller-sidebar-backdrop');
    if(!backdrop){backdrop=document.createElement('div');backdrop.className='seller-sidebar-backdrop';document.body.appendChild(backdrop);}
    backdrop.classList.toggle('open',sidebar&&sidebar.classList.contains('open'));
  }
  if(e.target.classList.contains('seller-sidebar-backdrop')){
    document.getElementById('seller-sidebar')?.classList.remove('open');
    e.target.classList.remove('open');
  }
});
