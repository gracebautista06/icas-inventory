<?php
/**
 * ╔══════════════════════════════════════════════════════╗
 *  ICAS PROPERTY INVENTORY SYSTEM — submit_report.php
 *  Instructor submits → Admin views & manages instantly
 * ╚══════════════════════════════════════════════════════╝
 */
session_start();

/* ─── DB CONNECTION ─────────────────────────────────── */
$DB_HOST = 'localhost';
$DB_NAME = 'icas_inventory';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    die('<p style="color:red;font-family:monospace;padding:2rem">DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

/* ─── AUTO-MIGRATE ──────────────────────────────────── */
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(80)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(120) NOT NULL,
    role       ENUM('admin','instructor') NOT NULL DEFAULT 'instructor',
    department VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS property_reports (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id   INT NOT NULL,
    property_name VARCHAR(150) NOT NULL,
    property_type ENUM('chair','table','computer','projector','whiteboard','cabinet','other') NOT NULL DEFAULT 'chair',
    location      VARCHAR(200) NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    incident_type ENUM('missing','damaged','stolen','defective') NOT NULL DEFAULT 'missing',
    description   TEXT,
    priority      ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status        ENUM('pending','under_review','resolved','closed') NOT NULL DEFAULT 'pending',
    admin_notes   TEXT,
    reviewed_by   INT DEFAULT NULL,
    reviewed_at   TIMESTAMP NULL DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);
");

/* Seed demo accounts once */
if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
    $ins = $pdo->prepare("INSERT INTO users (username,password,full_name,role,department) VALUES(?,?,?,?,?)");
    $ins->execute(['admin',       password_hash('admin123',      PASSWORD_DEFAULT), 'System Administrator', 'admin',      'ICT Office']);
    $ins->execute(['instructor1', password_hash('instructor123', PASSWORD_DEFAULT), 'Juan dela Cruz',       'instructor', 'College of Engineering']);
    $ins->execute(['instructor2', password_hash('instructor123', PASSWORD_DEFAULT), 'Maria Santos',         'instructor', 'College of Education']);
}

/* ─── HELPERS ───────────────────────────────────────── */
function loggedIn():bool  { return isset($_SESSION['uid']); }
function isAdmin():bool   { return loggedIn() && $_SESSION['role']==='admin'; }
function e(string $s):string { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
function go(string $u):void  { header("Location:$u"); exit; }
function flash(string $m, string $t='ok'):void { $_SESSION['flash']=['m'=>$m,'t'=>$t]; }
function popFlash():array { $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }

function statusBadge(string $s):string {
    $map=['pending'=>['#F59E0B','⏳'],'under_review'=>['#38BDF8','🔍'],'resolved'=>['#10B981','✅'],'closed'=>['#6B7280','🔒']];
    [$c,$i]=$map[$s]??['#94a3b8','•'];
    $label=ucfirst(str_replace('_',' ',$s));
    return "<span class='badge' style='--bc:$c'>$i $label</span>";
}
function priorityBadge(string $p):string {
    $map=['low'=>'#6B7280','medium'=>'#F59E0B','high'=>'#EF4444','critical'=>'#9333EA'];
    $c=$map[$p]??'#94a3b8';
    return "<span class='badge' style='--bc:$c'>".ucfirst($p)."</span>";
}
function incidentIcon(string $t):string {
    return ['missing'=>'🔍','damaged'=>'⚠️','stolen'=>'🚨','defective'=>'🔧'][$t]??'📋';
}

/* ─── ACTIONS ───────────────────────────────────────── */
$act = $_POST['act'] ?? $_GET['act'] ?? '';

/* LOGIN */
if ($act==='login' && $_SERVER['REQUEST_METHOD']==='POST') {
    $u=trim($_POST['username']??''); $pw=$_POST['password']??'';
    $row=$pdo->prepare("SELECT * FROM users WHERE username=?");
    $row->execute([$u]); $row=$row->fetch();
    if ($row && password_verify($pw,$row['password'])) {
        $_SESSION['uid']=$row['id'];
        $_SESSION['uname']=$row['username'];
        $_SESSION['name']=$row['full_name'];
        $_SESSION['role']=$row['role'];
        flash('Welcome back, '.$row['full_name'].'!');
        go($_SERVER['PHP_SELF']);
    }
    flash('Incorrect username or password.','err');
    go($_SERVER['PHP_SELF']);
}

/* LOGOUT */
if ($act==='logout') { session_destroy(); go($_SERVER['PHP_SELF']); }

/* SUBMIT REPORT (instructor) */
if ($act==='submit' && $_SERVER['REQUEST_METHOD']==='POST' && loggedIn()) {
    $pn=trim($_POST['property_name']??'');
    $pt=$_POST['property_type']??'chair';
    $loc=trim($_POST['location']??'');
    $qty=(int)($_POST['quantity']??1);
    $it=$_POST['incident_type']??'missing';
    $desc=trim($_POST['description']??'');
    $pri=$_POST['priority']??'medium';

    if (!$pn||!$loc||$qty<1) {
        flash('Please fill in all required fields.','err');
        go($_SERVER['PHP_SELF'].'?act=new');
    }

    $ins=$pdo->prepare("INSERT INTO property_reports
        (reporter_id,property_name,property_type,location,quantity,incident_type,description,priority)
        VALUES(?,?,?,?,?,?,?,?)");
    $ins->execute([$_SESSION['uid'],$pn,$pt,$loc,$qty,$it,$desc,$pri]);
    $rid=$pdo->lastInsertId();
    flash("Report #$rid submitted! The admin has been notified.");
    go($_SERVER['PHP_SELF']);
}

/* UPDATE STATUS (admin) */
if ($act==='update' && $_SERVER['REQUEST_METHOD']==='POST' && isAdmin()) {
    $rid=(int)($_POST['rid']??0);
    $st=$_POST['status']??'pending';
    $notes=trim($_POST['admin_notes']??'');
    $pdo->prepare("UPDATE property_reports SET status=?,admin_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")
        ->execute([$st,$notes,$_SESSION['uid'],$rid]);
    flash("Report #$rid updated to: ".ucfirst(str_replace('_',' ',$st)));
    go($_SERVER['PHP_SELF'].'?act=admin');
}

/* ─── FETCH DATA ────────────────────────────────────── */
$flash   = popFlash();
$page    = $_GET['act'] ?? (isAdmin()?'admin':'mine');
$reports = [];
$stats   = [];
$detail  = null;

if (loggedIn()) {
    if (isAdmin()) {
        $stats = $pdo->query("SELECT
            COUNT(*) total,
            SUM(status='pending') pending,
            SUM(status='under_review') reviewing,
            SUM(status='resolved') resolved,
            SUM(status='closed') closed,
            SUM(incident_type='missing') missing,
            SUM(incident_type='stolen') stolen,
            SUM(priority='critical') critical
        FROM property_reports")->fetch();

        $where=['1=1']; $params=[];
        if (!empty($_GET['st']))  { $where[]='pr.status=?';        $params[]=$_GET['st']; }
        if (!empty($_GET['it']))  { $where[]='pr.incident_type=?'; $params[]=$_GET['it']; }
        if (!empty($_GET['q']))   {
            $where[]='(pr.property_name LIKE ? OR pr.location LIKE ? OR u.full_name LIKE ?)';
            $params[]="%{$_GET['q']}%"; $params[]="%{$_GET['q']}%"; $params[]="%{$_GET['q']}%";
        }

        $sql="SELECT pr.*,u.full_name reporter_name,u.department,a.full_name reviewer_name
              FROM property_reports pr
              JOIN users u ON u.id=pr.reporter_id
              LEFT JOIN users a ON a.id=pr.reviewed_by
              WHERE ".implode(' AND ',$where)."
              ORDER BY FIELD(pr.status,'pending','under_review','resolved','closed'),
                       FIELD(pr.priority,'critical','high','medium','low'),
                       pr.created_at DESC";
        $s=$pdo->prepare($sql); $s->execute($params);
        $reports=$s->fetchAll();

        /* Single report detail for slide-in panel */
        if (!empty($_GET['id'])) {
            $s=$pdo->prepare("SELECT pr.*,u.full_name reporter_name,u.department,u.username reporter_username,
                a.full_name reviewer_name FROM property_reports pr
                JOIN users u ON u.id=pr.reporter_id
                LEFT JOIN users a ON a.id=pr.reviewed_by WHERE pr.id=?");
            $s->execute([(int)$_GET['id']]); $detail=$s->fetch();
        }
        $page='admin';
    } else {
        $s=$pdo->prepare("SELECT pr.*,a.full_name reviewer_name FROM property_reports pr
            LEFT JOIN users a ON a.id=pr.reviewed_by
            WHERE pr.reporter_id=? ORDER BY pr.created_at DESC");
        $s->execute([$_SESSION['uid']]); $reports=$s->fetchAll();
        if ($page!=='new') $page='mine';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ICAS – Property Inventory System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060b14;--bg2:#0d1523;--bg3:#162032;
  --line:rgba(255,255,255,.07);--text:#e2e8f0;--muted:#64748b;
  --blue:#38bdf8;--blue2:#0ea5e9;--gold:#f59e0b;
  --red:#ef4444;--green:#10b981;--purp:#9333ea;
  --r:10px;--sh:0 8px 32px rgba(0,0,0,.5);
  --font:'Plus Jakarta Sans',sans-serif;--mono:'JetBrains Mono',monospace;
  --sw:228px
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;
  background-image:
    radial-gradient(ellipse 70% 40% at 15% 0%,rgba(56,189,248,.08) 0%,transparent 60%),
    radial-gradient(ellipse 50% 35% at 85% 100%,rgba(147,51,234,.07) 0%,transparent 55%)
}
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--bg2)}
::-webkit-scrollbar-thumb{background:var(--bg3);border-radius:3px}
a{color:inherit;text-decoration:none}
input,select,textarea,button{font-family:var(--font)}

/* BADGE */
.badge{
  display:inline-flex;align-items:center;gap:.25rem;
  padding:.18rem .6rem;border-radius:20px;font-size:.68rem;font-weight:600;
  background:color-mix(in srgb,var(--bc) 15%,transparent);
  color:var(--bc);border:1px solid color-mix(in srgb,var(--bc) 30%,transparent);
  white-space:nowrap
}

/* TOPBAR */
.topbar{
  position:sticky;top:0;z-index:200;height:60px;
  background:rgba(6,11,20,.9);backdrop-filter:blur(18px);
  border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;gap:1rem
}
.brand{display:flex;align-items:center;gap:.7rem}
.brand-icon{
  width:34px;height:34px;border-radius:8px;
  background:linear-gradient(135deg,var(--blue),var(--purp));
  display:grid;place-items:center;font-size:.85rem;font-weight:800;color:#fff
}
.brand-name{font-size:.9rem;font-weight:700}
.brand-sub{font-size:.62rem;color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:.65rem}
.user-chip{
  display:flex;align-items:center;gap:.5rem;
  background:var(--bg3);border:1px solid var(--line);border-radius:20px;padding:.28rem .75rem .28rem .35rem
}
.user-av{
  width:26px;height:26px;border-radius:50%;
  background:linear-gradient(135deg,var(--blue2),var(--purp));
  display:grid;place-items:center;font-size:.65rem;font-weight:700;color:#fff;flex-shrink:0
}
.user-name{font-size:.78rem;font-weight:600}
.role-chip{
  font-size:.6rem;font-family:var(--mono);text-transform:uppercase;letter-spacing:.07em;
  padding:.14rem .45rem;border-radius:20px
}
.role-chip.admin{background:rgba(147,51,234,.15);color:#c084fc;border:1px solid rgba(147,51,234,.3)}
.role-chip.instructor{background:rgba(56,189,248,.12);color:var(--blue);border:1px solid rgba(56,189,248,.25)}

/* BUTTONS */
.btn{
  display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .95rem;
  border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;
  transition:all .15s;white-space:nowrap
}
.btn-sm{padding:.28rem .65rem;font-size:.72rem}
.btn-primary{background:var(--blue2);color:#fff}
.btn-primary:hover{background:var(--blue);transform:translateY(-1px)}
.btn-ghost{background:var(--bg3);color:var(--text);border:1px solid var(--line)}
.btn-ghost:hover{background:var(--bg2);border-color:rgba(255,255,255,.14)}
.btn-danger{background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.25)}
.btn-danger:hover{background:rgba(239,68,68,.2)}
.btn-success{background:rgba(16,185,129,.12);color:var(--green);border:1px solid rgba(16,185,129,.25)}
.btn-success:hover{background:rgba(16,185,129,.2)}

/* LAYOUT */
.layout{display:flex;min-height:calc(100vh - 60px)}
.sidebar{
  width:var(--sw);flex-shrink:0;
  background:rgba(13,21,35,.7);border-right:1px solid var(--line);
  padding:1.1rem .7rem;display:flex;flex-direction:column;gap:.15rem
}
.nav-sec{
  font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--muted);padding:.7rem .75rem .2rem
}
.nav-item{
  display:flex;align-items:center;gap:.6rem;
  padding:.52rem .75rem;border-radius:8px;
  font-size:.8rem;font-weight:500;color:var(--muted);transition:all .14s
}
.nav-item:hover{background:rgba(255,255,255,.04);color:var(--text)}
.nav-item.active{background:rgba(56,189,248,.1);color:var(--blue)}
.nav-item .ni{font-size:.92rem;width:18px;text-align:center;flex-shrink:0}
.nav-badge{
  margin-left:auto;background:var(--red);color:#fff;
  font-size:.58rem;font-weight:700;border-radius:10px;padding:.08rem .38rem;min-width:17px;text-align:center
}
.main{flex:1;padding:1.75rem;overflow-x:hidden;min-width:0}

/* FLASH */
.flash{
  display:flex;align-items:center;gap:.65rem;padding:.8rem 1.1rem;border-radius:var(--r);
  margin-bottom:1.25rem;font-size:.82rem;font-weight:500;animation:fslide .3s ease
}
.flash.ok {background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:var(--green)}
.flash.err{background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.25); color:var(--red)}
@keyframes fslide{from{transform:translateY(-6px);opacity:0}to{transform:none;opacity:1}}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.8rem;margin-bottom:1.5rem}
.stat-card{
  background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);
  padding:1rem 1.15rem;position:relative;overflow:hidden;transition:transform .18s
}
.stat-card:hover{transform:translateY(-2px)}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--sc,var(--blue)),transparent)}
.stat-n{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.3rem}
.stat-v{font-size:1.75rem;font-weight:800;line-height:1;color:var(--sc,var(--blue))}
.stat-s{font-size:.62rem;color:var(--muted);margin-top:.2rem}

/* FILTER BAR */
.filter-bar{
  display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;
  background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);
  padding:.8rem 1rem;margin-bottom:1rem
}
.filter-bar input,.filter-bar select{
  background:var(--bg3);border:1px solid var(--line);color:var(--text);
  border-radius:7px;padding:.38rem .75rem;font-size:.78rem;outline:none;transition:border-color .2s
}
.filter-bar input{flex:1;min-width:160px}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--blue)}

/* TABLE */
.tbl-wrap{background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
table{width:100%;border-collapse:collapse}
thead{background:rgba(13,21,35,.6)}
thead th{
  padding:.7rem 1rem;text-align:left;
  font-size:.63rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted)
}
tbody tr{border-top:1px solid var(--line);transition:background .12s;cursor:pointer}
tbody tr:hover{background:rgba(56,189,248,.05)}
tbody td{padding:.78rem 1rem;font-size:.8rem;vertical-align:middle}
tbody tr.rp td:first-child{border-left:3px solid var(--gold)}
tbody tr.rc td:first-child{border-left:3px solid var(--purp)}
tbody tr.rnew{animation:newrow 3s ease forwards}
@keyframes newrow{0%{background:rgba(56,189,248,.15)}100%{background:transparent}}

/* SH */
.sh{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.sh-title{font-size:1.05rem;font-weight:700}
.sh-sub{font-size:.73rem;color:var(--muted);margin-top:.15rem}

/* FORM */
.card{background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);padding:1.65rem}
.fg{margin-bottom:1.05rem}
.fl{display:block;font-size:.7rem;font-weight:700;color:var(--muted);margin-bottom:.38rem;letter-spacing:.04em;text-transform:uppercase}
.fc{
  width:100%;background:var(--bg3);border:1px solid var(--line);color:var(--text);
  border-radius:7px;padding:.52rem .85rem;font-size:.82rem;outline:none;transition:border-color .2s
}
.fc:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(56,189,248,.08)}
textarea.fc{resize:vertical;min-height:88px}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.req{color:var(--red)}
.hint{font-size:.67rem;color:var(--muted);margin-top:.28rem}

/* MODAL */
.modal-ov{
  position:fixed;inset:0;z-index:500;
  background:rgba(0,0,0,.72);backdrop-filter:blur(6px);
  display:flex;align-items:flex-start;justify-content:flex-end;
  animation:mfade .2s ease
}
@keyframes mfade{from{opacity:0}to{opacity:1}}
.modal-panel{
  width:min(560px,100%);height:100vh;overflow-y:auto;
  background:var(--bg2);border-left:1px solid var(--line);
  box-shadow:-8px 0 40px rgba(0,0,0,.65);animation:mslide .25s ease
}
@keyframes mslide{from{transform:translateX(24px);opacity:0}to{transform:none;opacity:1}}
.modal-head{
  position:sticky;top:0;z-index:10;
  background:var(--bg2);border-bottom:1px solid var(--line);
  padding:1.1rem 1.4rem;display:flex;align-items:center;gap:.7rem
}
.modal-head-title{font-size:.92rem;font-weight:700;flex:1}
.modal-body{padding:1.4rem}

.info-block{background:var(--bg3);border:1px solid var(--line);border-radius:8px;padding:1rem 1.15rem;margin-bottom:.9rem}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:.8rem}
.ir-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:.18rem}
.ir-val{font-size:.84rem;font-weight:500}
.desc-box{
  background:var(--bg3);border:1px solid var(--line);border-radius:8px;
  padding:.8rem .95rem;font-size:.81rem;line-height:1.65;white-space:pre-wrap;word-break:break-word
}
.aab{background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.2);border-radius:9px;padding:1.15rem}
.aab-title{font-size:.78rem;font-weight:700;color:var(--blue);margin-bottom:.9rem;display:flex;align-items:center;gap:.4rem}

/* INSTRUCTOR CARDS */
.rcards{display:flex;flex-direction:column;gap:.7rem}
.rcard{
  background:var(--bg2);border:1px solid var(--line);border-radius:var(--r);
  padding:1rem 1.15rem;display:flex;align-items:center;gap:.9rem;transition:border-color .14s
}
.rcard:hover{border-color:rgba(56,189,248,.3)}
.rcard-icon{
  width:38px;height:38px;border-radius:9px;flex-shrink:0;
  display:grid;place-items:center;font-size:1.15rem;
  background:var(--bg3);border:1px solid var(--line)
}
.rcard-body{flex:1;min-width:0}
.rcard-title{font-size:.87rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rcard-sub{font-size:.7rem;color:var(--muted);margin-top:.12rem}
.rcard-meta{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin-top:.38rem}
.rcard-right{text-align:right;flex-shrink:0}
.rcard-date{font-size:.66rem;color:var(--muted);margin-bottom:.35rem}

/* EMPTY */
.empty{text-align:center;padding:2.75rem 1rem}
.empty-icon{font-size:2.4rem;opacity:.3;margin-bottom:.65rem}
.empty-text{font-size:.83rem;color:var(--muted)}

/* LOGIN */
.login-wrap{min-height:100vh;display:grid;place-items:center;padding:1.5rem}
.login-box{
  width:100%;max-width:400px;background:var(--bg2);border:1px solid var(--line);
  border-radius:14px;padding:2.2rem 1.9rem;box-shadow:var(--sh)
}
.login-icon{
  width:50px;height:50px;border-radius:12px;
  background:linear-gradient(135deg,var(--blue),var(--purp));
  display:grid;place-items:center;margin:0 auto .9rem;
  font-size:1.2rem;font-weight:800;color:#fff
}
.login-title{text-align:center;font-size:1.1rem;font-weight:800;margin-bottom:.18rem}
.login-sub{text-align:center;font-size:.73rem;color:var(--muted);margin-bottom:1.65rem}
.demo-box{
  background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.18);
  border-radius:8px;padding:.75rem 1rem;margin-top:1.2rem;
  font-size:.71rem;color:var(--muted);line-height:1.75
}
.demo-box strong{color:var(--blue);font-weight:600}
code{font-family:var(--mono);font-size:.75em;background:rgba(255,255,255,.07);padding:.08rem .3rem;border-radius:4px}

@media(max-width:700px){
  .sidebar{display:none}.fr{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr 1fr}
  .info-grid{grid-template-columns:1fr}
  thead th:nth-child(n+5),tbody td:nth-child(n+5){display:none}
}
</style>
</head>
<body>

<?php if (!loggedIn()): ?>
<!-- ═══════════════ LOGIN ═══════════════ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-icon">IC</div>
    <div class="login-title">ICAS Property Inventory</div>
    <div class="login-sub">Property Incident Management System</div>
    <?php if ($flash): ?>
    <div class="flash <?=e($flash['t'])?>"><?=$flash['t']==='ok'?'✓':'✕'?> <?=e($flash['m'])?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="act" value="login">
      <div class="fg"><label class="fl">Username</label>
        <input name="username" class="fc" placeholder="Enter username" required autofocus></div>
      <div class="fg"><label class="fl">Password</label>
        <input name="password" type="password" class="fc" placeholder="Enter password" required></div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.58rem">Sign In →</button>
    </form>
    <div class="demo-box">
      <strong>Demo Credentials</strong><br>
      Admin &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;→ <code>admin</code> / <code>admin123</code><br>
      Instructor → <code>instructor1</code> / <code>instructor123</code>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════ AUTHENTICATED ═══════════════ -->

<div class="topbar">
  <div class="brand">
    <div class="brand-icon">IC</div>
    <div><div class="brand-name">ICAS Inventory System</div><div class="brand-sub">Property Incident Management</div></div>
  </div>
  <div class="topbar-right">
    <div class="user-chip">
      <div class="user-av"><?=strtoupper(substr($_SESSION['name'],0,1))?></div>
      <div class="user-name"><?=e($_SESSION['name'])?></div>
    </div>
    <span class="role-chip <?=e($_SESSION['role'])?>"><?=e($_SESSION['role'])?></span>
    <a href="?act=logout" class="btn btn-danger btn-sm">Sign Out</a>
  </div>
</div>

<div class="layout">
<nav class="sidebar">
  <?php if (isAdmin()): $pending=(int)($stats['pending']??0); ?>
  <div class="nav-sec">Admin Panel</div>
  <a href="?act=admin" class="nav-item <?=$page==='admin'&&empty($_GET['st'])&&empty($_GET['it'])?'active':''?>">
    <span class="ni">📊</span> All Reports
    <?php if($pending>0): ?><span class="nav-badge"><?=$pending?></span><?php endif; ?>
  </a>
  <a href="?act=admin&st=pending" class="nav-item <?=(($_GET['st']??'')==='pending'?'active':'')?>">
    <span class="ni">⏳</span> Pending
  </a>
  <a href="?act=admin&st=under_review" class="nav-item <?=(($_GET['st']??'')==='under_review'?'active':'')?>">
    <span class="ni">🔍</span> Under Review
  </a>
  <a href="?act=admin&st=resolved" class="nav-item <?=(($_GET['st']??'')==='resolved'?'active':'')?>">
    <span class="ni">✅</span> Resolved
  </a>
  <div class="nav-sec">By Incident</div>
  <a href="?act=admin&it=missing"  class="nav-item <?=(($_GET['it']??'')==='missing' ?'active':'')?>"><span class="ni">🔍</span> Missing</a>
  <a href="?act=admin&it=damaged"  class="nav-item <?=(($_GET['it']??'')==='damaged' ?'active':'')?>"><span class="ni">⚠️</span> Damaged</a>
  <a href="?act=admin&it=stolen"   class="nav-item <?=(($_GET['it']??'')==='stolen'  ?'active':'')?>"><span class="ni">🚨</span> Stolen</a>
  <a href="?act=admin&it=defective"class="nav-item <?=(($_GET['it']??'')==='defective'?'active':'')?>"><span class="ni">🔧</span> Defective</a>
  <?php else: ?>
  <div class="nav-sec">Instructor</div>
  <a href="<?=e($_SERVER['PHP_SELF'])?>" class="nav-item <?=$page==='mine'?'active':''?>"><span class="ni">📋</span> My Reports</a>
  <a href="?act=new" class="nav-item <?=$page==='new'?'active':''?>"><span class="ni">➕</span> Submit Report</a>
  <?php endif; ?>
</nav>

<main class="main">
<?php if ($flash): ?>
<div class="flash <?=e($flash['t'])?>"><?=$flash['t']==='ok'?'✅':'⛔'?> <?=e($flash['m'])?></div>
<?php endif; ?>

<?php
/* ═══════════════════════════════
   ADMIN — REPORTS DASHBOARD
═══════════════════════════════ */
if ($page==='admin' && isAdmin()):
?>

<div class="sh">
  <div>
    <div class="sh-title">📋 Property Reports — Admin View</div>
    <div class="sh-sub">All instructor submissions appear here. Click any row to review and act on it.</div>
  </div>
</div>

<div class="stats-row">
  <div class="stat-card" style="--sc:var(--blue)">
    <div class="stat-n">Total</div><div class="stat-v"><?=(int)($stats['total']??0)?></div><div class="stat-s">All reports</div>
  </div>
  <div class="stat-card" style="--sc:var(--gold)">
    <div class="stat-n">Pending</div><div class="stat-v"><?=(int)($stats['pending']??0)?></div><div class="stat-s">Needs action</div>
  </div>
  <div class="stat-card" style="--sc:#38bdf8">
    <div class="stat-n">Reviewing</div><div class="stat-v"><?=(int)($stats['reviewing']??0)?></div><div class="stat-s">In progress</div>
  </div>
  <div class="stat-card" style="--sc:var(--green)">
    <div class="stat-n">Resolved</div><div class="stat-v"><?=(int)($stats['resolved']??0)?></div><div class="stat-s">Completed</div>
  </div>
  <div class="stat-card" style="--sc:var(--red)">
    <div class="stat-n">Missing</div><div class="stat-v"><?=(int)($stats['missing']??0)?></div><div class="stat-s">Items missing</div>
  </div>
  <div class="stat-card" style="--sc:var(--purp)">
    <div class="stat-n">Critical</div><div class="stat-v"><?=(int)($stats['critical']??0)?></div><div class="stat-s">High priority</div>
  </div>
</div>

<form method="GET" class="filter-bar">
  <input type="hidden" name="act" value="admin">
  <input type="text" name="q" value="<?=e($_GET['q']??'')?>" placeholder="🔍  Search property, location or instructor…">
  <select name="st">
    <option value="">All Statuses</option>
    <?php foreach(['pending'=>'Pending','under_review'=>'Under Review','resolved'=>'Resolved','closed'=>'Closed'] as $k=>$v): ?>
    <option value="<?=$k?>" <?=($_GET['st']??'')===$k?'selected':''?>><?=$v?></option>
    <?php endforeach; ?>
  </select>
  <select name="it">
    <option value="">All Incidents</option>
    <?php foreach(['missing'=>'Missing','damaged'=>'Damaged','stolen'=>'Stolen','defective'=>'Defective'] as $k=>$v): ?>
    <option value="<?=$k?>" <?=($_GET['it']??'')===$k?'selected':''?>><?=$v?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Apply</button>
  <a href="?act=admin" class="btn btn-ghost btn-sm">Reset</a>
</form>

<?php if (empty($reports)): ?>
<div class="tbl-wrap"><div class="empty">
  <div class="empty-icon">📭</div>
  <div class="empty-text">No reports found. Adjust filters or wait for instructors to submit.</div>
</div></div>
<?php else: ?>
<div class="tbl-wrap">
  <table>
    <thead><tr>
      <th>Ref #</th><th>Property &amp; Details</th><th>Incident</th>
      <th>Location</th><th>Reported By</th><th>Priority</th>
      <th>Status</th><th>Date Submitted</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach($reports as $r):
      $cls='';
      if ($r['status']==='pending')    $cls='rp';
      if ($r['priority']==='critical') $cls.=' rc';
      $isNew = (!empty($_SESSION['last_new_id']) && (int)$_SESSION['last_new_id']===$r['id']);
    ?>
    <tr class="<?=trim($cls)?> <?=$isNew?'rnew':''?>" onclick="openReport(<?=$r['id']?>)">
      <td>
        <span style="font-family:var(--mono);font-size:.7rem;color:var(--muted)">#<?=$r['id']?></span>
        <?php if($isNew): ?><span class="badge" style="--bc:var(--green);display:block;margin-top:.2rem;width:fit-content">NEW</span><?php endif; ?>
      </td>
      <td>
        <div style="font-weight:600;font-size:.84rem"><?=e($r['property_name'])?></div>
        <div style="font-size:.67rem;color:var(--muted)"><?=ucfirst($r['property_type'])?> &nbsp;·&nbsp; Qty: <?=$r['quantity']?></div>
      </td>
      <td style="white-space:nowrap"><?=incidentIcon($r['incident_type'])?> <?=ucfirst($r['incident_type'])?></td>
      <td style="max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($r['location'])?></td>
      <td>
        <div style="font-size:.82rem"><?=e($r['reporter_name'])?></div>
        <div style="font-size:.67rem;color:var(--muted)"><?=e($r['department']??'')?></div>
      </td>
      <td><?=priorityBadge($r['priority'])?></td>
      <td><?=statusBadge($r['status'])?></td>
      <td style="font-size:.68rem;color:var(--muted);white-space:nowrap">
        <?=date('M d, Y',strtotime($r['created_at']))?><br>
        <?=date('h:i A',strtotime($r['created_at']))?>
      </td>
      <td><button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();openReport(<?=$r['id']?>)">View →</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p style="font-size:.7rem;color:var(--muted);margin-top:.65rem;text-align:center">
  🟡 Gold left border = Pending &nbsp;·&nbsp; 🟣 Purple left border = Critical &nbsp;·&nbsp; Click any row to open full report panel
</p>
<?php endif; ?>

<?php
/* ═══════════════════════════════
   INSTRUCTOR — MY REPORTS
═══════════════════════════════ */
elseif ($page==='mine'):
?>
<div class="sh">
  <div><div class="sh-title">📋 My Submitted Reports</div>
  <div class="sh-sub">Track your submissions and see admin feedback</div></div>
  <a href="?act=new" class="btn btn-primary">+ Submit New Report</a>
</div>

<?php if (empty($reports)): ?>
<div class="card"><div class="empty">
  <div class="empty-icon">📋</div>
  <div class="empty-text">No reports yet.<br>
  <a href="?act=new" style="color:var(--blue);font-weight:600;margin-top:.5rem;display:inline-block">Submit your first report →</a></div>
</div></div>
<?php else: ?>
<div class="rcards">
<?php foreach($reports as $r): ?>
<div class="rcard">
  <div class="rcard-icon"><?=incidentIcon($r['incident_type'])?></div>
  <div class="rcard-body">
    <div class="rcard-title"><?=e($r['property_name'])?></div>
    <div class="rcard-sub">📍 <?=e($r['location'])?> &nbsp;·&nbsp; Qty: <?=$r['quantity']?> &nbsp;·&nbsp; <?=ucfirst($r['property_type'])?></div>
    <div class="rcard-meta">
      <?=statusBadge($r['status'])?>
      <?=priorityBadge($r['priority'])?>
      <?php if ($r['reviewer_name']): ?>
      <span style="font-size:.67rem;color:var(--muted)">Reviewed by <?=e($r['reviewer_name'])?></span>
      <?php endif; ?>
    </div>
    <?php if ($r['admin_notes']): ?>
    <div style="margin-top:.45rem;font-size:.71rem;color:var(--blue);background:rgba(56,189,248,.07);border:1px solid rgba(56,189,248,.15);border-radius:6px;padding:.38rem .7rem">
      💬 Admin note: <?=e(mb_strimwidth($r['admin_notes'],0,120,'…'))?>
    </div>
    <?php endif; ?>
  </div>
  <div class="rcard-right">
    <div class="rcard-date"><?=date('M d',strtotime($r['created_at']))?><br><?=date('h:i A',strtotime($r['created_at']))?></div>
    <span style="font-family:var(--mono);font-size:.65rem;color:var(--muted)">#<?=$r['id']?></span>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
/* ═══════════════════════════════
   INSTRUCTOR — NEW REPORT
═══════════════════════════════ */
elseif ($page==='new'):
?>
<div class="sh">
  <div><div class="sh-title">➕ Submit Property Report</div>
  <div class="sh-sub">Fill in all fields below — the admin will see this immediately after submission</div></div>
  <a href="<?=e($_SERVER['PHP_SELF'])?>" class="btn btn-ghost btn-sm">← Back</a>
</div>

<form method="POST" style="max-width:640px">
  <input type="hidden" name="act" value="submit">
  <div class="card">

    <div class="fr">
      <div class="fg"><label class="fl">Property Name <span class="req">*</span></label>
        <input name="property_name" class="fc" placeholder="e.g., Monobloc Chair" required autofocus></div>
      <div class="fg"><label class="fl">Property Type <span class="req">*</span></label>
        <select name="property_type" class="fc">
          <option value="chair">🪑 Chair</option>
          <option value="table">🪽 Table</option>
          <option value="computer">💻 Computer</option>
          <option value="projector">📽️ Projector</option>
          <option value="whiteboard">📋 Whiteboard</option>
          <option value="cabinet">🗄️ Cabinet</option>
          <option value="other">📦 Other</option>
        </select></div>
    </div>

    <div class="fr">
      <div class="fg"><label class="fl">Classroom / Location <span class="req">*</span></label>
        <input name="location" class="fc" placeholder="e.g., Room 201 – Building A" required></div>
      <div class="fg"><label class="fl">Number of Items <span class="req">*</span></label>
        <input name="quantity" type="number" min="1" value="1" class="fc" required></div>
    </div>

    <div class="fr">
      <div class="fg"><label class="fl">Incident Type <span class="req">*</span></label>
        <select name="incident_type" class="fc">
          <option value="missing">🔍 Missing</option>
          <option value="damaged">⚠️ Damaged</option>
          <option value="stolen">🚨 Stolen</option>
          <option value="defective">🔧 Defective / Not Working</option>
        </select></div>
      <div class="fg"><label class="fl">Priority Level</label>
        <select name="priority" class="fc">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="critical">🚨 Critical</option>
        </select></div>
    </div>

    <div class="fg"><label class="fl">Description / Details</label>
      <textarea name="description" class="fc" placeholder="When was it noticed? Last known location? Any additional context that can help the admin?"></textarea>
      <div class="hint">The more detail you provide, the faster the admin can resolve it.</div>
    </div>

    <!-- PREVIEW NOTE -->
    <div style="background:rgba(56,189,248,.07);border:1px solid rgba(56,189,248,.2);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.75rem;color:var(--blue)">
      ℹ️ Once submitted, this report will instantly appear on the <strong>Admin Dashboard</strong> — highlighted as a new pending report.
    </div>

    <div style="display:flex;gap:.7rem;justify-content:flex-end;padding-top:.6rem;border-top:1px solid var(--line);margin-top:.2rem">
      <a href="<?=e($_SERVER['PHP_SELF'])?>" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">Submit Report →</button>
    </div>
  </div>
</form>

<?php endif; ?>
</main>
</div><!-- layout -->

<?php
/* ═══════════════════════════════
   ADMIN REPORT DETAIL MODAL
   Slides in from the right when
   admin clicks a row
═══════════════════════════════ */
if (isAdmin() && $detail):
  $r=$detail;
?>
<div class="modal-ov" id="mo" onclick="if(event.target===this)closeModal()">
  <div class="modal-panel">
    <div class="modal-head">
      <div>
        <div class="modal-head-title">
          <?=incidentIcon($r['incident_type'])?> <?=e($r['property_name'])?>
          &nbsp;<?=statusBadge($r['status'])?>
        </div>
        <div style="font-size:.68rem;color:var(--muted);margin-top:.12rem">
          Report #<?=$r['id']?> &nbsp;·&nbsp; Submitted <?=date('M d, Y \a\t h:i A',strtotime($r['created_at']))?>
        </div>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="closeModal()">✕ Close</button>
    </div>

    <div class="modal-body">

      <!-- WHO SUBMITTED -->
      <div style="background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.18);border-radius:9px;padding:.9rem 1.1rem;margin-bottom:.9rem;display:flex;align-items:center;gap:.8rem">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--blue2),var(--purp));display:grid;place-items:center;font-size:.82rem;font-weight:700;flex-shrink:0">
          <?=strtoupper(substr($r['reporter_name'],0,1))?>
        </div>
        <div>
          <div style="font-size:.85rem;font-weight:600"><?=e($r['reporter_name'])?></div>
          <div style="font-size:.68rem;color:var(--muted)"><?=e($r['department']??'')?> &nbsp;·&nbsp; @<?=e($r['reporter_username']??'')?></div>
        </div>
        <div style="margin-left:auto;text-align:right">
          <div style="font-size:.62rem;color:var(--muted)">Submitted</div>
          <div style="font-size:.74rem;font-weight:600"><?=date('M d, Y',strtotime($r['created_at']))?></div>
          <div style="font-size:.68rem;color:var(--muted)"><?=date('h:i A',strtotime($r['created_at']))?></div>
        </div>
      </div>

      <!-- REPORT DETAILS -->
      <div class="info-block">
        <div class="info-grid">
          <div><div class="ir-label">Property Type</div><div class="ir-val"><?=ucfirst($r['property_type'])?></div></div>
          <div><div class="ir-label">Incident</div><div class="ir-val"><?=incidentIcon($r['incident_type'])?> <?=ucfirst($r['incident_type'])?></div></div>
          <div><div class="ir-label">Location</div><div class="ir-val"><?=e($r['location'])?></div></div>
          <div><div class="ir-label">Quantity Affected</div><div class="ir-val"><?=(int)$r['quantity']?> unit(s)</div></div>
          <div><div class="ir-label">Priority</div><div class="ir-val"><?=priorityBadge($r['priority'])?></div></div>
          <div><div class="ir-label">Current Status</div><div class="ir-val"><?=statusBadge($r['status'])?></div></div>
        </div>
        <?php if ($r['description']): ?>
        <div style="border-top:1px solid var(--line);padding-top:.8rem">
          <div class="ir-label" style="margin-bottom:.4rem">Instructor's Description</div>
          <div class="desc-box"><?=nl2br(e($r['description']))?></div>
        </div>
        <?php else: ?>
        <div style="border-top:1px solid var(--line);padding-top:.7rem;font-size:.75rem;color:var(--muted);font-style:italic">No additional description provided.</div>
        <?php endif; ?>
      </div>

      <!-- PREVIOUS ADMIN NOTES -->
      <?php if ($r['admin_notes']): ?>
      <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:9px;padding:.9rem 1.1rem;margin-bottom:.9rem">
        <div style="font-size:.63rem;font-weight:700;color:var(--green);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.4rem">Previous Admin Notes</div>
        <div style="font-size:.81rem;line-height:1.65"><?=nl2br(e($r['admin_notes']))?></div>
        <?php if ($r['reviewer_name']): ?>
        <div style="font-size:.67rem;color:var(--muted);margin-top:.45rem">— <?=e($r['reviewer_name'])?> &nbsp;·&nbsp; <?=date('M d, Y',strtotime($r['reviewed_at']??'now'))?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ADMIN ACTION FORM -->
      <div class="aab">
        <div class="aab-title">⚙️ Admin Action — Update This Report</div>
        <form method="POST">
          <input type="hidden" name="act" value="update">
          <input type="hidden" name="rid" value="<?=$r['id']?>">
          <div class="fg">
            <label class="fl">Update Status</label>
            <select name="status" class="fc">
              <?php foreach(['pending'=>'⏳ Pending','under_review'=>'🔍 Under Review','resolved'=>'✅ Resolved','closed'=>'🔒 Closed'] as $k=>$v): ?>
              <option value="<?=$k?>" <?=$r['status']===$k?'selected':''?>><?=$v?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="fl">Admin Notes / Resolution</label>
            <textarea name="admin_notes" class="fc" placeholder="Describe action taken, findings, or resolution steps…"><?=e($r['admin_notes']??'')?></textarea>
          </div>
          <div style="display:flex;gap:.6rem;justify-content:flex-end">
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-success">✓ Save &amp; Update Report</button>
          </div>
        </form>
      </div>

    </div><!-- modal-body -->
  </div><!-- modal-panel -->
</div><!-- modal-ov -->
<?php endif; ?>

<script>
function openReport(id){
  const url=new URL(window.location.href);
  url.searchParams.set('act','admin');
  url.searchParams.set('id',id);
  // preserve existing filters
  window.location.href=url.toString();
}
function closeModal(){
  const url=new URL(window.location.href);
  url.searchParams.delete('id');
  window.location.href=url.toString();
}
/* ESC key closes modal */
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });
/* Auto-refresh admin table every 30s when no modal is open */
<?php if(isAdmin() && !$detail): ?>
let _rt=setTimeout(()=>location.reload(),30000);
document.addEventListener('visibilitychange',()=>{
  if(document.hidden){ clearTimeout(_rt); }
  else { _rt=setTimeout(()=>location.reload(),30000); }
});
<?php endif; ?>
</script>

<?php endif; /* end loggedIn */ ?>
</body>
</html>