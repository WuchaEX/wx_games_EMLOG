<!-- Plinko 弹珠台游戏内容（原生渲染） -->
<style>
.plinko-game-root{display:flex;flex-direction:column;align-items:center;gap:0;padding:10px 0;width:100%}
.plinko-game-root .bar-row1,.plinko-game-root .bar-row2{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;width:100%;max-width:760px}
.plinko-game-root .bar-row1{padding:8px 0 6px;margin-bottom:4px}
.plinko-game-root .bar-row2{padding:6px 0 8px;margin-bottom:4px}
.plinko-game-root .canvas-shell{position:relative;background:#1e1b18;border-radius:14px;box-shadow:0 4px 32px rgba(0,0,0,.45);overflow:hidden;width:100%;max-width:760px}
.plinko-game-root canvas{display:block;width:100%;height:auto;aspect-ratio:760/570}
.plinko-game-root .pill{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-radius:28px;padding:0 14px;height:35px;box-sizing:border-box;font-size:13px;white-space:nowrap}
.plinko-game-root .pill label{color:#8f867a;font-size:11px;text-transform:uppercase;letter-spacing:.5px}
.plinko-game-root .pill select,.plinko-game-root .pill input{background:transparent;border:none;color:#e8ddd0;font-size:13px;font-weight:600;outline:none;padding:2px 0}
.plinko-game-root .pill select option{background:#1e1b18;color:#e8ddd0}
.plinko-game-root .pill input{width:44px;text-align:center}
.plinko-game-root .pill input::-webkit-inner-spin-button{opacity:0}
.plinko-game-root .balance-pill{background:linear-gradient(135deg,rgba(226,176,74,.15),rgba(205,127,50,.1));border-color:rgba(226,176,74,.2)}
.plinko-game-root .balance-pill strong{color:#e2b04a;font-size:16px;font-weight:700;min-width:50px;text-align:center}
.plinko-game-root .btn{border:none;border-radius:26px;padding:10px 22px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit}
.plinko-game-root .btn:active{transform:scale(.96)}
.plinko-game-root .btn:disabled{opacity:.35;cursor:default;transform:none}
.plinko-game-root .btn-primary{background:linear-gradient(135deg,#e2b04a,#cd7f32);color:#15120f}
.plinko-game-root .btn-primary:hover:not(:disabled){box-shadow:0 0 18px rgba(226,176,74,.3)}
.plinko-game-root .btn-ghost{background:transparent;border:1px solid #332f2a;color:#8f867a;height:35px;box-sizing:border-box}
.plinko-game-root .btn-ghost:hover{color:#e8ddd0;border-color:#b8935a}
.plinko-game-root .btn-sm{padding:6px 14px;font-size:12px;border-radius:20px}
.plinko-game-root .btn-adj{background:rgba(226,176,74,.12);border:1px solid rgba(226,176,74,.2);color:#e2b04a;font-size:13px;font-weight:700;width:28px;height:24px;border-radius:6px;cursor:pointer;line-height:1;padding:0;transition:all .15s}
.plinko-game-root .btn-adj:hover{background:rgba(226,176,74,.25)}
.plinko-game-root .toggle-wrap{display:flex;align-items:center;gap:4px;font-size:13px;color:#8f867a;height:35px}
.plinko-game-root .auto-interval-select{background:#1e1b18;color:#e8ddd0;border:1px solid #332f2a;border-radius:17px;padding:0 10px;height:35px;font-size:12px;cursor:pointer;outline:none;-webkit-appearance:none;appearance:none;box-sizing:border-box}
.plinko-game-root .toggle{width:38px;height:22px;border-radius:11px;background:#332f2a;position:relative;cursor:pointer;transition:background .25s}
.plinko-game-root .toggle.active{background:#e2b04a}
.plinko-game-root .toggle::after{content:'';width:16px;height:16px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;transition:left .25s}
.plinko-game-root .toggle.active::after{left:19px}
.plinko-game-root .log{width:100%;max-width:760px;max-height:180px;overflow-y:auto;background:#1e1b18;border-radius:12px;padding:2px 0;margin-top:14px;border:1px solid #332f2a;font-size:12px;scrollbar-width:thin;scrollbar-color:#332f2a transparent}
.plinko-game-root .log .row{display:flex;justify-content:space-between;align-items:center;padding:7px 14px;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px}
.plinko-game-root .log .row:last-child{border-bottom:none}
.plinko-game-root .log .row-head{color:#bbb;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plinko-game-root .log .profit{color:#4aa36b}.plinko-game-root .log .loss{color:#e0554a}.plinko-game-root .log .even{color:#8f867a}
.plinko-game-root .bin-labels{display:flex;margin:0 auto;max-width:760px;width:100%;height:42px;background:#1e1b18;border-radius:0 0 14px 14px;box-shadow:0 4px 32px rgba(0,0,0,.45)}
.plinko-game-root .bin-label-item{flex:1;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;position:relative;border-right:1px solid rgba(0,0,0,.2);text-shadow:0 1px 4px rgba(0,0,0,.5)}
.plinko-game-root .bin-label-item:last-child{border-right:none}
@keyframes flashGold{0%{box-shadow:0 0 0 rgba(226,176,74,0)}50%{box-shadow:0 0 28px rgba(226,176,74,.5)}100%{box-shadow:0 0 0 rgba(226,176,74,0)}}
.plinko-game-root .canvas-shell.flash{animation:flashGold .6s ease-out}

/* AI 成员面板 */
.plinko-game-root .member-panel{padding:8px 0 4px;max-width:760px;width:100%;text-align:center}
.plinko-game-root .member-chip{display:inline-flex;align-items:center;gap:3px;padding:4px 10px;height:35px;box-sizing:border-box;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:18px;font-size:11px;color:#bbb;transition:all .2s;min-width:88px;justify-content:center}
.plinko-game-root .member-chip.locked{opacity:.4;cursor:default}
.plinko-game-root .member-chip.active{background:rgba(226,176,74,.18);border-color:#e2b04a;color:#e2b04a;font-weight:600;box-shadow:inset 0 0 0 1px rgba(226,176,74,0.6)}
.plinko-game-root .member-chip.chip-flash{animation:chipFlash .7s ease-out}
.plinko-game-root .member-chip.busy{cursor:not-allowed;opacity:.5}
@keyframes chipFlash{0%{transform:scale(1);box-shadow:0 0 0 rgba(226,176,74,0)}20%{transform:scale(1.18);box-shadow:0 0 18px rgba(226,176,74,.8)}100%{transform:scale(1);box-shadow:0 0 0 rgba(226,176,74,0)}}
</style>

<div class="plinko-game-root">
  <div class="bar bar-row1">
    <div class="pill balance-pill"><span style="font-size:18px">💎</span><strong id="balanceDisplay">200</strong></div>
    <button class="btn btn-primary" id="dropBtn" style="height:35px;padding:0 18px">投放</button>
    <div class="toggle-wrap" id="toggleWrap"><div class="toggle" id="toggleBtn"></div> 自动</div>
    <select id="autoInterval" class="auto-interval-select" title="自动投放间隔">
      <option value="500">500ms</option>
      <option value="750">750ms</option>
      <option value="1000" selected>1000ms</option>
    </select>
  </div>
  <div class="bar bar-row2">
    <div class="pill"><label>下注</label><input type="number" id="betAmount" value="1" min="1" max="10000" style="width:52px"></div>
    <div class="pill"><label>风险</label><select id="riskSelect"><option value="0">低</option><option value="1" selected>中</option><option value="2">高</option></select></div>
    <div class="pill"><label>行数</label><select id="rowSelect"><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option><option value="16" selected>16</option></select></div>
    <button class="btn btn-ghost btn-sm" id="clearLog">清记录</button>
  </div>

  <div class="member-panel" id="memberPanel"></div>

  <div class="canvas-shell" id="canvasShell">
    <canvas id="plinkoCanvas" width="760" height="570"></canvas>
  </div>
  <div class="bin-labels" id="binLabels"></div>
  <div class="log" id="logContainer"></div>
</div>

<style>
@media (max-width:900px) {
  .plinko-game-root .mav{display:none !important}
  .plinko-game-root .mdavatar{display:none !important}
  .plinko-game-root .balance-pill > span:first-child{display:none !important}
  .plinko-game-root .pill{font-size:12px;padding:0 10px;height:32px}
  .plinko-game-root .pill input[type="number"],.plinko-game-root .pill select{font-size:12px}
  .plinko-game-root #dropBtn{height:32px;font-size:13px;padding:0 14px}
  .plinko-game-root .toggle-wrap{font-size:12px}
  .plinko-game-root .auto-interval-select{height:30px;font-size:12px}
  .plinko-game-root .member-chip{padding:3px 8px;font-size:10px;min-width:72px}
  .plinko-game-root .member-chip .mname{font-size:10px}
  /* 统一上方几行间隔 */
  .plinko-game-root .bar-row1{gap:8px;padding:14px 0 10px;margin-bottom:6px}
  .plinko-game-root .bar-row2{gap:8px;padding:8px 0 10px;margin-bottom:6px}
  .plinko-game-root .member-panel{margin:4px 0}
}
</style>

<script>
// ============== PHP 注入 ==============
var VGAME_URL = "<?php echo $game_url; ?>".replace(/\/games\/plinko\/.*$/, "/games/ddz/assets/");
var balance = <?php echo floatval($saved_balance); ?>;
console.log('[Plinko] init: VGAME_URL='+VGAME_URL+' balance='+balance+' window._plinko_uid='+(window._plinko_uid||0));

// ============== 音效 ==============
var AUDIO_URL = '<?php echo $game_url; ?>audio/';
function playSfx(name) {
    try {
        var a = new Audio(AUDIO_URL + name + '.mp3');
        a.volume = 0.5;
        a.play().catch(function(){});
    } catch(e) {}
}

// ============== 常量 ==============
const ROW_COUNTS=[8,9,10,11,12,13,14,15,16];
const RISK_NAMES=['低','中','高'];
const BIN_PAYOUTS={
  8:{0:[[5.6,2.1,1.1,1,0.5,1,1.1,2.1,5.6],[13,3,1.3,0.7,0.4,0.7,1.3,3,13],[29,4,1.5,0.3,0.2,0.3,1.5,4,29]]},
  9:{0:[[5.6,2,1.6,1,0.7,0.7,1,1.6,2,5.6],[18,4,1.7,0.9,0.5,0.5,0.9,1.7,4,18],[43,7,2,0.6,0.2,0.2,0.6,2,7,43]]},
  10:{0:[[8.9,3,1.4,1.1,1,0.5,1,1.1,1.4,3,8.9],[22,5,2,1.4,0.6,0.4,0.6,1.4,2,5,22],[76,10,3,0.9,0.3,0.2,0.3,0.9,3,10,76]]},
  11:{0:[[8.4,3,1.9,1.3,1,0.7,0.7,1,1.3,1.9,3,8.4],[24,6,3,1.8,0.7,0.5,0.5,0.7,1.8,3,6,24],[120,14,5.2,1.4,0.4,0.2,0.2,0.4,1.4,5.2,14,120]]},
  12:{0:[[10,3,1.6,1.4,1.1,1,0.5,1,1.1,1.4,1.6,3,10],[33,11,4,2,1.1,0.6,0.3,0.6,1.1,2,4,11,33],[170,24,8.1,2,0.7,0.2,0.2,0.2,0.7,2,8.1,24,170]]},
  13:{0:[[8.1,4,3,1.9,1.2,0.9,0.7,0.7,0.9,1.2,1.9,3,4,8.1],[43,13,6,3,1.3,0.7,0.4,0.4,0.7,1.3,3,6,13,43],[260,37,11,4,1,0.2,0.2,0.2,0.2,1,4,11,37,260]]},
  14:{0:[[7.1,4,1.9,1.4,1.3,1.1,1,0.5,1,1.1,1.3,1.4,1.9,4,7.1],[58,15,7,4,1.9,1,0.5,0.2,0.5,1,1.9,4,7,15,58],[420,56,18,5,1.9,0.3,0.2,0.2,0.2,0.3,1.9,5,18,56,420]]},
  15:{0:[[15,8,3,2,1.5,1.1,1,0.7,0.7,1,1.1,1.5,2,3,8,15],[88,18,11,5,3,1.3,0.5,0.3,0.3,0.5,1.3,3,5,11,18,88],[620,83,27,8,3,0.5,0.2,0.2,0.2,0.2,0.5,3,8,27,83,620]]},
  16:{0:[[16,9,2,1.4,1.4,1.2,1.1,1,0.5,1,1.1,1.2,1.4,1.4,2,9,16],[110,41,10,5,3,1.5,1,0.5,0.3,0.5,1,1.5,3,5,10,41,110],[1000,130,26,9,4,2,0.2,0.2,0.2,0.2,0.2,2,4,9,26,130,1000]]}
};
function getPayout(rc,r,b){return (BIN_PAYOUTS[rc]?.[0]?.[r]?.[b]) ?? 1;}
function interpolateRGB(a,b,steps){const r=[];for(let i=0;i<steps;++i){const t=i/(steps-1);r.push({r:Math.round(a.r+(b.r-a.r)*t),g:Math.round(a.g+(b.g-a.g)*t),b:Math.round(a.b+(b.b-a.b)*t)});}return r;}

// ============== 成员系统状态 ==============
var selectedMember = '';
var memberLevel = 0;
var memberParams = {};
var memberData = {};
var memberConfig = {};
var memberExp = 0;
var expConfig = {mode:'ball', mult:1}; // EXP 获取模式（来自 config）

// ============== 状态 ==============
let betAmount=1,risk=1,rowCount=16,autoBet=false,autoTimer=null,logEntries=[],ballBetMap={};
let plinkoTotalBet=0,plinkoTotalPayout=0,plinkoBallCount=0,plinkoPlayCount=0;

function getActiveBallCount(){return Object.keys(ballBetMap).length}
function getMinBet(){return Math.max(1,Math.ceil(balance*0.01));}
function lockSettings(lock){
  betInput.disabled=lock;riskSelect.disabled=lock;rowSelect.disabled=lock;dropBtn.disabled=lock;
}

// ============== DOM ==============
const canvas=document.getElementById('plinkoCanvas');
const W=760,H=570;
const balDisplay=document.getElementById('balanceDisplay');
const betInput=document.getElementById('betAmount');
const riskSelect=document.getElementById('riskSelect');
const rowSelect=document.getElementById('rowSelect');
const dropBtn=document.getElementById('dropBtn');
const clearBtn=document.getElementById('clearLog');
const logContainer=document.getElementById('logContainer');
const binLabels=document.getElementById('binLabels');
const toggleBtn=document.getElementById('toggleBtn');
const canvasShell=document.getElementById('canvasShell');

function updateUI(){
  balDisplay.textContent=balance%1===0?balance:balance.toFixed(1);
  try{window.parent.postMessage({type:'plinko_balance',balance:balance},'*');}catch(e){}
  betAmount=parseInt(betInput.value,10)||1;
  const minBet=getMinBet();
  if(betAmount<minBet){betInput.value=minBet;betAmount=minBet;}
  if(betAmount>balance&&balance>0) betInput.value=Math.floor(balance);
  risk=parseInt(riskSelect.value,10);
  rowCount=parseInt(rowSelect.value,10);
  renderBinLabels();
}

function addLog(rn,rc,pm,bet,profit,bin,bal,bonus,rawP){
  plinkoBallCount++;
  const f=n=>{n=Number(n)||0;return n.toFixed(1);};
  const rawProfit = rawP!==undefined ? +rawP : +profit;
  logEntries.unshift({riskName:rn,rowCount:rc,bet:f(bet),payoutMult:f(pm),profit:f(profit),rawProfit:f(rawProfit),bonus:bonus||''});
  if(logEntries.length>50)logEntries.length=50;
  renderLog();
}
function renderLog(){
  logContainer.innerHTML=logEntries.map(e=>{
    const cls=+e.profit>0?'profit':(+e.profit<0?'loss':'even');
    const sign=+e.profit>0?'+':'';
    const signRaw = +e.rawProfit>0?'+':'';
    const hasBonus = (e.bonus&&e.bonus!=='');
    const bonusTag = hasBonus
      ? ' <span style="font-size:10px;color:#fdcb6e;background:rgba(253,203,110,0.10);border:1px solid rgba(253,203,110,0.3);padding:2px 7px;border-radius:6px;flex-shrink:0;white-space:nowrap;">'+e.bonus+'</span>'
      : '';
    const rawProfitTag = (hasBonus && +e.rawProfit!==+e.profit)
      ? '<span style="font-size:11px;color:#8f867a;text-decoration:line-through;margin-right:6px;">'+signRaw+e.rawProfit+'</span>'
      : '';
    return '<div class="row" style="display:flex;justify-content:space-between;align-items:center;gap:8px;"><div class="row-head" style="color:#bbb;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;min-width:0;">难度：'+e.riskName+' | 行数：'+e.rowCount+' | 下注：'+e.bet+' | 获奖：×'+e.payoutMult+'</div>'+bonusTag+'<span class="'+cls+'" style="font-size:14px;font-weight:700;flex-shrink:0;min-width:80px;text-align:right;">'+rawProfitTag+sign+e.profit+'</span></div>';
  }).join('');
}

function renderBinLabels(){
  const payouts=BIN_PAYOUTS[rowCount]?.[0]?.[risk]||[];
  const count=payouts.length;
  const red={r:190,g:40,b:40},yellow={r:210,g:170,b:30};
  const colors=interpolateRGB(red,yellow,Math.ceil(count/2));
  const allColors=[...colors,...colors.toReversed().slice(count%2===0?0:1)];
  binLabels.innerHTML=allColors.map((c,i)=>'<div class="bin-label-item" style="background:rgb('+c.r+','+c.g+','+c.b+')">×'+payouts[i]+'</div>').join('');
}

// ============== Matter.js 引擎 ==============
const PADDING_X=52,PADDING_TOP=36,PADDING_BOTTOM=28;
const PIN_CAT=0x0001,BALL_CAT=0x0002;
const frictionAirByRows={8:0.0395,9:0.041,10:0.038,11:0.0355,12:0.0414,13:0.0437,14:0.0401,15:0.0418,16:0.0364};
let engine,render,runner,pins=[],walls=[],sensor,pinsLastRowX=[];

function createEngine(){
  if(engine){Matter.Render.stop(render);Matter.Runner.stop(runner);Matter.World.clear(engine.world,false);Matter.Engine.clear(engine);}
  engine=Matter.Engine.create({timing:{timeScale:1}});
  render=Matter.Render.create({engine,canvas,options:{width:W,height:H,background:'#1a1612',wireframes:false}});
  runner=Matter.Runner.create();
  pins=[];pinsLastRowX=[];walls=[];ballBetMap={};
  placePinsAndWalls();
  sensor=Matter.Bodies.rectangle(W/2,H,W,10,{isSensor:true,isStatic:true,render:{visible:false}});
  Matter.Composite.add(engine.world,sensor);
  Matter.Events.on(engine,'collisionStart',function(ev){
    ev.pairs.forEach(function(p){const a=p.bodyA,b=p.bodyB;if(a===sensor)handleBin(b);else if(b===sensor)handleBin(a);});
  });
  Matter.Events.on(engine,'afterUpdate',function(){
    if(document.hidden) return;
    Matter.Composite.allBodies(engine.world).forEach(function(b){
      if(b.collisionFilter && b.collisionFilter.category===BALL_CAT && b.position.y > H+30){
        if(ballBetMap[b.id]!==undefined) handleBin(b);
      }
    });
  });
  Matter.Render.run(render);Matter.Runner.run(runner,engine);
}

function placePinsAndWalls(){
  if(pins.length){Matter.Composite.remove(engine.world,pins);pins=[];}
  if(walls.length){Matter.Composite.remove(engine.world,walls);walls=[];}
  pinsLastRowX=[];
  const pinDistX=(W-PADDING_X*2)/((3+rowCount-1)-1);
  const pinR=(24-rowCount)/2;
  for(let r=0;r<rowCount;r++){
    const y=PADDING_TOP+((H-PADDING_TOP-PADDING_BOTTOM)/(rowCount-1))*r;
    const rowPadX=PADDING_X+((rowCount-1-r)*pinDistX)/2;
    for(let c=0;c<3+r;c++){
      const x=rowPadX+((W-rowPadX*2)/(3+r-1))*c;
      const pin=Matter.Bodies.circle(x,y,pinR,{isStatic:true,render:{fillStyle:'#c89460'},collisionFilter:{category:PIN_CAT,mask:BALL_CAT}});
      pins.push(pin);
      if(r===rowCount-1) pinsLastRowX.push(x);
    }
  }
  Matter.Composite.add(engine.world,pins);
  const firstPinX=pins[0].position.x;
  const angle=Math.atan2(firstPinX-pinsLastRowX[0],H-PADDING_TOP-PADDING_BOTTOM);
  const wallX=firstPinX-(firstPinX-pinsLastRowX[0])/2-pinDistX*.25;
  walls.push(
    Matter.Bodies.rectangle(wallX,H/2,10,H,{isStatic:true,angle,render:{visible:false}}),
    Matter.Bodies.rectangle(W-wallX,H/2,10,H,{isStatic:true,angle:-angle,render:{visible:false}})
  );
  Matter.Composite.add(engine.world,walls);
}

function dropBall(isExtra){
  const minBet=getMinBet();
  if(betAmount<minBet){betAmount=minBet;betInput.value=minBet;}
  if(betAmount>balance){betAmount=Math.floor(balance);betInput.value=betAmount;}
  if(betAmount<=0) return;
  const pinR=(24-rowCount)/2,ballR=pinR*2;
  const offsetRange=((W-PADDING_X*2)/((3+rowCount-1)-1))*.8;
  var extraOffset=0;
  if(selectedMember==='jiyeon'&&memberLevel>0){
    extraOffset=parseFloat(memberParams.offset||'0');
  }
  const x=W/2+(Math.random()-.5)*offsetRange+extraOffset*(Math.random()>0.5?1:-1);
  var restitution=0.8+(selectedMember==='qri'?parseFloat(memberParams.restitution||'0'):0);
  var ball=Matter.Bodies.circle(x,0,ballR,{
    restitution:restitution,friction:.5,frictionAir:frictionAirByRows[rowCount]||.04,
    collisionFilter:{category:BALL_CAT,mask:PIN_CAT},
    render:{fillStyle:'#e0704a'}
  });
  if(selectedMember==='hyomin'&&memberLevel>0){
    var speedMul=1-parseFloat(memberParams.speed||'0');
    // 降低球的下落速度：增大空气阻力
    ball.frictionAir = (frictionAirByRows[rowCount]||0.04) * (1 + parseFloat(memberParams.speed||'0')*3);
  }
  Matter.Composite.add(engine.world,ball);
  ballBetMap[ball.id]=betAmount;
  if(isExtra) ball._isEunjungExtra = true; // 额外球不再触发额外
  lockSettings(true);
  setTimeout(function(){
    if(ballBetMap[ball.id]!==undefined){ handleBin(ball); }
  },12000);
}

function handleBin(ball){
  if(!ballBetMap[ball.id]) return;
  const bx=ball.position.x;let binIdx=-1;
  for(let i=pinsLastRowX.length-1;i>=0;i--){if(pinsLastRowX[i]<bx){binIdx=i;break}}
  const bet=ballBetMap[ball.id]||betAmount;
  Matter.Composite.remove(engine.world,ball); delete ballBetMap[ball.id];
  if(binIdx<0||binIdx>=pinsLastRowX.length-1){if(getActiveBallCount()===0){lockSettings(false);renderMemberPanel();}return}
  let mult=getPayout(rowCount,risk,binIdx);

  // Boram 技能：低倍槽退还（服务端权威）—前端仅做视觉脉冲
  if(selectedMember==='boram'&&memberLevel>0&&mult<1){
    pulseMemberChip('boram');
  }

  let rawPayout=Math.round(bet*mult*100)/100,rawProfit=rawPayout-bet;

  let bonusTags=[]; // 必须在额外球检查前声明

  // Eunjung 额外球：不扣本，显示纯收益
  if(ball._isEunjungExtra){
    rawProfit = rawPayout; // 与 profit 相同，不显示删除线
    bonusTags.push('🎁 额外球');
  }

  // Soyeon 技能：倍率加成
  let soyeonBonus=0, randMult=1;
  if(selectedMember==='soyeon'&&memberLevel>0){
    var prob=parseFloat(memberParams.prob||'0');
    if(Math.random()<prob){
      var minR=parseFloat(memberParams.min||'0.8');
      var maxR=parseFloat(memberParams.max||'1.5');
      randMult=minR+Math.random()*(maxR-minR);
      soyeonBonus=Math.round(rawPayout*(randMult-1)*100)/100;
      pulseMemberChip('soyeon');
    }
  }

  // Boram 退还（退还损失 × spacing）
  let boramRefund=0;
  if(selectedMember==='boram'&&memberLevel>0&&mult<1){
    var spacing=parseFloat(memberParams.spacing||'0');
    var loss=bet-rawPayout;
    if(spacing>0) boramRefund=Math.round(loss*spacing*100)/100;
  }

  let payout=rawPayout+soyeonBonus+boramRefund,profit=payout-bet;
  // 额外球：profit 强制 = rawPayout（不扣本）
  if(ball._isEunjungExtra) profit = rawPayout;

  // 合并 bonus 标签（追加在已有标签后）
  if(boramRefund>0) bonusTags.push('↩ 退还 +'+boramRefund);
  if(soyeonBonus!==0) bonusTags.push('✨ 倍率 ×'+randMult.toFixed(2));
  let bonusLabel=bonusTags.join(' ');

  balance+=payout;
  plinkoTotalPayout+=payout;
  addLog(RISK_NAMES[risk],rowCount,mult,bet,profit,binIdx,balance,bonusLabel,rawProfit);
  updateUI();
  if(window._plinko_uid>0){
    var xhr=new XMLHttpRequest();
    xhr.open('POST','?plugin=wx_games&game=plinko&plinko_action=log_ball',true);
    xhr.setRequestHeader('Content-Type','application/json');
    xhr.onload=function(){
      try{
        var resp=JSON.parse(xhr.responseText);
        if(resp.code===0&&resp.data){
          if(resp.data.balance!=null){
            balance=parseFloat(resp.data.balance);
            updateUI();
          }
          if(resp.data.exp!=null){
            memberExp=resp.data.exp;
            renderMemberPanel();
          }
        }
      }catch(e){}
    };
    xhr.send(JSON.stringify({betAmount:bet,multiplier:mult,payout:payout,profit:profit,risk:risk,rowCount:rowCount,binIndex:binIdx,_isEunjungExtra:!!ball._isEunjungExtra}));
  }
  // 落槽音效：3x以上播特殊音，否则播普通落槽音
  if(mult >= 3) playSfx('3x'); else playSfx('finish');
  if(profit>0){canvasShell.classList.add('flash');setTimeout(function(){canvasShell.classList.remove('flash');},600);}
  if(getActiveBallCount()===0){ lockSettings(false); renderMemberPanel(); }

  // Eunjung 技能：额外发射（不嵌套，额外球不会再触发）
  if(selectedMember==='eunjung'&&memberLevel>0 && !ball._isEunjungExtra){
    var extraProb=parseFloat(memberParams.prob||'0');
    if(Math.random()<extraProb){
      pulseMemberChip('eunjung');
      dropBall(true);
    }
  }
}

function toggleAuto(on){
  autoBet=on;
  if(on){
    toggleBtn.classList.add('active');
    if(autoTimer) clearInterval(autoTimer);
    const ms=parseInt(document.getElementById('autoInterval').value,10)||1000;
    var actualMs=ms;
    if(selectedMember==='hyomin'&&memberLevel>0){
      actualMs=Math.max(100,ms-parseInt(memberParams.interval||'0'));
    }
    autoTimer=setInterval(function(){
      if(!autoBet) return;
      if(balance>=betAmount){balance-=betAmount;plinkoTotalBet+=betAmount;plinkoPlayCount++;dropBall();updateUI();}
    },actualMs);
  }else{
    toggleBtn.classList.remove('active');
    clearInterval(autoTimer); autoTimer=null;
  }
}

// ============== 事件 ==============
dropBtn.addEventListener('click',function(){updateUI();if(betAmount>balance){return}balance-=betAmount;plinkoTotalBet+=betAmount;plinkoPlayCount++;dropBall();updateUI();});
toggleBtn.addEventListener('click',function(){updateUI();if(!autoBet&&betAmount>balance){return}toggleAuto(!autoBet);});
clearBtn.addEventListener('click',function(){logEntries=[];renderLog();});
betInput.addEventListener('change',updateUI);
riskSelect.addEventListener('change',updateUI);
rowSelect.addEventListener('change',function(){updateUI();createEngine();});

// 后台标签：暂停自动投球
document.addEventListener('visibilitychange',function(){
  if(document.hidden){if(autoBet) toggleAuto(false);}
});

// ============== 成员面板 ==============
var _panelBuilt = false;
function _buildPanel(){
  var el=document.getElementById('memberPanel');
  console.log('[Plinko] _buildPanel: el='+(el?'found':'NULL'));
  if(!el)return;
  var keys=['boram','qri','soyeon','eunjung','hyomin','jiyeon'];
  var ab=VGAME_URL||'';
  function av(k){var c=memberConfig[k]||{};return ab+(c.avatar||(k+'.jpg'));}
  var h='<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:center;">';
  keys.forEach(function(k){
    // 永远生成 img 和 name 结构（即使未解锁也保留）— 后续 renderMemberPanel 根据 unlocked 状态切换显示
    h+='<span class="member-chip" data-member="'+k+'" style="cursor:pointer">';
    h+='<img class="mav" data-set="0" src="'+av(k)+'" style="width:18px;height:18px;border-radius:50%;object-fit:cover;display:none" onerror="this.style.display=none" onload="this.style.display=&apos;&apos;">';
    h+='<span class="mname"></span>';
    h+='<span class="mlock" style="display:none;font-size:13px;">\uD83D\uDD12</span>';
    h+='</span>';
  });
  h+='<div class="mdetail" style="margin-top:6px;padding:10px 12px;background:rgba(255,255,255,.03);border-radius:10px;font-size:11px;color:#bbb;display:none;">';
  // 横向布局：左侧头像（占据3行高度），右侧3行内容
  h+='<div style="display:flex;gap:12px;align-items:stretch;">';
  // 左：头像（大，占满 3 行高度）
  h+='<img class="mdavatar" data-member="" style="width:56px;height:56px;border-radius:50%;border:2px solid #e2b04a;flex-shrink:0;align-self:center;display:none" onerror="this.style.display=none" onload="this.style.display=&apos;&apos;">';
  // 右：3行内容
  h+='<div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;min-height:56px;">';
  // 第1行：名字 + 等级 + 升级按钮（左聚拢）
  h+='<div style="display:flex;align-items:center;gap:8px;"><span class="mdname" style="font-weight:700;color:#e2b04a;font-size:14px;"></span><span class="mdlv" style="font-size:11px;color:#b8935a;"></span><button class="mdupgrade btn-upgrade" style="padding:3px 10px;font-size:11px;border-radius:14px;background:linear-gradient(135deg,#e2b04a,#cd7f32);color:#15120f;border:none;cursor:pointer;font-weight:600;display:none;flex-shrink:0;"></button></div>';
  // 第2行：技能描述 + 效果值 + EXP 规则徽章（同行 flex-wrap）
  h+='<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><span class="mdeff" style="color:#e0704a;font-weight:500;font-size:12px;flex:1;min-width:0;text-align:left;"></span><span class="mdexprule" style="font-size:10px;color:#b8935a;background:rgba(226,176,74,0.10);border:1px solid rgba(226,176,74,0.25);padding:1px 6px;border-radius:8px;flex-shrink:0;white-space:nowrap;">EXP·<span class="mdexpmode"></span>×<span class="mdexpmult"></span></span></div>';
  // 第3行：EXP进度条 + 文本
  h+='<div style="display:flex;align-items:center;gap:6px;"><div style="flex:1;height:5px;background:rgba(255,255,255,.1);border-radius:3px;"><div class="mdbar-fg" style="height:100%;width:0%;background:linear-gradient(90deg,#e2b04a,#cd7f32);border-radius:3px;transition:width .3s;"></div></div><span class="mdprog" style="font-size:10px;color:#8f867a;min-width:48px;text-align:right;"></span></div>';
  h+='</div></div></div>';
  el.innerHTML=h;
  _panelBuilt=true;
  el.querySelectorAll('.member-chip').forEach(function(chip){
    chip.addEventListener('click',function(){
      var k=this.getAttribute('data-member');
      if(!memberData[k]||!memberData[k].unlocked) return;
      if(getActiveBallCount()>0) return; // 有弹珠在飞时禁止切换 AI
      applyMember(k,memberData[k].level||1);
      renderMemberPanel();
      pulseMemberChip(k);
    });
  });
  // 升级按钮事件
  var upBtn = el.querySelector('.mdupgrade');
  if(upBtn){
    upBtn.addEventListener('click', function(){
      if(!selectedMember) return;
      upgradeMember(selectedMember);
    });
  }
  console.log('[Plinko] _buildPanel done, chips='+el.querySelectorAll('.member-chip').length);
}

function renderMemberPanel(){
  var el=document.getElementById('memberPanel');if(!el)return;
  if(!_panelBuilt||!el.querySelector('.member-chip')){_buildPanel();}
  var keys=['boram','qri','soyeon','eunjung','hyomin','jiyeon'];
  var ab=VGAME_URL||'';
  function av(k){var c=memberConfig[k]||{};return ab+(c.avatar||(k+'.jpg'));}
  keys.forEach(function(k){
    var chip=el.querySelector('.member-chip[data-member="'+k+'"]');if(!chip)return;
    var c=memberConfig[k]||{};
    var u=memberData[k]&&memberData[k].unlocked;
    var l=memberData[k]?(memberData[k].level||1):0;
    var a=selectedMember===k;
    chip.className='member-chip'+(a?' active':'')+(u?' unlocked':' locked')+(getActiveBallCount()>0?' busy':'');
  var img=chip.querySelector('img.mav');
  var nameEl=chip.querySelector('.mname');
  var lockEl=chip.querySelector('.mlock');
    if(u){
      if(img){
        if(img.getAttribute('data-set')==='0'){
          img.setAttribute('data-set','1');
          img.src=av(k);
        }
        // 已解锁就显示头像（onload 会把 display 切到 ''，但若是缓存立即加载的图则需手动显示）
        if(img.complete && img.naturalWidth>0) img.style.display='';
      }
      if(nameEl) nameEl.textContent=c.name||k;
      if(lockEl) lockEl.style.display='none';
    }else{
      if(img) img.style.display='none';
      if(nameEl) nameEl.textContent='';
      if(lockEl) lockEl.style.display='';
    }
    var lvOld=chip.querySelector('.mlv');if(lvOld) lvOld.remove();
    if(a&&l){
      var lvEl=document.createElement('small');
      lvEl.className='mlv';lvEl.style.cssText='opacity:.7';lvEl.textContent='Lv'+l;
      chip.appendChild(lvEl);
    }
  });
  var expEl=el.querySelector('.mexp');if(expEl) expEl.textContent='';
  var detail=el.querySelector('.mdetail');
  if(selectedMember){
    var ac=memberConfig[selectedMember]||{};
    var avs=ac.levels||[];
    var lvToShow=memberLevel||1;
    var nc=0,cps={};
    avs.forEach(function(lv){
      if(lv.level===lvToShow+1) nc=lv.exp_cost;
      if(lv.level===lvToShow){for(var pp in lv.params) cps[pp]=lv.params[pp];}
    });
    var pr=nc>0?Math.round(memberExp/nc*100):100;
    // 参数汉化映射：key → 中文标签，数值带单位
    var PARAM_LABELS = {
      spacing: '退还比例', restitution: '反弹增量',
      prob: '触发率', min: '最低倍率', max: '最高倍率',
      speed: '减速比例', interval: '间隔缩短', offset: '偏移距离'
    };
    var PARAM_PCT = {spacing:1, restitution:1, prob:1, speed:1}; // 值×100 显示为百分比
    var PARAM_UNIT = {min:'\u00d7', max:'\u00d7', interval:'ms', offset:'px'}; // 绝对单位
    var sf=[];
    for(var pk in cps){
      var v=cps[pk];
      var label=PARAM_LABELS[pk]||pk;
      var valStr;
      if(PARAM_PCT[pk]) valStr=Math.round(v*100)+'%';
      else if(PARAM_UNIT[pk]) valStr=v+PARAM_UNIT[pk];
      else valStr=v;
      sf.push(label+': '+valStr);
    }
    if(detail){
      detail.style.display='block';
      var mdAv=detail.querySelector('.mdavatar');
      if(mdAv){
        var lastAv = mdAv.getAttribute('data-member') || '';
        if(lastAv !== selectedMember){
          mdAv.src = av(selectedMember);
          mdAv.setAttribute('data-member', selectedMember);
        }
        if(mdAv.complete && mdAv.naturalWidth>0) mdAv.style.display='';
      }
      // 第1行：名字 + 等级
      detail.querySelector('.mdname').textContent = ac.name||selectedMember;
      var mdLv = detail.querySelector('.mdlv');
      if(mdLv) mdLv.textContent = 'Lv' + lvToShow;
      // 第2行：技能描述 + 效果值 + 升级按钮
      var effText = (ac.skill_desc||'') + (sf.length ? ' · ' + sf.join('  |  ') : '');
      detail.querySelector('.mdeff').textContent = effText || '无参数';
      detail.querySelector('.mdbar-fg').style.width=Math.min(pr,100)+'%';
      detail.querySelector('.mdprog').textContent=memberExp+'/'+(nc||'MAX');
      // 升级按钮（右上）
      var upBtn = detail.querySelector('.mdupgrade');
      if(upBtn){
        // 始终重置为橙色渐变（修复升级后残留绿色 bug）
        upBtn.style.background='linear-gradient(135deg,#e2b04a,#cd7f32)';
        upBtn.style.color='#15120f';
        if(nc>0 && memberExp>=nc){
          upBtn.style.display='inline-block';
          upBtn.style.opacity='1';
          upBtn.style.cursor='pointer';
          upBtn.textContent='⬆ Lv'+(lvToShow+1);
        }else if(nc>0){
          upBtn.style.display='inline-block';
          upBtn.style.opacity='0.5';
          upBtn.style.cursor='not-allowed';
          upBtn.textContent='EXP不够';
        }else{
          upBtn.style.display='none';
        }
      }
      // EXP 规则徽章
      var expModeEl = detail.querySelector('.mdexpmode');
      var expMultEl = detail.querySelector('.mdexpmult');
      if(expModeEl) expModeEl.textContent = (expConfig.mode === 'payout') ? '下注等值' : '每球+1';
      if(expMultEl) expMultEl.textContent = (expConfig.mult || 1).toString().replace(/\.0$/, '');
    }
  }else{
    if(detail) detail.style.display='none';
  }
}

function pulseMemberChip(key){
  var chip=document.querySelector('.member-chip[data-member="'+key+'"]');
  if(!chip) return;
  chip.classList.remove('chip-flash');
  void chip.offsetWidth;
  chip.classList.add('chip-flash');
  setTimeout(function(){chip.classList.remove('chip-flash');},700);
}

function applyMember(key,level){
  selectedMember=key;
  memberLevel=level||1;
  var levels=(memberConfig[key]||{}).levels||[];
  memberParams={};
  levels.forEach(function(lv){if(lv.level===memberLevel) memberParams=lv.params||{};});
  renderMemberPanel();
  renderBinLabels(); // 重新渲染底部倍率标签，反映 boram 视觉效果
}

function upgradeMember(key){
  if(!window._plinko_uid) return;
  var fd=new FormData();
  fd.append('member', key);
  var xhr=new XMLHttpRequest();
  xhr.open('POST','?plugin=wx_games&game=plinko&plinko_action=level_up',true);
  xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
  xhr.onload=function(){
    try{
      var r=JSON.parse(xhr.responseText);
      console.log('[Plinko] upgradeMember response:', r);
      if(r.code===0){
        // 更新本地状态
        if(r.data && r.data.exp!=null) memberExp=r.data.exp;
        if(memberData[key] && r.data && r.data.level!=null){
          memberData[key].level=r.data.level;
          memberLevel=r.data.level;
          applyMember(key, r.data.level);
        }
        pulseMemberChip(key);
        playSfx('levelup');
        // 升级后金币缩放反馈
        var btn=document.querySelector('.mdupgrade');
        if(btn){ btn.textContent='✓ 已升级'; btn.style.background='#4aa36b'; setTimeout(function(){renderMemberPanel();},1200); }
      }else{
        alert(r.message||'升级失败');
        renderMemberPanel();
      }
    }catch(e){ console.error('[Plinko] upgradeMember error:', e); }
  };
  xhr.send(fd);
}

function loadMembers(){
  console.log('[Plinko] loadMembers: uid='+window._plinko_uid);
  if(!window._plinko_uid) { console.log('[Plinko] loadMembers ABORT: no uid'); return; }
  var xhr=new XMLHttpRequest();
  xhr.open('GET','?plugin=wx_games&game=plinko&plinko_action=get_members',true);
  xhr.onload=function(){
    console.log('[Plinko] loadMembers response: status='+xhr.status+' text='+xhr.responseText.substring(0,200));
    try{
      var r=JSON.parse(xhr.responseText);
      console.log('[Plinko] loadMembers parsed: code='+r.code+' hasData='+!!r.data);
      if(r.code===0&&r.data){
        memberData=r.data.members||{};
        memberConfig=r.data.config||{};
        memberExp=r.data.exp||0;
        expConfig={mode:r.data.exp_mode||'ball', mult:parseFloat(r.data.exp_multiplier)||1};
        console.log('[Plinko] loadMembers: exp='+memberExp+' members='+Object.keys(memberData).length);
        var last=localStorage.getItem('plinko_member');
        if(last&&memberData[last]&&memberData[last].unlocked){
          applyMember(last,memberData[last].level||1);
        }
        renderMemberPanel();
      }
    }catch(e){ console.error('[Plinko] loadMembers parse error:', e); }
  };
  xhr.send();
}

// ============== 启动 ==============
// 先渲染空骨架（保证面板可见），再异步加载数据
console.log('[Plinko] === BOOT START ===');
_buildPanel();
renderMemberPanel();
loadMembers();
console.log('[Plinko] === BOOT END ===');
updateUI();
createEngine();
renderBinLabels();

// 兜底轮询：500ms 检查锁定状态
let _lockState=false;
const _origLock=lockSettings;
lockSettings=function(lock){_lockState=lock;_origLock(lock);};
setInterval(function(){if(_lockState&&getActiveBallCount()===0){_origLock(false);_lockState=false;}},500);
</script>