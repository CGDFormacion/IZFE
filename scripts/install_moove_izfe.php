<?php
// Installs the IZFE Moodle theme folder into a Moodle site.

define('CLI_SCRIPT', true);

$repoRoot = realpath(__DIR__ . '/..');
if ($repoRoot === false) {
    fwrite(STDERR, "Cannot resolve repository root.\n");
    exit(1);
}

$moodleRoot = $argv[1] ?? getcwd();
$moodleRoot = realpath($moodleRoot);
if ($moodleRoot === false || !is_file($moodleRoot . '/config.php')) {
    fwrite(STDERR, "Usage: php scripts/install_moove_izfe.php /path/to/moodle\n");
    fwrite(STDERR, "The Moodle path must contain config.php.\n");
    exit(1);
}

require_once($moodleRoot . '/config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/datalib.php');

$sourceTheme = $repoRoot . '/theme';
$targetTheme = $moodleRoot . '/theme';
$configFile = $repoRoot . '/config/theme_moove_export.json';
$categoryStructureFile = $repoRoot . '/config/category_language_subcategories.json';
$assetRoot = $repoRoot . '/assets/theme_moove_files';
$langOverrideRoot = $repoRoot . '/assets/lang_overrides';

if (!is_dir($sourceTheme)) {
    fwrite(STDERR, "Missing source theme folder: {$sourceTheme}\n");
    exit(1);
}
foreach (['boost', 'classic', 'moove'] as $requiredTheme) {
    if (!is_dir($sourceTheme . '/' . $requiredTheme)) {
        fwrite(STDERR, "Missing required theme: {$sourceTheme}/{$requiredTheme}\n");
        exit(1);
    }
}
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing exported config: {$configFile}\n");
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid JSON in {$configFile}\n");
    exit(1);
}

$categoryStructure = [];
if (is_file($categoryStructureFile)) {
    $categoryStructure = json_decode(file_get_contents($categoryStructureFile), true);
    if (!is_array($categoryStructure)) {
        fwrite(STDERR, "Invalid JSON in {$categoryStructureFile}\n");
        exit(1);
    }
}

function izfe_copy_dir(string $source, string $target): void {
    if (!is_dir($target) && !mkdir($target, 0775, true)) {
        throw new RuntimeException("Cannot create {$target}");
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;

        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true)) {
                throw new RuntimeException("Cannot create {$destination}");
            }
            continue;
        }

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        if (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException("Cannot copy {$item->getPathname()} to {$destination}");
        }
        chmod($destination, 0664);
    }
}

function izfe_remove_dir(string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

$backup = null;
if (is_dir($targetTheme)) {
    $backup = $targetTheme . '.backup-' . date('Ymd-His');
    if (!rename($targetTheme, $backup)) {
        fwrite(STDERR, "Cannot back up existing theme to {$backup}\n");
        exit(1);
    }
}

try {
    izfe_copy_dir($sourceTheme, $targetTheme);
} catch (Throwable $exception) {
    if ($backup !== null && !is_dir($targetTheme)) {
        rename($backup, $targetTheme);
    }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

foreach (($config['moodle_config'] ?? []) as $name => $value) {
    set_config($name, $value);
}
set_config('theme', 'moove');

if (!empty($config['site_course']) && is_array($config['site_course'])) {
    global $DB;

    $site = get_site();
    $updates = [];
    foreach (['fullname', 'shortname', 'summary', 'format'] as $field) {
        if (array_key_exists($field, $config['site_course'])) {
            $updates[$field] = $config['site_course'][$field];
        }
    }

    if ($updates) {
        $updates['id'] = $site->id;
        $DB->update_record('course', (object) $updates);
    }
}

if (!empty($config['theme_plugins']) && is_array($config['theme_plugins'])) {
    foreach ($config['theme_plugins'] as $plugin => $settings) {
        foreach ($settings as $name => $value) {
            set_config($name, $value, $plugin);
        }
    }
} else {
    foreach (($config['theme_moove'] ?? []) as $name => $value) {
        set_config($name, $value, 'theme_moove');
    }
}

$fs = get_file_storage();
$context = context_system::instance();
$restoredAreas = [];

foreach (($config['files'] ?? []) as $file) {
    $filearea = $file['filearea'] ?? null;
    $filename = $file['filename'] ?? null;
    if (!$filearea || !$filename || $filename === '.') {
        continue;
    }

    if (empty($restoredAreas[$filearea])) {
        $fs->delete_area_files($context->id, 'theme_moove', $filearea, 0);
        $restoredAreas[$filearea] = true;
    }

    $sourceFile = $assetRoot . '/' . $filearea . '/' . $filename;
    if (!is_file($sourceFile)) {
        fwrite(STDERR, "Missing asset: {$sourceFile}\n");
        exit(1);
    }

    $record = [
        'contextid' => $context->id,
        'component' => 'theme_moove',
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => $file['filepath'] ?? '/',
        'filename' => $filename,
    ];
    $fs->create_file_from_pathname($record, $sourceFile);
}

if (is_dir($langOverrideRoot)) {
    $languages = new DirectoryIterator($langOverrideRoot);
    foreach ($languages as $language) {
        if ($language->isDot() || !$language->isDir()) {
            continue;
        }

        $sourceFile = $language->getPathname() . '/theme_moove.php';
        if (!is_file($sourceFile)) {
            continue;
        }

        $targetDir = $CFG->dataroot . '/lang/' . $language->getFilename();
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
            fwrite(STDERR, "Cannot create {$targetDir}\n");
            exit(1);
        }
        if (!copy($sourceFile, $targetDir . '/theme_moove.php')) {
            fwrite(STDERR, "Cannot copy language override {$sourceFile}\n");
            exit(1);
        }
    }
}

if (function_exists('purge_all_caches')) {
    purge_all_caches();
}

if (!empty($categoryStructure['parents']) && is_array($categoryStructure['parents'])) {
    global $DB;

    foreach ($categoryStructure['parents'] as $parentConfig) {
        if (empty($parentConfig['name']) || empty($parentConfig['children']) || !is_array($parentConfig['children'])) {
            continue;
        }

        $parents = $DB->get_records('course_categories', ['name' => $parentConfig['name']]);
        foreach ($parents as $parentCategory) {
            foreach ($parentConfig['children'] as $childConfig) {
                if (empty($childConfig['name']) || empty($childConfig['idnumber'])) {
                    continue;
                }

                $existing = $DB->get_record('course_categories', [
                    'parent' => $parentCategory->id,
                    'idnumber' => $childConfig['idnumber'],
                ]);
                if ($existing) {
                    continue;
                }

                \core_course_category::create([
                    'name' => $childConfig['name'],
                    'idnumber' => $childConfig['idnumber'],
                    'parent' => $parentCategory->id,
                    'visible' => 1,
                ]);
            }
        }
    }
}

echo "IZFE theme folder installed in {$targetTheme}\n";
if ($backup !== null) {
    echo "Previous theme folder backup: {$backup}\n";
}
echo "Theme set to moove and theme plugin settings restored.\n";
