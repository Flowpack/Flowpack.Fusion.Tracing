<?php
namespace Flowpack\Fusion\Tracing\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

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

  protected $baseTs = null;

  protected $stackFrames = [];

  protected $stackFramesByPath = [];

  protected $sfCnt = 1;

  protected $evtCnt = 0;

  /**
   * @var bool
   */
  protected $enabled = true;

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

  private function writeStart(string $fusionPath): void
  {
    $name = $fusionPath;

    $lastSlash = strrpos($fusionPath, '/');
    if ($lastSlash !== false) {
      $name = substr($fusionPath, $lastSlash + 1);
    }

    $sf = $this->getStackFrame($fusionPath);

    $evt = [
      'name' => $name,
      'cat' => 'Fusion',
      'ph' => 'B',
      'ts' => $this->ts(),
      'pid' => 1,
      'tid' => 1,
      'sf' => $sf
    ];

    $this->appendEvent($evt);
  }

  private function writeEnd(): void
  {
    $evt = [
      'ph' => 'E',
      'ts' => $this->ts(),
      'pid' => 1,
      'tid' => 1
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

  private function appendEvent(array $evt): void
  {
    if ($this->tracefile === null) {
      $filename = FLOW_PATH_DATA . 'Logs/Traces/' . time() . '.trace';

      $this->tracefile = fopen($filename, 'w');
      fprintf($this->tracefile, "{\"traceEvents\":[\n");
    }

    fprintf($this->tracefile, ($this->evtCnt > 0 ? ',' : '') . json_encode($evt) . "\n");
    $this->evtCnt++;
  }

  public function shutdownObject()
  {
    if ($this->tracefile != null) {
      fprintf($this->tracefile, "],\n\"stackFrames\":" . json_encode($this->stackFrames, JSON_PRETTY_PRINT) . "}\n");
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

}
