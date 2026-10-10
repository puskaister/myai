<?php
// A feltöltendő dist/ mappa összeállítása a deploy workflow-ban:
//   public/                          → dist/
//   apps/<app>/ minden telepítéshez  → dist/<dir>/ + saját config.php
// A telepítések listája appokként: deploy/<app>.json. Az adatbázis-, SMTP- és
// Anthropic-adatok környezeti változókból (GitHub secretekből) jönnek.
declare(strict_types=1);

$root = dirname(__DIR__);
$dist = "$root/dist";

// app => a mappából kihagyandó fájlok
$apps = [
    'idopontfoglalo' => ['README.md', 'api/config.php'],
    'chatbot'        => ['README.md', 'api/config.php', 'composer.json', 'composer.lock', 'vendor'],
    'latogatok'      => ['README.md', 'api/config.php', 'TELEPITES.md'],
    'evfordulok'     => ['README.md', 'api/config.php'],
    'bevasarlolista' => ['README.md', 'api/config.php'],
    'ugyintezes'     => ['README.md', 'api/config.php'],
];

function fail(string $msg): void {
    fwrite(STDERR, "::error::$msg\n");
    exit(1);
}

function copy_tree(string $from, string $to, array $skip = []): void {
    if (!is_dir($to)) mkdir($to, 0755, true);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $rel = substr($item->getPathname(), strlen($from) + 1);
        foreach ($skip as $sk) {
            if ($rel === $sk || strpos($rel, $sk . '/') === 0) continue 2; // a mappa teljes tartalma is kimarad
        }
        $target = "$to/$rel";
        if ($item->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

// Egy mappa determinisztikus zipje: rögzített dátumokkal és sorrenddel a zip
// bájtra azonos, ha a tartalom nem változott — így nem töltődik fel feleslegesen.
function zip_dir(string $dir, string $zipPath, string $prefix = '', array $skip = []): string {
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = substr($f->getPathname(), strlen($dir) + 1);
        foreach ($skip as $sk) {
            if ($rel === $sk || strpos($rel, $sk . '/') === 0) continue 2;
        }
        $files[] = $rel;
    }
    sort($files, SORT_STRING);
    @unlink($zipPath);
    if (!is_dir(dirname($zipPath))) mkdir(dirname($zipPath), 0755, true);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) fail("Nem sikerült létrehozni: $zipPath");
    foreach ($files as $rel) {
        $zip->addFile("$dir/$rel", $prefix . $rel);
        $zip->setMtimeName($prefix . $rel, 315532800); // 1980-01-01
    }
    $zip->close();
    return sha1_file($zipPath);
}

// A vendor/ egyetlen zip-be: FTP-n a több ezer kis fájl órákig tartana (és egy
// hibánál az egész elölről indulna). A szerveren az app első használatkor maga
// csomagolja ki (ensure_vendor()).
function zip_vendor(string $vendorDir, string $zipPath): string {
    return zip_dir($vendorDir, $zipPath);
}

copy_tree("$root/public", $dist);

$db = [
    'host' => getenv('DB_HOST') ?: '172.30.50.11', // DiMa: a MySQL külön szerveren van
    'name' => (string) getenv('DB_NAME'),
    'user' => (string) getenv('DB_USER'),
    'pass' => (string) getenv('DB_PASS'),
    'charset' => 'utf8mb4',
];
$hasDb = $db['name'] !== '' && $db['user'] !== '' && $db['pass'] !== '';
if (!$hasDb) echo "::warning::Nincs DB_NAME / DB_USER / DB_PASS secret — a telepítők a 'hiányzó config' üzenetet mutatják.\n";

$smtp = [
    'host' => getenv('SMTP_HOST') ?: '',
    'port' => (int) (getenv('SMTP_PORT') ?: 465),
    'username' => getenv('SMTP_USER') ?: '',
    'password' => getenv('SMTP_PASS') ?: '',
    'from' => getenv('SMTP_FROM') ?: '',
];
$anthropicKey = (string) getenv('ANTHROPIC_API_KEY');
// Látható visszajelzés (jelszó nélkül), hogy az appok milyen úton küldenek levelet.
echo $smtp['host'] !== '' && $smtp['username'] !== '' && $smtp['password'] !== ''
    ? "::notice title=Levelezés::SMTP beállítva: {$smtp['host']}:{$smtp['port']}, felhasználó: {$smtp['username']}, feladó: " . ($smtp['from'] ?: $smtp['username']) . "\n"
    : "::warning title=Levelezés::Nincs SMTP beállítva (SMTP_HOST / SMTP_USER / SMTP_PASS secret) — az appok a PHP mail()-lel küldenek, ami gyakran spambe kerül vagy nem érkezik meg.\n";

$vendorZip = null;
$seenDirs = [];
$seenPrefixes = [];
foreach ($apps as $app => $skip) {
    $listFile = "$root/deploy/$app.json";
    if (!is_file($listFile)) continue;
    $list = json_decode((string) file_get_contents($listFile), true);
    if (!is_array($list['instances'] ?? null)) fail("Hibás deploy/$app.json");
    if ($app === 'chatbot' && $list['instances'] && !is_file("$root/apps/chatbot/vendor/autoload.php")) {
        fail('A chatbothoz előbb futtasd: composer install --no-dev --working-dir=apps/chatbot');
    }
    if ($app === 'chatbot' && $list['instances'] && $anthropicKey === '') {
        echo "::warning::Nincs ANTHROPIC_API_KEY secret — a chatbot telepíthető, de nem válaszol.\n";
    }

    foreach ($list['instances'] as $i => $inst) {
        $dir = (string) ($inst['dir'] ?? '');
        $prefix = (string) ($inst['prefix'] ?? '');
        if (!preg_match('/^[a-z0-9-]{2,40}$/', $dir)) fail("$app #$i: a 'dir' csak kisbetű, szám és kötőjel lehet: '$dir'");
        if (!preg_match('/^[a-z0-9]{2,20}_$/', $prefix)) fail("$app #$i: a 'prefix' kisbetű/szám és aláhúzásra végződik (pl. gumipont_): '$prefix'");
        if (isset($seenDirs[$dir]) || isset($seenPrefixes[$prefix])) fail("$app #$i: ismétlődő dir vagy prefix (az appok között is egyedi kell): $dir / $prefix");
        if (is_dir("$root/public/$dir")) fail("$app #$i: a public/$dir már létezik, ütközne");
        $seenDirs[$dir] = $seenPrefixes[$prefix] = true;

        copy_tree("$root/apps/$app", "$dist/$dir", $skip);
        if ($app === 'chatbot') {
            $vendorZip ??= zip_vendor("$root/apps/chatbot/vendor", "$root/dist-vendor.zip");
            copy("$root/dist-vendor.zip", "$dist/$dir/vendor.zip");
            file_put_contents("$dist/$dir/vendor.version", $vendorZip . "\n");
        }

        if ($hasDb) {
            $config = [
                'db' => $db,
                'prefix' => $prefix,
                'session_name' => ($app === 'idopontfoglalo' ? 'idopont' : $app) . '_' . rtrim($prefix, '_'),
                'smtp' => $smtp,
            ];
            if ($app === 'chatbot') $config['anthropic'] = ['api_key' => $anthropicKey, 'base_url' => ''];
            file_put_contents("$dist/$dir/api/config.php", "<?php\nreturn " . var_export($config, true) . ";\n");
        }
        echo "  $app → /$dir  (előtag: $prefix)\n";
    }
}
// Letölthető telepítőcsomagok a vásárlóknak (my-ai.hu/letoltes/). A csomagban
// nincs config.php (jelszó), a README helyett a vásárlói útmutató van.
$downloads = [
    'latogatoszamlalo' => ['app' => 'latogatok', 'skip' => ['README.md', 'api/config.php']],
    'evfordulok'       => ['app' => 'evfordulok', 'skip' => ['README.md', 'api/config.php']],
    'bevasarlolista'   => ['app' => 'bevasarlolista', 'skip' => ['README.md', 'api/config.php']],
];
foreach ($downloads as $name => $d) {
    $hash = zip_dir("$root/apps/{$d['app']}", "$dist/letoltes/files/$name.zip", "$name/", $d['skip']);
    file_put_contents("$dist/letoltes/files/$name.version", substr($hash, 0, 12) . "\n");
    echo "  letöltés → /letoltes/files/$name.zip (" . round(filesize("$dist/letoltes/files/$name.zip") / 1024) . " KB)\n";
}
echo "dist/ kész.\n";
