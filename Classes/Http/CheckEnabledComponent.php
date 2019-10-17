<?php
namespace Flowpack\Fusion\Tracing\Http;

use Neos\Flow\Annotations as Flow;
use Flowpack\Fusion\Tracing\Aspect\RuntimeTracing;

class CheckEnabledComponent implements \Neos\Flow\Http\Component\ComponentInterface {

  /**
   * @Flow\Inject
   * @var RuntimeTracing
   */
  protected $runtimeTracing;

  public function handle(\Neos\Flow\Http\Component\ComponentContext $componentContext)
  {
    $request = $componentContext->getHttpRequest();
    $traceNameHeader = $request->getHeader("X-Fusion-Tracing");
    if (is_array($traceNameHeader) ? $traceNameHeader !== [] : $traceNameHeader !== null) {
      $this->runtimeTracing->enable(is_array($traceNameHeader) ? $traceNameHeader[0] : $traceNameHeader);
    }
  }
}
