const assert = require('node:assert/strict');
const { calculate, format } = require('../../resources/js/scripts/pages/procurement.js');
const line = calculate('12,500000', '3.333333', '17');
assert.equal(format(line.net, 2), '41.67');
assert.equal(format(line.vat, 2), '7.08');
assert.equal(format(line.gross, 2), '48.75');
assert.equal(format(calculate('0.1', '0.05', '0').gross, 2), '0.01');
assert.equal(format(calculate('10','2','17','10').discount,2),'2.00');
assert.equal(format(calculate('10','2','17','10').gross,2),'21.06');
assert.throws(()=>calculate('10','2','17','101'));
assert.equal(format(calculate('100000000000', '100000000000', '17').gross, 2), '11700000000000000000000.00');
for (const args of [['0','1','17'], ['1','-1','17'], ['1','1','101'], ['1','1e2','17'], ['1.0000001','1','17']]) {
  assert.throws(() => calculate(...args));
}
console.log('Procurement fixed-point preview tests passed.');

// Execute the actual form recalculation with an editable quantity and its hidden source allocation.
const vm = require('node:vm');
const source = require('node:fs').readFileSync(require('node:path').join(__dirname,'../../resources/js/scripts/pages/procurement.js'),'utf8');
const inputs = {quantity:{value:'0.5'},price:{value:'2.450000'},vatRate:{value:'17'},discountRate:{value:'0'}};
const allocation = {value:'0.5'};
const cells = {net:{},gross:{}};
const totals = Object.fromEntries(['quantity','net','vat','gross','discount'].map(key=>[key,{}]));
const row = {
  querySelector(selector) {
    if(selector==='[data-single-allocation]')return allocation;
    const field=selector.match(/data-field="([^"]+)"/);if(field)return inputs[field[1]];
    const cell=selector.match(/data-value="([^"]+)"/);if(cell)return cells[cell[1]];
    throw new Error('Unexpected selector '+selector);
  },
  querySelectorAll(selector){return selector==='[data-allocation]'?[]:Object.values(cells);}
};
const form={querySelectorAll:()=>[row],querySelector:selector=>totals[selector.match(/data-total="([^"]+)"/)[1]]};
const context=vm.createContext({form,calculate,decimal:value=>BigInt(Math.round(Number(value)*1e6)),format});
vm.runInContext(source.slice(source.indexOf('  function recalculate() {'),source.indexOf("  form.addEventListener('input', recalculate);"))+'\nrecalculate();',context);
assert.equal(allocation.value,'0.5');assert.equal(totals.gross.textContent,'1,44');
inputs.quantity.value='0.25';inputs.price.value='4';
vm.runInContext('recalculate();',context);
assert.equal(allocation.value,'0.25');assert.equal(totals.gross.textContent,'1,17');
console.log('Editable quantities keep source allocation and totals synchronized.');
