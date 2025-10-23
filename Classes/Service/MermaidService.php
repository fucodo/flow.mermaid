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
    protected $settings;

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

    protected function getMermaidPackage(): PackageInterface
    {
        return $this->packageManager->getPackage('fucodo.mermaid');
    }

    public function getPuppeteerConfigPath(): string
    {
        return FLOW_PATH_DATA . 'Temporary/Mermaid/puppeteer-config.json';
    }

    protected function preparePuppeteerConfig(): void
    {
        Files::createDirectoryRecursively(dirname($this->getPuppeteerConfigPath()));
        $config = json_encode($this->settings, JSON_THROW_ON_ERROR);

        $this->logger->debug('Mermaid puppeteer config', ['config' => $config]);

        if (is_file($this->getPuppeteerConfigPath()) && (file_get_contents($this->getPuppeteerConfigPath()) === $config)) {
            return;
        }
        file_put_contents($this->getPuppeteerConfigPath(), $config);
    }

    public function renderMermaidGraph(string $mermaidGraph): string
    {

        $file = tempnam(FLOW_PATH_DATA . 'Temporary', 'mermaid-input');
        file_put_contents($file, $mermaidGraph);
        $buffer = $this->renderMermaidFile($file);
        unlink($file);
        return $buffer;
    }

    public function renderMermaidFile(string $file): string
    {
        $this->preparePuppeteerConfig();
        $outputFile = tempnam(FLOW_PATH_DATA . 'Temporary', 'mermaid-output');
        $outputFileWithExt =  $outputFile . '.svg';
        $command = \sprintf(
            '%s/node_modules/.bin/mmdc --puppeteerConfigFile %s -e svg -o %s < %s',
            escapeshellarg($this->getMermaidPackage()->getPackagePath()),
            escapeshellarg($this->getPuppeteerConfigPath()),
            escapeshellarg($outputFileWithExt),
            escapeshellarg($file)
        );

        $this->logger->debug('Mermaid command', ['command' => $command]);

        $process = Process::fromShellCommandline(
            $command
        );
        $process->start();
        $process->wait();
        $buffer = file_get_contents($outputFile . '.svg');
        unlink($outputFile);
        unlink($outputFileWithExt);
        return $buffer;
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
