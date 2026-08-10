<?php
session_start();
require __DIR__ . '/database/connection.php';
$pdo = getPDO();
$user = $_SESSION['user'] ?? null;
$config = require __DIR__ . '/config/config.php';
$storageCfg = require __DIR__ . '/config/storage.php';
$isAdmin = false;
if($user){
    try { $role = $pdo->query("SELECT role FROM users WHERE id=".(int)$user['id'])->fetchColumn(); $isAdmin = ($role==='admin'); } catch(Exception $e){ $isAdmin=false; }
}
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($config['app_name'])?> - Enterprise File Server</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>
tailwind.config={darkMode:'class', theme:{extend:{colors:{darkbg:'#0f1117', darkcard:'#1a1d27', darkborder:'#2a2d3a'}}}}
</script>
<style>
*{font-family:Inter,sans-serif}
.mono{font-family:'JetBrains Mono',monospace}
::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:99px}
.dark ::-webkit-scrollbar-thumb{background:#3a3d4d}
.card-hover{transition:all .25s cubic-bezier(.4,0,.2,1)}
.card-hover:hover{transform:translateY(-4px) scale(1.01);box-shadow:0 16px 32px rgba(0,0,0,.12)}
.dark .card-hover:hover{box-shadow:0 16px 32px rgba(0,0,0,.4)}
.file-card{transition:all .2s}
.file-card:hover{border-color:#6366f1 !important;background:#f8fafc}
.dark .file-card{background:#1e2130;border-color:#2a2d3a !important}
.dark .file-card:hover{background:#25293e !important;border-color:#6366f1 !important}
.glass{backdrop-filter:blur(12px);background:rgba(255,255,255,.85)}
.dark .glass{background:rgba(26,29,39,.85)}
.animate-in{animation:slideIn .4s ease-out}
@keyframes slideIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.gradient-mesh{background:radial-gradient(at 40% 20%, #6366f1 0%, transparent 50%), radial-gradient(at 80% 0%, #8b5cf6 0%, transparent 50%), radial-gradient(at 0% 50%, #06b6d4 0%, transparent 50%)}
/* === DARK MODE HARD OVERRIDES - fixes Tailwind CDN dark: not compiling === */
html.dark body{background:#0f1117 !important;color:#e2e8f0 !important}
html.dark .bg-white{background:#1e2130 !important;color:#e2e8f0 !important;border-color:#2a2d3a !important}
html.dark .bg-slate-50{background:#0f1117 !important}
html.dark .border{border-color:#2a2d3a !important}
html.dark .border-slate-200{border-color:#2a2d3a !important}
html.dark .text-slate-500{color:#a1a1b5 !important}
html.dark .text-slate-400{color:#9aa0b5 !important}
html.dark .text-slate-800{color:#e2e8f0 !important}
html.dark .text-slate-900{color:#f1f5f9 !important}
html.dark input, html.dark select, html.dark textarea{background:#0f1117 !important;color:#e2e8f0 !important;border-color:#2a2d3a !important}
html.dark input::placeholder{color:#6b7280 !important}
html.dark .bg-slate-100{background:#25293e !important;color:#cbd5e1 !important}
/* modal fix - keep white in light, dark in dark */
html.dark #folderModal > div, html.dark #previewModal > div{background:#1a1d27 !important;border-color:#2a2d3a !important}
#folderModal input{background:#0f1117;color:#fff}
html:not(.dark) #folderModal input{background:#0f1117 !important;color:#fff !important}
html.dark #folderModal input{background:#0f1117 !important;color:#fff !important}
html.dark .bg-slate-900{background:#e2e8f0 !important;color:#0f1117 !important}
</style>
<script>
// dark mode init before render
if(localStorage.getItem('theme')==='dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)){
  document.documentElement.classList.add('dark');
}
function toggleTheme(){
  const isDark=document.documentElement.classList.toggle('dark');
  localStorage.setItem('theme', isDark?'dark':'light');
  setTimeout(drawCharts,100);
}
</script>
</head>
<body class="bg-[#f6f7f9] dark:bg-[#0f1117] text-slate-800 dark:text-slate-100 overflow-x-hidden transition-colors duration-300">

<?php if (!$user): ?>
<!-- LAMP ANIMATION OVERLAY -->
<div id="lamp-overlay" class="fixed inset-0 z-50 bg-[#080b12] flex items-center justify-start transition-colors duration-[1500ms] ease-in-out">
  <div id="lamp-wrapper" class="relative ml-[2vw] lg:ml-[5vw] transition-transform duration-[1500ms] ease-in-out group" style="pointer-events: auto;">
    <svg width="300" height="400" viewBox="0 0 300 400" xmlns="http://www.w3.org/2000/svg" class="select-none overflow-visible">
      
      <!-- Base & Stand -->
      <ellipse cx="150" cy="380" rx="70" ry="15" fill="#334155" />
      <rect x="140" y="190" width="20" height="190" fill="#64748b" rx="4" />
      <circle cx="150" cy="190" r="15" fill="#475569" />

      <!-- Light Beam -->
      <polygon points="100,190 200,190 450,550 -150,550" fill="url(#light-grad)" opacity="0" id="light-beam" class="transition-opacity duration-[1500ms] pointer-events-none" />
      
      <!-- Lamp Shade (Green) -->
      <path d="M 110 50 L 190 50 L 220 190 L 80 190 Z" fill="#166534" id="lamp-shade" class="transition-colors duration-700 drop-shadow-xl" />
      
      <!-- Base inside shade -->
      <ellipse cx="150" cy="190" rx="70" ry="12" fill="#064e3b" id="lamp-bottom" class="transition-colors duration-700" />
      <circle cx="150" cy="190" r="15" fill="#111" id="lamp-bulb" class="transition-colors duration-500" />

      <!-- Cute Face -->
      <g id="lamp-face" class="transition-opacity duration-300">
        <circle cx="130" cy="130" r="5" fill="#022c22" class="lamp-eye" />
        <circle cx="170" cy="130" r="5" fill="#022c22" class="lamp-eye" />
        <path d="M 140 145 Q 150 160 160 145" stroke="#022c22" stroke-width="4" fill="transparent" stroke-linecap="round" />
        <!-- Blush -->
        <ellipse cx="115" cy="140" rx="8" ry="4" fill="#86efac" opacity="0.3" id="blush-l" class="transition-opacity duration-300" />
        <ellipse cx="185" cy="140" rx="8" ry="4" fill="#86efac" opacity="0.3" id="blush-r" class="transition-opacity duration-300" />
      </g>
      
      <!-- Pull Cord (Path for bending) -->
      <path id="pull-cord-line" d="M 150 190 Q 150 240 150 290" stroke="#cbd5e1" stroke-width="3" fill="transparent" class="group-hover:stroke-white" />
      <!-- Pull Cord Ball -->
      <circle cx="150" cy="290" r="10" fill="#94a3b8" id="pull-cord-ball" class="group-hover:fill-white shadow-lg cursor-grab active:cursor-grabbing" />

      <defs>
        <linearGradient id="light-grad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgba(74, 222, 128, 0.45)" />
          <stop offset="100%" stop-color="rgba(74, 222, 128, 0)" />
        </linearGradient>
      </defs>
    </svg>
    <div class="absolute top-[410px] w-full text-center text-slate-400 text-xs font-mono tracking-[0.2em] uppercase animate-pulse select-none" id="pull-text">Pull Me</div>
  </div>
</div>

<style>
@keyframes lampBlink {
  0%, 96% { transform: scaleY(1); }
  98% { transform: scaleY(0.1); }
  100% { transform: scaleY(1); }
}
.lamp-eye { transform-origin: center; animation: lampBlink 4s infinite; }

/* States */
.lamp-on #light-beam { opacity: 1; }
.lamp-on #lamp-shade { fill: #22c55e; }
.lamp-on #lamp-bottom { fill: #15803d; }
.lamp-on #lamp-bulb { fill: #fff; filter: drop-shadow(0 0 15px #fff); }
.lamp-on #lamp-face circle { fill: #064e3b; }
.lamp-on #lamp-face path { stroke: #064e3b; }
.lamp-on #blush-l, .lamp-on #blush-r { opacity: 0.7; }

#main-login-wrapper { opacity: 0; pointer-events: none; transition: opacity 1.5s ease-out; }
</style>

<script>
let lampIsOn = false;
let isAnimating = false;
let isDragging = false;
let startY = 0;
let currentY = 0;
const baseY = 290;
const maxPull = 50; // max px to pull down

document.addEventListener('DOMContentLoaded', () => {
  const pullBall = document.getElementById('pull-cord-ball');
  const pullLine = document.getElementById('pull-cord-line');
  const pullText = document.getElementById('pull-text');
  const wr = document.getElementById('lamp-wrapper');
  const overlay = document.getElementById('lamp-overlay');
  const mainLogin = document.getElementById('main-login-wrapper');

  function updateCord(dy) {
    pullBall.setAttribute('cy', baseY + dy);
    pullLine.setAttribute('d', `M 150 190 Q ${150 - dy*0.8} ${240 + dy*0.5} 150 ${baseY + dy}`);
  }

  function startDrag(e) {
    if(isAnimating) return;
    isDragging = true;
    startY = e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;
    pullBall.style.transition = 'none';
    pullLine.style.transition = 'none';
  }

  function onDrag(e) {
    if(!isDragging) return;
    const y = e.type.includes('mouse') ? e.clientY : e.touches[0].clientY;
    let dy = y - startY;
    if(dy < 0) dy = 0;
    if(dy > maxPull) dy = maxPull;
    currentY = dy;
    updateCord(dy);
  }

  function endDrag(e) {
    if(!isDragging) return;
    isDragging = false;
    
    pullBall.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
    pullLine.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
    
    if(currentY > 20) {
      triggerToggle();
    } else {
      updateCord(0);
    }
    currentY = 0;
  }

  pullBall.addEventListener('mousedown', startDrag);
  window.addEventListener('mousemove', onDrag);
  window.addEventListener('mouseup', endDrag);

  pullBall.addEventListener('touchstart', startDrag, {passive: true});
  window.addEventListener('touchmove', onDrag, {passive: true});
  window.addEventListener('touchend', endDrag);

  function triggerToggle() {
    if (isAnimating) return;
    isAnimating = true;
    
    pullText.style.opacity = '0';
    updateCord(0); // snap back visual
    
    if (lampIsOn) {
      wr.classList.remove('lamp-on');
      overlay.style.pointerEvents = 'auto'; // Block clicks to UI
      
      setTimeout(() => {
        overlay.style.backgroundColor = '#080b12';
        mainLogin.style.opacity = '0';
        mainLogin.style.pointerEvents = 'none';
        
        setTimeout(() => {
          pullText.textContent = "Pull to Turn On";
          pullText.style.opacity = '1';
          lampIsOn = false;
          isAnimating = false;
        }, 1500); // Wait for bg color
      }, 200);
      
    } else {
      wr.classList.add('lamp-on');
      
      setTimeout(() => {
        overlay.style.backgroundColor = 'transparent';
        overlay.style.pointerEvents = 'none'; // allow clicking through to the login form
        wr.style.pointerEvents = 'auto'; // Ensure lamp remains clickable!
        
        mainLogin.style.opacity = '1';
        mainLogin.style.pointerEvents = 'auto';
        
        setTimeout(() => {
          lampIsOn = true;
          isAnimating = false;
        }, 400);
      }, 400);
    }
  }
});
</script>

<!-- LOGIN with dark support -->
<div id="main-login-wrapper" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-4">
  <div class="w-full max-w-[960px] bg-white dark:bg-[#1a1d27] rounded-[24px] overflow-hidden shadow-2xl grid md:grid-cols-2 border dark:border-[#2a2d3a]">
    <div class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-8 md:p-10 text-white flex flex-col justify-between relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-64 h-64 bg-white/10 rounded-full blur-2xl"></div>
      <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-2xl"></div>
      <div>
        <div class="w-11 h-11 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center mb-6"><i class="bi bi-hdd-stack text-xl"></i></div>
        <h1 class="text-[26px] font-extrabold leading-tight"><?=htmlspecialchars($config['app_name'])?></h1>
        <p class="text-indigo-100 mt-3 text-sm leading-relaxed">Centralized document management for Artwork, Plates, Customer & Production files. Dark mode + Interactive Charts ready.</p>
        <div class="mt-6 space-y-2 text-sm">
          <div class="flex items-center gap-2"><i class="bi bi-moon-stars"></i> Dark / Light toggle</div>
          <div class="flex items-center gap-2"><i class="bi bi-bar-chart"></i> Graphical Dashboard with Chart.js</div>
          <div class="flex items-center gap-2"><i class="bi bi-layers"></i> Local Storage · Phase 1</div>
        </div>
      </div>
    </div>
    <div class="p-8 md:p-10 dark:bg-[#1a1d27]">
      <div class="flex justify-between items-center">
        <div><h2 class="text-xl font-bold">Welcome back</h2><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Sign in to your workspace</p></div>
        <button onclick="toggleTheme()" class="w-9 h-9 rounded-xl border dark:border-[#2a2d3a] flex items-center justify-center"><i class="bi bi-moon"></i></button>
      </div>
      <form id="loginForm" class="mt-8 space-y-4">
        <div><label class="text-sm font-medium">Email</label><input name="email" class="mt-1 w-full border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Enter your email"></div>
        <div><label class="text-sm font-medium">Password</label><input name="password" type="password" class="mt-1 w-full border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••"></div>
        <button class="w-full bg-slate-900 dark:bg-indigo-600 hover:bg-black dark:hover:bg-indigo-700 text-white rounded-xl py-3 font-medium transition">Sign in →</button>
        <div id="loginErr" class="hidden text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
      </form>
    </div>
  </div>
  <div class="absolute bottom-6 left-0 w-full text-center text-[11px] text-slate-400 dark:text-slate-500 font-mono tracking-tight">
    @Sheree Label Creation 2026 || @Developed by Mriganka B Debnath
  </div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const fd=new FormData(e.target); fd.append('action','login');
  const r=await fetch('api/auth.php?action=login',{method:'POST',body:fd});
  const j=await r.json();
  if(j.ok) location.reload();
  else {const el=document.getElementById('loginErr'); el.textContent=j.error||'Login failed'; el.classList.remove('hidden');}
});
</script>
</body></html>
<?php exit; endif; ?>

<!-- APP SHELL -->
<div class="flex min-h-screen">
  <!-- SIDEBAR -->
  <aside id="sidebar" class="w-[272px] bg-white dark:bg-[#1a1d27] border-r border-slate-200 dark:border-[#2a2d3a] flex flex-col fixed inset-y-0 left-0 z-30 lg:static lg:translate-x-0 lg:h-screen lg:shrink-0 -translate-x-full transition-all duration-300">
    <div class="h-[64px] flex items-center gap-3 px-5 border-b border-slate-100 dark:border-[#2a2d3a]">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-lg"><i class="bi bi-hdd-stack"></i></div>
      <div><div class="font-bold text-[15px] leading-none"><?=htmlspecialchars($config['app_name'])?></div></div>
      <button onclick="toggleTheme()" class="ml-auto w-7 h-7 rounded-lg border dark:border-[#2a2d3a] flex items-center justify-center text-slate-500 hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-moon-stars text-sm"></i></button>
    </div>

    <nav class="flex-1 overflow-y-auto p-3 space-y-4 min-h-0">
      <div>
        <div class="text-[11px] tracking-widest text-slate-400 font-semibold px-2 mb-2">MAIN</div>
        <div class="space-y-1">
          <button data-nav="dashboard" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-slate-900 dark:bg-indigo-600 text-white text-sm font-medium shadow"><i class="bi bi-grid-1x2"></i> Dashboard</button>
          <?php if($isAdmin): ?><button data-nav="users" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-people-fill"></i> Users & Permissions</button><?php endif; ?>
          <button data-nav="myfiles" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-folder2-open"></i> My Files</button>
          <button data-nav="shared" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-people"></i> Shared With Me</button>
          <button data-nav="recent" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-clock-history"></i> Recent</button>
          <button data-nav="starred" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-star"></i> Starred</button>
          <button data-nav="trash" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-trash3"></i> Trash</button>
          <?php if($isAdmin): ?><button data-nav="settings" class="nav-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-sm text-slate-700 dark:text-slate-300"><i class="bi bi-gear"></i> Settings</button><?php endif; ?>
        </div>
      </div>
      <div>
        <div class="text-[11px] tracking-widest text-slate-400 font-semibold px-2 mb-2">CATEGORIES</div>
        <div class="space-y-1 text-sm">
          <button onclick="filterCat('all')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-slate-400"></span> All Files</button>
          <button onclick="filterCat('images')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Images</button>
          <button onclick="filterCat('pdf')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-red-500"></span> PDF</button>
          <button onclick="filterCat('documents')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Documents</button>
          <button onclick="filterCat('excel')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-green-600"></span> Excel</button>
          <button onclick="filterCat('zip')" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-[#25293e] text-slate-700 dark:text-slate-300"><span class="w-2 h-2 rounded-full bg-amber-500"></span> ZIP</button>
        </div>
      </div>
    </nav>

    <div class="p-3 border-t border-slate-100 dark:border-[#2a2d3a] space-y-3">
      <div class="bg-gradient-to-br from-slate-900 to-indigo-900 dark:from-indigo-900 dark:to-violet-900 rounded-2xl p-4 text-white relative overflow-hidden">
        <div class="absolute -right-6 -top-6 w-20 h-20 bg-white/10 rounded-full blur-xl"></div>
        <div class="flex items-center justify-between text-xs relative"><span class="opacity-70">Storage</span><span class="mono bg-white/15 px-2 py-1 rounded-full text-[11px]" id="storageBadge">Local</span></div>
        <div class="mt-3 relative">
          <div class="flex justify-between text-xs mb-1"><span class="opacity-80">Used</span><span id="storageText" class="mono">— / — GB</span></div>
          <div class="h-2 bg-white/15 rounded-full overflow-hidden"><div id="storageBar" class="h-full bg-gradient-to-r from-indigo-400 to-white rounded-full transition-all duration-700" style="width:12%"></div></div>
          <canvas id="miniStorage" height="48" class="mt-3 w-full !max-h-[48px]"></canvas>
        </div>
      </div>
      <div class="flex items-center gap-3 px-2">
        <img src="https://i.pravatar.cc/100?img=32" class="w-9 h-9 rounded-full object-cover">
        <div class="flex-1 min-w-0"><div class="text-sm font-medium truncate"><?=htmlspecialchars($user['name'])?></div><div class="text-xs text-slate-500 truncate"><?=htmlspecialchars($user['email'])?></div></div>
        <button onclick="logout()" class="w-8 h-8 rounded-lg border dark:border-[#2a2d3a] flex items-center justify-center text-slate-500 hover:bg-slate-50 dark:hover:bg-[#25293e]"><i class="bi bi-box-arrow-right"></i></button>
      </div>
      <div class="pt-2 text-center text-[10px] text-slate-400 dark:text-slate-500 font-mono tracking-tight leading-relaxed">
        @Sheree Label Creation 2026 <br> @Developed by Mriganka B Debnath
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="flex-1 min-w-0 flex flex-col">
    <!-- TOP HEADER -->
    <header class="h-[64px] bg-white/80 dark:bg-[#1a1d27]/80 glass border-b border-slate-200 dark:border-[#2a2d3a] flex items-center gap-3 px-3 lg:px-6 sticky top-0 z-20">
      <button onclick="toggleSidebar()" class="lg:hidden w-9 h-9 rounded-xl border dark:border-[#2a2d3a] flex items-center justify-center"><i class="bi bi-list text-xl"></i></button>
      <div class="hidden md:flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400" id="breadcrumb"><span class="font-medium text-slate-900 dark:text-white">Home</span></div>
      <div class="flex-1 max-w-[560px] mx-2 lg:mx-6">
        <div class="relative">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input id="globalSearch" placeholder="Search files and folders…" class="w-full bg-slate-100 dark:bg-[#0f1117] border border-slate-200 dark:border-[#2a2d3a] rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:bg-white dark:focus:bg-[#1a1d27] focus:border-indigo-400 transition">
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="toggleTheme()" class="w-10 h-10 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#0f1117] flex items-center justify-center"><i class="bi bi-moon-stars"></i></button>
        <button onclick="document.getElementById('fileInput').click()" class="hidden sm:inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium shadow-lg shadow-indigo-500/20"><i class="bi bi-cloud-arrow-up"></i> Upload</button>
        <button onclick="openNewFolder()" class="inline-flex items-center gap-2 bg-slate-900 dark:bg-white dark:text-slate-900 text-white px-4 py-2.5 rounded-xl text-sm font-medium"><i class="bi bi-folder-plus"></i> <span class="hidden sm:inline">New Folder</span></button>
        <button class="w-10 h-10 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#0f1117] flex items-center justify-center relative"><i class="bi bi-bell"></i><span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-[#1a1d27] animate-pulse"></span></button>
        <img src="https://i.pravatar.cc/100?img=32" class="w-9 h-9 rounded-full hidden sm:block ring-2 ring-indigo-500/20">
      </div>
    </header>

    <!-- CONTENT -->
    <main class="flex-1 p-3 lg:p-6 space-y-6 overflow-y-auto">
      <!-- DASHBOARD - MODERN GRAPHICAL -->
      <section id="view-dashboard" class="space-y-6 animate-in">
        <!-- KPI CARDS with gradients -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="relative overflow-hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5 card-hover">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl"></div>
            <div class="flex justify-between relative"><span class="text-sm text-slate-500 dark:text-slate-400">Total Files</span><span class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center shadow-lg"><i class="bi bi-file-earmark"></i></span></div>
            <div id="statFiles" class="text-[28px] font-extrabold mt-3 tracking-tight">—</div>
            <div class="flex items-center gap-2 text-xs mt-1"><span class="px-2 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600">● Live</span><span class="text-slate-400">updated now</span></div>
          </div>
          <div class="relative overflow-hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5 card-hover">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl"></div>
            <div class="flex justify-between relative"><span class="text-sm text-slate-500 dark:text-slate-400">Total Folders</span><span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-lg"><i class="bi bi-folder2"></i></span></div>
            <div id="statFolders" class="text-[28px] font-extrabold mt-3">—</div><div class="text-xs text-slate-400 mt-1">Nested structure</div>
          </div>
          <div class="relative overflow-hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5 card-hover">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-violet-500/10 rounded-full blur-2xl"></div>
            <div class="flex justify-between relative"><span class="text-sm text-slate-500 dark:text-slate-400">Storage Used</span><span class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center shadow-lg"><i class="bi bi-hdd"></i></span></div>
            <div id="statUsed" class="text-[28px] font-extrabold mt-3">—</div><div class="text-xs text-slate-400 mt-1"><span id="statQuotaText">of — GB</span> · <span id="storagePct" class="font-semibold text-violet-600">—%</span></div>
          </div>
          <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 to-indigo-900 dark:from-[#1a1d27] dark:to-violet-900 rounded-[20px] p-5 card-hover text-white border border-slate-800">
            <div class="flex justify-between"><span class="text-sm opacity-70">Available</span><span class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center"><i class="bi bi-cloud-check"></i></span></div>
            <div id="statAvail" class="text-[28px] font-extrabold mt-3">—</div><div class="text-xs opacity-60 mt-1">Local · Phase 1 · <span class="opacity-100">Ready for Drive</span></div>
          </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="grid lg:grid-cols-3 gap-6">
          <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5">
            <div class="flex items-center justify-between"><h3 class="font-bold">Storage Overview</h3><span class="text-xs px-2 py-1 rounded-full bg-slate-100 dark:bg-[#0f1117]">Doughnut</span></div>
            <div class="mt-4 relative h-[220px]"><canvas id="storageChart"></canvas></div>
            <div class="flex justify-center gap-4 mt-3 text-xs">
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-indigo-600"></span> Used</span>
              <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-200 dark:bg-[#2a2d3a]"></span> Free</span>
            </div>
          </div>
          <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5">
            <div class="flex items-center justify-between"><h3 class="font-bold">Files by Type</h3><span class="text-xs px-2 py-1 rounded-full bg-slate-100 dark:bg-[#0f1117]">Interactive</span></div>
            <div class="mt-4 h-[220px]"><canvas id="typeChart"></canvas></div>
          </div>
          <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5 flex flex-col">
            <h3 class="font-bold">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3 mt-4">
              <button onclick="document.getElementById('fileInput').click()" class="group border dark:border-[#2a2d3a] rounded-2xl p-4 text-center hover:bg-slate-50 dark:hover:bg-[#0f1117] hover:border-indigo-300 transition"><i class="bi bi-cloud-arrow-up text-2xl text-indigo-600 group-hover:scale-110 transition inline-block"></i><div class="text-sm font-semibold mt-2">Upload</div><div class="text-xs text-slate-400">Drag & drop</div></button>
              <button onclick="openNewFolder()" class="group border dark:border-[#2a2d3a] rounded-2xl p-4 text-center hover:bg-slate-50 dark:hover:bg-[#0f1117] transition"><i class="bi bi-folder-plus text-2xl text-amber-600 group-hover:scale-110 transition inline-block"></i><div class="text-sm font-semibold mt-2">New Folder</div><div class="text-xs text-slate-400">Nested</div></button>
              <button onclick="switchView('myfiles')" class="group border dark:border-[#2a2d3a] rounded-2xl p-4 text-center hover:bg-slate-50 dark:hover:bg-[#0f1117] transition"><i class="bi bi-grid text-2xl text-slate-600 dark:text-slate-300 group-hover:scale-110 transition inline-block"></i><div class="text-sm font-semibold mt-2">Browse</div><div class="text-xs text-slate-400">Grid / List</div></button>
              <button onclick="toggleTheme()" class="group border dark:border-[#2a2d3a] rounded-2xl p-4 text-center hover:bg-slate-900 hover:text-white dark:hover:bg-white dark:hover:text-slate-900 transition"><i class="bi bi-moon-stars text-2xl group-hover:rotate-12 transition inline-block"></i><div class="text-sm font-semibold mt-2">Dark Mode</div><div class="text-xs opacity-60">Toggle</div></button>
            </div>
            <div id="dropZone" class="mt-4 border-2 border-dashed dark:border-[#2a2d3a] rounded-2xl p-5 text-center bg-slate-50/50 dark:bg-[#0f1117]/50 hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/20 transition cursor-pointer">
              <i class="bi bi-cloud-arrow-up text-2xl text-slate-400"></i>
              <div class="text-sm font-semibold mt-2">Drop files here</div>
              <div class="text-xs text-slate-500">StorageService → Local adapter</div>
            </div>
          </div>
        </div>

        <!-- ACTIVITY GRAPH + LISTS -->
        <div class="grid lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5">
            <div class="flex items-center justify-between"><h3 class="font-bold">Activity (7 days)</h3><span class="text-xs px-2 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-600">Live</span></div>
            <div class="mt-4 h-[200px]"><canvas id="activityChart"></canvas></div>
          </div>
          <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5">
            <div class="flex justify-between items-center"><h3 class="font-bold">Recent Files</h3><button onclick="switchView('recent')" class="text-sm text-indigo-600 hover:underline">View all →</button></div>
            <div id="recentList" class="mt-4 space-y-2"></div>
          </div>
        </div>

        <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-5">
          <div class="flex justify-between items-center"><h3 class="font-bold">Activity Log</h3><span class="text-xs bg-slate-100 dark:bg-[#0f1117] px-2 py-1 rounded-full">Interactive</span></div>
          <div id="activityList" class="mt-4 grid md:grid-cols-2 gap-3 max-h-[260px] overflow-y-auto pr-1"></div>
        </div>
      </section>

      <!-- MY FILES -->
      <section id="view-myfiles" class="hidden space-y-4 animate-in">
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2 text-sm">
            <button onclick="setViewMode('grid')" id="btnGrid" class="px-3 py-2 rounded-xl bg-slate-900 dark:bg-white dark:text-slate-900 text-white"><i class="bi bi-grid"></i> Grid</button>
            <button onclick="setViewMode('list')" id="btnList" class="px-3 py-2 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#1a1d27]"><i class="bi bi-list-ul"></i> List</button>
          </div>
          <div class="flex items-center gap-2 ml-auto flex-wrap">
            <select id="sortBy" class="border dark:border-[#2a2d3a] dark:bg-[#1a1d27] rounded-xl px-3 py-2 text-sm bg-white"><option value="name">Sort: Name</option><option value="date">Sort: Date</option><option value="size">Sort: Size</option><option value="type">Sort: Type</option></select>
            <select id="sortOrder" class="border dark:border-[#2a2d3a] dark:bg-[#1a1d27] rounded-xl px-3 py-2 text-sm bg-white"><option value="ASC">Asc</option><option value="DESC">Desc</option></select>
            <select id="dateFilter" class="border dark:border-[#2a2d3a] dark:bg-[#1a1d27] rounded-xl px-3 py-2 text-sm bg-white"><option value="all">Any time</option><option value="today">Today</option><option value="7days">Last 7 days</option><option value="30days">Last 30 days</option></select>
            <div class="flex items-center gap-1 p-1 bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-xl">
              <button data-filter="all" class="filter-btn px-3 py-1.5 rounded-lg bg-slate-900 dark:bg-white dark:text-slate-900 text-white text-sm">All</button>
              <button data-filter="images" class="filter-btn px-3 py-1.5 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">Images</button>
              <button data-filter="pdf" class="filter-btn px-3 py-1.5 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">PDF</button>
              <button data-filter="documents" class="filter-btn px-3 py-1.5 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">Docs</button>
              <button data-filter="excel" class="filter-btn px-3 py-1.5 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">Excel</button>
              <button data-filter="zip" class="filter-btn px-3 py-1.5 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">ZIP</button>
            </div>
          </div>
        </div>
        <div id="gridView" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4"></div>
        <div id="listView" class="hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 dark:bg-[#0f1117] text-slate-500 text-xs tracking-wide"><tr><th class="text-left px-4 py-3">Name</th><th class="text-left px-4 py-3">Type</th><th class="text-left px-4 py-3">Size</th><th class="text-left px-4 py-3">Modified</th><th class="text-left px-4 py-3">Owner</th><th class="text-right px-4 py-3">Actions</th></tr></thead>
              <tbody id="listBody"></tbody>
            </table>
          </div>
        </div>
        <div id="emptyState" class="hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-12 text-center">
          <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-[#0f1117] flex items-center justify-center mx-auto"><i class="bi bi-folder2-open text-2xl text-slate-400"></i></div>
          <h3 class="font-bold mt-4">No files here yet</h3>
          <p class="text-sm text-slate-500 mt-1">Upload your first file or create a folder.</p>
          <div class="flex justify-center gap-2 mt-4">
            <button onclick="document.getElementById('fileInput').click()" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm">Upload File</button>
            <button onclick="openNewFolder()" class="border dark:border-[#2a2d3a] px-4 py-2 rounded-xl text-sm bg-white dark:bg-[#1a1d27]">New Folder</button>
          </div>
        </div>
      </section>

      <section id="view-starred" class="hidden space-y-4"><h2 class="text-lg font-bold">Starred</h2><div id="starredGrid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div></section>
      <section id="view-trash" class="hidden space-y-4"><h2 class="text-lg font-bold">Trash</h2><p class="text-sm text-slate-500">Items in trash will be kept until you delete permanently or restore.</p><div id="trashGrid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div></section>
      <section id="view-recent" class="hidden space-y-4"><h2 class="text-lg font-bold">Recent Files</h2><div id="recentGrid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div></section>
      <section id="view-shared" class="hidden space-y-4"><div class="flex justify-between items-center"><h2 class="text-lg font-bold">Shared With Me</h2><button onclick="loadShared()" class="text-sm text-indigo-600">Refresh</button></div><div id="sharedGrid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div><div id="sharedEmpty" class="hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-12 text-center text-slate-500">No files shared with you yet. Use Share → Viewer/Editor to share.</div></section>
      <section id="view-settings" class="hidden space-y-6">
        <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Settings</h2><span class="text-xs bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 px-3 py-1 rounded-full">Admin only for Storage</span></div>
        <!-- Tabs -->
        <div class="flex gap-2 p-1 bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-xl w-fit">
          <button onclick="setTab('storage')" id="tab-storage" class="px-4 py-2 rounded-lg bg-slate-900 dark:bg-white dark:text-slate-900 text-white text-sm font-medium">Storage</button>
          <button onclick="setTab('general')" id="tab-general" class="px-4 py-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">General</button>
          <button onclick="setTab('share')" id="tab-share" class="px-4 py-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-[#0f1117]">Shares</button>
        </div>

        <div id="tab-panel-storage" class="space-y-6">
          <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
              <!-- Local -->
              <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
                <div class="flex justify-between items-start"><div><h3 class="font-bold flex items-center gap-2"><i class="bi bi-hdd"></i> Local Storage</h3><p class="text-sm text-slate-500 mt-1">XAMPP / Apache local disk — 2GB chunked upload active</p></div><button id="btn_active_local" onclick="activateStorage('local')" class="px-4 py-2 rounded-xl border dark:border-[#2a2d3a] text-xs font-semibold">Set Active</button></div>
                <div class="grid md:grid-cols-2 gap-3 mt-4">
                  <div><label class="text-xs font-semibold">Local Folder Path</label><input id="localPath" value="storage/local" class="mt-1 w-full border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm"></div>
                  <div><label class="text-xs font-semibold">Quota (GB)</label><input value="100" disabled class="mt-1 w-full border rounded-xl px-3 py-2.5 text-sm bg-slate-50 dark:bg-[#0f1117]"></div>
                </div>
                <div class="text-xs text-slate-400 mt-2">Chunked upload: 5MB/chunk → supports 2GB + multiple files. Configure php.ini: upload_max_filesize=2048M, post_max_size=2048M</div>
              </div>
              <!-- Google Drive -->
              <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
                <div class="flex justify-between"><div><h3 class="font-bold flex items-center gap-2"><i class="bi bi-google"></i> Google Drive</h3><p class="text-sm text-slate-500">OAuth 2.0 — connect your Drive</p></div><div class="flex gap-2"><button onclick="testGoogleDrive()" class="px-3 py-2 rounded-xl border dark:border-[#2a2d3a] text-xs font-semibold">Test Connection</button><button onclick="saveGoogle()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Save & Connect</button></div></div>
                <div class="grid md:grid-cols-2 gap-3 mt-4">
                  <input id="gd_client" placeholder="Client ID" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="gd_secret" placeholder="Client Secret" type="password" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="gd_redirect" value="http://localhost/file-server/api/oauth/google/callback.php" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm md:col-span-2">
                  <input id="gd_folder" placeholder="Drive Folder ID (optional)" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <button id="btn_active_google_drive" onclick="activateStorage('google_drive')" class="border dark:border-[#2a2d3a] rounded-xl px-3 py-2.5 text-sm">Set Active: Google Drive</button>
                </div>
                <div class="mt-3"><span id="gdTest" class="text-xs py-2"></span></div>
              </div>
              <!-- S3 / Hostinger -->
              <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
                <div class="flex justify-between"><div><h3 class="font-bold"><i class="bi bi-cloud"></i> Hostinger / S3 Compatible</h3><p class="text-sm text-slate-500">Any S3, Hostinger Object Storage</p></div><button onclick="saveS3()" class="px-4 py-2 rounded-xl bg-slate-900 dark:bg-white dark:text-slate-900 text-white text-xs font-semibold">Save</button></div>
                <div class="grid md:grid-cols-2 gap-3 mt-4">
                  <input id="s3_endpoint" placeholder="Endpoint (e.g. https://s3.hostinger.com)" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="s3_bucket" placeholder="Bucket" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="s3_key" placeholder="Access Key" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="s3_secret" placeholder="Secret Key" type="password" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="s3_region" placeholder="Region (ap-south-1)" value="ap-south-1" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <button id="btn_active_hostinger" onclick="activateStorage('hostinger')" class="border dark:border-[#2a2d3a] rounded-xl px-3 py-2.5 text-sm">Set Active: Hostinger</button>
                </div>
                <div class="mt-3 flex gap-2"><button onclick="testS3()" class="px-3 py-2 rounded-xl border dark:border-[#2a2d3a] text-xs">Test Connection</button><span id="s3Test" class="text-xs py-2"></span></div>
              </div>
              <!-- FTP / FTPS -->
              <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
                <div class="flex justify-between"><div><h3 class="font-bold"><i class="bi bi-hdd-network"></i> FTP / FTPS Server</h3><p class="text-sm text-slate-500">Standard FTP, FTPS (secure) — Hostinger FTP, Any FTP</p></div><button onclick="saveFtp()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Save FTP</button></div>
                <div class="grid md:grid-cols-2 gap-3 mt-4">
                  <input id="ftp_host" placeholder="FTP Host (e.g. 145.14.10.20 or ftp.yourdomain.com)" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="ftp_port" placeholder="Port" value="21" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="ftp_user" placeholder="FTP Username" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="ftp_pass" placeholder="FTP Password" type="password" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <input id="ftp_root" placeholder="Root Path (e.g. /public_html/files or /)" value="/" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
                  <select id="ftp_secure" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm"><option value="0">FTP (Normal)</option><option value="1">FTPS (Secure - TLS)</option></select>
                  <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="ftp_passive" checked> Passive Mode</label>
                  <button id="btn_active_ftp" onclick="activateStorage('ftp')" class="border dark:border-[#2a2d3a] rounded-xl px-3 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-[#0f1117]">Set Active: FTP</button>
                </div>
                <div class="text-xs text-slate-400 mt-2">XAMPP এ <code>php_ftp.dll</code> enable থাকতে হবে (php.ini → extension=ftp). Test: <code>ftp_connect</code> function exists.</div>
                <div class="mt-3 flex gap-2"><button onclick="testFtp()" class="px-3 py-2 rounded-xl border dark:border-[#2a2d3a] text-xs">Test Connection</button><span id="ftpTest" class="text-xs py-2"></span></div>
              </div>
            </div>
            <div class="space-y-4">
              <div class="bg-slate-900 dark:bg-[#0f1117] text-white rounded-[20px] p-6 border dark:border-[#2a2d3a]">
                <h3 class="font-bold">Active Provider</h3><div id="activeProvider" class="mt-3 mono text-sm bg-white/10 rounded-xl p-3">local</div>
                <div class="text-xs opacity-60 mt-2">All file ops go via StorageService → no UI change needed when switching.</div>
                <div class="mt-4 text-xs bg-amber-500/20 border border-amber-500/30 rounded-xl p-3">For 2GB upload, set in <b>php.ini</b>:<br>upload_max_filesize=2048M<br>post_max_size=2048M<br>max_execution_time=600</div>
              </div>
            </div>
          </div>
        </div>

        <div id="tab-panel-general" class="hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
          <div class="flex justify-between items-start">
            <div><h3 class="font-bold">General</h3><p class="text-sm text-slate-500 mt-1">Company settings — extend as needed.</p></div>
            <button onclick="saveGeneral()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Save Settings</button>
          </div>
          <div class="grid md:grid-cols-2 gap-3 mt-4">
            <input id="gen_company" placeholder="Company Name" value="<?=htmlspecialchars($config['app_name'])?>" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
            <input id="gen_email" placeholder="Support Email" value="admin@company.com" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
          </div>
        </div>

        <div id="tab-panel-share" class="hidden bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-6">
          <h3 class="font-bold">All Shares</h3><div id="shareList" class="mt-4 space-y-2 text-sm"></div>
        </div>
      </section>

      <section id="view-users" class="hidden space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-bold">Users & Permissions — Admin Control</h2>
          <button onclick="openUserModal()" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold"><i class="bi bi-person-plus"></i> Create User</button>
        </div>
        <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] overflow-hidden">
          <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 dark:bg-[#0f1117] text-slate-500 text-xs"><tr><th class="text-left px-4 py-3">User</th><th class="text-left px-4 py-3">Role</th><th class="text-left px-4 py-3">Quota</th><th class="text-left px-4 py-3">Used</th><th class="text-left px-4 py-3">Permissions</th><th class="text-right px-4 py-3">Actions</th></tr></thead><tbody id="userTable"></tbody></table></div>
        </div>
        <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 text-sm">Admin can set per-user space (quota GB), role (admin/user), and permissions (upload/download/share/delete). Quota enforced on 2GB chunked uploads.</div>
      </section>
    </main>
  </div>

  <aside id="detailsPanel" class="fixed right-0 top-[64px] bottom-0 w-[360px] bg-white dark:bg-[#1a1d27] border-l border-slate-200 dark:border-[#2a2d3a] translate-x-full transition-transform z-20 overflow-y-auto">
    <div class="p-5"><div class="flex justify-between items-center"><h3 class="font-bold">Details</h3><button onclick="closeDetails()" class="w-8 h-8 rounded-lg border dark:border-[#2a2d3a] flex items-center justify-center"><i class="bi bi-x-lg"></i></button></div><div id="detailsContent" class="mt-4 space-y-4"></div></div>
  </aside>
</div>

<div id="ctxMenu" class="hidden fixed bg-white dark:bg-[#1a1d27] rounded-xl py-2 w-52 z-50 text-sm shadow-xl border dark:border-[#2a2d3a]"></div>
<div id="previewModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col border dark:border-[#2a2d3a]">
    <div class="flex items-center justify-between px-5 py-3 border-b dark:border-[#2a2d3a]"><h3 id="previewTitle" class="font-bold truncate pr-4">Preview</h3><button onclick="closePreview()" class="w-8 h-8 rounded-lg border dark:border-[#2a2d3a] flex items-center justify-center"><i class="bi bi-x-lg"></i></button></div>
    <div id="previewBody" class="flex-1 overflow-auto p-4 bg-slate-50 dark:bg-[#0f1117] flex items-center justify-center min-h-[300px]"></div>
  </div>
</div>
<div id="folderModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] w-full max-w-md p-6 border dark:border-[#2a2d3a] shadow-2xl">
    <h3 class="font-bold text-slate-900 dark:text-white">New Folder</h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Create a new folder in current location</p>
    <input id="folderName" placeholder="Folder name (e.g. Job-001)" class="mt-4 w-full bg-white dark:bg-[#0f1117] border border-slate-200 dark:border-[#2a2d3a] rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <div class="flex justify-end gap-2 mt-5"><button onclick="closeFolderModal()" class="px-5 py-2.5 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#0f1117] text-sm font-medium">Cancel</button><button onclick="createFolder()" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Create Folder</button></div>
  </div>
</div>

<div id="shareModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] w-full max-w-md p-6 border dark:border-[#2a2d3a] shadow-2xl">
    <h3 class="font-bold">Share File</h3><p class="text-xs text-slate-500 mt-1">Share via email or generate link</p>
    <input type="hidden" id="shareFileId"><input type="hidden" id="shareFolderId">
    <div class="mt-4 space-y-3">
      <input id="shareEmail" placeholder="User email (e.g. user@company.com) — leave empty for link" class="w-full border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
      <select id="sharePerm" class="w-full border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm"><option value="viewer">Viewer (read + download)</option><option value="editor">Editor (can edit)</option><option value="download">Download only</option></select>
      <div id="shareLink" class="hidden mono text-xs bg-slate-100 dark:bg-[#0f1117] p-3 rounded-xl break-all"></div>
    </div>
    <div class="flex justify-end gap-2 mt-5"><button onclick="closeShare()" class="px-5 py-2.5 rounded-xl border dark:border-[#2a2d3a] text-sm">Cancel</button><button onclick="doShare()" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Share</button></div>
  </div>
</div>
<div id="userModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-[#1a1d27] rounded-[20px] w-full max-w-md p-6 border dark:border-[#2a2d3a] shadow-2xl">
    <h3 class="font-bold">Create User</h3>
    <div class="grid gap-3 mt-4">
      <input id="nu_name" placeholder="Full Name" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
      <input id="nu_email" placeholder="Email" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
      <input id="nu_pass" placeholder="Password" type="password" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm">
      <div class="grid grid-cols-2 gap-3"><select id="nu_role" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm"><option value="user">User</option><option value="admin">Admin</option></select><input id="nu_quota" type="number" value="10" placeholder="Quota GB" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-xl px-3 py-2.5 text-sm"></div>
    </div>
    <div class="flex justify-end gap-2 mt-5"><button onclick="closeUserModal()" class="px-5 py-2.5 rounded-xl border dark:border-[#2a2d3a] text-sm">Cancel</button><button onclick="createUser()" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Create</button></div>
  </div>
</div>
<input type="file" id="fileInput" multiple class="hidden">
<div id="toastWrap" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<div id="uploadQueue" class="fixed bottom-4 left-4 z-40 w-[360px] space-y-2"></div>

<script>
let currentFolder=null, viewMode='grid', currentFilter='all', selected=null;
let storageChart, typeChart, activityChart, miniChart;

function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('-translate-x-full'); }
document.querySelectorAll('.nav-btn').forEach(b=>b.addEventListener('click',()=>switchView(b.dataset.nav)));
function switchView(name){
  document.querySelectorAll('[id^="view-"]').forEach(s=>s.classList.add('hidden'));
  document.getElementById('view-'+name).classList.remove('hidden');
  document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('bg-slate-900','dark:bg-indigo-600','text-white'));
  const active=document.querySelector(`[data-nav="${name}"]`);
  if(active) active.classList.add('bg-slate-900','dark:bg-indigo-600','text-white');
  if(name==='myfiles') { currentFolder = null; loadFiles(); }
  if(name==='starred') loadStarred();
  if(name==='trash') loadTrash();
  if(name==='recent') loadRecent();
  if(name==='dashboard'){ refreshDashboard(); setTimeout(drawCharts,100); }
  if(name==='users') loadUsers();
  if(name==='shared') loadShared();
  if(name==='settings'){ loadStorage(); }
  document.getElementById('sidebar').classList.add('-translate-x-full');
  if(window.innerWidth>=1024) document.getElementById('sidebar').classList.remove('-translate-x-full');
}
function setViewMode(m){ viewMode=m; document.getElementById('btnGrid').className=m==='grid'?'px-3 py-2 rounded-xl bg-slate-900 dark:bg-white dark:text-slate-900 text-white':'px-3 py-2 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#1a1d27]'; document.getElementById('btnList').className=m==='list'?'px-3 py-2 rounded-xl bg-slate-900 dark:bg-white dark:text-slate-900 text-white':'px-3 py-2 rounded-xl border dark:border-[#2a2d3a] bg-white dark:bg-[#1a1d27]'; document.getElementById('gridView').classList.toggle('hidden',m!=='grid'); document.getElementById('listView').classList.toggle('hidden',m!=='list'); }
function filterCat(c){ currentFilter=c; loadFiles(); }
document.getElementById('globalSearch').addEventListener('input', debounce(()=>loadFiles(),300));
document.getElementById('sortBy').addEventListener('change',()=>loadFiles());
document.getElementById('sortOrder').addEventListener('change',()=>loadFiles());
document.getElementById('dateFilter').addEventListener('change',()=>loadFiles());

function toast(msg, type='success'){
  const w=document.getElementById('toastWrap');
  const el=document.createElement('div');
  el.className=`px-4 py-3 rounded-xl text-sm font-medium shadow-lg border ${type==='success'?'bg-white dark:bg-[#1a1d27] border-emerald-200 text-emerald-700':'bg-white dark:bg-[#1a1d27] border-red-200 text-red-700'}`;
  el.innerHTML=(type==='success'?'✓ ':'✕ ')+msg;
  w.appendChild(el); setTimeout(()=>el.remove(),2800);
}
function debounce(fn,ms){let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)}}
function isDark(){ return document.documentElement.classList.contains('dark'); }

function drawCharts(){
  const dark=isDark();
  const gridColor = dark ? '#2a2d3a' : '#e2e8f0';
  const textColor = dark ? '#94a3b8' : '#64748b';
  // storage doughnut - uses stats if available else mock
  const sc=document.getElementById('storageChart');
  if(sc){
    if(storageChart) storageChart.destroy();
    const used = parseFloat(document.getElementById('storagePct')?.textContent)||12.4;
    storageChart=new Chart(sc,{type:'doughnut', data:{labels:['Used','Free'], datasets:[{data:[used,100-used], backgroundColor:['#6366f1','#e2e8f0'], borderWidth:0, hoverOffset:8}]}, options:{cutout:'72%', plugins:{legend:{display:false}}, animation:{animateRotate:true, duration:800}}});
    // fix free color for dark
    if(dark) {storageChart.data.datasets[0].backgroundColor=['#6366f1','#2a2d3a']; storageChart.update();}
  }
  const tc=document.getElementById('typeChart');
  if(tc){
    if(typeChart) typeChart.destroy();
    // use real counts from stats API if available, fallback to mock
    const bt = window._byType || {images:0,pdf:0,docs:0,excel:0,zip:0,other:0};
    typeChart=new Chart(tc,{type:'bar', data:{labels:['Images','PDF','Docs','Excel','ZIP','Other'], datasets:[{label:'Files', data:[bt.images,bt.pdf,bt.docs,bt.excel,bt.zip,bt.other], backgroundColor:['#10b981','#ef4444','#3b82f6','#22c55e','#f59e0b','#6366f1'], borderRadius:8, barThickness:18}]}, options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{grid:{display:false}, ticks:{color:textColor}}, y:{grid:{color:gridColor}, ticks:{color:textColor}, beginAtZero:true, ticks:{precision:0}}}}});
  }
  const ac=document.getElementById('activityChart');
  if(ac){
    if(activityChart) activityChart.destroy();
    activityChart=new Chart(ac,{type:'line', data:{labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets:[{label:'Uploads', data:[3,5,2,8,6,9,4], borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,.15)', fill:true, tension:.4, pointRadius:4, pointHoverRadius:6}, {label:'Downloads', data:[2,3,4,5,3,6,7], borderColor:'#06b6d4', backgroundColor:'rgba(6,182,214,.1)', fill:true, tension:.4, pointRadius:3}]}, options:{responsive:true, maintainAspectRatio:false, interaction:{intersect:false, mode:'index'}, plugins:{legend:{labels:{color:textColor, usePointStyle:true}}}, scales:{x:{grid:{color:gridColor}, ticks:{color:textColor}}, y:{grid:{color:gridColor}, ticks:{color:textColor}}}}});
  }
  const mc=document.getElementById('miniStorage');
  if(mc){
    if(miniChart) miniChart.destroy();
    miniChart=new Chart(mc,{type:'bar', data:{labels:['','','','',''], datasets:[{data:[30,45,35,60,40], backgroundColor: dark ? '#6366f1' : '#-indigo-600', borderRadius:4}]}, options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{x:{display:false}, y:{display:false}}}});
  }
}

async function refreshDashboard(){
  const r=await fetch('api/files.php?action=stats'); const j=await r.json();
  // animate numbers
  animateValue('statFiles', j.totalFiles);
  animateValue('statFolders', j.totalFolders);
  const usedGB=(j.used/1024/1024/1024).toFixed(2);
  document.getElementById('statUsed').textContent=usedGB+' GB';
  const quotaGB = (j.quota/1024/1024/1024);
  document.getElementById('statAvail').textContent=(quotaGB - usedGB).toFixed(1)+' GB';
  const pct=Math.min(100, (j.used/j.quota*100)).toFixed(1);
  window._byType=j.byType||{images:0,pdf:0,docs:0,excel:0,zip:0,other:0};
  document.getElementById('storageText').textContent=usedGB+' GB / '+quotaGB.toFixed(0)+' GB';
  const sq = document.getElementById('statQuotaText'); if(sq) sq.textContent='of '+quotaGB.toFixed(0)+' GB';
  document.getElementById('storageBar').style.width=pct+'%';
  document.getElementById('storagePct').textContent=pct+'%';
  drawCharts();
  const rr=await fetch('api/files.php?action=recent'); const rj=await rr.json();
  const rl=document.getElementById('recentList'); rl.innerHTML='';
  (rj.files||[]).slice(0,5).forEach(f=>{
    rl.innerHTML+=`<div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-[#0f1117] border border-transparent hover:border-slate-200 dark:hover:border-[#2a2d3a] transition cursor-pointer" onclick="switchView('myfiles')"><div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-[#0f1117] flex items-center justify-center text-slate-600 dark:text-slate-300"><i class="bi ${iconFor(f.extension, f.mime_type)}"></i></div><div class="flex-1 min-w-0"><div class="text-sm font-medium truncate">${esc(f.name)}</div><div class="text-xs text-slate-500">${(f.size/1024).toFixed(0)} KB · ${f.extension||''}</div></div><span class="text-xs text-slate-400">${timeAgo(f.updated_at||f.created_at)}</span></div>`;
  });
  const al=document.getElementById('activityList'); al.innerHTML='';
  (rj.logs||[]).slice(0,8).forEach(l=>{
    al.innerHTML+=`<div class="flex gap-3 p-3 rounded-xl bg-slate-50 dark:bg-[#0f1117] border dark:border-[#2a2d3a]"><div class="w-8 h-8 rounded-full bg-slate-900 dark:bg-indigo-600 text-white flex items-center justify-center text-xs flex-shrink-0"><i class="bi bi-activity"></i></div><div class="flex-1 min-w-0"><div class="text-sm truncate"><span class="font-semibold">${esc(l.target_name||l.action)}</span> <span class="text-slate-500">· ${esc(l.action)}</span></div><div class="text-xs text-slate-400">${timeAgo(l.created_at)} · ${esc(l.details||'')}</div></div></div>`;
  });
  if(!rj.logs?.length) al.innerHTML='<div class="text-sm text-slate-400 col-span-2">No activity yet — upload a file to see live graph.</div>';
}
function animateValue(id, end){
  const el=document.getElementById(id);
  let start=0; const duration=600; const startTime=performance.now();
  function step(now){
    const p=Math.min(1,(now-startTime)/duration);
    const eased=1-Math.pow(1-p,3);
    el.textContent=Math.floor(eased*end);
    if(p<1) requestAnimationFrame(step);
    else el.textContent=end;
  }
  requestAnimationFrame(step);
}

function iconFor(ext,mime){
  ext=(ext||'').toLowerCase();
  if(['jpg','jpeg','png','webp','gif'].includes(ext)) return 'bi-image';
  if(ext==='pdf') return 'bi-file-earmark-pdf';
  if(['xls','xlsx','csv'].includes(ext)) return 'bi-file-earmark-spreadsheet';
  if(['doc','docx'].includes(ext)) return 'bi-file-earmark-word';
  if(['zip','rar','7z'].includes(ext)) return 'bi-file-earmark-zip';
  if((mime||'').startsWith('video')) return 'bi-camera-video';
  return 'bi-file-earmark';
}
function esc(s){return (s||'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
function timeAgo(d){ if(!d) return ''; const diff=(Date.now()-new Date(d.replace(' ','T')))/1000; if(diff<60) return 'just now'; if(diff<3600) return Math.floor(diff/60)+'m ago'; if(diff<86400) return Math.floor(diff/3600)+'h ago'; return Math.floor(diff/86400)+'d ago'; }
function fmtDate(d){ if(!d) return ''; const dt=new Date(d.replace(' ','T')); return dt.toLocaleString('en-US',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}); }
function fmtSize(b){ if(b<1024) return b+' B'; if(b<1024*1024) return (b/1024).toFixed(1)+' KB'; if(b<1024*1024*1024) return (b/1024/1024).toFixed(1)+' MB'; return (b/1024/1024/1024).toFixed(2)+' GB'; }

async function loadFiles(){
  const q=document.getElementById('globalSearch').value;
  const sort=document.getElementById('sortBy').value;
  const order=document.getElementById('sortOrder').value;
  const dateFilter=document.getElementById('dateFilter').value;
  const params=new URLSearchParams({action:'list', folder_id: currentFolder||'root', q, filter: currentFilter, sort, order, dateFilter});
  const r=await fetch('api/files.php?'+params); const j=await r.json();
  const br=await fetch('api/files.php?action=breadcrumb&folder_id='+(currentFolder||'root')).then(x=>x.json());
  const bc=document.getElementById('breadcrumb');
  let html=`<button onclick="navTo(null)" class="hover:underline">Home</button>`;
  (br.trail||[]).forEach(t=> html+=` <span class="text-slate-300">/</span> <button onclick="navTo(${t.id})" class="${t.id==currentFolder?'font-bold text-slate-900 dark:text-white':''} hover:underline">${esc(t.name)}</button>`);
  bc.innerHTML=html;
  const grid=document.getElementById('gridView'); grid.innerHTML='';
  const listBody=document.getElementById('listBody'); listBody.innerHTML='';
  const empty=document.getElementById('emptyState');
  const total=(j.folders||[]).length+(j.files||[]).length;
  empty.classList.toggle('hidden', total!==0);
  (j.folders||[]).forEach(f=>{
    grid.innerHTML+=folderCard(f);
    listBody.innerHTML+=`<tr class="border-t dark:border-[#2a2d3a] hover:bg-slate-50 dark:hover:bg-[#0f1117]"><td class="px-4 py-3"><button onclick="navTo(${f.id})" class="flex items-center gap-3"><span class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-600 flex items-center justify-center"><i class="bi bi-folder-fill"></i></span><span class="font-medium">${esc(f.name)}</span></button></td><td class="px-4 py-3 text-slate-500">Folder</td><td class="px-4 py-3">—</td><td class="px-4 py-3 text-slate-500">${fmtDate(f.created_at)}</td><td class="px-4 py-3">You</td><td class="px-4 py-3 text-right"><button onclick="showCtx(event,${f.id},'folder')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-[#0f1117]"><i class="bi bi-three-dots-vertical"></i></button></td></tr>`;
  });
  (j.files||[]).forEach(f=>{
    grid.innerHTML+=fileCard(f);
    listBody.innerHTML+=`<tr class="border-t dark:border-[#2a2d3a] hover:bg-slate-50 dark:hover:bg-[#0f1117]"><td class="px-4 py-3"><button onclick="previewFile(${f.id})" class="flex items-center gap-3"><span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-[#0f1117] flex items-center justify-center"><i class="bi ${iconFor(f.extension,f.mime_type)}"></i></span><span class="font-medium truncate max-w-[220px]">${esc(f.name)}</span></button></td><td class="px-4 py-3">${esc(f.extension||'')}</td><td class="px-4 py-3">${fmtSize(f.size)}</td><td class="px-4 py-3">${fmtDate(f.updated_at||f.created_at)}</td><td class="px-4 py-3">You</td><td class="px-4 py-3 text-right"><button onclick="showCtx(event,${f.id},'file')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-[#0f1117]"><i class="bi bi-three-dots-vertical"></i></button></td></tr>`;
  });
}
function folderCard(f){
  const count = f.file_count !== undefined ? f.file_count : 0;
  const gradients = [
    'from-[#00F2FE] to-[#4FACFE]',
    'from-[#FF0844] to-[#FFB199]',
    'from-[#00C6FF] to-[#0072FF]',
    'from-[#F093FB] to-[#F5576C]',
    'from-[#43E97B] to-[#38F9D7]',
    'from-[#FAD961] to-[#F76B1C]',
    'from-[#B12A5B] to-[#FF8177]'
  ];
  const grad = gradients[f.id % gradients.length];
  
  return `<div class="file-card bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-4 cursor-pointer group" onclick="navTo(${f.id})" oncontextmenu="showCtx(event,${f.id},'folder');return false">
    <div class="flex justify-between items-start">
      <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center text-xl shadow flex-shrink-0"><i class="bi bi-folder-fill"></i></div>
      <div class="flex items-center gap-1">
        <div class="text-[28px] font-black text-transparent bg-clip-text bg-gradient-to-br ${grad} drop-shadow-sm pr-1" title="${count} files in this folder">${count}</div>
        <button onclick="event.stopPropagation();showCtx(event,${f.id},'folder')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-[#0f1117] opacity-0 group-hover:opacity-100"><i class="bi bi-three-dots-vertical text-slate-400"></i></button>
      </div>
    </div>
    <div class="font-semibold text-sm mt-3 truncate">${esc(f.name)}</div><div class="text-xs text-slate-500 mt-1">Folder · ${timeAgo(f.created_at)}</div></div>`;
}
function fileCard(f){
  const isImg = ['jpg','jpeg','png','webp','gif'].includes((f.extension||'').toLowerCase());
  return `<div class="file-card bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-4 cursor-pointer group" onclick="selectFile(${f.id})" ondblclick="previewFile(${f.id})" oncontextmenu="showCtx(event,${f.id},'file');return false" data-id="${f.id}">
    <div class="flex justify-between items-start"><div class="w-12 h-12 rounded-xl ${isImg?'bg-gradient-to-br from-emerald-500 to-teal-500':'bg-gradient-to-br from-indigo-500 to-violet-500'} text-white flex items-center justify-center text-xl shadow"><i class="bi ${iconFor(f.extension,f.mime_type)}"></i></div>
      <div class="flex items-center gap-1"><button onclick="event.stopPropagation();toggleStar(${f.id},'file')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-[#0f1117] ${f.is_starred?'text-amber-500':'text-slate-300'}"><i class="bi ${f.is_starred?'bi-star-fill':'bi-star'}"></i></button><button onclick="event.stopPropagation();showCtx(event,${f.id},'file')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-[#0f1117] opacity-0 group-hover:opacity-100"><i class="bi bi-three-dots-vertical"></i></button></div></div>
    <div class="font-semibold text-sm mt-3 truncate" title="${esc(f.name)}">${esc(f.name)}</div><div class="text-xs text-slate-500 mt-1 flex gap-2"><span>${esc((f.extension||'').toUpperCase())}</span><span>·</span><span>${fmtSize(f.size)}</span></div><div class="text-xs text-slate-400 mt-1 truncate">${timeAgo(f.updated_at||f.created_at)} · You</div></div>`;
}
function navTo(id){ currentFolder=id; loadFiles(); }
document.querySelectorAll('.filter-btn').forEach(b=>b.addEventListener('click',()=>{ document.querySelectorAll('.filter-btn').forEach(x=>{x.classList.remove('bg-slate-900','dark:bg-white','dark:text-slate-900','text-white')}); b.classList.add('bg-slate-900','dark:bg-white','dark:text-slate-900','text-white'); currentFilter=b.dataset.filter; loadFiles();}));

async function selectFile(id){
  document.querySelectorAll('.file-card').forEach(c=>c.classList.remove('ring-2','ring-indigo-500'));
  const el=document.querySelector(`[data-id="${id}"]`); if(el) el.classList.add('ring-2','ring-indigo-500');
  const r=await fetch('api/files.php?action=list&folder_id='+(currentFolder||'root')); const j=await r.json();
  const f=[...(j.files||[])].find(x=>x.id==id); if(!f) return;
  selected={id, type:'file', data:f}; openDetails(f);
}
function openDetails(f){
  document.getElementById('detailsPanel').classList.remove('translate-x-full');
  document.getElementById('detailsContent').innerHTML=`
    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center text-2xl shadow"><i class="bi ${iconFor(f.extension,f.mime_type)}"></i></div>
    <div class="font-bold">${esc(f.name)}</div>
    <div class="space-y-2 text-sm">
      <div class="flex justify-between"><span class="text-slate-500">Type</span><span class="font-medium">${esc(f.extension||'')}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">Size</span><span class="font-medium">${fmtSize(f.size)}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">Modified</span><span>${esc(f.updated_at||f.created_at)}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">Provider</span><span class="mono text-xs bg-slate-100 dark:bg-[#0f1117] px-2 py-1 rounded">${esc(f.storage_provider)}</span></div>
    </div>
    <div class="grid grid-cols-2 gap-2 pt-2">
      <button onclick="previewFile(${f.id})" class="border dark:border-[#2a2d3a] rounded-xl py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-eye"></i> Preview</button>
      <a href="api/download.php?id=${f.id}" class="bg-slate-900 dark:bg-white dark:text-slate-900 text-white rounded-xl py-2.5 text-sm font-medium text-center">Download</a>
      <button onclick="renamePrompt(${f.id},'file')" class="border dark:border-[#2a2d3a] rounded-xl py-2.5 text-sm">Rename</button>
      <button onclick="deleteItem(${f.id},'file')" class="border dark:border-[#2a2d3a] rounded-xl py-2.5 text-sm text-red-600">Delete</button>
    </div>`;
}
function closeDetails(){ document.getElementById('detailsPanel').classList.add('translate-x-full'); }
let ctxTarget=null;
function showCtx(e,id,type){
  e.preventDefault(); e.stopPropagation(); ctxTarget={id,type};
  const m=document.getElementById('ctxMenu');
  m.innerHTML=`<button onclick="previewFile(${id})" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-eye mr-2"></i>Preview</button><button onclick="window.location='api/download.php?id=${id}'" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-download mr-2"></i>Download</button><button onclick="openShare(${id},'${type}')" class="w-full text-left px-4 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-950 text-indigo-600"><i class="bi bi-share mr-2"></i>Share</button><button onclick="renamePrompt(${id},'${type}')" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-pencil mr-2"></i>Rename</button><button onclick="toggleStar(${id},'${type}')" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-[#0f1117]"><i class="bi bi-star mr-2"></i>Star</button><div class="border-t dark:border-[#2a2d3a] my-1"></div><button onclick="deleteItem(${id},'${type}')" class="w-full text-left px-4 py-2 hover:bg-red-50 dark:hover:bg-red-950 text-red-600"><i class="bi bi-trash mr-2"></i>Delete</button>`;
  m.classList.remove('hidden');
  let x = e.pageX; let y = e.pageY;
  if(x + m.offsetWidth > window.innerWidth) x = window.innerWidth - m.offsetWidth - 8;
  if(y + m.offsetHeight > window.innerHeight) y = window.innerHeight - m.offsetHeight - 8;
  m.style.left=x+'px'; m.style.top=y+'px';
}
document.addEventListener('click',()=>document.getElementById('ctxMenu').classList.add('hidden'));
async function previewFile(id){
  document.getElementById('previewModal').classList.remove('hidden');
  const title=document.getElementById('previewTitle'); const body=document.getElementById('previewBody');
  body.innerHTML='<div class="text-slate-400">Loading…</div>';
  const r=await fetch('api/files.php?action=list&folder_id='+(currentFolder||'root')); const j=await r.json();
  let f=[...(j.files||[])].find(x=>x.id==id); if(!f) f={id};
  if(!f.extension){ body.innerHTML=`<iframe src="api/download.php?id=${id}&preview=1" class="w-full h-[60vh] border dark:border-[#2a2d3a] rounded-xl bg-white"></iframe>`; title.textContent='Preview #'+id; return; }
  title.textContent=f.name; const ext=(f.extension||'').toLowerCase();
  if(['jpg','jpeg','png','webp','gif'].includes(ext)) body.innerHTML=`<img src="api/download.php?id=${id}&preview=1" class="max-w-full max-h-[70vh] rounded-xl shadow">`;
  else if(ext==='pdf') body.innerHTML=`<iframe src="api/download.php?id=${id}&preview=1" class="w-full h-[65vh] border rounded-xl bg-white"></iframe>`;
  else if(['txt','csv','log','md'].includes(ext)){ const txt=await fetch('api/download.php?id='+id+'&preview=1').then(r=>r.text()); body.innerHTML=`<pre class="w-full max-h-[65vh] overflow-auto bg-white dark:bg-[#0f1117] border dark:border-[#2a2d3a] rounded-xl p-4 text-sm mono whitespace-pre-wrap">${esc(txt.slice(0,20000))}</pre>`; }
  else body.innerHTML=`<div class="text-center p-8"><div class="w-16 h-16 rounded-2xl bg-slate-200 dark:bg-[#0f1117] flex items-center justify-center mx-auto text-2xl"><i class="bi ${iconFor(ext,'')}"></i></div><div class="font-bold mt-3">Preview not available</div><a href="api/download.php?id=${id}" class="inline-flex mt-4 bg-slate-900 dark:bg-white dark:text-slate-900 text-white px-5 py-2.5 rounded-xl text-sm">Download File</a></div>`;
}
function closePreview(){ document.getElementById('previewModal').classList.add('hidden'); }
function openNewFolder(){ document.getElementById('folderModal').classList.remove('hidden'); document.getElementById('folderName').focus(); }
function closeFolderModal(){ document.getElementById('folderModal').classList.add('hidden'); document.getElementById('folderName').value=''; }
async function createFolder(){
  const name=document.getElementById('folderName').value.trim(); if(!name) return toast('Folder name required','error');
  const fd=new FormData(); fd.append('action','create_folder'); fd.append('name',name); fd.append('parent_id', currentFolder||'');
  const r=await fetch('api/files.php?action=create_folder',{method:'POST',body:fd}); const j=await r.json();
  if(j.ok){ toast('Folder created'); closeFolderModal(); loadFiles(); refreshDashboard(); } else toast(j.error||'Failed','error');
}
const fileInput=document.getElementById('fileInput');
fileInput.addEventListener('change',()=>uploadFiles(fileInput.files));
const dropZone=document.getElementById('dropZone');
['dragenter','dragover'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault(); dropZone.classList.add('border-indigo-400','bg-indigo-50','dark:bg-indigo-950/30')}));
;['dragleave','drop'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault(); dropZone.classList.remove('border-indigo-400','bg-indigo-50','dark:bg-indigo-950/30')}));
dropZone.addEventListener('drop',e=>{ const files=e.dataTransfer.files; if(files.length) uploadFiles(files); });
document.addEventListener('dragover',e=>e.preventDefault());
document.addEventListener('drop',e=>{ if(e.dataTransfer.files.length){ e.preventDefault(); uploadFiles(e.dataTransfer.files); }});
async function uploadFiles(fileList){
  if(!fileList.length) return;
  const CHUNK=5*1024*1024; // 5MB
  for(const file of fileList){
    // small files < 10MB use direct upload, large use chunked (supports 2GB + multiple)
    if(file.size < 10*1024*1024){
      await directUpload(file);
    } else {
      await chunkedUpload(file, CHUNK);
    }
  }
  fileInput.value='';
}
async function directUpload(file){
  const queue=document.getElementById('uploadQueue');
  const card=document.createElement('div'); card.className='bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-2xl p-4 shadow-lg animate-in';
  card.innerHTML=`<div class="flex justify-between text-sm"><span class="font-medium truncate pr-2">${esc(file.name)}</span><span class="text-xs text-slate-500">${fmtSize(file.size)}</span></div><div class="h-2 bg-slate-100 dark:bg-[#0f1117] rounded-full overflow-hidden mt-2"><div class="h-full bg-gradient-to-r from-indigo-600 to-violet-600 rounded-full transition-all" style="width:0%"></div></div><div class="flex justify-between text-xs mt-1"><span class="progress-text">0%</span><button class="cancel text-slate-400">Cancel</button></div>`;
  queue.appendChild(card); const bar=card.querySelector('div > div'); const txt=card.querySelector('.progress-text');
  const xhr=new XMLHttpRequest(); const fd=new FormData(); fd.append('action','upload'); fd.append('folder_id', currentFolder||''); fd.append('files[]', file);
  xhr.upload.onprogress=e=>{ if(e.lengthComputable){ const p=Math.round(e.loaded/e.total*100); bar.style.width=p+'%'; txt.textContent=p+'%'; } };
  const p=await new Promise((resolve,reject)=>{ xhr.onload=()=> resolve(xhr); xhr.onerror=()=> reject(new Error('Network')); card.querySelector('.cancel').onclick=()=>{ xhr.abort(); card.remove(); reject(new Error('Cancelled')); }; xhr.open('POST','api/files.php?action=upload'); xhr.send(fd); }).catch(e=>{ toast(e.message,'error'); card.remove(); return null; });
  if(p && p.status>=200 && p.status<300){ bar.style.width='100%'; txt.textContent='✓ Complete'; setTimeout(()=>card.remove(),1800); toast('Uploaded: '+file.name); loadFiles(); refreshDashboard(); } else if(p){ bar.style.background='#ef4444'; txt.textContent='Failed'; toast('Upload failed','error'); }
}
async function chunkedUpload(file, chunkSize){
  const queue=document.getElementById('uploadQueue');
  const card=document.createElement('div'); card.className='bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-2xl p-4 shadow-lg animate-in';
  card.innerHTML=`<div class="flex justify-between text-sm"><span class="font-medium truncate pr-2">${esc(file.name)} (chunked 2GB)</span><span class="text-xs text-slate-500">${fmtSize(file.size)}</span></div><div class="h-2 bg-slate-100 dark:bg-[#0f1117] rounded-full overflow-hidden mt-2"><div class="h-full bg-gradient-to-r from-indigo-600 to-violet-600 rounded-full" style="width:0%"></div></div><div class="flex justify-between text-xs mt-1"><span class="progress-text">0% • 0/${Math.ceil(file.size/chunkSize)} chunks</span><button class="cancel text-slate-400">Cancel</button></div>`;
  queue.appendChild(card); const bar=card.querySelector('div > div'); const txt=card.querySelector('.progress-text');
  let cancelled=false; card.querySelector('.cancel').onclick=()=>{ cancelled=true; card.remove(); toast('Cancelled','error'); };
  
// === STORAGE SETTINGS ===
async function loadStorage(){
  const r=await fetch('api/storage_settings.php?action=get').then(r=>r.json());
  document.getElementById('activeProvider').textContent = r.config.driver;
  if(r.config.drivers.ftp){
    const f=r.config.drivers.ftp;
    document.getElementById('ftp_host').value=f.host||'';
    document.getElementById('ftp_port').value=f.port||21;
    document.getElementById('ftp_user').value=f.username||'';
    document.getElementById('ftp_root').value=f.root||'/';
    document.getElementById('ftp_secure').value=f.secure?1:0;
    document.getElementById('ftp_passive').checked = f.passive!==false;
  }
  if(r.config.drivers.google_drive){
    document.getElementById('gd_client').value = r.config.drivers.google_drive.client_id||'';
    document.getElementById('gd_secret').value = r.config.drivers.google_drive.client_secret||'';
    document.getElementById('gd_folder').value = r.config.drivers.google_drive.folder_id||'';
  }
}
async function saveGoogle(){
  const fd=new FormData(); fd.append('action','save'); fd.append('provider','google_drive');
  fd.append('client_id', document.getElementById('gd_client').value);
  fd.append('client_secret', document.getElementById('gd_secret').value);
  fd.append('folder_id', document.getElementById('gd_folder').value);
  fd.append('redirect_uri', document.getElementById('gd_redirect').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok?'Google Drive saved':'Error',''+(r.ok?'success':'error')); loadStorage();
}
async function saveFtp(){
  const fd=new FormData(); fd.append('action','save'); fd.append('provider','ftp');
  fd.append('host', document.getElementById('ftp_host').value);
  fd.append('port', document.getElementById('ftp_port').value);
  fd.append('username', document.getElementById('ftp_user').value);
  fd.append('password', document.getElementById('ftp_pass').value);
  fd.append('root', document.getElementById('ftp_root').value);
  fd.append('secure', document.getElementById('ftp_secure').value);
  fd.append('passive', document.getElementById('ftp_passive').checked?1:0);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok?'FTP saved':'Error: '+(r.error||''), r.ok?'success':'error'); loadStorage();
}
async function testFtp(){
  document.getElementById('ftpTest').textContent='Testing...';
  const fd=new FormData(); fd.append('action','test_ftp');
  fd.append('host', document.getElementById('ftp_host').value);
  fd.append('port', document.getElementById('ftp_port').value);
  fd.append('username', document.getElementById('ftp_user').value);
  fd.append('password', document.getElementById('ftp_pass').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  document.getElementById('ftpTest').textContent = r.ok ? '✓ Connected' : '✗ '+(r.error||'Failed');
  document.getElementById('ftpTest').className = r.ok ? 'text-xs py-2 text-emerald-600' : 'text-xs py-2 text-red-600';
}
async function saveS3(){
  const fd=new FormData(); fd.append('action','save'); fd.append('provider','hostinger');
  fd.append('endpoint', document.getElementById('s3_endpoint').value);
  fd.append('bucket', document.getElementById('s3_bucket').value);
  fd.append('api_key', document.getElementById('s3_key').value);
  fd.append('secret', document.getElementById('s3_secret').value);
  fd.append('region', document.getElementById('s3_region').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok?'Hostinger/S3 saved':'Error');
}
async function activateStorage(p){
  const fd=new FormData(); fd.append('action','set_active'); fd.append('provider', p);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok? p+' is now ACTIVE': r.error, r.ok?'success':'error'); loadStorage();
}
function setTab(name){
  ['storage','general','share'].forEach(n=>{
    document.getElementById('tab-panel-'+n).classList.toggle('hidden', n!==name);
    document.getElementById('tab-'+n).classList.toggle('bg-slate-900', n===name);
    document.getElementById('tab-'+n).classList.toggle('text-white', n===name);
    document.getElementById('tab-'+n).classList.toggle('dark:bg-white', n===name);
    document.getElementById('tab-'+n).classList.toggle('dark:text-slate-900', n===name);
  });
  if(name==='share') loadShares(); if(name==='storage') loadStorage();
}
async function loadShares(){
  const r=await fetch('api/share.php?action=list').then(r=>r.json());
  const el=document.getElementById('shareList'); el.innerHTML='';
  (r.shares||[]).forEach(s=>{ el.innerHTML+=`<div class="flex justify-between items-center border dark:border-[#2a2d3a] rounded-xl p-3"><div><div class="font-medium">${esc(s.file_name||s.folder_name||'Link')} • ${esc(s.permission)}</div><div class="text-xs text-slate-500">${esc(s.shared_with_email||'public link')} • ${timeAgo(s.created_at)}</div></div><button onclick="deleteShare(${s.id})" class="text-xs text-red-600">Revoke</button></div>`; });
  if(!r.shares?.length) el.innerHTML='<div class="text-sm text-slate-400">No shares yet.</div>';
}
async function deleteShare(id){ await fetch('api/share.php', {method:'POST', body: Object.assign(new FormData(), (()=>{const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); return fd;})())}); loadShares(); }

// === SHARE ===
function openShare(id, type){
  document.getElementById('shareFileId').value = type==='file'?id:'';
  document.getElementById('shareFolderId').value = type==='folder'?id:'';
  document.getElementById('shareLink').classList.add('hidden');
  document.getElementById('shareModal').classList.remove('hidden');
}
function closeShare(){ document.getElementById('shareModal').classList.add('hidden'); }
async function doShare(){
  const fd=new FormData(); fd.append('action','create');
  if(document.getElementById('shareFileId').value) fd.append('file_id', document.getElementById('shareFileId').value);
  if(document.getElementById('shareFolderId').value) fd.append('folder_id', document.getElementById('shareFolderId').value);
  fd.append('share_with', document.getElementById('shareEmail').value);
  fd.append('permission', document.getElementById('sharePerm').value);
  const r=await fetch('api/share.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ const el=document.getElementById('shareLink'); el.textContent='Link: '+r.link; el.classList.remove('hidden'); toast('Shared successfully'); } else toast(r.error,'error');
}
async function loadShared(){
  const r=await fetch('api/share.php?action=shared_with_me').then(r=>r.json());
  const g=document.getElementById('sharedGrid'); g.innerHTML='';
  [...(r.folders||[]),...(r.files||[])].forEach(it=>{
    if(it.item_type==='folder' || it.folder_name) g.innerHTML+=folderCard(it);
    else g.innerHTML+=fileCard({...it, is_starred:0});
  });
  document.getElementById('sharedEmpty').classList.toggle('hidden', g.innerHTML!=='');
}

// === USERS ADMIN ===
async function loadUsers(){
  const r=await fetch('api/users.php?action=list').then(r=>r.json());
  if(r.error){ document.getElementById('userTable').innerHTML=`<tr><td colspan=6 class="p-6 text-center text-red-500">${esc(r.error)} (admin only)</td></tr>`; return; }
  const tb=document.getElementById('userTable'); tb.innerHTML='';
  r.users.forEach(u=>{
    tb.innerHTML+=`<tr class="border-t dark:border-[#2a2d3a] hover:bg-slate-50 dark:hover:bg-[#0f1117]"><td class="px-4 py-3"><div class="font-medium">${esc(u.name)}</div><div class="text-xs text-slate-500">${esc(u.email)}</div></td><td class="px-4 py-3"><select onchange="setRole(${u.id},this.value)" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-lg px-2 py-1 text-xs"><option ${u.role==='admin'?'selected':''} value="admin">Admin</option><option ${u.role==='user'?'selected':''} value="user">User</option></select></td><td class="px-4 py-3"><div class="flex items-center gap-2"><input type="number" value="${u.quota_gb}" onchange="setQuota(${u.id},this.value)" class="w-16 border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-lg px-2 py-1 text-xs"> GB</div></td><td class="px-4 py-3 text-xs">${fmtSize(u.used)} / ${u.quota_gb}GB</td><td class="px-4 py-3"><button onclick="editPerms(${u.id})" class="text-xs px-2 py-1 rounded-lg border dark:border-[#2a2d3a]">Permissions</button></td><td class="px-4 py-3 text-right"><button onclick="delUser(${u.id})" class="text-xs text-red-600 hover:underline">Delete</button></td></tr>`;
  });
}
function openUserModal(){ document.getElementById('userModal').classList.remove('hidden'); }
function closeUserModal(){ document.getElementById('userModal').classList.add('hidden'); }
async function createUser(){
  const fd=new FormData(); fd.append('action','create');
  fd.append('name', document.getElementById('nu_name').value);
  fd.append('email', document.getElementById('nu_email').value);
  fd.append('password', document.getElementById('nu_pass').value);
  fd.append('role', document.getElementById('nu_role').value);
  fd.append('quota_gb', document.getElementById('nu_quota').value);
  const r=await fetch('api/users.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ toast('User created'); closeUserModal(); loadUsers(); } else toast(r.error,'error');
}
async function setQuota(id,v){ const fd=new FormData(); fd.append('action','update_quota'); fd.append('id',id); fd.append('quota_gb',v); await fetch('api/users.php',{method:'POST',body:fd}); toast('Quota updated'); }
async function setRole(id,v){ const fd=new FormData(); fd.append('action','update_role'); fd.append('id',id); fd.append('role',v); await fetch('api/users.php',{method:'POST',body:fd}); toast('Role updated'); }
async function delUser(id){ if(!confirm('Delete user?')) return; const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); const r=await fetch('api/users.php',{method:'POST',body:fd}).then(r=>r.json()); if(r.ok){ toast('Deleted'); loadUsers(); } else toast(r.error,'error'); }
async function editPerms(uid){
  const r=await fetch('api/users.php?action=permissions&user_id='+uid).then(r=>r.json());
  const p=r.permissions; const checks = Object.entries(p).filter(([k])=>k.startsWith('can_')).map(([k,v])=> `<label class="flex items-center gap-2 text-sm"><input type="checkbox" ${v?'checked':''} id="perm_${k}"> ${k}</label>`).join('');
  const html = `<div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onclick="this.remove()"><div class="bg-white dark:bg-[#1a1d27] rounded-2xl p-6 w-full max-w-sm" onclick="event.stopPropagation()"><h3 class="font-bold">Permissions</h3><div class="mt-3 space-y-2">${checks}</div><button onclick="savePerms(${uid})" class="mt-4 w-full bg-indigo-600 text-white rounded-xl py-2 text-sm">Save</button></div></div>`;
  document.body.insertAdjacentHTML('beforeend', html);
}
async function savePerms(uid){
  const fd=new FormData(); fd.append('action','save_permissions'); fd.append('user_id',uid);
  ['can_upload','can_download','can_share','can_delete','can_create_folder'].forEach(k=>{ if(document.getElementById('perm_'+k)?.checked) fd.append(k,'1'); });
  await fetch('api/users.php',{method:'POST', body:fd}); document.querySelector('.fixed.inset-0.bg-black\/40')?.remove(); toast('Permissions saved');
}

// Patch context menu to add Share

// init
  const initFd=new FormData(); initFd.append('action','init'); initFd.append('filename', file.name); initFd.append('totalSize', file.size); initFd.append('totalChunks', Math.ceil(file.size/chunkSize)); initFd.append('folder_id', currentFolder||'');
  const initRes=await fetch('api/chunk_upload.php', {method:'POST', body:initFd}).then(r=>r.json());
  if(!initRes.ok){ toast(initRes.error||'Init failed','error'); card.remove(); return; }
  const uploadId=initRes.uploadId;
  const total=Math.ceil(file.size/chunkSize);
  for(let i=0;i<total;i++){
    if(cancelled) return;
    const chunk=file.slice(i*chunkSize, (i+1)*chunkSize);
    const fd=new FormData(); fd.append('action','upload_chunk'); fd.append('uploadId', uploadId); fd.append('chunkIndex', i); fd.append('chunk', chunk);
    const res=await fetch('api/chunk_upload.php', {method:'POST', body:fd}).then(r=>r.json());
    if(!res.ok){ toast('Chunk '+i+' failed','error'); card.remove(); return; }
    const pct=Math.round((i+1)/total*100); bar.style.width=pct+'%'; txt.textContent=pct+'% • '+(i+1)+'/'+total+' chunks';
  }
  // complete
  const compFd=new FormData(); compFd.append('action','complete'); compFd.append('uploadId', uploadId);
  const comp=await fetch('api/chunk_upload.php', {method:'POST', body:compFd}).then(r=>r.json());
  if(comp.ok){ bar.style.width='100%'; txt.textContent='✓ Complete • '+fmtSize(file.size); setTimeout(()=>card.remove(),2000); toast('2GB Upload done: '+file.name); loadFiles(); refreshDashboard(); } else { toast(comp.error||'Assemble failed','error'); card.remove(); }
}
async function toggleStar(id,type){ const fd=new FormData(); fd.append('action','star'); fd.append('id',id); fd.append('type',type); const r=await fetch('api/files.php?action=star',{method:'POST',body:fd}); const j=await r.json(); if(j.ok){ toast(j.starred?'Starred':'Unstarred'); loadFiles(); } }
async function renamePrompt(id,type){ const name=prompt('New name:'); if(!name) return; const fd=new FormData(); fd.append('action','rename'); fd.append('id',id); fd.append('type',type); fd.append('name',name); const r=await fetch('api/files.php?action=rename',{method:'POST',body:fd}); const j=await r.json(); if(j.ok){ toast('Renamed'); loadFiles(); } else toast(j.error||'Failed','error'); }
async function deleteItem(id,type){ if(!confirm('Move to Trash?')) return; const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); fd.append('type',type); const r=await fetch('api/files.php?action=delete',{method:'POST',body:fd}); const j=await r.json(); if(j.ok){ toast('Moved to Trash'); loadFiles(); refreshDashboard(); } else toast(j.error||'Failed','error'); }
async function loadStarred(){ const r=await fetch('api/files.php?action=starred_list'); const j=await r.json(); const g=document.getElementById('starredGrid'); g.innerHTML=''; [...(j.folders||[]),...(j.files||[])].forEach(it=>{ if(it.item_type==='folder') g.innerHTML+=folderCard(it); else g.innerHTML+=fileCard(it); }); if(!g.innerHTML) g.innerHTML='<div class="col-span-full bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-[20px] p-12 text-center text-slate-500">No starred items yet.</div>'; }
async function loadTrash(){ const r=await fetch('api/files.php?action=trash_list'); const j=await r.json(); const g=document.getElementById('trashGrid'); g.innerHTML=''; [...(j.folders||[]),...(j.files||[])].forEach(it=>{ const isFolder=it.item_type==='folder'; g.innerHTML+=`<div class="bg-white dark:bg-[#1a1d27] rounded-[20px] border dark:border-[#2a2d3a] p-4"><div class="w-10 h-10 rounded-xl ${isFolder?'bg-amber-100 dark:bg-amber-950 text-amber-600':'bg-slate-100 dark:bg-[#0f1117]'} flex items-center justify-center"><i class="bi ${isFolder?'bi-folder':'bi-file-earmark'}"></i></div><div class="font-semibold text-sm mt-3 truncate">${esc(it.name||it.original_name)}</div><div class="flex gap-2 mt-3"><button onclick="restoreItem(${it.id},'${isFolder?'folder':'file'}')" class="flex-1 border dark:border-[#2a2d3a] rounded-xl py-2 text-xs">Restore</button><button onclick="permaDelete(${it.id},'${isFolder?'folder':'file'}')" class="flex-1 bg-red-600 text-white rounded-xl py-2 text-xs">Delete Forever</button></div></div>`; }); if(!g.innerHTML) g.innerHTML='<div class="col-span-full bg-white dark:bg-[#1a1d27] border dark:border-[#2a2d3a] rounded-[20px] p-12 text-center text-slate-500">Trash is empty.</div>'; }
async function restoreItem(id,type){ const fd=new FormData(); fd.append('action','restore'); fd.append('id',id); fd.append('type',type); await fetch('api/files.php?action=restore',{method:'POST',body:fd}); toast('Restored'); loadTrash(); loadFiles(); }
async function permaDelete(id,type){ if(!confirm('Permanently delete?')) return; const fd=new FormData(); fd.append('action','permanent_delete'); fd.append('id',id); fd.append('type',type); await fetch('api/files.php?action=permanent_delete',{method:'POST',body:fd}); toast('Permanently deleted'); loadTrash(); }
async function loadRecent(){ const r=await fetch('api/files.php?action=recent'); const j=await r.json(); const g=document.getElementById('recentGrid'); g.innerHTML=''; (j.files||[]).forEach(f=> g.innerHTML+=fileCard(f)); }
async function logout(){ await fetch('api/auth.php?action=logout',{method:'POST'}); location.reload(); }

// === STORAGE SETTINGS ===
async function loadStorage(){
  const r=await fetch('api/storage_settings.php?action=get').then(r=>r.json());
  document.getElementById('activeProvider').textContent = r.config.driver;
  if(r.config.drivers.ftp){
    const f=r.config.drivers.ftp;
    document.getElementById('ftp_host').value=f.host||'';
    document.getElementById('ftp_port').value=f.port||21;
    document.getElementById('ftp_user').value=f.username||'';
    document.getElementById('ftp_root').value=f.root||'/';
    document.getElementById('ftp_secure').value=f.secure?1:0;
    document.getElementById('ftp_passive').checked = f.passive!==false;
  }
  if(r.config.drivers.google_drive){
    document.getElementById('gd_client').value = r.config.drivers.google_drive.client_id||'';
    document.getElementById('gd_secret').value = r.config.drivers.google_drive.client_secret||'';
    document.getElementById('gd_folder').value = r.config.drivers.google_drive.folder_id||'';
    if(r.config.drivers.google_drive.redirect_uri) document.getElementById('gd_redirect').value = r.config.drivers.google_drive.redirect_uri;
  }
  
  // Highlight active provider
  const activeDriver = r.config.driver || 'local';
  const btns = {
    'local': document.getElementById('btn_active_local'),
    'google_drive': document.getElementById('btn_active_google_drive'),
    'hostinger': document.getElementById('btn_active_hostinger'),
    'ftp': document.getElementById('btn_active_ftp')
  };
  
  for (const [key, btn] of Object.entries(btns)) {
    if (!btn) continue;
    if (key === activeDriver) {
      btn.className = "px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold flex items-center justify-center gap-2";
      btn.innerHTML = `<i class="bi bi-check-circle-fill"></i> Active`;
      btn.disabled = true;
    } else {
      btn.className = "px-4 py-2 rounded-xl border dark:border-[#2a2d3a] text-xs font-semibold hover:bg-slate-50 dark:hover:bg-[#0f1117] transition";
      btn.innerHTML = `Set Active`;
      btn.disabled = false;
    }
  }
}
async function saveGoogle(){
  const fd=new FormData(); fd.append('action','save'); fd.append('provider','google_drive');
  const clientId = document.getElementById('gd_client').value;
  fd.append('client_id', clientId);
  fd.append('client_secret', document.getElementById('gd_secret').value);
  fd.append('folder_id', document.getElementById('gd_folder').value);
  fd.append('redirect_uri', document.getElementById('gd_redirect').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok && clientId) {
      toast('Google Drive config saved. Redirecting to Google Auth...');
      const urlRes = await fetch('api/storage_settings.php', {method:'POST', body: new URLSearchParams({action:'google_auth_url'})}).then(r=>r.json());
      if (urlRes.ok) { window.location.href = urlRes.url; } else { toast(urlRes.error, 'error'); }
  } else { toast(r.ok?'Saved (Enter Client ID to connect)':'Error',''+(r.ok?'success':'error')); loadStorage(); }
}
async function testGoogleDrive(){
  const el = document.getElementById('gdTest'); el.innerText = 'Testing...'; el.className = 'text-xs py-2 text-slate-500';
  const fd=new FormData(); fd.append('action','test_google_drive');
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok) { el.innerText = 'Connected & Authorized!'; el.className = 'text-xs py-2 text-green-600 font-bold'; }
  else { el.innerText = r.error; el.className = 'text-xs py-2 text-red-600 font-bold'; }
}

async function saveGeneral(){
  const fd=new FormData(); fd.append('action','save_general');
  fd.append('app_name', document.getElementById('gen_company').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok) { toast('Settings saved! Reloading...'); setTimeout(()=>location.reload(), 1000); } else toast(r.error||'Error','error');
}

async function saveS3(){
  const fd=new FormData(); fd.append('action','save'); fd.append('provider','hostinger');
  fd.append('endpoint', document.getElementById('s3_endpoint').value);
  fd.append('bucket', document.getElementById('s3_bucket').value);
  fd.append('api_key', document.getElementById('s3_key').value);
  fd.append('secret', document.getElementById('s3_secret').value);
  fd.append('region', document.getElementById('s3_region').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok?'Hostinger/S3 saved':'Error');
}
async function testS3(){
  const el = document.getElementById('s3Test'); el.innerText = 'Testing...'; el.className = 'text-xs py-2 text-slate-500';
  const fd=new FormData(); fd.append('action','test_hostinger');
  fd.append('endpoint', document.getElementById('s3_endpoint').value);
  fd.append('bucket', document.getElementById('s3_bucket').value);
  fd.append('api_key', document.getElementById('s3_key').value);
  fd.append('secret', document.getElementById('s3_secret').value);
  fd.append('region', document.getElementById('s3_region').value);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok) { el.innerText = 'Connection Successful!'; el.className = 'text-xs py-2 text-green-600 font-bold'; }
  else { el.innerText = r.error; el.className = 'text-xs py-2 text-red-600 font-bold'; }
}
async function activateStorage(p){
  const fd=new FormData(); fd.append('action','set_active'); fd.append('provider', p);
  const r=await fetch('api/storage_settings.php', {method:'POST', body:fd}).then(r=>r.json());
  toast(r.ok? p+' is now ACTIVE': r.error, r.ok?'success':'error'); loadStorage();
}
function setTab(name){
  ['storage','general','share'].forEach(n=>{
    document.getElementById('tab-panel-'+n).classList.toggle('hidden', n!==name);
    document.getElementById('tab-'+n).classList.toggle('bg-slate-900', n===name);
    document.getElementById('tab-'+n).classList.toggle('text-white', n===name);
    document.getElementById('tab-'+n).classList.toggle('dark:bg-white', n===name);
    document.getElementById('tab-'+n).classList.toggle('dark:text-slate-900', n===name);
  });
  if(name==='share') loadShares(); if(name==='storage') loadStorage();
}
async function loadShares(){
  const r=await fetch('api/share.php?action=list').then(r=>r.json());
  const el=document.getElementById('shareList'); el.innerHTML='';
  (r.shares||[]).forEach(s=>{ el.innerHTML+=`<div class="flex justify-between items-center border dark:border-[#2a2d3a] rounded-xl p-3"><div><div class="font-medium">${esc(s.file_name||s.folder_name||'Link')} • ${esc(s.permission)}</div><div class="text-xs text-slate-500">${esc(s.shared_with_email||'public link')} • ${timeAgo(s.created_at)}</div></div><button onclick="deleteShare(${s.id})" class="text-xs text-red-600">Revoke</button></div>`; });
  if(!r.shares?.length) el.innerHTML='<div class="text-sm text-slate-400">No shares yet.</div>';
}
async function deleteShare(id){ await fetch('api/share.php', {method:'POST', body: Object.assign(new FormData(), (()=>{const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); return fd;})())}); loadShares(); }

// === SHARE ===
function openShare(id, type){
  document.getElementById('shareFileId').value = type==='file'?id:'';
  document.getElementById('shareFolderId').value = type==='folder'?id:'';
  document.getElementById('shareLink').classList.add('hidden');
  document.getElementById('shareModal').classList.remove('hidden');
}
function closeShare(){ document.getElementById('shareModal').classList.add('hidden'); }
async function doShare(){
  const fd=new FormData(); fd.append('action','create');
  if(document.getElementById('shareFileId').value) fd.append('file_id', document.getElementById('shareFileId').value);
  if(document.getElementById('shareFolderId').value) fd.append('folder_id', document.getElementById('shareFolderId').value);
  fd.append('share_with', document.getElementById('shareEmail').value);
  fd.append('permission', document.getElementById('sharePerm').value);
  const r=await fetch('api/share.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ const el=document.getElementById('shareLink'); el.textContent='Link: '+r.link; el.classList.remove('hidden'); toast('Shared successfully'); } else toast(r.error,'error');
}
async function loadShared(){
  const r=await fetch('api/share.php?action=shared_with_me').then(r=>r.json());
  const g=document.getElementById('sharedGrid'); g.innerHTML='';
  [...(r.folders||[]),...(r.files||[])].forEach(it=>{
    if(it.item_type==='folder' || it.folder_name) g.innerHTML+=folderCard(it);
    else g.innerHTML+=fileCard({...it, is_starred:0});
  });
  document.getElementById('sharedEmpty').classList.toggle('hidden', g.innerHTML!=='');
}

// === USERS ADMIN ===
async function loadUsers(){
  const r=await fetch('api/users.php?action=list').then(r=>r.json());
  if(r.error){ document.getElementById('userTable').innerHTML=`<tr><td colspan=6 class="p-6 text-center text-red-500">${esc(r.error)} (admin only)</td></tr>`; return; }
  const tb=document.getElementById('userTable'); tb.innerHTML='';
  r.users.forEach(u=>{
    tb.innerHTML+=`<tr class="border-t dark:border-[#2a2d3a] hover:bg-slate-50 dark:hover:bg-[#0f1117]"><td class="px-4 py-3"><div class="font-medium">${esc(u.name)}</div><div class="text-xs text-slate-500">${esc(u.email)}</div></td><td class="px-4 py-3"><select onchange="setRole(${u.id},this.value)" class="border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-lg px-2 py-1 text-xs"><option ${u.role==='admin'?'selected':''} value="admin">Admin</option><option ${u.role==='user'?'selected':''} value="user">User</option></select></td><td class="px-4 py-3"><div class="flex items-center gap-2"><input type="number" value="${u.quota_gb}" onchange="setQuota(${u.id},this.value)" class="w-16 border dark:border-[#2a2d3a] dark:bg-[#0f1117] rounded-lg px-2 py-1 text-xs"> GB</div></td><td class="px-4 py-3 text-xs">${fmtSize(u.used)} / ${u.quota_gb}GB</td><td class="px-4 py-3"><button onclick="editPerms(${u.id})" class="text-xs px-2 py-1 rounded-lg border dark:border-[#2a2d3a]">Permissions</button></td><td class="px-4 py-3 text-right"><button onclick="delUser(${u.id})" class="text-xs text-red-600 hover:underline">Delete</button></td></tr>`;
  });
}
function openUserModal(){ document.getElementById('userModal').classList.remove('hidden'); }
function closeUserModal(){ document.getElementById('userModal').classList.add('hidden'); }
async function createUser(){
  const fd=new FormData(); fd.append('action','create');
  fd.append('name', document.getElementById('nu_name').value);
  fd.append('email', document.getElementById('nu_email').value);
  fd.append('password', document.getElementById('nu_pass').value);
  fd.append('role', document.getElementById('nu_role').value);
  fd.append('quota_gb', document.getElementById('nu_quota').value);
  const r=await fetch('api/users.php', {method:'POST', body:fd}).then(r=>r.json());
  if(r.ok){ toast('User created'); closeUserModal(); loadUsers(); } else toast(r.error,'error');
}
async function setQuota(id,v){ const fd=new FormData(); fd.append('action','update_quota'); fd.append('id',id); fd.append('quota_gb',v); await fetch('api/users.php',{method:'POST',body:fd}); toast('Quota updated'); }
async function setRole(id,v){ const fd=new FormData(); fd.append('action','update_role'); fd.append('id',id); fd.append('role',v); await fetch('api/users.php',{method:'POST',body:fd}); toast('Role updated'); }
async function delUser(id){ if(!confirm('Delete user?')) return; const fd=new FormData(); fd.append('action','delete'); fd.append('id',id); const r=await fetch('api/users.php',{method:'POST',body:fd}).then(r=>r.json()); if(r.ok){ toast('Deleted'); loadUsers(); } else toast(r.error,'error'); }
async function editPerms(uid){
  const r=await fetch('api/users.php?action=permissions&user_id='+uid).then(r=>r.json());
  const p=r.permissions; const checks = Object.entries(p).filter(([k])=>k.startsWith('can_')).map(([k,v])=> `<label class="flex items-center gap-2 text-sm"><input type="checkbox" ${v?'checked':''} id="perm_${k}"> ${k}</label>`).join('');
  const html = `<div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onclick="this.remove()"><div class="bg-white dark:bg-[#1a1d27] rounded-2xl p-6 w-full max-w-sm" onclick="event.stopPropagation()"><h3 class="font-bold">Permissions</h3><div class="mt-3 space-y-2">${checks}</div><button onclick="savePerms(${uid})" class="mt-4 w-full bg-indigo-600 text-white rounded-xl py-2 text-sm">Save</button></div></div>`;
  document.body.insertAdjacentHTML('beforeend', html);
}
async function savePerms(uid){
  const fd=new FormData(); fd.append('action','save_permissions'); fd.append('user_id',uid);
  ['can_upload','can_download','can_share','can_delete','can_create_folder'].forEach(k=>{ if(document.getElementById('perm_'+k)?.checked) fd.append(k,'1'); });
  await fetch('api/users.php',{method:'POST', body:fd}); document.querySelector('.fixed.inset-0.bg-black\/40')?.remove(); toast('Permissions saved');
}

// Patch context menu to add Share

// init
refreshDashboard(); loadFiles(); setTimeout(drawCharts,300);
</script>
</body>
</html>
