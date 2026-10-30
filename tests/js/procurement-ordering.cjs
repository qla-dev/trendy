const assert = require('node:assert/strict');
const {dimensions,groupSources} = require('../../resources/js/scripts/pages/procurement-ordering.js');
const source = {dimensions:'20x30x50',pieces:'20',required:'10',remaining:'10',work_order:'RN-1'};
assert.equal(dimensions([source]), '20 × 30 × 1000');
assert.equal(dimensions([source,{...source,pieces:'10',work_order:'RN-2'}]), '20 × 30 × 1500');
assert.equal(dimensions([{...source,remaining:'5'}]), '20 × 30 × 500');
assert.equal(dimensions([{...source,dimensions:'20,5×30×50,5'}]), '20,5 × 30 × 1010');
assert.match(dimensions([{...source,pieces:null}]), /broj komada nije potvrđen/);
assert.equal(dimensions([source,{...source,dimensions:'40x30x100'}]), '20 × 30 × 1000\n40 × 30 × 2000');
console.log('Procurement length calculations passed');
const grouped=groupSources([{...source,id:'a',material:'STEEL',unit:'KG',vat_rate:'17'},
  {...source,id:'b',material:'STEEL',unit:'KG',vat_rate:'17',dimensions:'20.0×30,0×100'},
  {...source,id:'c',material:'STEEL',unit:'KG',vat_rate:'17',dimensions:'70x120x125'}]);
assert.deepEqual(grouped.map(group=>group.map(row=>row.id)),[['a','b'],['c']]);
assert.equal(groupSources([{...source,id:'a',unit:'KG'},{...source,id:'b',unit:'KOM'}]).length,2);
console.log('Procurement cross-section grouping passed');
