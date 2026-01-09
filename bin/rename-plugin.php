#!/usr/bin/env php
<?php

declare(strict_types=1);


use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaded = false;
foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaded = true;

        break;
    }
}

if (!$autoloaded) {
    fwrite(\STDERR, "Cannot find autoload.php. Please run 'composer install' first.\n");
    exit(1);
}

/**
 * @author camilleislasse <guiziweb@gmail.com>
 */
class RenamePluginCommand extends Command
{
    private const EXCLUDE_DIRS = ['vendor', 'var', 'node_modules', '.git'];

    private const FILE_PATTERNS = ['*.php', '*.yaml', '*.yml', '*.xml', '.env*', 'compose*.yml'];

    protected function configure(): void
    {
        $this
            ->setName('rename')
            ->setDescription('Rename the plugin skeleton to your custom plugin name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without applying them')
            ->addOption('company', null, InputOption::VALUE_REQUIRED, 'Company name (PascalCase)')
            ->addOption('feature', null, InputOption::VALUE_REQUIRED, 'Feature name (PascalCase)')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Plugin description')
            ->addOption('skip-interaction', null, InputOption::VALUE_NONE, 'Skip interactive mode (useful for automation)')
            ->addOption('sylius', null, InputOption::VALUE_NONE, 'Use Sylius official plugin naming convention (Sylius\{Name}Plugin)')
            ->addOption('auto-detect', null, InputOption::VALUE_NONE, 'Auto-detect plugin name from directory (e.g., FooPlugin → company=Sylius, feature=Foo)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sylius Plugin Renamer');

        $skipInteraction = (bool) $input->getOption('skip-interaction');

        if (!$this->checkGitStatus($io, $skipInteraction)) {
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $io->note('DRY RUN MODE - No files will be modified');
        }

        if (!file_exists(__DIR__ . '/../src/AcmeSyliusExamplePlugin.php')) {
            $io->warning('Plugin appears to be already renamed (AcmeSyliusExamplePlugin.php not found)');
            if (!$skipInteraction && !$io->confirm('Continue anyway?', false)) {
                return Command::SUCCESS;
            }
        }

        $io->writeln('You can rollback with: git reset --hard HEAD');
        $io->newLine();

        $syliusMode = (bool) $input->getOption('sylius');
        $autoDetect = (bool) $input->getOption('auto-detect');

        $names = $this->getPluginInformation($input, $io, $syliusMode, $autoDetect);
        $description = $names['description'];

        $io->section('Configuration Summary');
        $io->table(
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

        if (!$dryRun && !$skipInteraction) {
            if (!$io->confirm('Continue with this configuration?', true)) {
                $io->info('Renaming cancelled.');

                return Command::SUCCESS;
            }
        }

        try {
            $this->renamePluginFiles($io, $names, $dryRun);
            $this->updateFileContents($io, $names, $dryRun);
            $this->updateComposerJson($io, $names['package'], $description, $names, $dryRun);

            if (!$dryRun) {
                $this->runComposerDumpAutoload($io);
            }

            $this->checkRemainingReferences($io);

            if ($dryRun) {
                $io->success('Dry run completed! Run without --dry-run to apply changes.');
            } else {
                $io->success('Plugin renamed successfully!');
                $io->newLine();
                $io->writeln('<comment>Next steps:</comment>');
                $io->listing([
                    'Review the changes: <info>git diff</info>',
                    'Run: <info>composer install</info> (to refresh autoload)',
                    'Run: <info>make init database-init load-fixtures</info> (if using Docker)',
                ]);

                if (unlink(__FILE__)) {
                    $io->note('This script has been deleted (one-time use only).');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function checkGitStatus(SymfonyStyle $io, bool $skipInteraction): bool
    {
        if (!is_dir(__DIR__ . '/../.git')) {
            $io->warning('Not a git repository. Changes cannot be easily reverted.');

            return $skipInteraction ? true : $io->confirm('Continue anyway?', false);
        }

        $status = shell_exec('git status --porcelain 2>&1');
        if ($status === null || $status === false) {
            $io->warning('Could not check git status.');

            return $skipInteraction ? true : $io->confirm('Continue anyway?', false);
        }

        if (trim($status) !== '') {
            $io->warning([
                'You have uncommitted changes in your repository:',
                '',
                $status,
                '',
                'The rename script will modify many files. It is recommended to commit or stash your changes first.',
            ]);

            return $skipInteraction ? true : $io->confirm('Continue anyway?', false);
        }

        return true;
    }

    private function getPluginInformation(InputInterface $input, SymfonyStyle $io, bool $syliusMode, bool $autoDetect): array
    {
        $io->section('Plugin Information');

        // Auto-detect from directory name if enabled
        if ($autoDetect) {
            $detected = $this->detectFromDirectory();
            if ($detected !== null) {
                $io->note("Auto-detected from directory: {$detected['feature']}Plugin");
                $companyName = $syliusMode ? 'Sylius' : $detected['company'];
                $featureName = $detected['feature'];
            }
        }

        if ($syliusMode) {
            $io->note('Using Sylius official naming convention: Sylius\{Name}Plugin');
            $companyName = 'Sylius';
        }

        if (!isset($companyName)) {
            $io->writeln('Example: Company "Acme" + Feature "Search" → AcmeSearchPlugin');
            $io->newLine();

            $companyName = $input->getOption('company');
            if ($companyName === null || !is_string($companyName) || !$this->validateName($companyName)) {
                $question = new Question('Company name (PascalCase, ex: Acme)');
                $question->setValidator(function ($answer) {
                    if (!is_string($answer) || !$this->validateName($answer)) {
                        throw new \RuntimeException('Company name must be in PascalCase (start with uppercase letter, no spaces or special characters)');
                    }

                    return $answer;
                });
                $question->setMaxAttempts(null);
                $companyName = $io->askQuestion($question);
            }
        }

        if (!isset($featureName)) {
            $featureName = $input->getOption('feature');
            if ($featureName === null || !is_string($featureName) || !$this->validateName($featureName)) {
                $question = new Question('Feature name (PascalCase, ex: Search)');
                $question->setValidator(function ($answer) {
                    if (!is_string($answer) || !$this->validateName($answer)) {
                        throw new \RuntimeException('Feature name must be in PascalCase (start with uppercase letter, no spaces or special characters)');
                    }

                    return $answer;
                });
                $question->setMaxAttempts(null);
                $featureName = $io->askQuestion($question);
            }
        }

        $description = $input->getOption('description');
        if ($description === null || !is_string($description)) {
            $question = new Question('Plugin description', 'A Sylius plugin');
            $description = $io->askQuestion($question);
        }

        if (!is_string($description)) {
            $description = 'A Sylius plugin';
        }

        assert(is_string($companyName));
        assert(is_string($featureName));

        $names = $this->generateNameVariations($companyName, $featureName, $syliusMode);
        $names['description'] = $description;

        return $names;
    }

    /**
     * @return array{company: string, feature: string}|null
     */
    private function detectFromDirectory(): ?array
    {
        $dirName = basename(dirname(__DIR__));

        // Match patterns like "FooPlugin", "SyliusFooPlugin", "AcmeFooPlugin"
        if (preg_match('/^(?:Sylius)?([A-Z][a-zA-Z0-9]*)Plugin$/', $dirName, $matches)) {
            return [
                'company' => 'Sylius',
                'feature' => $matches[1],
            ];
        }

        if (preg_match('/^([A-Z][a-zA-Z0-9]*)([A-Z][a-zA-Z0-9]*)Plugin$/', $dirName, $matches)) {
            return [
                'company' => $matches[1],
                'feature' => $matches[2],
            ];
        }

        return null;
    }

    private function generateNameVariations(string $company, string $feature, bool $syliusMode = false): array
    {
        $plugin = $feature . 'Plugin';
        $featureSnake = $this->toSnakeCase($feature);

        if ($syliusMode) {
            // Sylius official: Sylius\AdyenPlugin\SyliusAdyenPlugin, alias: sylius_adyen
            $fullClass = 'Sylius' . $plugin;
            $extensionClass = 'Sylius' . $feature . 'Extension';
            $featureKebab = $this->toKebabCase($feature);

            return [
                'company' => 'Sylius',
                'plugin' => $plugin,
                'full_class' => $fullClass,
                'extension_class' => $extensionClass,
                'package' => 'sylius/' . $featureKebab . '-plugin',
                'db' => 'sylius_' . $featureSnake,
                'config_key' => 'sylius_' . $featureSnake,
            ];
        }

        // Community: Acme\SearchPlugin\AcmeSearchPlugin, alias: acme_search
        $fullClass = $company . $plugin;
        $extensionClass = $company . $feature . 'Extension';

        $companyKebab = $this->toKebabCase($company);
        $pluginKebab = $this->toKebabCase($plugin);
        $companySnake = $this->toSnakeCase($company);

        return [
            'company' => $company,
            'plugin' => $plugin,
            'full_class' => $fullClass,
            'extension_class' => $extensionClass,
            'package' => $companyKebab . '/' . $pluginKebab,
            'db' => $companySnake . '_' . $featureSnake,
            'config_key' => $companySnake . '_' . $featureSnake,
        ];
    }

    private function renamePluginFiles(SymfonyStyle $io, array $names, bool $dryRun): void
    {
        $io->section('Renaming PHP files');

        $oldMainFile = __DIR__ . '/../src/AcmeSyliusExamplePlugin.php';
        $newMainFile = __DIR__ . "/../src/{$names['full_class']}.php";

        $oldExtensionFile = __DIR__ . '/../src/DependencyInjection/AcmeSyliusExampleExtension.php';
        $newExtensionFile = __DIR__ . "/../src/DependencyInjection/{$names['extension_class']}.php";

        $renamedFiles = [];

        if (file_exists($oldMainFile)) {
            if ($dryRun) {
                $renamedFiles[] = "[DRY RUN] src/AcmeSyliusExamplePlugin.php → src/{$names['full_class']}.php";
            } else {
                if (!rename($oldMainFile, $newMainFile)) {
                    throw new \RuntimeException("Failed to rename {$oldMainFile}");
                }
                $renamedFiles[] = "src/AcmeSyliusExamplePlugin.php → src/{$names['full_class']}.php";
            }
        }

        if (file_exists($oldExtensionFile)) {
            if ($dryRun) {
                $renamedFiles[] = "[DRY RUN] src/DependencyInjection/AcmeSyliusExampleExtension.php → src/DependencyInjection/{$names['extension_class']}.php";
            } else {
                if (!rename($oldExtensionFile, $newExtensionFile)) {
                    throw new \RuntimeException("Failed to rename {$oldExtensionFile}");
                }
                $renamedFiles[] = "src/DependencyInjection/AcmeSyliusExampleExtension.php → src/DependencyInjection/{$names['extension_class']}.php";
            }
        }

        if (count($renamedFiles) > 0) {
            $io->listing($renamedFiles);
        }

        $io->success('PHP files renamed');
    }

    private function updateFileContents(SymfonyStyle $io, array $names, bool $dryRun): void
    {
        $io->section('Updating file contents');

        $replacements = [
            'acme_sylius_example_plugin_%kernel.environment%' => 'sylius_%kernel.environment%',
            'Acme\\SyliusExamplePlugin' => "{$names['company']}\\{$names['plugin']}",
            'AcmeSyliusExamplePlugin' => $names['full_class'],
            'AcmeSyliusExampleExtension' => $names['extension_class'],
            '@AcmeSyliusExamplePlugin' => "@{$names['full_class']}",
            'Tests\\Acme\\SyliusExamplePlugin' => "Tests\\{$names['company']}\\{$names['plugin']}",
            'acme_sylius_example_plugin' => $names['db'],
            'acme_sylius_example' => $names['config_key'],
        ];

        $finder = new Finder();
        $finder->files()
            ->in(__DIR__ . '/..')
            ->exclude(self::EXCLUDE_DIRS)
            ->ignoreDotFiles(false)
            ->name(self::FILE_PATTERNS)
            ->notName('rename-plugin.php');

        $updatedCount = 0;
        $updatedFiles = [];

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            $originalContent = $content;

            foreach ($replacements as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }

            if ($content !== $originalContent) {
                if ($dryRun) {
                    $updatedFiles[] = $file->getRelativePathname();
                } else {
                    if (file_put_contents($filePath, $content) === false) {
                        throw new \RuntimeException("Failed to write file: {$filePath}");
                    }
                    $updatedFiles[] = $file->getRelativePathname();
                }
                ++$updatedCount;
            }
        }

        if ($updatedCount > 0) {
            $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
            $io->success("{$prefix} {$updatedCount} files:");
            $io->listing($updatedFiles);
        } else {
            $io->info('No files to update');
        }
    }

    private function updateComposerJson(SymfonyStyle $io, string $packageName, string $description, array $names, bool $dryRun): void
    {
        $io->section('Updating composer.json');

        $composerFile = __DIR__ . '/../composer.json';
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

        if ($dryRun) {
            $io->writeln('[DRY RUN] Would update composer.json with:');
            $io->writeln('  - name: ' . $packageName);
            $io->writeln('  - description: ' . $description);
            $io->writeln('  - autoload PSR-4 namespace: ' . "{$names['company']}\\{$names['plugin']}\\");
        } else {
            $newContent = json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n";
            if (file_put_contents($composerFile, $newContent) === false) {
                throw new \RuntimeException("Failed to write {$composerFile}");
            }
            $io->success('composer.json updated');
        }
    }

    private function runComposerDumpAutoload(SymfonyStyle $io): void
    {
        $io->section('Refreshing autoload files');

        exec('composer dump-autoload 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $io->warning('Failed to run composer dump-autoload. Please run it manually.');

            return;
        }

        $io->success('Autoload files refreshed');
        if ($io->isVerbose()) {
            $io->writeln(implode("\n", $output));
        }
    }

    private function checkRemainingReferences(SymfonyStyle $io): void
    {
        $io->section('Checking for remaining references');

        $finder = new Finder();
        $finder->files()
            ->in(__DIR__ . '/..')
            ->exclude(self::EXCLUDE_DIRS)
            ->notName(['*.md', 'rename-plugin.php']);

        $patterns = ['Acme', 'SyliusExample', 'acme_sylius_example'];
        $found = [];

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read file: {$filePath}");
            }

            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $found[] = $file->getRelativePathname();

                    break;
                }
            }
        }

        if (count($found) > 0) {
            $io->warning('Found remaining references in ' . count($found) . ' files:');
            $io->listing($found);
            $io->note('Please review these references manually. Some may be intentional (documentation, guides).');
        } else {
            $io->success('No remaining references found!');
        }
    }

    private function validateName(string $name): bool
    {
        if (empty($name)) {
            return false;
        }

        return (bool) preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name);
    }

    private function toKebabCase(string $str): string
    {
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $str);

        return strtolower($result !== null ? $result : $str);
    }

    private function toSnakeCase(string $str): string
    {
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $str);

        return strtolower($result !== null ? $result : $str);
    }
}

// Create and run application
$application = new Application('Sylius Plugin Renamer', '1.0.0');
$application->add(new RenamePluginCommand());
$application->setDefaultCommand('rename', true);
$application->run();
