<?php

declare(strict_types=1);

namespace fucodo\mermaid\Service;

use Neos\Flow\Package\PackageInterface;
use Neos\Flow\Package\PackageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class MermaidService
{
    /**
     * @Flow\InjectConfiguration(package="fucodo.mermaid", path="puppeteer-config")
     * @var array
     */
    protected $puppeteerSettings;

    /**
     * @Flow\InjectConfiguration(package="fucodo.mermaid", path="svgo-config")
     * @var array
     */
    protected $svgoSettings;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var \Neos\Cache\Frontend\StringFrontend $cache
     */
    protected $graphicsCache;

    protected function getMermaidPackage(): PackageInterface
    {
        return $this->packageManager->getPackage('fucodo.mermaid');
    }

    public function getPuppeteerConfigPath(): string
    {
        return FLOW_PATH_DATA . 'Temporary/Mermaid/puppeteer-config.json';
    }

    public function getSvgoConfigPath()
    {
        return FLOW_PATH_DATA . 'Temporary/Mermaid/svgo-config.js';
    }

    public function getMermaidConfigPath(): string
    {
        return $this->getMermaidPackage()->getPackagePath() . 'mermaid.config.json';
    }

    protected function preparePuppeteerConfig(): void
    {
        Files::createDirectoryRecursively(dirname($this->getPuppeteerConfigPath()));
        $config = json_encode($this->puppeteerSettings, JSON_THROW_ON_ERROR);

        $this->logger->debug('Mermaid puppeteer config', ['config' => $config]);

        if (is_file($this->getPuppeteerConfigPath()) && (file_get_contents($this->getPuppeteerConfigPath()) === $config)) {
            return;
        }
        file_put_contents($this->getPuppeteerConfigPath(), $config);
    }

    public function renderMermaidGraph(string $mermaidGraph, string $format = 'svg'): string
    {

        $file = tempnam(FLOW_PATH_DATA . 'Temporary', 'mermaid-input');
        file_put_contents($file, $mermaidGraph);
        $buffer = $this->renderMermaidFile($file, $format);
        unlink($file);
        return $buffer;
    }

    public function renderMermaidFile(string $file, string $format = 'svg'): string
    {
        $cacheTag = sha1($format . '-' . file_get_contents($file) . '-' . file_get_contents($this->getPuppeteerConfigPath()) . '-' . file_get_contents($this->getMermaidConfigPath()));
        if ($this->graphicsCache->has($cacheTag)) {
            return $this->graphicsCache->get($cacheTag);
        }

        // prepare stuff
        $this->preparePuppeteerConfig();
        $outputFile = tempnam(FLOW_PATH_DATA . 'Temporary', 'mermaid-output');
        $outputFileWithExt =  $outputFile . '.' . $format;

        // render graph
        $command = \sprintf(
            '%s/node_modules/.bin/mmdc -c %s --puppeteerConfigFile %s -e %s -o %s < %s',
            escapeshellarg($this->getMermaidPackage()->getPackagePath()),
            escapeshellarg($this->getMermaidConfigPath()),
            escapeshellarg($this->getPuppeteerConfigPath()),
            escapeshellarg($format),
            escapeshellarg($outputFileWithExt),
            escapeshellarg($file)
        );
        $this->logger->debug('Mermaid command', ['command' => $command]);
        $process = Process::fromShellCommandline(
            $command
        );
        $process->start();
        $process->wait();

        // optimize grafics for mpdf etc.
        #if ($format === 'svg') {
        #    $this->useInkScape($outputFileWithExt);
        #    $this->useSvgGo($outputFileWithExt);
        #}

        // build return stuff
        $buffer = file_get_contents($outputFileWithExt);

        $this->graphicsCache->set($cacheTag, $buffer);

        unlink($outputFile);
        unlink($outputFileWithExt);
        return $buffer;
    }

    protected function useSvgGo(string $file)
    {
        file_put_contents($this->getSvgoConfigPath(), 'module.exports = ' . json_encode($this->svgoSettings, JSON_THROW_ON_ERROR));
        $command = \sprintf(
            '%s/node_modules/.bin/svgo --config %s --output %s --input %s',
            escapeshellarg($this->getMermaidPackage()->getPackagePath()),
            escapeshellarg($this->getSvgoConfigPath()),
            escapeshellarg($file),
            escapeshellarg($file)
        );
        $this->logger->debug(
            'Svgo command',
            [
                'command' => $command,
                'sizeIn' => filesize($file),
            ]
        );
        $process = Process::fromShellCommandline(
            $command
        );
        $process->start();
        $process->wait();
        $this->logger->debug(
            'Svgo command',
            [
                'command' => $command, 'output' => $process->getOutput(),
                'sizeIn' => filesize($file),
            ]
        );
    }

    public function useInkScape(string $file): void
    {
        $command = \sprintf(
            'inkscape --export-filename=%s %s',
            escapeshellarg($file),
            escapeshellarg($file)
        );
        $this->logger->debug(
            'inkscape command',
            [
                'command' => $command,
                'sizeIn' => filesize($file),
            ]
        );
        $process = Process::fromShellCommandline(
            $command
        );
        $process->start();
        $process->wait();
        $this->logger->debug(
            'inkscape command',
            [
                'command' => $command, 'output' => $process->getOutput(),
                'sizeIn' => filesize($file),
            ]
        );
    }

    public function install()
    {
        $commands = [
            sprintf(
                '(cd %s; npm install)',
                escapeshellarg($this->getMermaidPackage()->getPackagePath())
            ),
            sprintf(
                '(cd %s; npx puppeteer browsers install chrome-headless-shell)',
                escapeshellarg($this->getMermaidPackage()->getPackagePath())
            )
        ];

        foreach ($commands as $command) {
            $this->logger->debug('Mermaid command', ['command' => $command]);
            $process = Process::fromShellCommandline(
                $command
            );
            $process->start();
            $process->wait();
            $this->logger->debug(
                'Mermaid command output',
                [
                    'command' => $command,
                    'output' => $process->getOutput()
                ]);
        }
    }
}
