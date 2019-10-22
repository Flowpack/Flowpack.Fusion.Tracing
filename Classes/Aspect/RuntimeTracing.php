<?php
namespace Flowpack\Fusion\Tracing\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\Files;

/**
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class RuntimeTracing
{

    /**
     * @var resource
     */
    protected $tracefile = null;

    /**
     * @var float
     */
    protected $baseTs = null;

    /**
     * @var array
     */
    protected $stackFrames = [];

    /**
     * @var array
     */
    protected $stackFramesByPath = [];

    /**
     * @var int
     */
    protected $sfCnt = 1;

    /**
     * @var int
     */
    protected $evtCnt = 0;

    /**
     * @var bool
     */
    protected $enabled = false;

    /**
     * @var string
     */
    protected $traceName;

    /**
     * @Flow\Before("method(Neos\Fusion\Core\Cache\RuntimeContentCache->enter())")
     */
    public function onEnter(JoinPointInterface $joinPoint)
    {
        if (!$this->enabled) {
            return;
        }

        $fusionPath = $joinPoint->getMethodArgument('fusionPath');
        $this->writeStart($fusionPath);
    }

    /**
     * @Flow\After("method(Neos\Fusion\Core\Cache\RuntimeContentCache->leave())")
     */
    public function onLeave(JoinPointInterface $joinPoint)
    {
        if (!$this->enabled) {
            return;
        }

        $this->writeEnd();
    }

    public function writeStart(
        string $fusionPath,
        bool $computeStackFrame = true,
        string $cat = 'evaluate',
        array $args = []
    ): void {
        $name = $fusionPath;

        $lastSlash = strrpos($fusionPath, '/');
        if ($lastSlash !== false) {
            $name = substr($fusionPath, $lastSlash + 1);
        }

        $sf = null;
        if ($computeStackFrame) {
            $sf = $this->getStackFrame($fusionPath);
        }

        $evt = [
            'name' => $name,
            'cat' => $cat,
            'ph' => 'B',
            'ts' => $this->ts(),
            'pid' => 1,
            'tid' => 1,
            'sf' => $sf,
            'args' => $args
        ];

        $this->appendEvent($evt);
    }

    public function writeEnd(array $args = []): void
    {
        $evt = [
            'ph' => 'E',
            'ts' => $this->ts(),
            'pid' => 1,
            'tid' => 1,
            'args' => $args
        ];
        $this->appendEvent($evt);
    }

    private function ts(): int
    {
        if ($this->baseTs === null) {
            $this->baseTs = microtime(true) * 1000 * 1000;
            return 0;
        }

        return (microtime(true) * 1000 * 1000) - $this->baseTs;
    }

    public function appendEvent(array $evt): void
    {
        if ($this->tracefile === null) {
            $filename = FLOW_PATH_DATA . 'Logs/Traces/' . $this->traceName . '-' . time() . '.trace';

            $this->tracefile = fopen($filename, 'w');
            fwrite($this->tracefile, "{\"traceEvents\":[\n");
        }

        fwrite($this->tracefile, ($this->evtCnt > 0 ? ',' : '') . json_encode($evt) . "\n");
        $this->evtCnt++;
    }

    public function shutdownObject()
    {
        if ($this->tracefile != null) {
            fwrite($this->tracefile,
                "],\n\"stackFrames\":" . json_encode($this->stackFrames, JSON_PRETTY_PRINT) . "}\n");
        }
    }

    public function getStackFrame(string $fusionPath): int
    {
        $pathParts = explode('/', $fusionPath);

        // Find SF of longest existing prefix

        $baseSf = null;
        $n = count($pathParts);
        for ($i = $n; $i > 0; $i--) {
            $pathPrefix = implode('/', array_slice($pathParts, 0, $i));
            if (isset($this->stackFramesByPath[$pathPrefix])) {
                $baseSf = $this->stackFramesByPath[$pathPrefix];
                break;
            }
        }

        // Build child SFs

        for ($j = $i; $j < $n; $j++) {
            $pathPrefix = implode('/', array_slice($pathParts, 0, $j + 1));
            $frame = [
                'name' => $pathParts[$j]
            ];
            if ($baseSf !== null) {
                $frame['parent'] = (string)$baseSf;
            }
            $baseSf = $this->sfCnt++;
            $this->stackFrames[(string)$baseSf] = $frame;
            $this->stackFramesByPath[$pathPrefix] = $baseSf;
        }

        return $baseSf;
    }

    public function getStackFrames(): array
    {
        return $this->stackFrames;
    }

    public function enable(string $traceName): void
    {
        $this->enabled = true;
        $this->traceName = $traceName;

        $this->createTracesDirectory();
    }

    private function createTracesDirectory()
    {
        Files::createDirectoryRecursively(FLOW_PATH_DATA . 'Logs/Traces');
    }

}
