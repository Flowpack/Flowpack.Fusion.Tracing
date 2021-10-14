<?php
namespace Flowpack\Fusion\Tracing\Http\Middleware;

use Flowpack\Fusion\Tracing\Aspect\RuntimeTracing;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Look for a "X-Fusion-Tracing" request header and start tracing
 */
class CheckEnabledMiddleware implements MiddlewareInterface
{
    /**
     * @Flow\Inject
     * @var RuntimeTracing
     */
    protected $runtimeTracing;

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $traceNameHeader = $request->getHeaderLine('X-Fusion-Tracing');
        if (!empty($traceNameHeader)) {
            $this->runtimeTracing->enable($traceNameHeader);
        }
        return $handler->handle($request);
    }
}
