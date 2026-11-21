document.querySelectorAll('[data-signature]').forEach(box => {
  if (box.dataset.ready) return;
  box.dataset.ready = '1';
  const canvas=box.querySelector('canvas'), ctx=canvas.getContext('2d');
  const data=box.querySelector('[name=signature_data]'), file=box.querySelector('[name=signature_file]');
  const consent=box.querySelector('[name=signature_consent]'), status=box.querySelector('[data-signature-status]');
  const preview=box.querySelector('[data-upload-preview]');
  let drawing=false,dirty=false,url=null;
  const update=()=>{consent.required=(!data.disabled&&!!data.value)||(!file.disabled&&!!file.files.length);status.textContent=consent.required?'Gambar siap. Konfirmasikan pemakaiannya untuk versi ini.':'Belum ada gambar. Persetujuan tanpa gambar tetap dapat dilakukan.';};
  const point=e=>{const r=canvas.getBoundingClientRect();return[(e.clientX-r.left)*canvas.width/r.width,(e.clientY-r.top)*canvas.height/r.height];};
  if(ctx){
  ctx.lineWidth=3;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#182e70';
  canvas.addEventListener('pointerdown',e=>{if(e.button!==0)return;e.preventDefault();drawing=true;canvas.setPointerCapture(e.pointerId);const[x,y]=point(e);ctx.beginPath();ctx.moveTo(x,y);});
  canvas.addEventListener('pointermove',e=>{if(!drawing)return;const[x,y]=point(e);ctx.lineTo(x,y);ctx.stroke();dirty=true;});
  const finish=()=>{if(!drawing)return;drawing=false;if(dirty)data.value=canvas.toDataURL('image/png');update();};
  canvas.addEventListener('pointerup',finish);canvas.addEventListener('pointercancel',finish);
  box.querySelector('[data-clear]').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);dirty=false;data.value='';consent.checked=false;update();});
  }
  box.querySelectorAll('[name=signature_method]').forEach(r=>r.addEventListener('change',()=>{const upload=r.value==='upload';box.querySelector('[data-draw]').hidden=upload;box.querySelector('[data-upload]').hidden=!upload;data.disabled=upload;file.disabled=!upload;consent.checked=false;update();}));
  file.addEventListener('change',()=>{if(url)URL.revokeObjectURL(url);url=null;preview.hidden=true;file.setCustomValidity('');consent.checked=false;const f=file.files[0];if(f){if(f.size>524288||!f.name.toLowerCase().endsWith('.png'))file.setCustomValidity('Pilih PNG maksimal 512 KB.');else{url=URL.createObjectURL(f);preview.src=url;preview.hidden=false;}}update();});
  if(!ctx){box.querySelector('[value=upload]').click();status.textContent='Gambar langsung tidak tersedia. Gunakan unggah PNG.';}
});
