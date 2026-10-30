(function () {
  'use strict';
  function fitsA4(s) {
    return Number.isInteger(s.columns) && Number.isInteger(s.rows) && s.columns>0 && s.rows>0 && s.columns*s.rows<=200 && s.width>=28 && s.height>=30 && s.gap_x>=0 && s.gap_y>=0 && s.margin_x+s.offset_x>=0 && s.margin_y+s.offset_y>=0
      && s.margin_x+s.offset_x+s.columns*s.width+(s.columns-1)*s.gap_x<=210 && s.margin_y+s.offset_y+s.rows*s.height+(s.rows-1)*s.gap_y<=297;
  }
  function labelPages(labels, capacity) {
    if (!Number.isInteger(capacity) || capacity<1) throw new Error('Neispravan broj naljepnica po stranici.');
    const pages=[];
    for(let i=0;i<labels.length;i+=capacity)pages.push(labels.slice(i,i+capacity));
    return pages;
  }
  if(typeof module!=='undefined'&&module.exports)module.exports={fitsA4,labelPages};
  if(typeof document==='undefined')return;
  const labels=window.procurementLabels || [],s=window.procurementLabelLayout;
  const root=document.getElementById('label-pages'),error=document.getElementById('label-error'),print=document.getElementById('print-labels');
  print.disabled=true;
  if(!s || !fitsA4(s)){error.textContent='Raspored naljepnica ne odgovara A4 papiru.';return;}
  if(!labels.length){error.textContent='Nema stavki za štampu naljepnica.';return;}
  const images=[];
  for(const page of labelPages(labels,s.columns*s.rows)){
    const sheet=document.createElement('section');sheet.className='sheet';
    sheet.style.paddingLeft=(s.margin_x+s.offset_x)+'mm';sheet.style.paddingTop=(s.margin_y+s.offset_y)+'mm';
    sheet.style.gridTemplateColumns=`repeat(${s.columns},${s.width}mm)`;sheet.style.gridTemplateRows=`repeat(${s.rows},${s.height}mm)`;sheet.style.columnGap=s.gap_x+'mm';sheet.style.rowGap=s.gap_y+'mm';
    for(let i=0;i<s.columns*s.rows;i++){
      const slot=document.createElement('div');slot.className='label-slot';const label=page[i];
      if(!label){slot.classList.add('empty');sheet.append(slot);continue;}
      function text(className,value){const item=document.createElement('div');item.className=className;item.textContent=value || '';slot.append(item);}
      text('label-title',label.title);text('label-dimensions',label.dimensions);
      const qr=document.createElement('img');qr.alt='QR '+label.code+' · '+label.order;
      qr.src='https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=20&format=svg&data='+encodeURIComponent(label.url);slot.append(qr);images.push(qr);
      text('label-code',label.code);text('label-order','Narudžbenica: '+label.order);text('label-details',label.details);sheet.append(slot);
    }
    root.append(sheet);
  }
  async function ready(){try{await Promise.all(images.map(img=>img.decode()));error.textContent='';print.disabled=false;return true;}catch(e){error.textContent='QR kod nije učitan. Osvježite stranicu prije štampe.';print.disabled=true;return false;}}
  print.addEventListener('click',async()=>{if(await ready())window.print();});
  ready();
}());
