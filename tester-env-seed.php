<?php
define('MODX_API_MODE', true);
require __DIR__ . '/index.php';

use MODX\Revolution\modDocument;
use MODX\Revolution\modResource;
use MODX\Revolution\modTemplate;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modTemplateVarResource;
use MODX\Revolution\modTemplateVarTemplate;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserMessage;
use MODX\Revolution\modUserProfile;

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

// Resolve the template backing the seeded resources (default template,
// falling back to the first template) so TV template access matches.
$templateId = (int) $modx->getOption('default_template', null, 1);
$seedTemplate = $templateId ? $modx->getObject(modTemplate::class, $templateId) : null;
if (!$seedTemplate) {
    $templateQuery = $modx->newQuery(modTemplate::class);
    $templateQuery->sortby('id', 'ASC');
    $templateQuery->limit(1);
    $foundTemplates = $modx->getCollection(modTemplate::class, $templateQuery);
    $seedTemplate = $foundTemplates ? reset($foundTemplates) : null;
}
if (!$seedTemplate) {
    throw new RuntimeException('No template available for TV seed');
}
$templateId = (int) $seedTemplate->get('id');

// Deterministic template variables: textarea TV with boolean input options
// (Autogrow=No, Resizable=Yes), image TV for &-filenames, date TV with empty
// input properties. Each is assigned to the seed template (get-or-create).
$tvs = [
    [
        'name' => 'berthNotes',
        'caption' => 'Berth Notes',
        'description' => 'Deterministic textarea TV fixture for boolean input options.',
        'type' => 'textarea',
        'input_properties' => ['allowBlank' => true, 'inputHeight' => 140, 'textareaGrow' => false, 'textareaResizable' => true],
    ],
    [
        'name' => 'berthImage',
        'caption' => 'Berth Image',
        'description' => 'Deterministic image TV fixture for filenames containing &.',
        'type' => 'image',
        'input_properties' => [],
    ],
    [
        'name' => 'berthInspectionDate',
        'caption' => 'Berth Inspection Date',
        'description' => 'Deterministic date TV fixture with empty input properties.',
        'type' => 'date',
        'input_properties' => null,
    ],
];
$tvIds = [];
foreach ($tvs as $fields) {
    $tv = $modx->getObject(modTemplateVar::class, ['name' => $fields['name']])
        ?: $modx->newObject(modTemplateVar::class);
    $tv->fromArray([
        'name' => $fields['name'], 'caption' => $fields['caption'],
        'description' => $fields['description'], 'type' => $fields['type'],
        'category' => 0, 'locked' => false, 'elements' => '', 'rank' => 0,
        'display' => 'default', 'default_text' => '',
        'input_properties' => $fields['input_properties'], 'output_properties' => [],
    ]);
    if (!$tv->save()) {
        throw new RuntimeException('Could not save TV ' . $fields['name']);
    }
    $tvIds[$fields['name']] = (int) $tv->get('id');
    $access = $modx->getObject(modTemplateVarTemplate::class, ['tmplvarid' => $tv->get('id'), 'templateid' => $templateId])
        ?: $modx->newObject(modTemplateVarTemplate::class);
    // NOTE: fromArray() skips primary-key fields by default and both of these
    // are PK, so set them explicitly (mirrors the TV Create processor).
    $access->set('tmplvarid', (int) $tv->get('id'));
    $access->set('templateid', $templateId);
    $access->set('rank', 0);
    if (!$access->save()) {
        throw new RuntimeException('Could not assign TV ' . $fields['name'] . ' to template');
    }
}

// Deterministic image fixture whose filename contains &: fixed 8x8 JPEG
// bytes plus a fixed mtime keep re-seeds identical.
$tvFixtureDir = (defined('MODX_BASE_PATH') ? MODX_BASE_PATH : __DIR__ . '/') . 'assets/tv-fixtures/';
$harborJpg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCAAIAAgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAaEAACAgMAAAAAAAAAAAAAAAAAERIUI0Nh/8QAFQEBAQAAAAAAAAAAAAAAAAAAAwX/xAAiEQABAgQHAQAAAAAAAAAAAAARAQIABhMiAwQUIUFhcZH/2gAMAwEAAhEDEQA/AKnntbYT6kwAFKeWoai9zi87qR51EaYserRtRA3hB9j/2Q==', true);
if ($harborJpg === false) {
    throw new RuntimeException('Could not decode tv fixture image');
}
$harborMtime = gmmktime(10, 0, 0, 9, 2, 2024);
if (!is_dir($tvFixtureDir) && !mkdir($tvFixtureDir, 0755, true)) {
    throw new RuntimeException('Could not create ' . $tvFixtureDir);
}
$harborPath = $tvFixtureDir . 'harbor&crane.jpg';
if (file_put_contents($harborPath, $harborJpg) === false) {
    throw new RuntimeException('Could not write ' . $harborPath);
}
if (!touch($harborPath, $harborMtime)) {
    throw new RuntimeException('Could not set mtime on ' . $harborPath);
}
clearstatcache(true, $harborPath);

// Seed TV values on the Autumn Berth Schedule resource (get-or-create).
$berthSchedule = $modx->getObject(modResource::class, ['alias' => 'autumn-berth-schedule', 'context_key' => 'web']);
if (!$berthSchedule) {
    throw new RuntimeException('Seed resource autumn-berth-schedule is missing');
}
$tvValues = [
    'berthImage' => 'assets/tv-fixtures/harbor&crane.jpg',
    'berthInspectionDate' => '2024-09-15 10:00:00',
];
foreach ($tvValues as $tvName => $tvValue) {
    $tvr = $modx->getObject(modTemplateVarResource::class, ['tmplvarid' => $tvIds[$tvName], 'contentid' => $berthSchedule->get('id')])
        ?: $modx->newObject(modTemplateVarResource::class);
    $tvr->fromArray(['tmplvarid' => $tvIds[$tvName], 'contentid' => (int) $berthSchedule->get('id'), 'value' => $tvValue]);
    if (!$tvr->save()) {
        throw new RuntimeException('Could not save TV value ' . $tvName);
    }
}
echo "Seeded TVs berthNotes (textarea Autogrow=No Resizable=Yes), berthImage (image), berthInspectionDate (date, no props) on template {$templateId}\n";
echo "Seeded image fixture assets/tv-fixtures/harbor&crane.jpg with TV values on autumn-berth-schedule\n";

// Second manager user plus one inbox message to tester-admin (get-or-create).
$observer = $modx->getObject(modUser::class, ['username' => 'dock-observer'])
    ?: $modx->newObject(modUser::class);
$observer->set('username', 'dock-observer');
$observer->set('password', 'dock-observer-17082005');
$observer->set('active', true);
if (!$observer->save()) {
    throw new RuntimeException('Could not save user dock-observer');
}
$observerProfile = $modx->getObject(modUserProfile::class, ['internalKey' => (int) $observer->get('id')]);
if (!$observerProfile) {
    $observerProfile = $modx->newObject(modUserProfile::class);
    // Deterministic profile/user ID-divergence prerequisite (scenario-local):
    // a fresh dock-observer profile takes a PK outside the user-ID domain
    // while its internalKey still equals the observer user ID, so baseline
    // internalKey joins succeed and the mutant id joins miss. An existing
    // profile is left untouched, keeping reseeds canonical.
    $observerProfile->set('id', 9000 + (int) $observer->get('id'));
}
$observerProfile->fromArray([
    'internalKey' => (int) $observer->get('id'),
    'email' => 'dock-observer@example.test', 'fullname' => 'Dock Observer', 'blocked' => false,
]);
if (!$observerProfile->save()) {
    throw new RuntimeException('Could not save profile for dock-observer');
}
$admin = $modx->getObject(modUser::class, ['username' => 'tester-admin']);
if (!$admin) {
    throw new RuntimeException('Admin user tester-admin is missing');
}
$inboxMessage = $modx->getObject(modUserMessage::class, [
    'sender' => (int) $observer->get('id'),
    'recipient' => (int) $admin->get('id'),
    'subject' => 'Dockside shift handover',
]) ?: $modx->newObject(modUserMessage::class);
$inboxMessage->fromArray([
    'type' => 'user', 'subject' => 'Dockside shift handover',
    'message' => "Harbor tide log reviewed; berth assignments confirmed for the autumn window.\n",
    'sender' => (int) $observer->get('id'), 'recipient' => (int) $admin->get('id'),
    'private' => 1, 'date_sent' => '2024-09-02 09:00:00', 'read' => 0,
]);
if (!$inboxMessage->save()) {
    throw new RuntimeException('Could not save inbox message to tester-admin');
}
echo "Seeded user dock-observer with inbox message to tester-admin\n";
