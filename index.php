<?php

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="website.zip"');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["error" => "Only GET method allowed"]);
    exit;
}

if (!isset($_GET['url']) || empty($_GET['url'])) {
    echo json_encode(["error" => "No URL provided"]);
    exit;
}

$url = $_GET['url'];

if (!preg_match('/^https?:\/\//', $url)) {
    $url = "http://" . $url;
}

$tempDir = sys_get_temp_dir() . '/' . uniqid('web_');
mkdir($tempDir);

$html = file_get_contents($url);
file_put_contents("$tempDir/index.html", $html);

// শুধু main html নিলে হবে না, css, js পেতে হলে এগুলো parse করতে হবে। নিচে example:
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($html);
$xpath = new DOMXPath($doc);

$assets = [
    '//link[@rel="stylesheet"]/@href',
    '//script/@src',
    '//img/@src'
];

foreach ($assets as $query) {
    $nodes = $xpath->query($query);
    foreach ($nodes as $node) {
        $src = $node->nodeValue;

        $assetUrl = parse_url($src, PHP_URL_SCHEME) ? $src : rtrim($url, '/') . '/' . ltrim($src, '/');
        $pathInfo = pathinfo(parse_url($assetUrl, PHP_URL_PATH));
        $filename = $pathInfo['basename'] ?? uniqid();

        $content = @file_get_contents($assetUrl);
        if ($content) {
            file_put_contents("$tempDir/$filename", $content);
        }
    }
}

$zip = new ZipArchive();
$zipPath = "$tempDir/website.zip";
$zip->open($zipPath, ZipArchive::CREATE);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir));
foreach ($files as $name => $file) {
    if (!$file->isDir() && basename($file) != 'website.zip') {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($tempDir) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();

readfile($zipPath);

// Cleanup
array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);
    $links = [];
    foreach ($matches[1] as $link) {
        if (strpos($link, 'http') !== 0) {
            $link = rtrim($base, '/') . '/' . ltrim($link, '/');
        }
        $links[] = $link;
    }
    return $links;
}

function extract_parts($html) {
    preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $cssMatches);
    preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $jsMatches);

    $css = implode("\n", $cssMatches[1]);
    $js = implode("\n", $jsMatches[1]);

    $cleaned_html = preg_replace('/<style[^>]*>.*?<\/style>/is', '<!-- CSS removed -->', $html);
    $cleaned_html = preg_replace('/<script[^>]*>.*?<\/script>/is', '<!-- JS removed -->', $cleaned_html);

    return [$cleaned_html, $css, $js];
}

while (!empty($toVisit) && count($visited) < $maxPages) {
    $current = array_shift($toVisit);
    if (in_array($current, $visited)) continue;
    if (parse_url($current, PHP_URL_HOST) !== $domain) continue;

    $content = fetch_content($current);
    if (!$content) continue;

    [$html, $css, $js] = extract_parts($content);

    $path = trim(parse_url($current, PHP_URL_PATH), "/");
    $path = $path ? str_replace("/", "_", $path) : "index";

    $files["$path.html"] = $html;
    if ($css) $files["{$path}_style.css"] = $css;
    if ($js) $files["{$path}_script.js"] = $js;

    $links = extract_links($content, $current);
    foreach ($links as $link) {
        if (!in_array($link, $visited) && !in_array($link, $toVisit)) {
            $toVisit[] = $link;
        }
    }

    $visited[] = $current;
}

// Create ZIP
$tmpFile = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE);
foreach ($files as $filename => $content) {
    $zip->addFromString($filename, $content);
}
$zip->close();

// Return file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $domain . '.zip"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
exit;
?>
