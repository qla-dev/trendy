const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/scripts/pages/app-production-plan.js', 'utf8');
const context = vm.createContext({
  escapeHtml: value => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
  formatQuantity: value => String(value)
});
vm.runInContext(source.slice(source.indexOf('  function renderOperationsFlow'), source.indexOf('  function showRowDetails')), context);
assert(vm.runInContext('renderOperationsFlow([])', context).includes('Nema operacija'));
context.operations = [{ naziv: 'First', pozicija: '1', is_finished: true }, { naziv: 'Second', pozicija: '2', is_finished: false }];
const flow = vm.runInContext('renderOperationsFlow(operations)', context);
assert(flow.indexOf('First') < flow.indexOf('Second'));
assert(flow.includes('text-success'));
assert(flow.includes('Nezavršeno'));
assert(vm.runInContext('renderDetails({loading: true}, "RN1")', context).includes('role="status"'));
assert(vm.runInContext('renderDetails({error: true}, "RN1")', context).includes('plan-details-retry'));
const details = vm.runInContext('renderDetails({data: {operations, materials: [{naziv: "Hidden material"}]}}, "RN1")', context);
assert(details.includes('First'));
assert(!details.includes('Materijali'));
assert(!details.includes('Hidden material'));
console.log('Production plan detail rendering checks passed.');
