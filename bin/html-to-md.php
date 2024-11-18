<?php
/**
 * Used to rename HTML files to MD files.
 * Adds a JSON file for Docusaurus index.
 * Organizes files into subdirectories based on type.
 */

function getDirContents(string $dir, array $results = []): array
{
    $files = scandir($dir);

    foreach ($files as $file) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
        if (!is_dir($path)) {
            if (!preg_match('/\.html$/', $path)) {
                continue;
            }
            $results[] = $path;
        } elseif ($file !== '.' && $file !== '..') {
            $results += getDirContents($path, $results);
        }
    }

    return $results;
}

function get_target_dir(): string
{
    $target = getcwd();
    $options = getopt('d::', ['dir::']);
    $option = $options['d'] ?? $options['dir'] ?? null;

    if (empty($option)) {
        return $target;
    }

    if ($option[0] !== '/') {
        // relative path
        $target .= '/' . $option;
    }

    if (!is_readable($target) || !is_dir($target)) {
        echo sprintf('The directory provided (%s) is not a valid target directory.', $target) . PHP_EOL;
        echo 'This script requires a target working directory that can be provided using --dir="/path/to/docs"' . PHP_EOL;
        exit(1);
    }

    return $target;
}

function organizeFile(string $filePath, string $baseDir): string
{
    $fileName = basename($filePath);
    if (preg_match('/App-(Controller|Entity|Form|Repository|Command)-(.*)\.html$/', $fileName, $matches)) {
        $type = strtolower($matches[1]);
        $targetDir = $baseDir . '/' . $type;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $newPath = $targetDir . '/' . $fileName;
        rename($filePath, $newPath);
        return $newPath;
    }

    return $filePath;
}

function generateJsonFile(string $dir, string $label, int $position, string $description): void
{
    $jsonContent = [
        "label" => $label,
        "position" => $position,
        "link" => [
            "type" => "generated-index",
            "description" => $description,
        ],
    ];

    $jsonPath = $dir . '/_category_.json';

    // Ensure the directory exists
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($jsonPath, json_encode($jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "JSON file generated at: $jsonPath" . PHP_EOL;
}

$target = get_target_dir();
$files = getDirContents($target);

$position = 2; // Initial sidebar position
foreach ($files as $file) {
    echo sprintf('Processing %s...', $file);
    $content = file_get_contents($file);

    // Skip empty files
    if (empty(trim($content))) {
        echo 'Empty file. Deleting...';
        unlink($file);
        echo 'DONE' . PHP_EOL;
        continue;
    }

    // Organize file into type-based directories
    $file = organizeFile($file, $target);

    // Replace .html links with .md
    $content = str_replace('.html)', '.md)', $content);
    $content = preg_replace('/\.html(\#[\w\_]+)\)/', '.md$1)', $content);

    // Rename file to .md
    $mdFilePath = preg_replace('/\.html$/', '.md', $file);

    file_put_contents($mdFilePath, $content);
    rename($file, $mdFilePath);
    echo 'DONE' . PHP_EOL;
}

// Generate the JSON file for each organized folder
$organizedDirs = ['controller', 'entity', 'form', 'repository', 'command'];
foreach ($organizedDirs as $dir) {
    $path = $target . '/' . $dir;
    if (is_dir($path)) {
        generateJsonFile($path, ucfirst($dir) . " Documentation", $position++, "Browse all available " . $dir . "s in this project.");
    }
}
