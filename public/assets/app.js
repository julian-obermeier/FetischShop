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


function enhanceCameraInputs(){
  document.querySelectorAll('input[type="file"][capture][accept*="image"]').forEach(input=>{
    if(input.dataset.cameraEnhanced==='1')return;
    input.dataset.cameraEnhanced='1';

    const wrap=document.createElement('div');
    wrap.className='camera-tools';

    const direct=document.createElement('button');
    direct.type='button';
    direct.className='btn ghost camera-open';
    direct.textContent='Kamera direkt öffnen';

    const panel=document.createElement('div');
    panel.className='camera-panel';
    panel.hidden=true;

    const video=document.createElement('video');
    video.playsInline=true;
    video.muted=true;
    video.autoplay=true;

    const actions=document.createElement('div');
    actions.className='row-actions';

    const shoot=document.createElement('button');
    shoot.type='button';
    shoot.className='btn primary';
    shoot.textContent='Foto aufnehmen';

    const close=document.createElement('button');
    close.type='button';
    close.className='btn ghost';
    close.textContent='Kamera schließen';

    const preview=document.createElement('img');
    preview.className='camera-preview';
    preview.hidden=true;
    preview.alt='Vorschau des aufgenommenen Fotos';

    actions.append(shoot,close);
    panel.append(video,actions);
    wrap.append(direct,panel,preview);
    input.insertAdjacentElement('afterend',wrap);

    const form=input.closest('form');
    let source=form?.querySelector('input[name="capture_source"]');
    if(form&&!source){
      source=document.createElement('input');
      source.type='hidden';
      source.name='capture_source';
      source.value='file_picker';
      form.appendChild(source);
    }

    let stream=null;
    const stop=()=>{
      if(stream){stream.getTracks().forEach(track=>track.stop());stream=null;}
      panel.hidden=true;
    };

    direct.addEventListener('click',async()=>{
      if(!navigator.mediaDevices?.getUserMedia){
        input.click();
        return;
      }
      try{
        stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
        video.srcObject=stream;
        panel.hidden=false;
      }catch(_){
        input.click();
      }
    });

    shoot.addEventListener('click',()=>{
      if(!video.videoWidth||!video.videoHeight)return;
      const canvas=document.createElement('canvas');
      canvas.width=video.videoWidth;
      canvas.height=video.videoHeight;
      const ctx=canvas.getContext('2d');
      if(!ctx)return;
      ctx.drawImage(video,0,0,canvas.width,canvas.height);
      canvas.toBlob(blob=>{
        if(!blob)return;
        const file=new File([blob],'kamera-'+Date.now()+'.jpg',{type:'image/jpeg',lastModified:Date.now()});
        const dt=new DataTransfer();
        dt.items.add(file);
        input.files=dt.files;
        input.dispatchEvent(new Event('change',{bubbles:true}));
        if(source)source.value='live_camera';
        preview.src=URL.createObjectURL(blob);
        preview.hidden=false;
        stop();
      },'image/jpeg',0.95);
    });

    close.addEventListener('click',stop);
    input.addEventListener('change',()=>{
      if(source&&source.value!=='live_camera')source.value='file_picker';
      const file=input.files?.[0];
      if(file&&file.type.startsWith('image/')){
        if(preview.src)URL.revokeObjectURL(preview.src);
        preview.src=URL.createObjectURL(file);
        preview.hidden=false;
      }
    });
  });
}
document.addEventListener('DOMContentLoaded',enhanceCameraInputs);
