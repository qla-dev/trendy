(function () {
  'use strict';
  function fitsA4(s) {
    return Number.isInteger(s.columns) && Number.isInteger(s.rows) && s.columns>0 && s.rows>0 && s.columns*s.rows<=200 && s.width>=28 && s.height>=30 && s.gap_x>=0 && s.gap_y>=0 && s.margin_x+s.offset_x>=0 && s.margin_y+s.offset_y>=0
      && s.margin_x+s.offset_x+s.columns*s.width+(s.columns-1)*s.gap_x<=210 && s.margin_y+s.offset_y+s.rows*s.height+(s.rows-1)*s.gap_y<=297;
  }
  function availableSlots(s, start, occupied, count) {
    const slots=[];
    for(let i=Math.max(0,start);i<s.columns*s.rows&&slots.length<count;i++)if(!occupied.includes(i))slots.push(i);
    return slots;
  }
  if(typeof module!=='undefined'&&module.exports)module.exports={fitsA4,availableSlots};
  if(typeof document==='undefined')return;
  const labels = window.procurementLabels;
  const sheet = document.getElementById('label-sheet');
  const error = document.getElementById('label-error');
  const fields = [...document.querySelectorAll('[data-setting]')];
  const storageKey = 'enalog-procurement-a4-v1';
  let occupied = [], selected = [], mode = false;
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    occupied = Array.isArray(saved.occupied) ? saved.occupied : [];
    fields.forEach(input => { if (saved.settings && Number.isFinite(saved.settings[input.dataset.setting])) input.value = saved.settings[input.dataset.setting]; });
  } catch (_) { /* A new browser starts with a blank sheet. */ }
  function settings() { return Object.fromEntries(fields.map(input => [input.dataset.setting, Number(input.value)])); }
  function persist() { try { localStorage.setItem(storageKey, JSON.stringify({occupied:occupied, settings:settings()})); } catch (_) { error.textContent='Postavke nije moguće sačuvati u pregledniku.'; } }
  function render() {
    const s = settings();
    error.textContent = '';
    sheet.replaceChildren();
    const valid = fitsA4(s);
    document.getElementById('print-labels').disabled = !valid;
    if (!valid) { error.textContent='Raspored izlazi iz A4 papira ili su mjesta premala za čitljiv QR. Provjerite milimetre, redove i kolone.'; return; }
    sheet.style.paddingLeft=(s.margin_x+s.offset_x)+'mm'; sheet.style.paddingTop=(s.margin_y+s.offset_y)+'mm';
    sheet.style.gridTemplateColumns=`repeat(${s.columns}, ${s.width}mm)`; sheet.style.gridTemplateRows=`repeat(${s.rows}, ${s.height}mm)`; sheet.style.columnGap=s.gap_x+'mm'; sheet.style.rowGap=s.gap_y+'mm';
    const choices=[...document.querySelectorAll('.label-choice:checked')].map(input=>Number(input.value));
    let position=0;
    selected=selected.filter(slot=>slot>=0 && slot<s.rows*s.columns && !occupied.includes(slot));
    for (let i=0; i<s.rows*s.columns; i++) {
      const slot=document.createElement('div'); slot.dataset.slot=String(i); slot.className='label-slot empty'; slot.tabIndex=0; slot.setAttribute('role','button'); slot.setAttribute('aria-label','Mjesto '+(i+1));
      const number=document.createElement('span'); number.className='slot-number'; number.textContent=String(i+1); slot.append(number);
      if (occupied.includes(i)) { slot.classList.add('occupied'); slot.append(document.createTextNode('Zauzeto')); }
      else if (selected.includes(i) && position<choices.length) {
        const label=labels[choices[position++]]; slot.classList.remove('empty');
        const title=document.createElement('span'); title.className='label-title'; title.textContent=label.title;
        const qr=document.createElement('img'); qr.alt='QR '+label.code; qr.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=20&format=svg&data='+encodeURIComponent(label.url);
        qr.addEventListener('error',()=>{ if(!qr.isConnected)return; error.textContent='QR servis nije dostupan. Štampanje je blokirano dok se QR ne učita.'; document.getElementById('print-labels').disabled=true; });
        const code=document.createElement('small'); code.textContent=label.code; slot.append(title,qr,code);
      }
      function toggle() { if(mode) { occupied=occupied.includes(i)?occupied.filter(n=>n!==i):[...occupied,i]; selected=selected.filter(n=>n!==i); persist(); } else if(!occupied.includes(i)) selected=selected.includes(i)?selected.filter(n=>n!==i):[...selected,i]; render(); }
      slot.addEventListener('click',toggle); slot.addEventListener('keydown',event=>{if(event.key===' ' || event.key==='Enter'){event.preventDefault();toggle();}}); sheet.append(slot);
    }
  }
  fields.forEach(input=>input.addEventListener('change',()=>{selected=[];persist();render();}));
  document.querySelectorAll('.label-choice').forEach(input=>input.addEventListener('change',render));
  document.getElementById('fill-labels').addEventListener('click',()=>{const s=settings(),start=Number(document.getElementById('start-slot').value)-1; const count=document.querySelectorAll('.label-choice:checked').length; selected=availableSlots(s,start,occupied,count);render();if(selected.length<count)error.textContent='Nema dovoljno slobodnih mjesta za sve odabrane etikete.';});
  document.getElementById('toggle-occupied').addEventListener('click',function(){mode=!mode;this.textContent=mode?'Način: označavanje zauzetih mjesta':'Način: odabir za štampu';});
  document.getElementById('new-sheet').addEventListener('click',()=>{if(window.confirm('Očistiti evidenciju mjesta za novi A4 papir?')){occupied=[];selected=[];persist();render();}});
  document.getElementById('print-labels').addEventListener('click',async()=>{
    const images=[...sheet.querySelectorAll('img')];
    if(!images.length){error.textContent='Odaberite mjesta i etikete za štampu.';return;}
    try { await Promise.all(images.map(img=>img.decode())); } catch(_){error.textContent='Sačekajte učitavanje svih QR kodova.';return;}
    window.print();
    if(window.confirm('Jesu li odabrane etikete uspješno odštampane?')){occupied=[...new Set([...occupied,...images.map(img=>Number(img.parentElement.dataset.slot))])];selected=[];persist();render();}
  });
  render();
}());
