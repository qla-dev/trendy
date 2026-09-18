const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/js/scripts/pages/app-production-plan.js', 'utf8');
const handlers = {};
const week = { value: '', on(events, callback) { handlers.week = callback; }, val() { return this.value; } };
const year = { value: '2026', on(events, callback) { handlers.year = callback; }, val() { return this.value; } };
const fields = ['datum_od', 'datum_do'].map(key => ({
  key, value: '', _flatpickr: {
    config: { onChange: [] },
    setDate(value) { fields.find(field => field._flatpickr === this).value = value; },
    clear() { fields.find(field => field._flatpickr === this).value = ''; }
  }
}));
const dates = {
  on(events, callback) { handlers.dates = callback; return this; },
  each(callback) { fields.forEach(field => callback.call(field)); return this; }
};
const $ = selector => {
  if (selector === '.f[data-k="kw"]') return week;
  if (selector === '.f[data-k="year"]') return year;
  if (typeof selector === 'string') return dates;
  return { data() { return selector && selector.key; }, on() {} };
};
const context = vm.createContext({ $, Date });
vm.runInContext(source.slice(source.indexOf('  var weekDatesAuto'), source.indexOf('  function filters()')), context);
week.value = '26';
handlers.week();
assert.deepEqual(fields.map(field => field.value), ['2026-06-22', '2026-06-28']);
assert.equal(vm.runInContext('weekDatesAuto', context), true);
week.value = '';
handlers.week();
assert.deepEqual(fields.map(field => field.value), ['', '']);
fields[0].value = '2026-06-01';
fields[1].value = '2026-06-15';
handlers.dates();
assert.equal(week.value, ''); // Dates never back-fill a week.
assert.equal(vm.runInContext('weekDatesAuto', context), false);
week.value = '1';
handlers.week();
assert.deepEqual(fields.map(field => field.value), ['2025-12-29', '2026-01-04']);
fields[0]._flatpickr.config.onChange[0]();
assert.equal(vm.runInContext('weekDatesAuto', context), false);
year.value = '2027';
handlers.year();
assert.deepEqual(fields.map(field => field.value), ['2027-01-04', '2027-01-10']);
assert.equal(vm.runInContext('isoWeekDates(2026, 53)[1]', context), '2027-01-03');
assert.equal(vm.runInContext('isoWeekDates(2027, 53)', context), null);
console.log('Week/date synchronization checks passed.');
