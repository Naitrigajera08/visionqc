/* VisionQC dashboard – front-end only, simulated realistic data */
const $=s=>document.querySelector(s),fmt=n=>Math.round(n).toLocaleString('en-IN'),R=(a,b)=>a+Math.random()*(b-a),pick=a=>a[Math.floor(Math.random()*a.length)];
const inr=n=>'₹'+fmt(n);

/* ---------- Data ---------- */
// Categories drive bins + cards + charts (nothing hard-coded in HTML)
const cats=[{n:'Good',c:'#A3D65C',i:'fa-circle-check',v:8558,s:'Passed'},{n:'Damaged',c:'#D7263D',i:'fa-burst',v:212,s:'Rejected'},
{n:'Cracked',c:'#F2C94C',i:'fa-bolt',v:142,s:'Rejected'},{n:'Burned',c:'#FF6B35',i:'fa-fire',v:100,s:'Rejected'}];
const prods=[{id:'P-101',n:'Brake Disc',p:1450,i:'fa-compact-disc',d:38},{id:'P-102',n:'Ceramic Insulator',p:620,i:'fa-bolt',d:52},
{id:'P-103',n:'Steel Gear',p:890,i:'fa-gear',d:71},{id:'P-104',n:'Glass Bottle',p:45,i:'fa-wine-bottle',d:140},
{id:'P-105',n:'PCB Board',p:380,i:'fa-microchip',d:29},{id:'P-106',n:'Piston Ring',p:275,i:'fa-ring',d:64},
{id:'P-107',n:'Alloy Wheel',p:3200,i:'fa-life-ring',d:12},{id:'P-108',n:'Ball Bearing',p:520,i:'fa-bullseye',d:48}];
const machines=[['CNC Lathe L-01',62,4210,94,96],['Press Line P-02',74,6890,88,81],['Casting Unit C-03',88,9120,79,58],['Conveyor A',41,2310,97,98],['Laser Cutter LC-5',56,3550,92,91],['Kiln K-07',96,11800,71,44]];
const ops=[['Amit Patel','Shift A',1240,98.4,96],['Neha Desai','Shift A',1185,97.1,94],['Imran Qureshi','Shift B',1302,95.6,92],['Priya Joshi','Shift B',1098,98.9,97],['Kiran Mehta','Shift C',964,93.2,88],['Vikram Solanki','Shift C',1010,94.5,90]];
const sev={Critical:'#D7263D',Warning:'#FF6B35',Info:'#138496'};
const alerts=[['Critical','Kiln K-07 temperature at 96°C – above safe limit','fa-temperature-full'],['Warning','Defect rate on CAM-03 crossed 6% in last 15 min','fa-triangle-exclamation'],['Info','Shift B started – 18 operators checked in','fa-user-check'],
['Warning','Casting Unit C-03 maintenance due in 120 hrs','fa-screwdriver-wrench'],['Critical','Burned parts spike on Line B (12 in 5 min)','fa-fire'],['Info','Model v3.2.1 calibration completed on 12 cameras','fa-camera']];
const dayLoss=prods.reduce((a,p)=>a+p.d*p.p,0),defects=cats.slice(1).reduce((a,c)=>a+c.v,0),total=cats.reduce((a,c)=>a+c.v,0);

/* ---------- Sidebar ---------- */
const nav=[['Dashboard','fa-gauge-high','dashboard'],['Live Monitoring','fa-video','live'],['Product Master','fa-boxes-stacked','loss'],['Category Master','fa-layer-group','cats'],['Price Management','fa-indian-rupee-sign','loss'],['Detection History','fa-clock-rotate-left','hist'],['Analytics','fa-chart-line','charts'],['Reports','fa-file-lines','cost'],['Alerts','fa-bell','alerts'],['Machine Health','fa-heart-pulse','mach'],['Operator Performance','fa-user-gear','ops'],['Settings','fa-gear','foot']];
$('#nav').innerHTML=nav.map((n,i)=>`<a href="#${n[2]}" class="${i?'':'on'}"><i class="fa-solid ${n[1]}"></i>${n[0]}</a>`).join('');
$('#nav').onclick=e=>{const a=e.target.closest('a');if(!a)return;document.querySelectorAll('#nav a').forEach(x=>x.classList.remove('on'));a.classList.add('on');$('#sb').classList.remove('open')};
$('#menu').onclick=()=>$('#sb').classList.toggle('open');

/* ---------- Top bar ---------- */
const tick=()=>$('#clock').textContent=new Date().toLocaleTimeString('en-IN');tick();setInterval(tick,1000);
$('#theme').onclick=()=>{const h=document.documentElement,l=h.dataset.theme==='dark';h.dataset.theme=l?'light':'dark';$('#theme i').className='fa '+(l?'fa-sun':'fa-moon');Chart.defaults.color=l?'#51606f':'#BDC3C7';Object.values(Chart.instances).forEach(c=>c.update())};

/* ---------- Animated counters ---------- */
function count(el){const to=+el.dataset.to,d=+el.dataset.d||0,t0=performance.now();(function f(t){const k=Math.min((t-t0)/1400,1),e=1-Math.pow(1-k,3);
el.textContent=(el.dataset.p||'')+(d?(to*e).toFixed(d):fmt(to*e))+(el.dataset.s||'');k<1&&requestAnimationFrame(f)})(t0)}
const cn=()=>document.querySelectorAll('[data-to]').forEach(count);

/* ---------- KPI cards ---------- */
const kp=[["Today's Products",total,'+4.2%',1,'fa-boxes-stacked','#0A6783'],['Good Products',cats[0].v,'+3.8%',1,'fa-circle-check','#5E9B1F'],['Defective Products',defects,'-1.1%',1,'fa-triangle-exclamation','#D7263D'],
['Current Defect Rate',defects/total*100,'-0.4%',1,'fa-percent','#FF6B35','','%',2],["Today's Loss",dayLoss,'+2.3%',0,'fa-indian-rupee-sign','#9a3b1d','₹'],['Est. Monthly Loss',dayLoss*26,'+5.6%',0,'fa-calendar-days','#138496','₹'],
['Machine Health',86.4,'+0.9%',1,'fa-heart-pulse','#2C3E50','','%',1],['Active Cameras',12,'0%',1,'fa-camera','#0A6783','','/12']];
$('#kpis').innerHTML=kp.map(k=>`<div class="col-6 col-xl-3"><div class="glass kpi" style="--c:${k[5]}"><i class="fa-solid ${k[4]}"></i><div><small>${k[0]}</small><h3 data-to="${k[1]}" data-p="${k[6]||''}" data-s="${k[7]||''}" data-d="${k[8]||0}">0</h3><span class="tr ${k[2][0]=='-'==(k[0].match(/Defect|Loss/)!=null)||k[2]=='0%'?'up':'dn'}">${k[2][0]=='-'?'▼':'▲'} ${k[2].replace(/[+-]/,'')} vs yesterday</span></div></div></div>`).join('');

/* ---------- Conveyor flow + dynamic bins ---------- */
$('#flow').innerHTML=[['fa-camera','Camera'],['fa-brain','AI Detection'],['fa-robot','Sorting Arm'],['fa-box-open','Category Bins']].map(s=>`<div class="stage"><i class="fa-solid ${s[0]}"></i>${s[1]}</div>`).join('<i class="fa fa-arrow-right ar"></i>');
$('#bins').innerHTML=cats.map(c=>`<div class="col-6 col-md-3"><div class="bin" id="bin-${c.n}" style="--c:${c.c}"><div class="bimg"><i class="fa-solid ${prods[cats.indexOf(c)].i}"></i></div><b data-v>${fmt(c.v)}</b><span>${c.n}</span><br><small>${c.s}</small></div></div>`).join('');

/* ---------- Factory overview ---------- */
$('#fo').innerHTML=[['Total Cameras','12','fa-camera'],['Running Machines','5 / 6','fa-industry'],['Inspection Speed','58 parts/min','fa-gauge'],['Avg Confidence','96.2%','fa-bullseye'],['Avg Production','1,502 /hr','fa-chart-simple'],['Current Shift','Shift B','fa-business-time']].map(f=>`<div class="col-6"><div class="fo"><small><i class="fa ${f[2]}"></i> ${f[0]}</small><b>${f[1]}</b></div></div>`).join('');

/* ---------- Category cards ---------- */
const spark=c=>{const p=Array.from({length:10},(_,i)=>`${i*14},${30-R(4,26)}`).join(' ');return `<svg viewBox="0 0 126 32" width="100%" height="32"><polyline points="${p}" fill="none" stroke="${c}" stroke-width="2"/></svg>`};
const drawCats=()=>$('#catc').innerHTML=cats.map(c=>`<div class="col-6 col-xl-3"><div class="glass cc" style="--c:${c.c}"><div class="d-flex justify-content-between"><i class="fa-solid ${c.i}"></i><small>${(c.v/total*100).toFixed(1)}%</small></div><h3>${fmt(c.v)}</h3><small>${c.n}</small>${spark(c.c)}</div></div>`).join('');drawCats();

/* ---------- Detection table (search, sort, pagination) ---------- */
const cols=[['t','Time'],['img','Image'],['id','Product ID'],['n','Product Name'],['cat','Category'],['p','Price'],['loss','Est. Loss'],['cf','Confidence'],['cam','Camera'],['st','Status'],['a','Action']];
let rows=[],page=1,sk='t',asc=false,q='';const PS=8;
const mk=(k)=>{const p=pick(prods),c=cats[k!=null?k:(Math.random()<.9?0:1+Math.floor(Math.random()*3))];return{t:new Date(Date.now()-rows.length*47000),id:p.id+'-'+Math.floor(R(1e4,9e4)),n:p.n,i:p.i,cat:c.n,col:c.c,p:p.p,loss:c.n=='Good'?0:p.p,cf:+R(88,99.9).toFixed(1),cam:'CAM-'+String(1+Math.floor(R(0,12))).padStart(2,'0'),st:c.n=='Good'?'Passed':pick(['Rejected','Rejected','Review'])}};
for(let i=0;i<48;i++)rows.push(mk());
$('#th').innerHTML=cols.map(c=>`<th data-k="${c[0]}">${c[1]}</th>`).join('');
$('#th').onclick=e=>{const k=e.target.dataset.k;if(!k||k=='img'||k=='a')return;asc=sk==k?!asc:true;sk=k;page=1;draw()};
$('#ts').oninput=e=>{q=e.target.value.toLowerCase();page=1;draw()};$('#gs').oninput=e=>{$('#ts').value=q=e.target.value.toLowerCase();page=1;draw()};
function draw(){let r=rows.filter(x=>Object.values(x).join(' ').toLowerCase().includes(q)).sort((a,b)=>(a[sk]>b[sk]?1:-1)*(asc?1:-1));const pages=Math.max(1,Math.ceil(r.length/PS));page=Math.min(page,pages);
$('#tbl tbody').innerHTML=r.slice((page-1)*PS,page*PS).map(x=>`<tr><td>${x.t.toLocaleTimeString('en-IN')}</td><td><span class="th"><i class="fa ${x.i}"></i></span></td><td>${x.id}</td><td>${x.n}</td><td><span class="bd" style="--c:${x.col}">${x.cat}</span></td><td>${inr(x.p)}</td><td>${inr(x.loss)}</td><td>${x.cf}%</td><td>${x.cam}</td><td>${x.st}</td><td><button class="bt"><i class="fa fa-eye"></i></button></td></tr>`).join('')||'<tr><td colspan=11>No detections match your search.</td></tr>';
$('#pg').innerHTML=`<button class="bt" data-g="${page-1}">Prev</button>`+Array.from({length:pages},(_,i)=>`<button class="bt ${i+1==page?'on':''}" data-g="${i+1}">${i+1}</button>`).join('')+`<button class="bt" data-g="${page+1}">Next</button>`}
$('#pg').onclick=e=>{const g=+e.target.dataset.g;if(g){page=g;draw()}};draw();

/* ---------- Top defective, loss table ---------- */
const sorted=[...prods].sort((a,b)=>b.d*b.p-a.d*a.p);
$('#topd').innerHTML=sorted.slice(0,5).map(p=>`<div class="td"><span class="th" style="width:46px;height:46px"><i class="fa ${p.i}"></i></span><div><div class="d-flex justify-content-between"><b>${p.n}</b><small>${p.d} defects · ${inr(p.d*p.p)}</small></div><div class="pb mt-1"><i style="width:0" data-w="${p.d*p.p/(sorted[0].d*sorted[0].p)*100}%"></i></div></div></div>`).join('');
$('#lt').innerHTML='<thead><tr><th>Product</th><th>Unit Price</th><th>Defects</th><th>Today\'s Loss</th><th>Monthly Loss</th></tr></thead><tbody>'+prods.map(p=>`<tr><td><i class="fa ${p.i} me-2"></i>${p.n}</td><td>${inr(p.p)}</td><td>${p.d}</td><td>${inr(p.d*p.p)}</td><td>${inr(p.d*p.p*26)}</td></tr>`).join('')+'</tbody>';

/* ---------- Alerts, machines, operators ---------- */
const alHTML=a=>`<div class="al" style="--c:${sev[a[0]]}"><i class="fa-solid ${a[2]}"></i><div><span class="bd" style="--c:${sev[a[0]]}">${a[0]}</span> <small>${new Date().toLocaleTimeString('en-IN')}</small><p>${a[1]}</p><button class="bt">Acknowledge</button></div></div>`;
$('#al').innerHTML=alerts.map(alHTML).join('');
const ring=(v,c)=>`<div class="ring" style="--p:${v};--c:${c}"><span>${v}%</span></div>`,hc=v=>v>85?'#A3D65C':v>65?'#FF6B35':'#D7263D';
$('#mc').innerHTML=machines.map(m=>`<div class="col-md-6"><div class="glass mc"><div class="d-flex gap-3 align-items-center">${ring(m[4],hc(m[4]))}<div class="flex-grow-1"><b>${m[0]}</b><br><small><i class="fa fa-temperature-half"></i> ${m[1]}°C · ${fmt(m[2])} hrs · Eff ${m[3]}%</small></div></div><span class="bd mt-2 d-inline-block" style="--c:${m[4]>85?'#A3D65C':m[4]>65?'#FF6B35':'#D7263D'}">${m[4]>85?'Healthy':m[4]>65?'Maintenance soon':'Service now'}</span></div></div>`).join('');
$('#oc').innerHTML=ops.map(o=>`<div class="col-md-6 col-xl-4"><div class="glass oc"><span class="av">${o[0].split(' ').map(x=>x[0]).join('')}</span><div class="flex-grow-1"><b>${o[0]}</b> <small>· ${o[1]}</small><br><small>${fmt(o[2])} checked · ${o[3]}% accuracy</small></div>${ring(o[4],hc(o[4]))}</div></div>`).join('');

/* ---------- Cost & waste ---------- */
$('#cw').innerHTML=[["Today's Loss",inr(dayLoss),'fa-indian-rupee-sign','#D7263D'],['Weekly Loss',inr(dayLoss*6.2),'fa-calendar-week','#FF6B35'],['Monthly Loss',inr(dayLoss*26),'fa-calendar-days','#138496'],
["Today's Waste",'412 kg','fa-dumpster','#2C3E50'],['Weekly Waste','2.6 t','fa-weight-hanging','#0A6783'],['CO₂ Saved','1.8 t','fa-leaf','#5E9B1F'],['Rejected Materials','454 units','fa-ban','#9a3b1d']].map(c=>`<div class="col-6 col-md-4"><div class="glass kpi" style="--c:${c[3]}"><i class="fa-solid ${c[2]}"></i><div><small>${c[0]}</small><h3 style="font-size:1.2rem">${c[1]}</h3></div></div></div>`).join('');

/* ---------- Charts ---------- */
Chart.defaults.color='#BDC3C7';Chart.defaults.font.family='Segoe UI';Chart.defaults.borderColor='rgba(189,195,199,.12)';Chart.defaults.maintainAspectRatio=false;
const hrs=Array.from({length:12},(_,i)=>`${i+6}:00`),grad=(ctx,c)=>{const g=ctx.createLinearGradient(0,0,0,300);g.addColorStop(0,c+'aa');g.addColorStop(1,c+'05');return g};
const ch=(id,type,data,opt={})=>new Chart($('#'+id),{type,data,options:{plugins:{legend:{display:data.datasets.length>1||['doughnut','pie'].includes(type),position:'bottom'}},...opt}});
const cx=id=>$('#'+id).getContext('2d');
ch('c1','line',{labels:hrs,datasets:[{label:'Total',data:[640,1180,1420,1510,1480,1390,820,1460,1520,1490,1440,1010],borderColor:'#138496',backgroundColor:grad(cx('c1'),'#138496'),fill:true,tension:.4},{label:'Defective',data:[38,70,92,88,81,79,52,84,95,88,77,60],borderColor:'#FF6B35',tension:.4}]});
ch('c2','doughnut',{labels:cats.slice(1).map(c=>c.n),datasets:[{data:cats.slice(1).map(c=>c.v),backgroundColor:cats.slice(1).map(c=>c.c),borderWidth:0}]},{cutout:'68%'});
ch('c3','bar',{labels:hrs,datasets:[{label:'Defects',data:[38,70,92,88,81,79,52,84,95,88,77,60],backgroundColor:'#0A6783',borderRadius:6}]});
ch('c4','bar',{labels:machines.map(m=>m[0]),datasets:[{label:'Efficiency %',data:machines.map(m=>m[3]),backgroundColor:'#138496',borderRadius:6}]},{indexAxis:'y'});
ch('c5','line',{labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],datasets:[{label:'Units',data:[9120,9480,8890,9350,9610,7200,3100],borderColor:'#A3D65C',backgroundColor:grad(cx('c5'),'#A3D65C'),fill:true,tension:.4}]});
ch('c6','pie',{labels:cats.slice(1).map(c=>c.n),datasets:[{data:[.42,.31,.27].map(x=>x*dayLoss),backgroundColor:['#D7263D','#F2C94C','#FF6B35'],borderWidth:0}]});

/* ---------- Live simulation ---------- */
function live(){const k=Math.random()<.88?0:1+Math.floor(Math.random()*3),c=cats[k],p=pick(prods),cf=R(90,99.8).toFixed(1);c.v++;
const b=$('#bin-'+c.n);b.querySelector('[data-v]').textContent=fmt(c.v);b.classList.remove('pop');void b.offsetWidth;b.classList.add('pop');
$('#cp').textContent=p.n;$('#cc').textContent=c.n;$('#cf').textContent=cf+'%';$('#bl').textContent=c.n+' '+cf+'%';$('#obj').className='fa-solid obj '+p.i;
const bb=$('#bbox');bb.style.borderColor=c.c;bb.style.boxShadow='0 0 14px '+c.c;bb.firstElementChild.style.background=c.c;bb.firstElementChild.style.color=k==0||k==2?'#10202f':'#fff';
$('#fps').textContent=Math.round(R(58,61));$('#item').style.background=c.c;
const r=mk(k);r.p=p.p;r.n=p.n;r.i=p.i;r.t=new Date();r.loss=k?p.p:0;r.cf=+cf;rows.unshift(r);draw();drawCats();
if(k&&Math.random()<.4){const a=[k==3?'Critical':'Warning',`${c.n} ${p.n} detected on ${r.cam} (${cf}%)`,k==3?'fa-fire':'fa-triangle-exclamation'];$('#al').insertAdjacentHTML('afterbegin',alHTML(a));$('#bc').textContent=+$('#bc').textContent+1}}
setInterval(live,2800);

/* ---------- Boot ---------- */
cn();setTimeout(()=>document.querySelectorAll('.pb i').forEach(i=>i.style.width=i.dataset.w),300);
