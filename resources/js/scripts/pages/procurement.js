(function () {
  'use strict';
  // BigInt fixed-point preview matches BCMath: six decimal inputs, half-up cents per line.
  const scale = 1000000n;
  function decimal(value) {
    const text = String(value).trim().replace(',', '.');
    if (!/^\d{1,12}(?:\.\d{1,6})?$/.test(text)) throw new Error('Neispravan broj');
    const parts = text.split('.');
    return BigInt(parts[0]) * scale + BigInt((parts[1] || '').padEnd(6, '0'));
  }
  function format(value, digits) {
    const text = value.toString().padStart(digits + 1, '0');
    return text.slice(0, -digits) + '.' + text.slice(-digits);
  }
  function calculate(quantity, price, rate, discountRate = '0') {
    const q = decimal(quantity), p = decimal(price), r = decimal(rate);
    const d = decimal(discountRate);
    if (d > 100n * scale) throw new Error('Neispravan rabat');
    if (q <= 0n || r > 100n * scale) throw new Error('Neispravna kilaža ili PDV');
    const base = (q * p + 5000000000n) / 10000000000n;
    const discount = (base * d + 50000000n) / 100000000n;
    const net = base - discount;
    const vat = (net * r + 50000000n) / 100000000n;
    return { quantity: q, net: net, vat: vat, gross: net + vat, discount: discount };
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { calculate, format };
  if (typeof document === 'undefined') return;
  // Browser validation follows the browser language unless explicitly translated.
  document.addEventListener('invalid',event=>{
    const field=event.target;
    if(!field.closest('#material-order-form,#procurement-form') || typeof field.setCustomValidity!=='function')return;
    field.setCustomValidity('');
    const state=field.validity;
    if(state.valid)return;
    const message=state.valueMissing?'Popunite ovo polje.':
      state.typeMismatch?'Unesite vrijednost u ispravnom obliku.':
      state.tooLong?'Uneseni tekst je predugačak.':
      state.tooShort?'Uneseni tekst je prekratak.':
      state.rangeUnderflow?'Unesite veću vrijednost.':
      state.rangeOverflow?'Unesite manju vrijednost.':
      state.badInput || state.stepMismatch?'Unesite ispravan broj.':'Provjerite unesenu vrijednost.';
    field.setCustomValidity(message);
  },true);
  const clearValidation=event=>{
    const field=event.target;
    if(field.closest('#material-order-form,#procurement-form') && typeof field.setCustomValidity==='function')field.setCustomValidity('');
  };
  document.addEventListener('input',clearValidation,true);
  document.addEventListener('change',clearValidation,true);
  let selectionQueue = Promise.resolve();
  const selection = document.getElementById('requirement-selection');
  let selectedIds = selection ? JSON.parse(selection.dataset.selected || '[]') : [];
  const filteredIds = selection ? JSON.parse(selection.dataset.filteredIds || '[]') : [];
  let filteredTotal=Number(selection?.dataset.filteredTotal ?? filteredIds.length);
  let filteredComplete=filteredIds.length===filteredTotal;
  let filteredSelected=Number(selection?.dataset.filteredSelected ?? selectedIds.filter(id=>filteredIds.includes(id)).length);
  let confirmedFilteredSelected=filteredSelected,bulkPending=false,displayCount=null;
  const selectAll = document.getElementById('select-filtered-requirements');
  function updateSelectAll(){
    if(!selectAll)return;
    if(filteredComplete){const ids=new Set(selectedIds);filteredSelected=filteredIds.filter(id=>ids.has(id)).length;}
    selectAll.checked=filteredTotal>0 && filteredSelected===filteredTotal;
    selectAll.indeterminate=filteredSelected>0 && filteredSelected<filteredTotal;
  }
  let confirmedIds=selectedIds.slice(), selectionVersion=0;
  function renderSelection(){
    const count=displayCount ?? selectedIds.length,ids=new Set(selectedIds);
    document.getElementById('selection-count').textContent='Odabrano: '+count;
    document.getElementById('clear-selection').disabled=!count || bulkPending;
    document.querySelectorAll('.requirement-select').forEach(input=>{input.checked=ids.has(input.value);input.disabled=bulkPending;});
    updateSelectAll();if(selectAll)selectAll.disabled=bulkPending || !filteredTotal;
  }
  window.procurementSelection = {ready:()=>selectionQueue, ids:()=>selectedIds};
  function saveSelection(change) {
    const version=++selectionVersion,previous=new Set(selectedIds),ids=new Set(change.clear?[]:selectedIds);
    const known=new Set(filteredIds),beforeFiltered=filteredSelected;
    (change.add || []).forEach(id=>ids.add(id));
    (change.remove || []).forEach(id=>ids.delete(id));
    if(change.filtered)filteredIds.forEach(id=>{if(change.filtered==='add')ids.add(id);else ids.delete(id);});
    if(change.clear)filteredSelected=0;
    else if(change.filtered)filteredSelected=change.filtered==='add'?filteredTotal:0;
    else {
      (change.add || []).forEach(id=>{if(known.has(id) && !previous.has(id))filteredSelected++;});
      (change.remove || []).forEach(id=>{if(known.has(id) && previous.has(id))filteredSelected--;});
    }
    if(change.filtered && !filteredComplete){displayCount=selectedIds.length-beforeFiltered+filteredSelected;bulkPending=true;}
    selectedIds=[...ids];renderSelection();
    const payload=change.filtered?{...change,filter_token:selection.dataset.filterToken}:change;
    const request = selectionQueue.then(async () => {
      const response = await fetch(selection.dataset.url, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':selection.dataset.csrf},body:JSON.stringify(payload)});
      if (!response.ok) throw new Error('Odabir nije sačuvan. Osvježite stranicu i pokušajte ponovo.');
      const data = await response.json(),previousConfirmed=new Set(confirmedIds);confirmedIds=data.selected;
      if(data.filtered_ids){filteredIds.splice(0,filteredIds.length,...data.filtered_ids);filteredComplete=true;filteredTotal=filteredIds.length;}
      const confirmedSet=new Set(confirmedIds);
      confirmedFilteredSelected=change.clear?0:(filteredComplete?filteredIds.filter(id=>confirmedSet.has(id)).length:confirmedFilteredSelected+filteredIds.filter(id=>confirmedSet.has(id) && !previousConfirmed.has(id)).length-filteredIds.filter(id=>previousConfirmed.has(id) && !confirmedSet.has(id)).length);
      // Older responses must not overwrite a newer click already shown in the table.
      if(version===selectionVersion){selectedIds=confirmedIds.slice();filteredSelected=confirmedFilteredSelected;bulkPending=false;displayCount=null;renderSelection();}
    }).catch(error=>{
      if(version===selectionVersion){selectedIds=confirmedIds.slice();filteredSelected=confirmedFilteredSelected;bulkPending=false;displayCount=null;renderSelection();}
      throw error;
    });
    selectionQueue = request.catch(error => { window.alert(error.message); });
    return request;
  }
  if (selection && document.getElementById('clear-selection')) {
    updateSelectAll();
    selectAll?.addEventListener('change',()=>saveSelection({filtered:selectAll.checked?'add':'remove'}).catch(()=>updateSelectAll()));
    selection.querySelectorAll('.requirement-select').forEach(input => input.addEventListener('change', () => saveSelection(input.checked ? {add:[input.value]} : {remove:[input.value]}).catch(() => {})));
    document.getElementById('clear-selection').addEventListener('click', () => saveSelection({clear:true}).then(() => { if (new URLSearchParams(window.location.search).has('selected_only')) window.location.reload(); }).catch(() => {}));
    document.getElementById('create-from-selection')?.addEventListener('submit', function (event) { event.preventDefault(); selectionQueue.then(() => this.submit()).catch(() => {}); });
    document.querySelectorAll('.pagination a, .procurement-compact th a').forEach(link => link.addEventListener('click', event => { event.preventDefault(); selectionQueue.then(() => { window.location.href=link.href; }).catch(() => {}); }));
  }
  document.querySelectorAll('[data-auto-filter]').forEach(filter => {
    let timer;
    function submit() { selectionQueue.then(() => { filter.classList.add('procurement-loading'); filter.submit(); }).catch(() => {}); }
    filter.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); submit(); });
    filter.addEventListener('change', event => { if (event.target.tagName === 'SELECT' || event.target.type === 'checkbox' || event.target.type === 'date') { clearTimeout(timer); submit(); } });
    Array.from(filter.elements).filter(input => !filter.contains(input) && input.type === 'date').forEach(input => input.addEventListener('change', () => { clearTimeout(timer); submit(); }));
    Array.from(filter.elements).filter(input => input.tagName === 'INPUT' && !['hidden','checkbox','date'].includes(input.type)).forEach(input => input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(submit, 450); }));
  });
  document.querySelectorAll('[data-filter-toggle]').forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.dataset.filterToggle);
    const expanded = button.getAttribute('aria-expanded') !== 'true';
    panel.classList.toggle('d-none', !expanded);
    button.setAttribute('aria-expanded', String(expanded));
    button.querySelector('[data-filter-toggle-label]').textContent = expanded ? 'Sakrij filtere' : 'Pokaži filtere';
    const state = panel.querySelector('input[name="filters_open"]');
    if (state) state.value = expanded ? '1' : '0';
  }));
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  const draft = document.getElementById('download-draft');
  if (draft) draft.addEventListener('click', () => {
    const cleanHeader = value => value.replace(/[\r\n]/g, ' ');
    const text = 'To: '+cleanHeader(document.getElementById('draft-to').value)+'\r\nSubject: =?UTF-8?B?'+btoa(unescape(encodeURIComponent(cleanHeader(document.getElementById('draft-subject').value))))+'?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Unsent: 1\r\n\r\n'+document.getElementById('draft-body').value.replace(/\r?\n/g,'\r\n');
    const url=URL.createObjectURL(new Blob([text],{type:'message/rfc822'})); const link=document.createElement('a');link.href=url;link.download='nacrt-nabavke.eml';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  const form = document.getElementById('procurement-form');
  if (!form) return;
  function recalculate() {
    const totals = { quantity: 0n, net: 0n, vat: 0n, gross: 0n, discount: 0n };
    let valid = true;
    form.querySelectorAll('[data-calculation-row]').forEach(row => {
      try {
        const value = field => row.querySelector('[data-field="' + field + '"]').value;
        const singleAllocation = row.querySelector('[data-single-allocation]');
        if (singleAllocation) singleAllocation.value = value('quantity');
        const allocations = row.querySelectorAll('[data-allocation]');
        if (allocations.length && !row.hasAttribute('data-ordered-specification')) {
          let quantity = 0n;
          allocations.forEach(input => { quantity += decimal(input.value); });
          row.querySelector('[data-field="quantity"]').value = format(quantity, 6);
        }
        const line = calculate(value('quantity'), value('price'), value('vatRate'), row.querySelector('[data-field="discountRate"]') ? value('discountRate') : '0');
        for (const key of Object.keys(totals)) totals[key] += line[key];
        row.querySelector('[data-value="net"]').textContent = format(line.net, 2).replace('.', ',');
        row.querySelector('[data-value="gross"]').textContent = format(line.gross, 2).replace('.', ',');
      } catch (_) {
        valid = false;
        row.querySelectorAll('[data-value]').forEach(cell => { cell.textContent = '—'; });
      }
    });
    for (const key of Object.keys(totals)) { const target = form.querySelector('[data-total="' + key + '"]'); if (target) target.textContent = valid ? format(totals[key], key === 'quantity' ? 6 : 2).replace('.', ',') : '—'; }
  }
  form.addEventListener('input', recalculate);
  form.addEventListener('submit', async event => {
    const button=document.getElementById('save-procurement'),status=document.getElementById('procurement-save-status');
    button.disabled=true;if(status){status.hidden=false;status.textContent='Spremanje dokumenta…';}
    if(form.elements.length<900)return;
    event.preventDefault();
    try {
      const payload={};
      for(const [name,value] of new FormData(form)){
        const keys=name.match(/[^\[\]]+/g);let node=payload;
        keys.forEach((key,index)=>{if(index===keys.length-1)node[key]=value;else node=node[key]??=(/^\d+$/.test(keys[index+1])?[]:{});});
      }
      const response=await fetch(form.action,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':payload._token},body:JSON.stringify(payload)});
      if(!response.ok){const data=await response.json();throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Dokument nije sačuvan.');}
      window.location.assign(response.url);
    }catch(error){if(status)status.textContent=error.message;button.disabled=false;}
  });
  recalculate();
}());
