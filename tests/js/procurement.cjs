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
