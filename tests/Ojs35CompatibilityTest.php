<?php

use PHPUnit\Framework\TestCase;

class Ojs35CompatibilityTest extends TestCase
{
    public function testRuntimePhpFilesUseOjs35CompatibleConventions(): void
    {
        $runtimeFiles = $this->getRuntimePhpFiles();
        $legacyFiles = array_values(array_filter($runtimeFiles, function (string $file): bool {
            return substr($file, -8) === '.inc.php';
        }));

        $this->assertSame([], $legacyFiles, 'OJS 3.5 plugins must not ship runtime .inc.php files.');

        foreach ($runtimeFiles as $file) {
            $contents = file_get_contents($this->getPluginPath() . '/' . $file);

            $this->assertStringNotContainsString('import(', $contents, $file . ' must not use legacy import().');
            $this->assertStringNotContainsString('HookRegistry::', $contents, $file . ' must use PKP\\plugins\\Hook.');
        }
    }

    public function testPluginEntryPointLoadsNamespacedPluginClass(): void
    {
        $indexContents = file_get_contents($this->getPluginPath() . '/index.php');
        $versionContents = file_get_contents($this->getPluginPath() . '/version.xml');

        $this->assertStringContainsString(
            'APP\\plugins\\generic\\doiForTranslation\\DoiForTranslationPlugin',
            $indexContents
        );
        $this->assertStringContainsString(
            '<class>DoiForTranslationPlugin</class>',
            $versionContents
        );
    }

    public function testContinuousIntegrationUsesOjs35Templates(): void
    {
        $ciContents = file_get_contents($this->getPluginPath() . '/.gitlab-ci.yml');

        $this->assertStringContainsString('ref: stable-3_5_0', $ciContents);
        $this->assertStringNotContainsString('ref: stable-3_3_0', $ciContents);
    }

    public function testWorkflowUsesOjs35VueExtension(): void
    {
        $mainScript = file_get_contents($this->getPluginPath() . '/resources/js/main.js');
        $plugin = file_get_contents($this->getPluginPath() . '/DoiForTranslationPlugin.php');

        $this->assertStringContainsString("storeExtend('workflow'", $mainScript);
        $this->assertStringContainsString("extendFn('getHeaderItems'", $mainScript);
        $this->assertStringContainsString("\$buildUrl . '/build.iife.js", $plugin);
        $this->assertFileExists($this->getPluginPath() . '/public/build/build.iife.js');
        $this->assertFileDoesNotExist($this->getPluginPath() . '/templates/nonTranslationWorkflow.tpl');
        $this->assertFileDoesNotExist($this->getPluginPath() . '/templates/refTranslatedWorkflow.tpl');
        $this->assertStringNotContainsString("Hook::add('Template::Workflow'", $plugin);
    }

    public function testVersionMetadataIsPreparedForOjs35Release(): void
    {
        $version = simplexml_load_file($this->getPluginPath() . '/version.xml');

        $this->assertNotFalse($version);
        $this->assertSame('3.0.0.0', (string) $version->release);
    }

    public function testDocumentationDeclaresOjs35Compatibility(): void
    {
        $experimentalCompatibilityStatements = [
            'README.md' => 'OJS 3.5.0-x (experimental support)',
            'README.pt_BR.md' => 'OJS 3.5.0-x (suporte experimental)',
            'README.es.md' => 'OJS 3.5.0-x (soporte experimental)',
        ];

        foreach ($experimentalCompatibilityStatements as $readme => $statement) {
            $contents = file_get_contents($this->getPluginPath() . '/' . $readme);

            $this->assertStringContainsString($statement, $contents, $readme);
            $this->assertStringNotContainsString('OJS 3.3.0-x', $contents, $readme);
        }
    }

    private function getRuntimePhpFiles(): array
    {
        $pluginPath = $this->getPluginPath();
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pluginPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace($pluginPath . '/', '', $file->getPathname());
            if (strpos($relativePath, 'tests/') === 0 || strpos($relativePath, 'cypress/') === 0) {
                continue;
            }

            if (preg_match('/\\.inc\\.php$|\\.php$/', $relativePath)) {
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }

    private function getPluginPath(): string
    {
        return dirname(__DIR__);
    }
}
