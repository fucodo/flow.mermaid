<?php

namespace fucodo\mermaid\ViewHelpers;

use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use fucodo\mermaid\Service\MermaidService;

class MermaidViewHelper extends AbstractViewHelper
{
    /**
     * Specifies whether the escaping interceptors should be disabled or enabled for the result of renderChildren() calls within this ViewHelper
     * @see isChildrenEscapingEnabled()
     *
     * Note: If this is NULL the value of $this->escapingInterceptorEnabled is considered for backwards compatibility
     *
     * @var boolean
     * @api
     */
    protected $escapeChildren = false;

    /**
     * Specifies whether the escaping interceptors should be disabled or enabled for the render-result of this ViewHelper
     * @see isOutputEscapingEnabled()
     *
     * @var boolean
     * @api
     */
    protected $escapeOutput = false;

    /**
     * @Flow\Inject
     * @var MermaidService
     */
    protected $mermaidService;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('useBase64', 'bool', 'use base64 or not', false, false);
        $this->registerArgument('format', 'string', 'use base64 or not', false, 'svg');
    }

    public function render(): string
    {
        $format = $this->arguments['format'];
        if ($format === 'svg') {
            if ($this->arguments['useBase64']) {
                return '<img width="100%" src="data:image/xml+svg;base64,' . base64_encode($this->mermaidService->renderMermaidGraph($this->renderChildren(), $format)) . '" />';
            }
            return $this->mermaidService->renderMermaidGraph($this->renderChildren(), $format);
        }

        return '<img width="100%" src="data:image/' . $format . ',' . base64_encode($this->mermaidService->renderMermaidGraph($this->renderChildren(), $format)) . '" />';
    }
}
