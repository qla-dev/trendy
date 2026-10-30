const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('resources/js/scripts/pages/app-production-plan.js','utf8');
let mode='all';
const context=vm.createContext({rowColourFilter:{val:()=>mode}});
vm.runInContext(source.slice(source.indexOf('  function rowColour('),source.indexOf('  function applyRowColour(')),context);
for(const selection of ['all','basic']) {
  mode=selection;
  for(const status of ['O','E','P','R','F','I','Z','C']) for(const colour of ['teal','orange','yellow','green','purple','grey']) {
    context.row={is_late:true,is_previous_week_open_order:false,status_code:status,priority_row_color:colour};
    assert.equal(vm.runInContext('rowColour(row)',context),'red');
  }
}
mode='all';context.row={is_late:false,priority_row_color:'teal'};
assert.equal(vm.runInContext('rowColour(row)',context),'teal');
context.row={is_previous_week_open_order:true,priority_row_color:'yellow'};
assert.equal(vm.runInContext('rowColour(row)',context),'red');
mode='none';context.row={is_late:true,priority_row_color:'yellow'};
assert.equal(vm.runInContext('rowColour(row)',context),'');
console.log('Overdue rows override every status/priority colour.');
