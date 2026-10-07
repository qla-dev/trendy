// One-off: derives extra screen sources (NFC states, operation states, fuller tables, charts, QR label).
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const W = (f, s) => fs.writeFileSync(path.join(ROOT, 'screens-src', f), s);
const R = (f) => fs.readFileSync(path.join(ROOT, 'screens-src', f), 'utf8');

// NFC page straight from the Blade markup
const nfc = fs
  .readFileSync(path.join(ROOT, '../views/content/apps/nfc-card/app-nfc-card.blade.php'), 'utf8')
  .split(/\r?\n/)
  .slice(308, 409)
  .join('\n');
const fill = (state) =>
  nfc
    .replace(/@php[\s\S]*?@endphp/g, '')
    .replace(/\{\{--[\s\S]*?--\}\}/g, '')
    .replace(/\{\{\s*\$nfcCard \? 'linked' : 'idle'\s*\}\}/, state)
    .replace(/\{\{\s*route\([^}]*\}\}/g, '#')
    .replace(/\{\{\s*asset\('([^']+)'\)\s*\}\}/g, '../$1')
    .replace(/\{\{\s*\$holderName\s*\}\}/g, 'Admin')
    .replace(/\{\{\s*\$nfcCard\['uid_display'\][^}]*\}\}/g, 'C3 6E 1C 28')
    .replace(/\{\{\s*\$nfcCard\['linked_at'\][^}]*\}\}/g, '07.10.2026. 08:14')
    .replace(/\{\{[^}]*\}\}/g, '')
    .replace(/^\s*@(?:if|else|elseif|endif|php|endphp|foreach|endforeach|isset|endisset)\b.*$/gm, '');
const nfcMeta = '<!--{"title":"Moja NFC kartica","mobile":true,"blade":["apps/nfc-card/app-nfc-card.blade.php"]}-->\n';
W('m-nfc-scan.html', nfcMeta + fill('idle'));
W('m-nfc-card.html', nfcMeta + fill('linked'));

// Operations: real footer styles + a "checked" second state
let ops = R('m-operacije.html');
if (!ops.includes('panels/footer')) {
  ops = ops.replace('"blade":["apps/invoice/app-invoice-scan-operations.blade.php"]', '"blade":["apps/invoice/app-invoice-scan-operations.blade.php","../panels/footer.blade.php"]');
  W('m-operacije.html', ops);
}
W(
  'm-operacije-2.html',
  ops
    .replace(
      'class="scan-operation-row is-enabled" role="listitem"><span class="scan-operation-position">50</span>',
      'class="scan-operation-row is-finished is-complete" role="listitem"><span class="scan-operation-position">50</span>',
    )
    .replace(
      /(OP50<\/span><span class="scan-operation-name">Bravarija<\/span><\/span><span class="scan-operation-finished-check)" aria-label="Operacija nije završena"/,
      '$1 is-checked" aria-label="Operacija završena"',
    ),
);

// Scanners: full black backdrop, printed work-order label in front of the camera
const label =
  '<img src="../images/qr-label.svg" style="position:absolute;inset:14%;width:72%;height:72%;transform:rotate(-4deg);border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.6)">';
for (const f of ['m-qr.html', 'm-sirovina.html']) {
  let t = R(f);
  if (!t.includes('"bare":true')) t = t.replace('"mobile":true', '"mobile":true,"bare":true');
  t = t.replace(/(<div id="(?:sirovina-)?qr-scanner-region" class="position-absolute" style="inset: 0;">)<\/div>/, `$1${label}</div>`);
  if (!t.includes('#05070a')) t = t.replace('-->\n', '-->\n<style>body{background:#05070a!important}.modal-backdrop{opacity:.992!important;background:#000}</style>\n');
  W(f, t);
}

// Dashboard: ApexCharts with the options from dashboard-ecommerce.js
let d = R('d-dashboard.html');
if (!d.includes('apexcharts.css')) d = d.replace('"css":["', '"css":["vendors/css/charts/apexcharts.css","');
if (!d.includes('apexcharts.min.js')) {
  d += `
<script src="../vendors/js/charts/apexcharts.min.js"></script>
<script>
var M=['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'],cur=[86,94,112,108,121,117,126,98,132,140,0,0],cmp=[-72,-80,-91,-88,-95,-101,-99,-84,-103,-109,-97,-92];
var off={enabled:false};
new ApexCharts(document.querySelector('#revenue-report-chart'),{chart:{height:230,stacked:true,type:'bar',toolbar:{show:false},animations:off},plotOptions:{bar:{columnWidth:'17%',endingShape:'rounded'},distributed:true},colors:['#ff9f43','#dcdae3'],series:[{name:'2026',data:cur},{name:'2025',data:cmp}],dataLabels:{enabled:false},legend:{show:false},grid:{padding:{top:-20,bottom:-10},yaxis:{lines:{show:false}}},xaxis:{categories:M,labels:{style:{colors:'#b9b9c3',fontSize:'0.86rem'}},axisTicks:{show:false},axisBorder:{show:false}},yaxis:{labels:{style:{colors:'#b9b9c3',fontSize:'0.86rem'}}},tooltip:{enabled:false}}).render();
new ApexCharts(document.querySelector('#budget-chart'),{chart:{height:80,toolbar:{show:false},zoom:{enabled:false},type:'line',sparkline:{enabled:true},animations:off},stroke:{curve:'smooth',dashArray:[0,5],width:[2]},colors:['#ff9f43','#dcdae3'],series:[{name:'2026',data:cur.slice(0,10)},{name:'2025',data:cmp.slice(0,10).map(Math.abs)}],tooltip:{enabled:false}}).render();
new ApexCharts(document.querySelector('#earnings-chart'),{chart:{type:'donut',height:120,toolbar:{show:false},animations:off},dataLabels:{enabled:false},series:[53,16,31],legend:{show:false},labels:['Proizvodnja','Usluge','Mašine'],stroke:{width:0},colors:['#28c76f66','#28c76f33','#28c76f'],grid:{padding:{right:-20,bottom:-8,left:-20}},plotOptions:{pie:{startAngle:-10,donut:{labels:{show:true,name:{offsetY:15},value:{offsetY:-15},total:{show:true,offsetY:15,label:'Proizvodnja',formatter:function(){return '53%'}}}}}}}).render();
new ApexCharts(document.querySelector('#statistics-order-chart'),{chart:{height:70,type:'bar',stacked:true,toolbar:{show:false},animations:off},grid:{show:false,padding:{left:0,right:0,top:-15,bottom:-15}},plotOptions:{bar:{horizontal:false,columnWidth:'20%',colors:{backgroundBarColors:['#f3f3f3','#f3f3f3','#f3f3f3','#f3f3f3','#f3f3f3'],backgroundBarRadius:5}}},legend:{show:false},dataLabels:{enabled:false},colors:['#ff9f43'],series:[{name:'2026',data:[45,85,65,45,65]}],xaxis:{labels:{show:false},axisBorder:{show:false},axisTicks:{show:false}},yaxis:{show:false},tooltip:{enabled:false}}).render();
new ApexCharts(document.querySelector('#statistics-profit-chart'),{chart:{height:70,type:'line',toolbar:{show:false},zoom:{enabled:false},animations:off},grid:{borderColor:'#EBEBEB',strokeDashArray:5,xaxis:{lines:{show:true}},yaxis:{lines:{show:false}},padding:{top:-30,bottom:-10}},stroke:{width:3},colors:['#00cfe8'],series:[{data:[0,20,5,30,15,45]}],markers:{size:2,colors:'#00cfe8',strokeColors:'#00cfe8',strokeWidth:2},xaxis:{labels:{show:true,style:{fontSize:'0px'}},axisBorder:{show:false},axisTicks:{show:false}},yaxis:{show:false},tooltip:{enabled:false}}).render();
</script>`;
}
W('d-dashboard.html', d);

// Stock overview: a full page of materials
const stock = [
  ['LIM-S235-5', 'Lim S235 5mm 1500x3000', 'KG', 'Skladište sirovina', '1.250,5'],
  ['SIP-S355-40', 'Šipka S355 Ø40', 'M', 'Skladište sirovina', '386,2'],
  ['AL-6082-20', 'Aluminij EN AW-6082 20mm', 'KG', 'Skladište sirovina', '742'],
  ['INOX-304-2', 'Inox lim 1.4301 2mm', 'KG', 'Skladište sirovina', '518,75'],
  ['CIJ-60x40', 'Cijev pravougaona 60x40x3', 'M', 'Skladište sirovina', '214'],
  ['VIJ-M10-35', 'Vijak M10x35 DIN 933 8.8', 'KOM', 'Skladište repromaterijala', '6.400'],
  ['MAT-M10', 'Matica M10 DIN 934', 'KOM', 'Skladište repromaterijala', '9.850'],
  ['PRA-RAL7016', 'Prah RAL 7016', 'KG', 'Skladište boja', '96,5'],
];
let z = R('d-zalihe.html');
const zRows = z.match(/<tr><td class="material-code-cell">[\s\S]*?<\/tr>/g);
if (zRows.length === 1) {
  const row = zRows[0];
  z = z.replace(
    row,
    stock
      .map((r) =>
        row
          .replace('LIM-S235-5', r[0])
          .replace('Lim S235 5mm 1500x3000', r[1])
          .replace('>KG<', `>${r[2]}<`)
          .replace('>Skladište sirovina<', `>${r[3]}<`)
          .replace('1.250,5', r[4]),
      )
      .join('\n'),
  );
  W('d-zalihe.html', z);
}

// Released materials: a page of documents
const docs = [
  ['26-6400-0000412', '26-6000-0001687', '25-0110-0003084', '10', 'LIM-S235-5', 'Lim S235 5mm 1500x3000', '2,4', 'KG', '12,48 KM'],
  ['26-6400-0000411', '26-6000-0001684', '25-0110-0003079', '10', 'SIP-S355-40', 'Šipka S355 Ø40', '18,6', 'M', '96,72 KM'],
  ['26-6400-0000410', '26-6000-0001684', '25-0110-0003079', '20', 'VIJ-M10-35', 'Vijak M10x35 DIN 933 8.8', '240', 'KOM', '28,80 KM'],
  ['26-6400-0000409', '26-6000-0001681', '25-0110-0003071', '10', 'AL-6082-20', 'Aluminij EN AW-6082 20mm', '31,25', 'KG', '143,75 KM'],
  ['26-6400-0000408', '26-6000-0001679', '25-0110-0003068', '10', 'INOX-304-2', 'Inox lim 1.4301 2mm', '12,8', 'KG', '70,40 KM'],
  ['26-6400-0000407', '26-6000-0001676', '25-0110-0003060', '30', 'PRA-RAL7016', 'Prah RAL 7016', '4,5', 'KG', '40,50 KM'],
];
let k = R('d-dokumenti.html');
const kRows = k.match(/<tr><td class="released-doc-document-cell">[\s\S]*?<\/tr>/g);
if (kRows.length === 1) {
  const row = kRows[0];
  const keys = ['26-6400-0000412', '26-6000-0001687', '25-0110-0003084', '>10<', 'LIM-S235-5', 'Lim S235 5mm 1500x3000', '>2,4<', '>KG<', '12,48 KM'];
  k = k.replace(
    row,
    docs
      .map((r) => keys.reduce((x, key, i) => x.replace(key, key.startsWith('>') ? `>${r[i]}<` : r[i]), row))
      .join('\n'),
  );
  W('d-dokumenti.html', k);
}

// Printed work-order label with a QR code (finder patterns + seeded modules)
let seed = 7;
const rnd = () => (seed = (seed * 1664525 + 1013904223) >>> 0) / 4294967296;
const N = 25;
const finder = (x, y) => {
  for (const [ox, oy] of [[0, 0], [N - 7, 0], [0, N - 7]]) {
    const dx = x - ox;
    const dy = y - oy;
    if (dx >= 0 && dx < 7 && dy >= 0 && dy < 7) return dx === 0 || dx === 6 || dy === 0 || dy === 6 || (dx > 1 && dx < 5 && dy > 1 && dy < 5) ? 1 : 0;
  }
  return -1;
};
let cells = '';
for (let y = 0; y < N; y++)
  for (let x = 0; x < N; x++) {
    const v = finder(x, y);
    if (v === 1 || (v === -1 && rnd() > 0.5)) cells += `<rect x="${x}" y="${y}" width="1.02" height="1.02"/>`;
  }
fs.writeFileSync(
  path.join(ROOT, 'public/app/images/qr-label.svg'),
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-3 -3 ${N + 6} ${N + 6}"><rect x="-3" y="-3" width="${N + 6}" height="${N + 6}" fill="#fff"/><g fill="#111">${cells}</g></svg>`,
);
console.log('sources ready');
