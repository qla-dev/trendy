(function () {
  'use strict';
  function dimensions(sources) {
    const groups = new Map(), unknown = [];
    for (const source of sources) {
      const match = String(source.dimensions).match(/^\s*(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)\s*[x×*]\s*(\d+(?:[.,]\d+)?)\s*$/i);
      if (!match || !source.pieces || Number(source.pieces) <= 0) { unknown.push(source.work_order + ': ' + (source.dimensions || 'Bez dimenzija') + ' (broj komada nije potvrđen)'); continue; }
      const profile = match[1] + ' × ' + match[2];
      const fraction = source.remaining != null && Number(source.required)>0 ? Number(source.remaining)/Number(source.required) : 1;
      groups.set(profile, (groups.get(profile) || 0) + Number(match[3].replace(',', '.')) * Number(source.pieces) * fraction);
    }
    return [...groups].map(([profile, length]) => profile + ' × ' + String(Number(length.toFixed(6))).replace('.', ',')).concat(unknown).join('\n');
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
  const materials=new Map(), searches=new Map();
  let options, next=0, picker=null;
  const number=value=>String(Number(Number(value).toFixed(6))).replace('.',',');
  function icon(name){return window.feather ? window.feather.icons[name].toSvg({width:16,height:16}) : (name==='plus'?'+':'×');}
  function ensureMaterial(source){materials.set(source.material,source);}
  function sourcesFor(row){return options.requirements.filter(s=>row.orderIds.includes(s.id));}
  function update(row, reset=true){
    const source=materials.get(row.querySelector('[data-material]').value), sources=sourcesFor(row);
    const holder=row.querySelector('[data-allocations]');holder.replaceChildren();
    function hidden(name,value){const input=document.createElement('input');input.type='hidden';input.name='items['+row.orderIndex+']'+name;input.value=value;holder.append(input);}
    sources.forEach((s,a)=>{hidden('[allocations]['+a+'][source_id]',s.id);hidden('[allocations]['+a+'][quantity]',s.remaining);});
    if(!sources.length && source) hidden('[source_id]','catalog_'+btoa(unescape(encodeURIComponent(source.material))));
    const button=row.querySelector('[data-orders]');
    button.textContent=sources.length ? sources.length+' RN odabrano' : 'Odaberite RN';
    button.title=sources.map(s=>s.work_order+' · '+s.dimensions).join('\n');
    row.querySelector('[data-need]').textContent=sources.length?dimensions(sources):'Za skladište';
    const need=dimensions(sources), actual=sources[0] || source;
    if(reset){
      const quantity=sources.reduce((sum,s)=>sum+Number(s.remaining),0);
      row.querySelector('[data-quantity]').value=quantity?Number(quantity.toFixed(6)):'';
      row.querySelector('[data-dimensions]').value=sources.length && !need.includes('(') && !need.includes('\n') ? need:'';
      row.querySelector('[data-pieces]').value=sources.length?'1':'';
      row.querySelector('[data-price]').value=actual?.price || '';
      row.querySelector('[data-vat]').value=actual?.vat_rate || '0';
    }
    row.querySelector('[data-stock]').textContent=actual?number(options.stock[actual.material] || '0')+'\u00a0'+actual.unit:'—';
    row.querySelector('[data-unit]').textContent=actual?.unit || '';
  }
  function closePicker(commit=true){
    if(!picker)return;
    const current=picker;picker=null;current.panel.remove();current.trigger.setAttribute('aria-expanded','false');
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
    const list=panel.querySelector('.rn-picker-list'),rows=options.requirements.filter(s=>s.material===code);
    function count(){panel.querySelector('[data-rn-count]').textContent='Odabrano: '+ids.size;}
    for(const source of rows){
      const label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span'),details=document.createElement('small');
      input.type='checkbox';input.className='form-check-input';input.value=source.id;input.checked=ids.has(source.id);input.disabled=used.has(source.id);
      text.append(document.createTextNode(source.work_order));details.textContent=(source.dimensions || 'Bez dimenzija')+' · '+number(source.remaining)+'\u00a0'+source.unit+(input.disabled?' · već odabrano':'');text.append(details);label.append(input,text);list.append(label);
      input.addEventListener('change',()=>{if(input.checked && ids.size+used.size>=100){input.checked=false;error.textContent='Najviše 100 potreba može biti odabrano.';return;}if(input.checked)ids.add(source.id);else ids.delete(source.id);count();});
    }
    if(!rows.length)list.textContent='Nema preostalih potreba za ovaj materijal. Možete naručiti za skladište.';
    panel.querySelector('[data-rn-search]').addEventListener('input',event=>{const q=event.target.value.toLowerCase();list.querySelectorAll('label').forEach(label=>{label.style.display=label.textContent.toLowerCase().includes(q)?'':'none';});});
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
    if(body.children.length>=100){error.textContent='Najviše 100 redova je dozvoljeno.';return null;}
    const row=document.createElement('tr');row.orderIndex=next++;row.orderIds=[];
    row.innerHTML='<td style="min-width:230px"><select data-material aria-label="Materijal" required></select></td><td><button type="button" class="btn btn-outline-secondary rn-picker-trigger" data-orders aria-haspopup="dialog" aria-expanded="false">Odaberite RN</button><div data-allocations></div></td><td style="min-width:200px;white-space:pre-line" data-need></td><td><input class="form-control" style="min-width:165px" data-dimensions required maxlength="1000" aria-label="Dimenzije za narudžbu"></td><td><input class="form-control" style="width:90px" data-pieces inputmode="decimal" aria-label="Komada"></td><td data-stock></td><td><div class="order-quantity"><input class="form-control" style="width:110px" data-quantity required inputmode="decimal" aria-label="Količina"><span data-unit></span></div></td><td><input class="form-control" style="width:100px" data-price required inputmode="decimal" aria-label="Cijena"></td><td><input class="form-control" style="width:80px" data-vat required inputmode="decimal" aria-label="PDV"></td><td class="order-actions"><div class="d-flex gap-50"><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--success" data-add title="Dodaj red" aria-label="Dodaj red">'+icon('plus')+'</button><button type="button" class="btn btn-sm app-table-action-btn app-table-action-btn--danger" data-remove title="Obriši red" aria-label="Obriši red">'+icon('trash-2')+'</button></div></td>';
    for(const [field,key] of Object.entries({dimensions:'ordered_dimensions',pieces:'pieces',quantity:'quantity',price:'price',vat:'vat_rate'}))row.querySelector('[data-'+field+']').name='items['+row.orderIndex+']['+key+']';
    if(after)after.after(row);else body.prepend(row);
    const material=row.querySelector('[data-material]');material.append(new Option('',''));
    for(const [code,source] of materials)material.append(new Option(code+' — '+source.name,code));
    $(material).select2({dropdownParent:$(modal),width:'100%',placeholder:'Pretražite materijal',allowClear:true,
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
    row.querySelector('[data-remove]').addEventListener('click',()=>{if(picker?.row===row)closePicker(false);$(material).select2('destroy');row.remove();if(!body.children.length)addRow();});
    if(old){
      const ids=old.ids || (old.allocations || [{source_id:old.source_id}]).map(a=>a.source_id);
      const source=options.requirements.find(s=>ids.includes(s.id)) || [...materials.values()].find(s=>ids.includes(s.id));
      const code=old.materialCode || source?.material;
      if(code){$(material).val(code).trigger('change');row.orderIds=ids;update(row);}
      for(const [field,key] of Object.entries({dimensions:'ordered_dimensions',pieces:'pieces',quantity:'quantity',price:'price',vat:'vat_rate'}))if(old[key]!=null)row.querySelector('[data-'+field+']').value=old[key];
    }
    return row;
  }
  $('#order-supplier').select2({dropdownParent:$(modal),width:'100%',placeholder:'Dobavljač',allowClear:true});
  modal.addEventListener('show.bs.modal',async()=>{
    if(options)return;
    if(!body.children.length)addRow();
    document.getElementById('submit-material-order').disabled=true;
    try{
      const response=await fetch(modal.dataset.optionsUrl,{headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Materijale nije moguće učitati. Ponovo otvorite prozor.');
      options=await response.json();[...options.requirements,...options.catalog].forEach(ensureMaterial);searches.set('',options);
      const old=window.procurementOldItems || [];
      if(old.length){
        [...body.children].forEach(row=>{$(row.querySelector('[data-material]')).select2('destroy');row.remove();});
        old.slice().reverse().forEach(item=>addRow(item));
      }else{
        for(const row of body.children){
          const material=row.querySelector('[data-material]');
          for(const [code,source] of materials)material.append(new Option(code+' — '+source.name,code));
          material.disabled=false;row.querySelector('[data-orders]').disabled=false;
        }
      }
      document.getElementById('submit-material-order').disabled=false;
    }catch(e){options=null;error.textContent=e.message;}
  });
  document.getElementById('material-order-form').addEventListener('submit',event=>{
    closePicker();
    if(!body.children.length || !event.target.checkValidity()){event.preventDefault();event.target.reportValidity();error.textContent='Dodajte materijal i popunite obavezna polja.';return;}
    document.getElementById('submit-material-order').disabled=true;
  });
  if((window.procurementOldItems || []).length)window.bootstrap.Modal.getOrCreateInstance(modal).show();
}());
