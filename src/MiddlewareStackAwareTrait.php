<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-12-08
 * Time: 11:44
 */

namespace Inhere\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Trait MiddlewareChainAwareTrait
 * @package Inhere\Middleware
 *
 * ```php
 * class MyApp implements RequestHandlerInterface {
 *  use MiddlewareStackAwareTrait;
 *
 *  public function handleRequest(ServerRequestInterface $request): ResponseInterface
 *  {
 *      return new Response;
 *  }
 * }
 * ```
 */
trait MiddlewareStackAwareTrait
{
    /** @var \SplStack */
    private $stack;

    /** @var bool */
    private $locked = false;

    /** @var CallableResolverInterface */
    private $callableResolver;

    /**
     * @param callable[] ...$middleware
     * @return $this
     * @throws \RuntimeException
     */
    public function use(...$middleware): self
    {
        return $this->add(...$middleware);
    }

    /**
     * Add middleware
     * This method prepends new middleware to the application middleware stack.
     * @param array ...$middleware Any callable that accepts two arguments:
     *                           1. A Request object
     *                           2. A Handler object
     * @return $this
     * @throws \RuntimeException
     */
    public function add(...$middleware): self
    {
        if ($this->locked) {
            throw new \RuntimeException('Middleware can’t be added once the stack is dequeuing');
        }

        if (null === $this->stack) {
            $this->prepareStack();
        }

        foreach ($middleware as $item) {
            $this->stack[] = $item;
        }

        return $this;
    }

    /**
     * 调用此方法开始执行所有中间件
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function callStack(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->stack) {
            $this->prepareStack();
        }

        $this->locked = true;

        // 保证每次的调用栈是完整且从0开始
        $that = clone $this;
        $response = $that->handle($request);

        $this->locked = false;

        return $response;
    }

    /**
     * 不要在外部直接调用，内部调用的
     * @internal
     * {@inheritDoc}
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // $handler = clone $this;

        // IMPORTANT: if no middleware. this is end point of the chain.
        if ($this->stack->isEmpty()) {
            return $this->handleRequest($request);
        }

        $middleware = $this->stack->shift();
        // $middleware = current($handler->stack);
        // next($handler->stack);

        if ($middleware instanceof MiddlewareInterface) {
            /** @var RequestHandlerInterface $this */
            $response = $middleware->process($request, $this);
        } elseif (\is_callable($middleware)) {
            $response = $middleware($request, $this);
        } elseif ($this->callableResolver) {
            $middleware = $this->callableResolver->resolve($middleware);
            $response = $middleware($request, $this);
        } else {
            throw new \InvalidArgumentException('The middleware is not a callable.');
        }

        if (!$response instanceof ResponseInterface) {
            throw new \UnexpectedValueException(
                'Middleware must return object and instance of \Psr\Http\Message\ResponseInterface'
            );
        }

        return $response;
    }

    /**
     * 在这里处理请求返回响应对象
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    abstract public function handleRequest(ServerRequestInterface $request): ResponseInterface;

    /**
     * @param callable|null $kernel
     * @throws \RuntimeException
     */
    protected function prepareStack(callable $kernel = null)
    {
        if (null !== $this->stack) {
            throw new \RuntimeException('MiddlewareStack can only be seeded once.');
        }

        $this->stack = new \SplStack;
        $this->stack->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP);

        if ($kernel) {
            $this->stack[] = $kernel;
        }
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @param CallableResolverInterface $callableResolver
     */
    public function setCallableResolver(CallableResolverInterface $callableResolver)
    {
        $this->callableResolver = $callableResolver;
    }

    /**
     * @return CallableResolverInterface
     */
    public function getCallableResolver(): CallableResolverInterface
    {
        return $this->callableResolver;
    }
}
