(function () {
  'use strict';
  function dimensions(sources) {
    const groups = new Map(), unknown = [];
    for (const source of sources) {
      const match = String(source.dimensions).match(/^\s*(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)\s*$/i);
      if (!match || !source.pieces || Number(source.pieces) <= 0) { unknown.push(String(source.dimensions || '').trim() || '—'); continue; }
      const profile = match[1] + ' × ' + match[2];
      const fraction = source.remaining != null && Number(source.required)>0 ? Number(source.remaining)/Number(source.required) : 1;
      groups.set(profile, (groups.get(profile) || 0) + Number(match[3].replace(',', '.')) * Number(source.pieces) * fraction);
    }
    return [...groups].map(([profile, length]) => profile + ' × ' + String(Number(length.toFixed(6))).replace('.', ',')).concat([...new Set(unknown)]).join('\n');
  }
  function groupSources(sources) {
    const groups = new Map();
    for (const source of sources) {
      const match = String(source.dimensions).match(/^\s*(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)/i);
      const profile = match ? match.slice(1).map(v=>Number(v.replace(',','.'))).join('x') : 'unknown:'+source.id;
      const key = [source.material,profile,source.unit,source.vat_rate].join('|');
      if (!groups.has(key)) groups.set(key,[]);
      groups.get(key).push(source);
    }
    return [...groups.values()];
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = {dimensions,groupSources};
  if (typeof document === 'undefined') return;
  const modal=document.getElementById('order-material-modal');
  if (!modal) return;
  const $=window.jQuery, body=document.getElementById('material-order-rows'), error=document.getElementById('order-material-error');
  const selectLanguage={
    errorLoading:()=> 'Rezultate nije moguće učitati.',
    inputTooLong:()=> 'Unesite manje znakova.',
    inputTooShort:()=> 'Unesite više znakova.',
    loadingMore:()=> 'Učitavanje dodatnih rezultata…',
    maximumSelected:()=> 'Dostignut je najveći dozvoljeni broj odabira.',
    noResults:()=> 'Nema rezultata.',searching:()=> 'Pretraživanje…',
    removeAllItems:()=> 'Poništi odabir',removeItem:()=> 'Ukloni stavku',search:()=> 'Pretraga'
  };
  const materials=new Map(), searches=new Map(), sourceById=new Map();
  let options, next=0, picker=null, inquiry=false, signature=null, restored=false, buildingRows=false;
  function setDeadline(){
    if(!inquiry || buildingRows)return;
    let value='';
    for(const row of body.children)for(const source of sourcesFor(row)){
      const date=source.inquiry_deadline;
      if(date && (!value || date<value))value=date;
    }
    const input=document.getElementById('order-deadline');
    if(input.value===value)return;
    // This is a derived field. Do not emit native change events while building the modal.
    if(input._flatpickr)input._flatpickr.setDate(value,false,'Y-m-d');else input.value=value;
  }
  const number=value=>String(Number(Number(value).toFixed(6))).replace('.',',');
  function icon(name){return window.feather ? window.feather.icons[name].toSvg({width:16,height:16}) : (name==='plus'?'+':'×');}
  function ensureMaterial(source){materials.set(source.material,source);}
  function sourcesFor(row){return row.orderIds.map(id=>sourceById.get(id)).filter(Boolean);}
  function update(row, reset=true){
    const source=materials.get(row.querySelector('[data-material]').value), sources=sourcesFor(row);
    const holder=row.querySelector('[data-allocations]');holder.replaceChildren();
    function hidden(name,value){const input=document.createElement('input');input.type='hidden';input.name='items['+row.orderIndex+']'+name;input.value=value;holder.append(input);}
    sources.forEach((s,a)=>{hidden('[allocations]['+a+'][source_id]',s.id);hidden('[allocations]['+a+'][quantity]',s.remaining);});
    if(!sources.length && source) hidden('[source_id]','catalog_'+btoa(unescape(encodeURIComponent(source.material))));
    if(inquiry){
      const dates=sources.map(s=>s.inquiry_deadline).filter(Boolean).sort();
      if(dates.length)hidden('[deadline]',dates[0]);
    }
    const button=row.querySelector('[data-orders]');
    button.textContent=sources.length ? sources.length+' RN odabrano' : 'Odaberite RN';
    button.title=sources.map(s=>s.work_order+' · '+s.dimensions).join('\n');
    const need=dimensions(sources), actual=sources[0] || source;
    row.querySelector('[data-need]').textContent=sources.length?need:'Za skladište';
    if(reset){
      const quantity=sources.reduce((sum,s)=>sum+Number(s.remaining),0);
      row.querySelector('[data-quantity]').value=quantity?Number(quantity.toFixed(6)):'';
      row.querySelector('[data-dimensions]').value=sources.length && need!=='—' && !need.includes('(') && !need.includes('\n') ? need:'';
      row.querySelector('[data-pieces]').value=sources.length?'1':'';
      row.querySelector('[data-price]').value=inquiry?'':(actual?.price || '');
      row.querySelector('[data-vat]').value=actual?.vat_rate || '0';
    }
    row.querySelector('[data-stock]').textContent=actual?number(options.stock[actual.material] || '0')+'\u00a0'+actual.unit:'—';
    row.querySelector('[data-unit]').textContent=actual?.unit || '';
    row.querySelector('[data-price]').required=!inquiry;
    setDeadline();
  }
  function closePicker(commit=true){
    if(!picker)return;
    const current=picker;picker=null;current.cancel?.();current.panel.remove();current.trigger.setAttribute('aria-expanded','false');
    if(commit){
      const chosen=options.requirements.filter(s=>current.ids.has(s.id));
      const groups=groupSources(chosen);
      const ids=(groups[0] || []).map(s=>s.id);
      const changed=ids.length!==current.row.orderIds.length || ids.some(id=>!current.row.orderIds.includes(id));
      current.row.orderIds=ids;update(current.row,changed);
      let previous=current.row;
      for(const group of groups.slice(1)){
        const added=addRow({materialCode:group[0].material,ids:group.map(s=>s.id)},previous);
        if(added) previous=added;
      }
    }
  }
  function positionPicker(){
    if(!picker)return;
    const rect=picker.trigger.getBoundingClientRect(),panel=picker.panel;
    panel.style.left=Math.max(16,Math.min(rect.left,window.innerWidth-panel.offsetWidth-16))+'px';
    panel.style.top=Math.max(16,Math.min(rect.bottom+4,window.innerHeight-panel.offsetHeight-16))+'px';
  }
  function openPicker(row){
    if(picker?.row===row){closePicker();return;}
    closePicker();
    const trigger=row.querySelector('[data-orders]'),code=row.querySelector('[data-material]').value;
    if(!code)return;
    const panel=document.createElement('div');panel.className='rn-picker';panel.setAttribute('role','dialog');panel.setAttribute('aria-label','Odabir radnih naloga');
    panel.innerHTML='<input class="form-control" data-rn-search placeholder="Pretražite RN" aria-label="Pretražite RN"><div class="rn-picker-list"></div><div class="d-flex align-items-center justify-content-between"><small data-rn-count></small><button type="button" class="btn btn-primary btn-sm" data-rn-done>Završi odabir</button></div>';
    const ids=new Set(row.orderIds),used=new Set([...body.children].filter(r=>r!==row).flatMap(r=>r.orderIds));
    picker={row,trigger,panel,ids};trigger.setAttribute('aria-expanded','true');modal.append(panel);
    const current=picker,list=panel.querySelector('.rn-picker-list');
    let requestVersion=0,controller=null,timer;
    current.cancel=()=>{clearTimeout(timer);controller?.abort();};
    const more=document.createElement('button');more.type='button';more.className='btn btn-outline-primary btn-sm mb-1';more.textContent='Učitaj još';more.hidden=true;list.after(more);
    function count(){panel.querySelector('[data-rn-count]').textContent='Odabrano: '+ids.size;}
    function append(source){
      const label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span'),details=document.createElement('small');
      input.type='checkbox';input.className='form-check-input';input.value=source.id;input.checked=ids.has(source.id);input.disabled=used.has(source.id);
      text.append(document.createTextNode(source.work_order));details.textContent=(source.dimensions || 'Bez dimenzija')+' · '+number(source.remaining)+'\u00a0'+source.unit+(input.disabled?' · već odabrano':'');text.append(details);label.append(input,text);list.append(label);
      input.addEventListener('change',()=>{if(input.checked)ids.add(source.id);else ids.delete(source.id);count();});
    }
    async function load(page=1){
      controller?.abort();controller=new AbortController();const version=++requestVersion;
      more.disabled=true;
      if(page===1)list.textContent='Učitavanje…';
      try{
        const params=new URLSearchParams({material:code,page:String(page),q:panel.querySelector('[data-rn-search]').value.trim()});
        const response=await fetch(modal.dataset.optionsUrl+'?'+params,{headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok)throw new Error('Radne naloge nije moguće učitati.');
        const data=await response.json();if(picker!==current || version!==requestVersion)return;
        if(page===1)list.replaceChildren();
        const known=new Map(options.requirements.map(source=>[source.id,source]));
        data.requirements.forEach(source=>{known.set(source.id,source);sourceById.set(source.id,source);ensureMaterial(source);append(source);});
        options.requirements=[...known.values()];
        more.hidden=!data.nextPage;more.onclick=()=>load(data.nextPage);more.disabled=false;
        if(!list.children.length)list.textContent='Nema radnih naloga za odabrani materijal i pretragu.';
        positionPicker();
      }catch(e){if(e.name!=='AbortError' && picker===current){if(page===1)list.textContent=e.message;else error.textContent=e.message;more.disabled=false;}}
    }
    panel.querySelector('[data-rn-search]').addEventListener('input',()=>{clearTimeout(timer);controller?.abort();requestVersion++;timer=setTimeout(()=>{if(picker===current)load();},200);});
    load();
    panel.querySelector('[data-rn-done]').addEventListener('click',()=>{closePicker();trigger.focus();});
    count();positionPicker();panel.querySelector('input').focus();
  }
  document.addEventListener('pointerdown',event=>{if(picker && !picker.panel.contains(event.target) && !picker.trigger.contains(event.target))closePicker();});
  window.addEventListener('resize',positionPicker);modal.addEventListener('scroll',positionPicker,true);
  modal.addEventListener('keydown',event=>{
    if(event.key==='Escape' && picker){event.preventDefault();event.stopPropagation();const trigger=picker.trigger;closePicker();trigger.focus();}
    else if(event.key==='Escape' && modal.querySelector('.select2-container--open')){event.preventDefault();event.stopPropagation();$(modal).find('select.select2-hidden-accessible').each(function(){$(this).select2('close');});}
  },true);
  modal.addEventListener('hide.bs.modal',()=>closePicker());
  function addRow(old,after){
    const row=document.createElement('tr');row.orderIndex=next++;row.orderIds=[];
    row.innerHTML='<td style="min-width:230px"><select data-material aria-label="Materijal" required></select></td><td><button type="button" class="btn btn-outline-secondary rn-picker-trigger" data-orders aria-haspopup="dialog" aria-expanded="false">Odaberite RN</button><div data-allocations></div></td><td style="min-width:200px;white-space:pre-line" data-need></td><td><input class="form-control" style="min-width:165px" data-dimensions required maxlength="1000" aria-label="Dimenzije za narudžbu"></td><td><input class="form-control" style="width:90px" data-pieces inputmode="decimal" aria-label="Komada"></td><td data-stock></td><td><div class="order-quantity"><input class="form-control" style="width:110px" data-quantity required inputmode="decimal" aria-label="Količina"><span data-unit></span></div></td><td><input class="form-control" style="width:100px" data-price required inputmode="decimal" aria-label="Cijena"></td><td><input class="form-control" style="width:80px" data-vat required inputmode="decimal" aria-label="PDV"></td><td class="order-actions"><div class="d-flex gap-50"><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--success" data-add title="Dodaj red" aria-label="Dodaj red">'+icon('plus')+'</button><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" data-remove title="Obriši red" aria-label="Obriši red">'+icon('trash-2')+'</button></div></td>';
    for(const [field,key] of Object.entries({dimensions:'ordered_dimensions',pieces:'pieces',quantity:'quantity',price:'price',vat:'vat_rate'}))row.querySelector('[data-'+field+']').name='items['+row.orderIndex+']['+key+']';
    if(after)after.after(row);else body.prepend(row);
    const material=row.querySelector('[data-material]');material.append(new Option('',''));
    for(const [code,source] of materials)material.append(new Option(code+' — '+source.name,code));
    $(material).select2({language:selectLanguage,dropdownParent:$(modal),width:'100%',placeholder:'Pretražite materijal',allowClear:true,
      ajax:{delay:120,data:params=>({q:(params.term || '').trim()}),transport:(params,success,failure)=>{
        const q=params.data.q.toLowerCase(),controller=new AbortController();
        if(searches.has(q)){success(searches.get(q));return {abort(){}};}
        fetch(modal.dataset.optionsUrl+'?catalog_only=1&q='+encodeURIComponent(q),{headers:{Accept:'application/json'},signal:controller.signal})
          .then(response=>{if(!response.ok)throw new Error('Pretraga nije dostupna');return response.json();}).then(data=>{searches.set(q,data);success(data);}).catch(e=>{if(e.name!=='AbortError')failure(e);});
        return {abort(){controller.abort();}};
      },processResults:(data,params)=>{
        data.catalog.forEach(ensureMaterial);Object.assign(options.stock,data.stock);
        const q=(params.term || '').toLowerCase(),result=new Map();
        for(const source of [...materials.values(),...data.catalog])if((source.material+' '+source.name).toLowerCase().includes(q))result.set(source.material,{id:source.material,text:source.material+' — '+source.name});
        return {results:[...result.values()]};
      }}});
    $(material).on('change',()=>{closePicker();row.orderIds=[];update(row);});
    material.disabled=!options;row.querySelector('[data-orders]').disabled=!options;
    row.querySelector('[data-orders]').addEventListener('click',()=>openPicker(row));
    row.querySelector('[data-add]').addEventListener('click',()=>{if(options){closePicker();addRow();}});
    row.querySelector('[data-remove]').addEventListener('click',()=>{if(picker?.row===row)closePicker(false);$(material).select2('destroy');row.remove();if(!body.children.length)addRow();setDeadline();});
    if(old){
      const ids=old.ids || (old.allocations || [{source_id:old.source_id}]).map(a=>a.source_id);
      const source=options.requirements.find(s=>ids.includes(s.id)) || [...materials.values()].find(s=>ids.includes(s.id));
      const code=old.materialCode || source?.material;
      if(code){$(material).val(code).trigger('change');row.orderIds=ids;update(row);}
      for(const [field,key] of Object.entries({dimensions:'ordered_dimensions',pieces:'pieces',quantity:'quantity',price:'price',vat:'vat_rate'}))if(old[key]!=null)row.querySelector('[data-'+field+']').value=old[key];
    }
    return row;
  }
  $('#order-supplier').select2({language:selectLanguage,dropdownParent:$(modal),width:'100%',placeholder:'Dobavljač',allowClear:true});
  const loading=document.getElementById('order-material-loading'),retry=document.getElementById('order-material-retry'),pdfButton=document.getElementById('preview-material-pdf');
  let lastTrigger=null,loadController=null,loadVersion=0;
  async function loadModal(event){
    lastTrigger=event.relatedTarget || lastTrigger;
    const version=++loadVersion;loadController?.abort();loadController=new AbortController();
    const controller=loadController,timeout=setTimeout(()=>controller.abort(),20000);
    loading.hidden=false;loading.lastChild.textContent='Učitavanje odabranih radnih naloga i materijala…';retry.hidden=true;body.hidden=true;modal.setAttribute('aria-busy','true');
    const submit=document.getElementById('submit-material-order');submit.disabled=true;pdfButton.disabled=true;error.textContent='';
    inquiry=(lastTrigger?.dataset.documentType || (restored?'order':window.procurementOldType))==='inquiry';
    document.getElementById('order-document-type').value=inquiry?'inquiry':'order';
    document.getElementById('order-material-title').textContent=inquiry?'Upit za materijal':'Naruči materijal';
    document.getElementById('order-date-label').textContent=inquiry?'Datum upita':'Datum narudžbe';
    submit.textContent=inquiry?'Sačuvaj upit':'Sačuvaj narudžbenicu';pdfButton.hidden=!inquiry;
    try{
      await window.procurementSelection?.ready();
      if(controller.signal.aborted || version!==loadVersion)throw new DOMException('Učitavanje je prekinuto','AbortError');
      const selected=window.procurementSelection?.ids() || [];
      const oldItems=restored?[]:(window.procurementOldItems || []);
      const restoreIds=oldItems.flatMap(item=>(item.allocations || [{source_id:item.source_id}]).map(a=>a.source_id)).filter(Boolean);
      if(!options || [...selected,...restoreIds].some(id=>!options.requirements.some(source=>source.id===id))){
        const response=await fetch(modal.dataset.optionsUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('#material-order-form [name="_token"]').value},signal:controller.signal,body:JSON.stringify({selected,source_ids:restoreIds})});
        if(!response.ok)throw new Error('Materijale nije moguće učitati. Ponovo otvorite prozor.');
        if(!response.headers.get('content-type')?.includes('application/json'))throw new Error('Server nije vratio podatke. Osvježite stranicu i prijavite se ponovo ako je sesija istekla.');
        const data=await response.json();if(version!==loadVersion)return;
        if(!Array.isArray(data.requirements) || !Array.isArray(data.catalog))throw new Error('Odgovor servera ne sadrži ispravnu listu materijala. Pokušajte ponovo.');
        options=data;sourceById.clear();options.requirements.forEach(source=>sourceById.set(source.id,source));[...options.requirements,...options.catalog].forEach(ensureMaterial);
      }
      const currentSignature=JSON.stringify([inquiry,selected.slice().sort()]);
      if(currentSignature!==signature){
        buildingRows=true;
        [...body.children].forEach(row=>{$(row.querySelector('[data-material]')).select2('destroy');row.remove();});
        const old=restored?[]:(window.procurementOldItems || []);restored=true;
        if(old.length)old.slice().reverse().forEach(item=>addRow(item));
        else{
          const chosen=options.requirements.filter(s=>selected.includes(s.id));
          if(selected.length && !chosen.length)throw new Error('Odabrani radni nalozi nisu učitani. Pokušajte ponovo.');
          const groups=groupSources(chosen).reverse();
          for(let i=0;i<groups.length;i++){
            if(i && i%8===0)await new Promise(resolve=>requestAnimationFrame(resolve));
            if(controller.signal.aborted || version!==loadVersion)throw new DOMException('Učitavanje je prekinuto','AbortError');
            const group=groups[i];addRow({materialCode:group[0].material,ids:group.map(s=>s.id)});
          }
          if(!body.children.length)addRow();
          if(selected.length && chosen.length!==selected.length)error.textContent='Dio odabranih potreba više nije dostupan za nabavku.';
        }
        signature=currentSignature;buildingRows=false;
      }
      if(inquiry)setDeadline();
      submit.disabled=false;pdfButton.disabled=false;
    }catch(e){if(version===loadVersion){buildingRows=false;error.textContent=e.name==='AbortError'?'Učitavanje nije završeno. Pokušajte ponovo.':e instanceof SyntaxError?'Server je vratio prazan ili neispravan odgovor. Pokušajte ponovo.':e.message;retry.hidden=false;}}
    finally{clearTimeout(timeout);if(version===loadVersion){loading.hidden=true;body.hidden=false;modal.removeAttribute('aria-busy');}}
  }
  modal.addEventListener('show.bs.modal',loadModal);
  retry.addEventListener('click',()=>loadModal({relatedTarget:lastTrigger}));
  modal.addEventListener('hide.bs.modal',()=>{loadVersion++;loadController?.abort();modal.removeAttribute('aria-busy');});
  document.getElementById('material-order-form').addEventListener('submit',async event=>{
    closePicker();
    const form=event.target,submit=document.getElementById('submit-material-order');
    if(!body.children.length || !form.checkValidity()){event.preventDefault();form.reportValidity();error.textContent='Dodajte materijal i popunite obavezna polja.';return;}
    document.getElementById('order-open-pdf').value=event.submitter?.hasAttribute('data-open-pdf')?'1':'0';
    submit.disabled=true;pdfButton.disabled=true;loading.hidden=false;loading.lastChild.textContent=document.getElementById('order-open-pdf').value==='1'?'Priprema PDF-a…':'Spremanje dokumenta…';
    // Large filtered selections can exceed PHP's form-input limit. JSON preserves every RN allocation.
    if(form.elements.length<900)return;
    event.preventDefault();
    try{
      const payload={};
      for(const [name,value] of new FormData(form)){
        const keys=name.match(/[^\[\]]+/g);let node=payload;
        keys.forEach((key,index)=>{if(index===keys.length-1)node[key]=value;else node=node[key]??=(/^\d+$/.test(keys[index+1])?[]:{});});
      }
      payload.items=payload.items.filter(Boolean);
      const response=await fetch(form.action,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':payload._token},body:JSON.stringify(payload)});
      if(!response.ok){const data=await response.json();throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Dokument nije sačuvan. Pokušajte ponovo.');}
      window.location.assign(response.url);
    }catch(e){error.textContent=e.message;submit.disabled=false;pdfButton.disabled=false;loading.hidden=true;}
  });
  const inquiryButton=document.querySelector('#material-page-actions [data-document-type="inquiry"]');
  if(inquiryButton)inquiryButton.addEventListener('click',event=>{
    // Also handles an already-open materials page with the previous navigation markup.
    event.preventDefault();event.stopImmediatePropagation();
    window.bootstrap.Modal.getOrCreateInstance(modal).show(inquiryButton);
  },true);
  const params=new URLSearchParams(window.location.search);
  if(params.get('open_inquiry')==='1' && inquiryButton && !inquiryButton.disabled){
    const open=()=>window.bootstrap.Modal.getOrCreateInstance(modal).show(inquiryButton);
    params.delete('open_inquiry');window.history.replaceState(null,'',window.location.pathname+(params.size?'?'+params:'')+window.location.hash);
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',open,{once:true});else open();
  }else if((window.procurementOldItems || []).length)window.bootstrap.Modal.getOrCreateInstance(modal).show();
}());
