<?php
namespace fucodo\mermaid\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use fucodo\mermaid\Service\MermaidService;

/**
 * @Flow\Scope("singleton")
 */
class MermaidCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var MermaidService
     */
    protected $mermaidService;

    public function renderCommand(string $file)
    {
        $this->output($this->mermaidService->renderMermaidFile($file));
    }

    public function installCommand()
    {
        $this->mermaidService->install();
    }
}
