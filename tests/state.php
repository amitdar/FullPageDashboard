<?php
// Run with: php tests/state.php. All state is confined to a temporary directory.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check($condition, $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/dashboard-state-' . bin2hex(random_bytes(8));
mkdir($directory);
copy(__DIR__ . '/../api.php', $directory . '/api.php');
file_put_contents($directory . '/config.php', '<?php define("INIT_URL_PATH", __DIR__ . "/init.txt");');
file_put_contents($directory . '/init.txt', "http://localhost/welcome\n");
$_SERVER['REQUEST_METHOD'] = 'GET';
require $directory . '/api.php';
$path = $directory . '/urls.json';
$backup = $path . '.bak';
$passed = 0;

try {
    $defaults = array(array('title' => 'Default', 'url' => "http://localhost/welcome\n"));
    $api = new Api();
    check($api->getUrls() === $defaults, 'Missing file must initialize');
    check($api->getUrls() === $defaults, 'Repeated getUrls must return cached URLs');
    check(count(glob($directory . '/urls.json.corrupt-*')) === 0, 'Missing file is not corrupt');
    check(file_get_contents($backup) === file_get_contents($path), 'Initialization must seed a valid backup');
    $passed++;

    $invalid = array('', '{', 'null', '{}', '{"options":null,"urls":[]}',
        '{"options":[],"urls":null}', '{"options":[],"urls":[null]}',
        '{"options":[],"urls":[{"title":"Missing URL"}]}',
        '{"options":[],"urls":[{"title":1,"url":"http://example.com"}]}',
        '{"options":[],"urls":{"1":{"title":"Wrong index","url":"http://example.com"}}}');
    foreach ($invalid as $contents) {
        file_put_contents($path, $contents);
        $before = glob($directory . '/urls.json.corrupt-*');
        $api = new Api();
        check($api->getUrls() === $defaults, 'Invalid state must recover: ' . $contents);
        $backups = array_values(array_diff(glob($directory . '/urls.json.corrupt-*'), $before));
        check(count($backups) === 1, 'Corrupt file must be backed up once');
        check(file_get_contents($backups[0]) === $contents, 'Backup must preserve exact bytes');
        check(json_decode(file_get_contents($path), true)['urls'] === $defaults, 'Recovery must persist');
        $api = new Api();
        check($api->getUrls() === $defaults, 'Recovered state must reload');
        check(count(glob($directory . '/urls.json.corrupt-*')) === count($before) + 1,
            'Reload must not create another backup');
        $passed++;
    }

    $valid = array('options' => array('speed' => 15, 'hideTabBar' => 1, 'version' => '0.1'),
        'urls' => array(array('title' => 'Custom', 'url' => 'https://example.com')));
    $encoded = json_encode($valid);
    file_put_contents($path, $encoded);
    $api = new Api();
    check($api->getUrls() === $valid['urls'], 'Valid URLs must survive');
    check($api->getOptions() === $valid['options'], 'Valid options must survive');
    check(file_get_contents($path) === $encoded, 'Reading valid state must not rewrite it');
    $passed++;

    // Recover real settings, not just a working dashboard with default settings.
    foreach (array(null, '', '{', '{"options":null,"urls":[]}') as $broken) {
        file_put_contents($backup, $encoded);
        if ($broken === null) {
            unlink($path);
        } else {
            file_put_contents($path, $broken);
        }
        $api = new Api();
        check($api->getUrls() === $valid['urls'], 'Recovery must restore custom URLs');
        check($api->getOptions() === $valid['options'], 'Recovery must restore custom options');
        check(json_decode(file_get_contents($path), true) === $valid, 'Primary must be repaired');
        check(file_get_contents($backup) === $encoded, 'Recovery must not overwrite good backup');
        $passed++;
    }

    foreach (array(null, '', '{', '{"urls":[],"options":null}') as $brokenBackup) {
        file_put_contents($path, '');
        if ($brokenBackup === null) {
            unlink($backup);
        } else {
            file_put_contents($backup, $brokenBackup);
        }
        $before = glob($directory . '/urls.json.bak.corrupt-*');
        $api = new Api();
        check($api->getUrls() === $defaults, 'No usable copy must restore defaults');
        check(file_get_contents($backup) === file_get_contents($path), 'Fallback must seed backup');
        if ($brokenBackup !== null) {
            $archives = array_values(array_diff(glob($directory . '/urls.json.bak.corrupt-*'), $before));
            check(count($archives) === 1 && file_get_contents($archives[0]) === $brokenBackup,
                'Damaged backup must also be preserved');
        }
        $passed++;
    }

    foreach (array(null, '{') as $brokenBackup) {
        file_put_contents($path, $encoded);
        if ($brokenBackup === null) {
            unlink($backup);
        } else {
            file_put_contents($backup, $brokenBackup);
        }
        $api = new Api();
        check($api->getUrls() === $valid['urls'], 'Valid primary must win over invalid backup');
        check(json_decode(file_get_contents($backup), true) === $valid, 'Legacy primary must seed backup');
        $passed++;
    }

    $_POST = array('action' => 'speed', 'value' => '55');
    $api = new Api();
    $api->callAction();
    check(json_decode(file_get_contents($backup), true) === $valid, 'Save must retain previous valid version');
    check(json_decode(file_get_contents($path), true)['options']['speed'] === 55, 'New version must be saved');
    $passed++;

    // Simulate an interrupted temporary write; readers must ignore it.
    file_put_contents($directory . '/.urls-interrupted', '{');
    file_put_contents($path, '');
    $api = new Api();
    check($api->getUrls() === $valid['urls'] && $api->getOptions() === $valid['options'],
        'Interrupted update must recover previous configuration');
    check(file_get_contents($backup) === $encoded, 'Recovery must retain previous backup');
    unlink($directory . '/.urls-interrupted');
    $passed++;

    // POST paths must recover too, and must read relative to api.php, not cwd.
    chdir(sys_get_temp_dir());
    file_put_contents($path, '');
    file_put_contents($backup, json_encode(array('options' => array('version' => '0.1'), 'urls' => $defaults)));
    $_POST = array('action' => 'speed', 'value' => '42');
    $api = new Api();
    $api->callAction();
    $stored = json_decode(file_get_contents($path), true);
    check($stored['options']['speed'] === 42 && $stored['urls'] === $defaults, 'POST must recover');
    $passed++;

    $_POST = array('action' => 'add', 'title' => 'Another', 'url' => 'https://example.org');
    $api = new Api();
    $api->callAction();
    $stored = json_decode(file_get_contents($path), true);
    check(count($stored['urls']) === 2, 'Atomic update must persist new URL');
    check($stored['options']['speed'] === 42, 'Update must preserve options');
    check(glob($directory . '/.urls-*') === array(), 'Successful writes must leave no temporary files');
    $passed++;

    $before = file_get_contents($path);
    $backupBefore = file_get_contents($backup);
    $_POST = array('action' => 'add', 'title' => "\xff", 'url' => 'https://example.org');
    try {
        $api = new Api();
        $api->callAction();
        throw new LogicException('Invalid UTF-8 should fail encoding');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'Cannot encode dashboard state', 'Expected encoding failure');
    }
    check(file_get_contents($path) === $before, 'Failed write must preserve previous state');
    check(file_get_contents($backup) === $backupBefore, 'Failed encoding must preserve backup too');
    $passed++;

    file_put_contents($path, '{"options":[],"urls":[]}');
    $api = new Api();
    check($api->getUrls() === array(), 'Empty URL list is valid, not corrupt');
    $passed++;
    echo "$passed state tests passed\n";
} finally {
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    foreach (glob($directory . '/.urls-*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}
