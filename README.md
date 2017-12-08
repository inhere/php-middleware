# Middleware

the psr-15 HTTP Middleware implement.

ref https://github.com/php-fig/fig-standards/blob/master/proposed/http-handlers/middleware.md


## 项目地址

- **github** https://github.com/inhere/php-middleware.git
- **git@osc** https://gitee.com/inhere/php-middleware.git

## 安装

- composer 命令

```php
composer require inhere/middleware
```

- composer.json

```json
{
    "require": {
        "inhere/middleware": "dev-master"
    }
}
```

- 直接拉取

```bash
git clone https://github.com/inhere/php-middleware.git // github
git clone https://gitee.com/inhere/php-middleware.git // git@osc
```

## 使用

### 一个基于中间件的应用

- 引入相关类

路由器，psr 7的http message 库

```php
use Inhere\Http\HttpFactory;
use Inhere\Library\Helpers\Http;
use Inhere\Middleware\MiddlewareStackAwareTrait;
use Inhere\Middleware\RequestHandlerInterface;
use Inhere\Route\ORouter;
use Inhere\Route\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
```

- 创建一个应用类

```php

$app = new class implements RequestHandlerInterface {
    use MiddlewareStackAwareTrait;

    /**
     * @var ORouter
     */
    private $router;

    public function run(ServerRequestInterface $request)
    {
        $response = $this->callStack($request);

        Http::respond($response);
    }

    /**
     * 在这里处理请求返回响应对象
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Throwable
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uriPath = $request->getUri()->getPath();
        $response = HttpFactory::createResponse();

        try {
            // $this->router->match($uriPath, $method);
            $result = $this->router->dispatch(null, $uriPath, $method);
            $response->getBody()->write($result);
        } catch (Throwable $e) {
            $response->getBody()->write($e->getTraceAsString());
        }

        return $response;
    }

    /**
     * @return RouterInterface
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * @param RouterInterface $router
     */
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }
};
```

- 创建路由器并注册路由

```php
$router = new ORouter();

/**
 * add routes
 */
$router->get('/', function () {
   echo 'hello, world';
});

$router->get('/hello/{name}', function ($args) {
    echo "hello, {$args['name']}";
});

```

- 添加中间件

```php
/**
 * add middleware
 */
$app->use(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
    echo 'before handle0 > ';
    $res = $handler->handle($request);
    echo ' > after handle0';

    return $res;
});

$app->use(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
    echo 'before handle1 > ';
    $res = $handler->handle($request);
    echo ' > after handle1';

    return $res;
});
```

- 准备运行

```php
/**
 * run
 */
$req = HttpFactory::createServerRequestFromArray($_SERVER);

$app->setRouter($router);
$app->run($req);
```

- 运行server

```bash
$ php -S 127.0.0.1:8009 examples/app.php
```

访问： http://127.0.0.1:8009

visit: `/hello/tom` 
response:

```text
before handle0 > before handle1 > hello, tom > after handle1 > after handle0
```

## ref project

- https://github.com/mindplay-dk/middleman
- https://github.com/middlewares/utils
- https://github.com/middlewares/psr15-middlewares

## License 

MIT
