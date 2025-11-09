<?php

declare(strict_types=1);

namespace App;

use Castor\Attribute\AsTask;
use function Castor\finder;
use function Castor\fs;
use function Castor\io;

#[AsTask(name: 'rename', namespace: 'plugin', description: 'Rename the plugin skeleton to your custom plugin name')]
function plugin_rename(): void
{
    io()->title('Sylius Plugin Renamer');

    io()->writeln('You can rollback with: git reset --hard HEAD');
    io()->newLine();

    io()->section('Plugin Information');

    io()->writeln('Example: Company "Acme" + Feature "Search" → AcmeSearchPlugin');
    io()->newLine();

    do {
        $companyName = io()->ask('Company name (PascalCase, ex: Acme)');
        if (!is_string($companyName) || !validateName($companyName)) {
            io()->error('Company name must be in PascalCase (start with uppercase letter, no spaces or special characters)');
            $companyName = null;
        }
    } while ($companyName === null);

    do {
        $featureName = io()->ask('Feature name (PascalCase, ex: Search)');
        if (!is_string($featureName) || !validateName($featureName)) {
            io()->error('Feature name must be in PascalCase (start with uppercase letter, no spaces or special characters)');
            $featureName = null;
        }
    } while ($featureName === null);

    $description = io()->ask(
        'Plugin description',
        'A Sylius plugin',
    );
    assert(is_string($description));

    $names = generateNameVariations($companyName, $featureName);

    io()->section('Configuration Summary');
    io()->table(
        ['Property', 'Value'],
        [
            ['Full namespace', "{$names['company']}\\{$names['plugin']}"],
            ['Full class name', $names['full_class']],
            ['Extension class', $names['extension_class']],
            ['Package name', $names['package']],
            ['Database name', $names['db']],
            ['Config key', $names['config_key']],
            ['Description', $description],
        ],
    );

    if (!io()->confirm('Continue with this configuration?')) {
        io()->info('Renaming cancelled.');

        return;
    }

    io()->section('Renaming PHP files');
    renamePluginFiles($names);

    io()->section('Updating file contents');
    updateFileContents($names);

    io()->section('Updating composer.json');
    updateComposerJson($names['package'], $description, $names);

    io()->section('Checking for remaining references');
    checkRemainingReferences();

    io()->success('Plugin renamed successfully!');
    io()->block([
        'Next steps:',
        '1. Review the changes: git diff',
        '2. Run: make init database-init load-fixtures',
    ], 'INFO', 'fg=black;bg=cyan', ' ', true);
}

function generateNameVariations(string $company, string $feature): array
{
    $plugin = $feature . 'Plugin';
    $fullClass = $company . $plugin;
    $fullClassWithoutPlugin = $company . $feature;

    $companyKebab = toKebabCase($company);
    $pluginKebab = toKebabCase($plugin);
    $companySnake = toSnakeCase($company);
    $pluginSnake = toSnakeCase($plugin);
    $fullSnake = $companySnake . '_' . $pluginSnake;

    return [
        'company' => $company,
        'plugin' => $plugin,
        'full_class' => $fullClass,
        'extension_class' => $fullClassWithoutPlugin . 'Extension',
        'package' => $companyKebab . '/' . $pluginKebab,
        'db' => $fullSnake,
        'config_key' => $fullSnake,
    ];
}

function renamePluginFiles(array $names): void
{
    $oldMainFile = 'src/AcmeSyliusExamplePlugin.php';
    $newMainFile = "src/{$names['full_class']}.php";

    $oldExtensionFile = 'src/DependencyInjection/AcmeSyliusExampleExtension.php';
    $newExtensionFile = "src/DependencyInjection/{$names['extension_class']}.php";

    if (fs()->exists($oldMainFile)) {
        fs()->rename($oldMainFile, $newMainFile);
        io()->writeln(" → Renamed: {$oldMainFile} → {$newMainFile}");
    }

    if (fs()->exists($oldExtensionFile)) {
        fs()->rename($oldExtensionFile, $newExtensionFile);
        io()->writeln(" → Renamed: {$oldExtensionFile} → {$newExtensionFile}");
    }

    io()->success('PHP files renamed');
}

function updateFileContents(array $names): void
{
    $replacements = [
        'Acme\\SyliusExamplePlugin' => "{$names['company']}\\{$names['plugin']}",
        'AcmeSyliusExamplePlugin' => $names['full_class'],
        'AcmeSyliusExampleExtension' => $names['extension_class'],
        '@AcmeSyliusExamplePlugin' => "@{$names['full_class']}",
        'Tests\\Acme\\SyliusExamplePlugin' => "Tests\\{$names['company']}\\{$names['plugin']}",
        'acme_sylius_example_plugin' => $names['db'],
        'acme_sylius_example' => $names['config_key'],
        'sylius_%kernel.environment%' => "{$names['db']}_%kernel.environment%",
    ];

    $files = finder()
        ->in('.')
        ->exclude(['vendor', 'var', 'node_modules', '.git'])
        ->name(['*.php', '*.yaml', '*.yml', '*.xml', '.env*', 'compose*.yml'])
        ->notName('castor.php')
        ->ignoreDotFiles(false)
        ->followLinks(false)
        ->files();

    $filesArray = iterator_to_array($files->getIterator());

    $updatedCount = 0;
    $updatedFiles = [];

    foreach ($filesArray as $file) {
        if (is_dir($file->getPathname())) {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            continue;
        }

        $originalContent = $content;

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($file->getPathname(), $content);
            ++$updatedCount;
            $updatedFiles[] = $file->getRelativePathname();
        }
    }

    if ($updatedCount > 0) {
        io()->success("{$updatedCount} files updated:");
        foreach ($updatedFiles as $updatedFile) {
            io()->writeln("  → {$updatedFile}");
        }
    } else {
        io()->info('No files updated');
    }
}

function updateComposerJson(string $packageName, string $description, array $names): void
{
    $composerFile = 'composer.json';
    $composerContent = file_get_contents($composerFile);

    if ($composerContent === false) {
        throw new \RuntimeException("Failed to read {$composerFile}");
    }

    $composer = json_decode($composerContent, true);

    if (!is_array($composer)) {
        throw new \RuntimeException("Failed to parse {$composerFile}");
    }

    $composer['name'] = $packageName;
    $composer['description'] = $description;

    unset($composer['autoload']['psr-4']['Acme\\SyliusExamplePlugin\\']);
    $composer['autoload']['psr-4']["{$names['company']}\\{$names['plugin']}\\"] = 'src/';

    unset($composer['autoload-dev']['psr-4']['Tests\\Acme\\SyliusExamplePlugin\\']);
    $composer['autoload-dev']['psr-4']["Tests\\{$names['company']}\\{$names['plugin']}\\"] = ['tests/', 'tests/TestApplication/src/'];

    file_put_contents(
        $composerFile,
        json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
    );

    io()->success('composer.json updated');
}

function checkRemainingReferences(): void
{
    $files = finder()
        ->in('.')
        ->exclude(['vendor', 'var', 'node_modules', '.git'])
        ->notName(['*.md', 'castor.php'])
        ->followLinks(false)
        ->files();

    $patterns = ['Acme', 'SyliusExample', 'acme_sylius_example'];
    $found = [];

    foreach ($files as $file) {
        // Skip if it's a directory or symlink to a directory
        if (is_dir($file->getPathname())) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $found[] = $file->getRelativePathname();

                break;
            }
        }
    }

    if (count($found) > 0) {
        io()->warning('Found remaining references in ' . count($found) . ' files:');
        foreach ($found as $file) {
            io()->writeln("  → {$file}");
        }
        io()->note('Please review these references manually. Some may be intentional (documentation, guides).');
    } else {
        io()->success('No remaining references found!');
    }
}

function validateName(string $name): bool
{
    if (empty($name)) {
        return false;
    }

    return (bool) preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name);
}

function toKebabCase(string $str): string
{
    $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $str);

    return strtolower($result !== null ? $result : $str);
}

function toSnakeCase(string $str): string
{
    $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);

    return strtolower($result !== null ? $result : $str);
}
