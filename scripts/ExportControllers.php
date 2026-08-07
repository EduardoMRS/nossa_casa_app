<?php
$modelList =  glob(__DIR__ . '/../app/Http/Controllers/*.php');
$content = '';

foreach ($modelList as $model) {
    $content .= "\n" . str_replace('<?php', '<?php // ' . basename($model), file_get_contents($model));
}
file_put_contents(__DIR__ . '/../Controllers.php', $content);