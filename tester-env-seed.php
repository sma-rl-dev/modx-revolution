<?php
define('MODX_API_MODE', true);
require __DIR__ . '/index.php';

use MODX\Revolution\modDocument;
use MODX\Revolution\modResource;

$modx->initialize('mgr');
$template = (int) $modx->getOption('default_template');
$records = [
    ['alias' => 'operations-hub', 'pagetitle' => 'Operations Hub', 'parent' => 0, 'isfolder' => true, 'content' => '<h1>Operations Hub</h1><p>Team updates and schedules.</p>'],
    ['alias' => 'harbor-safety-briefing', 'pagetitle' => 'Harbor Safety Briefing', 'parent' => 'operations-hub', 'isfolder' => false, 'content' => '<h1>Harbor Safety Briefing</h1><p>Review the dockside safety checklist before each shift.</p>'],
    ['alias' => 'autumn-berth-schedule', 'pagetitle' => 'Autumn Berth Schedule', 'parent' => 'operations-hub', 'isfolder' => false, 'content' => '<h1>Autumn Berth Schedule</h1><p>Confirmed berth assignments for the autumn service window.</p>'],
];
$ids = [];
foreach ($records as $record) {
    $resource = $modx->getObject(modResource::class, ['alias' => $record['alias'], 'context_key' => 'web']) ?: $modx->newObject(modResource::class);
    $parent = is_string($record['parent']) ? $ids[$record['parent']] : $record['parent'];
    $resource->fromArray([
        'pagetitle' => $record['pagetitle'], 'alias' => $record['alias'], 'parent' => $parent,
        'isfolder' => $record['isfolder'], 'published' => true, 'content' => $record['content'],
        'template' => $template, 'context_key' => 'web', 'class_key' => modDocument::class,
        'createdby' => 1, 'hidemenu' => false, 'searchable' => true, 'cacheable' => true,
    ]);
    if (!$resource->save()) {
        throw new RuntimeException('Could not save ' . $record['alias']);
    }
    $ids[$record['alias']] = (int) $resource->get('id');
}
$modx->cacheManager->refresh();

// Deterministic media-browser sort fixtures: the default media source
// (empty basePath, basePathRelative) resolves to the web root, so these
// live under assets/sort-fixtures/. Fixed content plus fixed mtimes keep
// re-seeds identical; newest-first order (zulu, harbor, alpha) differs
// from alphabetical name order (alpha, harbor, zulu).
$fixtureDir = (defined('MODX_BASE_PATH') ? MODX_BASE_PATH : __DIR__ . '/') . 'assets/sort-fixtures/';
$fixtures = [
    ['name' => 'alpha-dock-manifest.txt', 'mtime' => gmmktime(10, 0, 0, 1, 10, 2024), 'content' => "Alpha dock manifest fixture.\n"],
    ['name' => 'harbor-tide-log.txt', 'mtime' => gmmktime(10, 0, 0, 6, 15, 2024), 'content' => "Harbor tide log fixture.\n"],
    ['name' => 'zulu-berth-report.txt', 'mtime' => gmmktime(10, 0, 0, 11, 20, 2024), 'content' => "Zulu berth report fixture.\n"],
];
if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0755, true)) {
    throw new RuntimeException('Could not create ' . $fixtureDir);
}
foreach ($fixtures as $fixture) {
    $path = $fixtureDir . $fixture['name'];
    if (file_put_contents($path, $fixture['content']) === false) {
        throw new RuntimeException('Could not write ' . $path);
    }
    if (!touch($path, $fixture['mtime'])) {
        throw new RuntimeException('Could not set mtime on ' . $path);
    }
    clearstatcache(true, $path);
}
echo "Seeded Operations Hub, Harbor Safety Briefing, Autumn Berth Schedule\n";
echo "Seeded media sort fixtures in assets/sort-fixtures/ (alpha oldest, zulu newest)\n";
