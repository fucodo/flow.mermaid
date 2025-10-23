# fucodo.mermaid — Mermaid rendering for Neos Flow

This Flow package provides a small service and CLI commands to render Mermaid diagrams (https://mermaid.js.org/) to SVG using mermaid-cli (mmdc) with a Puppeteer configuration that can be controlled from Flow Settings.

## Features

- Render Mermaid definitions from files to SVG
- Programmatic rendering service for inline Mermaid strings
- CLI helper to bootstrap node dependencies and the headless browser
- Stores a Puppeteer config JSON under Data/Temporary/Mermaid/ for reproducible runs

## Prerequisites
- Node.js and npm installed on the host where Flow runs
- Network access to install npm packages on first setup
- A Chromium/Chrome executable suitable for headless use
  - The package can install chrome-headless-shell via Puppeteer (recommended for portability)

## Installation

1) Ensure the package is available in your Flow distribution (DistributionPackages/fucodo.mermaid) and that composer autoload is up to date.
2) Run the install command to fetch node dependencies and a headless browser:
   `./flow mermaid:install`
   This performs, inside the package directory:
   - `npm install`
   - `npx puppeteer browsers install chrome-headless-shell`

## Configuration

The package reads its Puppeteer configuration from Flow settings and writes it to Data/Temporary/Mermaid/puppeteer-config.json before each render.

Default settings (Configuration/Settings.yaml in this package):

```yaml
fucodo:
  mermaid:
    # is written in the puppeteer config file to guide mermaid to the correct location of the browser
    puppeteer-config:
      executablePath: '/usr/bin/chromium'
      args: ['--no-sandbox']
```

Override in your project’s Configuration/Settings.yaml as needed. Common options:
- executablePath: absolute path to the Chromium/Chrome binary or chrome-headless-shell installed by Puppeteer
- args: array of Chromium flags, e.g. ['--no-sandbox', '--disable-gpu']

## Usage

CLI rendering from a Mermaid file to SVG (stdout):
- Render file:
  `./flow mermaid:render /absolute/path/to/diagram.mmd > diagram.svg`

Programmatic rendering in PHP (e.g., in a Flow controller/service):

```php
use fucodo\mermaid\Service\MermaidService;
use Neos\Flow\Annotations as Flow;

class MyService {
    /**
     * @Flow\Inject
     * @var MermaidService
     */
    protected $mermaidService;

    public function buildSvg(): string
    {
        $mermaid = "graph TD; A-->B; A-->C; B-->D; C-->D;";
        return $this->mermaidService->renderMermaidGraph($mermaid);
    }
}
```

## Commands

- `mermaid:install`
  Fetches npm dependencies for mermaid-cli and installs a Chromium variant via Puppeteer that mmdc can drive. Run this after deployment or if dependencies changed.

- mermaid:render <file>
  Reads the Mermaid source from <file> and writes the resulting SVG to stdout.

## How it works

- MermaidService prepares a Puppeteer config JSON at Data/Temporary/Mermaid/puppeteer-config.json using the fucodo.mermaid.puppeteer-config settings.
- It invokes mermaid-cli (mmdc) located in this package’s node_modules, using:
  {PackagePath}/node_modules/.bin/mmdc --puppeteerConfigFile {config} -e svg -o {tmpOutput} < {input}
- Output is an SVG string read from the temporary file and returned/printed.

## Tips and troubleshooting

- If rendering fails with browser launch errors, ensure the configured executablePath exists and is executable, or re-run ./flow mermaid:install to install chrome-headless-shell and then point executablePath to it.
- On Linux containers, you may need --no-sandbox in args. This is the default in the package settings.
- Make sure the Flow process user can write to Data/Temporary/.
- If mmdc is not found, re-run mermaid:install or ensure node_modules exists under the package directory.

## Paths used by the package
- Puppeteer config JSON: Data/Temporary/Mermaid/puppeteer-config.json
- Temp input/output files: Data/Temporary/*

## License

MIT

## Credits

- Uses mermaid-cli (https://github.com/mermaid-js/mermaid-cli)
- Uses Puppeteer for headless Chromium
- Integrates with Neos Flow
