const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/scripts/pages/app-production-plan.js', 'utf8').replace(/\r\n/g, '\n');
const handlers = {};
const original = { _DT_RowIndex: 5 };
const cells = [{}, {}];
const row = { id: 'RN1', naziv: 'Existing' };
const retainedRows = new Map([['RN1', { value: JSON.stringify(row), node: original, cells }]]);
const context = vm.createContext({ livePolling: true, retainedRows, tableElement: { on: (name, handler) => handlers[name] = handler } });
vm.runInContext(source.slice(source.indexOf("  tableElement.on('preDraw.dt'"), source.indexOf("  tableElement.on('xhr.dt error.dt")), context);
const changedNode = {};
const newNode = {};
const settings = { aoData: [{ _aData: row, nTr: {} }, { _aData: { id: 'RN2', naziv: 'Changed' }, nTr: changedNode }, { _aData: { id: 'RN3' }, nTr: newNode }] };
handlers['preDraw.dt']({}, settings);
assert.equal(settings.aoData[0].nTr, original);
assert.equal(settings.aoData[0].anCells, cells);
assert.equal(original._DT_RowIndex, 0);
assert.equal(cells[1]._DT_CellIndex.column, 1);
assert.equal(settings.aoData[1].nTr, changedNode);
assert.equal(settings.aoData[2].nTr, newNode);
assert(source.includes('data.filter = filters()'));
assert(source.includes('table.ajax.reload(null, false)'));
assert(source.includes('window.setInterval(pollPlan, 5 * 60 * 1000)'));
console.log('Live polling retains unchanged rows and leaves new/changed rows intact.');

// Exercise lifecycle events and request completion with a controllable clock.
const timers = new Map();
const lifecycle = {};
const notices = {};
const requests = [];
let timerId = 0;
let editorOpen = false;
let processing = true;
const win = {
  setInterval(fn, delay) { const id = ++timerId; timers.set(id, { fn, delay, interval: true }); return id; },
  clearInterval(id) { timers.delete(id); },
  setTimeout(fn, delay) { const id = ++timerId; timers.set(id, { fn, delay }); return id; },
  clearTimeout(id) { timers.delete(id); }
};
const doc = { hidden: false };
function element(key) {
  if (!notices[key]) notices[key] = { hidden: true, value: '' };
  return {
    text(value) { notices[key].value = value; return this; },
    addClass() { notices[key].hidden = true; return this; },
    removeClass() { notices[key].hidden = false; return this; },
    on(name, fn) { lifecycle[name] = fn; return this; }
  };
}
const requestSettings = { jqXHR: { readyState: 4 }, oApi: { _fnProcessingDisplay(settings, value) { processing = value; } } };
const polling = vm.createContext({
  window: win, document: doc, livePolling: false, retainedRows: new Map(),
  requestErrorShown: false, setPlanLoading() {},
  showRequestError() { throw new Error('Background errors must not open dialogs'); },
  table: {
    settings() { return [requestSettings]; },
    rows() { return { every() {} }; },
    ajax: { reload(callback, resetPage) { requests.push({ resetPage }); requestSettings.jqXHR.readyState = 1; } }
  },
  tableElement: { on(name, fn) { lifecycle[name] = fn; } },
  $(target) { return target === '.plan-inline-editor' ? { length: editorOpen ? 1 : 0 } : element(target); }
});
vm.runInContext(source.slice(source.indexOf("  tableElement.on('xhr.dt', function (event"), source.indexOf('  var detailCache')), polling);
const ajaxHandlers = vm.runInContext('({' + source.slice(source.indexOf('      timeout: 45000,'), source.indexOf('\n    },\n    language:')) + '})', polling);
function finish(status = 200) {
  requestSettings.jqXHR.readyState = 4;
  if (status === 200) lifecycle['xhr.dt']({}, requestSettings, { data: [] });
  else ajaxHandlers.error({ status });
  ajaxHandlers.complete();
}
const intervals = () => [...timers.values()].filter(timer => timer.interval);
assert.equal(intervals().length, 1);
assert.equal(intervals()[0].delay, 300000);
assert.equal(ajaxHandlers.timeout, 45000);
lifecycle.pageshow({ originalEvent: { persisted: false } });
assert.equal(intervals().length, 1, 'pageshow must not duplicate the timer');
lifecycle.pagehide();
assert.equal(timers.size, 0);
polling.pollPlan();
assert.equal(requests.length, 0, 'No polling after pagehide');
lifecycle.pageshow({ originalEvent: { persisted: true } });
assert.equal(intervals().length, 1);
assert.equal(requests.length, 1, 'Restoring from memory refreshes immediately');
assert.equal(requests[0].resetPage, false);
polling.pollPlan();
assert.equal(requests.length, 1, 'No overlapping requests');
finish(500);
assert.equal(polling.livePolling, false);
assert.equal(processing, false);
assert.equal(notices['#production-plan-refresh-notice'].hidden, false);
assert.match(notices['#production-plan-refresh-message'].value, /zastarjeli/);
const retry = [...timers.entries()].find(([, timer]) => !timer.interval);
assert.equal(retry[1].delay, 30000);
timers.delete(retry[0]); retry[1].fn();
assert.equal(requests.length, 2);
finish();
assert.equal(notices['#production-plan-refresh-notice'].hidden, true);
assert.equal(timers.size, 1, 'Success cancels pending retries');
doc.hidden = true; polling.pollPlan(); assert.equal(requests.length, 2);
doc.hidden = false; lifecycle.visibilitychange(); assert.equal(requests.length, 3);
finish();
editorOpen = true; polling.pollPlan(); assert.equal(requests.length, 3);
editorOpen = false; polling.pollPlan(); finish(419);
assert.match(notices['#production-plan-refresh-message'].value, /Sesija je istekla/);
assert.equal(timers.size, 1, 'Expired sessions do not trigger rapid retries');
polling.pollPlan(); finish(0); // Includes network failure / timeout.
assert.equal(timers.size, 2);
lifecycle.pagehide(); assert.equal(timers.size, 0, 'Leaving also cancels retries');
lifecycle.pageshow({ originalEvent: { persisted: true } }); finish();
assert.equal(intervals().length, 1);
console.log('Polling resumes after history/tab restoration, reports failures, retries, recovers and keeps pagination.');
const view = fs.readFileSync('resources/views/content/apps/production/app-production-plan.blade.php', 'utf8');
const noticeClasses = view.match(/id="production-plan-refresh-notice" class="([^"]+)"/)[1].split(/\s+/);
assert(!noticeClasses.includes('alert'), 'The layout automatically removes .alert nodes after 5 seconds; polling notice must persist');
