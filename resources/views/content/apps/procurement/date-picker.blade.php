@once
<link rel="stylesheet" href="{{ asset('vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/base/plugins/forms/pickers/form-flat-pickr.css') }}">
<style>
.procurement-calendar{z-index:1085!important}.procurement-calendar .calendar-actions{display:flex;justify-content:space-between;padding:8px;border-top:1px solid #d8d6de}
.procurement-date-display{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23757585' stroke-width='2'%3E%3Crect x='3' y='5' width='18' height='16' rx='2'/%3E%3Cpath d='M16 3v4M8 3v4M3 11h18'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:34px!important}
</style>
<script defer src="{{ asset('vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
 'use strict';
 if(typeof window.flatpickr!=='function')return;
 const locale={firstDayOfWeek:1,
  weekdays:{shorthand:['Ned','Pon','Uto','Sri','Čet','Pet','Sub'],longhand:['Nedjelja','Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota']},
  months:{shorthand:['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Avg','Sep','Okt','Nov','Dec'],longhand:['Januar','Februar','Mart','April','Maj','Juni','Juli','August','Septembar','Oktobar','Novembar','Decembar']},
  rangeSeparator:' do ',weekAbbreviation:'Sed.',yearAriaLabel:'Godina',monthAriaLabel:'Mjesec'};
 let active=null,pointerTarget=null;
 function commit(instance){
  const text=instance.altInput.value.trim();
  if(instance.selectedDates.length && text===instance.formatDate(instance.selectedDates[0],'d.m.Y'))return;
  if(!text && !instance.input.value)return;
  instance.setDate(text,true,'d.m.Y');
 }
 function position(instance){
  if(!instance.isOpen)return;
  const rect=instance.altInput.getBoundingClientRect(),calendar=instance.calendarContainer;
  calendar.style.position='fixed';
  const height=calendar.offsetHeight,width=calendar.offsetWidth;
  const top=rect.bottom+height+6<=window.innerHeight ? rect.bottom+6 : Math.max(8,rect.top-height-6);
  calendar.style.top=Math.min(top,Math.max(8,window.innerHeight-height-8))+'px';
  calendar.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px';
  calendar.style.right='auto';
 }
 document.querySelectorAll('input[data-procurement-date]').forEach(function(input,index){
  window.flatpickr(input,{dateFormat:'Y-m-d',altInput:true,altFormat:'d.m.Y',ariaDateFormat:'d. F Y.',allowInput:true,disableMobile:true,
   locale:locale,monthSelectorType:'static',
   onReady:function(dates,value,instance){
    const display=instance.altInput,calendar=instance.calendarContainer;
    const modalContent=input.closest('.modal-content');if(modalContent)modalContent.append(calendar);
    calendar.classList.add('procurement-calendar');display.classList.add('procurement-date-display');display.placeholder='dd.mm.gggg';display.autocomplete='off';
    display.id=(input.id || 'procurement-date-'+index)+'-display';
    const label=input.parentElement.querySelector('label');
    display.setAttribute('aria-label',input.getAttribute('aria-label') || (label ? label.textContent.trim():'Datum'));
    if(label && label.htmlFor===input.id)label.htmlFor=display.id;
    display.addEventListener('blur',function(event){if(!calendar.contains(event.relatedTarget || pointerTarget)){commit(instance);instance.close();}});
    const actions=document.createElement('div');actions.className='calendar-actions';
    [['Danas',function(){instance.setDate(new Date(),true);instance.close();}],['Obriši',function(){instance.clear(true);instance.close();}]].forEach(function(action){
     const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-primary';button.textContent=action[0];button.addEventListener('click',action[1]);actions.append(button);
    });calendar.append(actions);
   },
   onOpen:function(dates,value,instance){if(active && active!==instance)active.close();active=instance;position(instance);requestAnimationFrame(function(){position(instance);});},
   onClose:function(dates,value,instance){if(active===instance)active=null;},
   onMonthChange:function(dates,value,instance){position(instance);},onYearChange:function(dates,value,instance){position(instance);}
  });
 });
 document.addEventListener('pointerdown',function(event){pointerTarget=event.target;if(active && !active.calendarContainer.contains(event.target) && event.target!==active.altInput && event.target!==active.input){const instance=active;commit(instance);instance.close();}},true);
 document.addEventListener('scroll',function(){if(active)position(active);},true);
 window.addEventListener('resize',function(){if(active)position(active);});
 document.addEventListener('keydown',function(event){if(active && (event.key==='Escape' || (event.key==='Enter' && event.target===active.altInput))){const instance=active;event.preventDefault();event.stopPropagation();commit(instance);instance.altInput.focus();instance.close();}},true);
});
</script>
@endonce
