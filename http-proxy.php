<?php

class HttpProxyServer
{
    static $frontendCloseCount = 0;
    static $backendCloseCount = 0;
    static $frontends = array();
    static $backends = array();
    static $serv;

    /**
     * @param $fd
     * @return swoole_http_client
     */
    static function getClient($fd)
    {
        if (!isset(HttpProxyServer::$frontends[$fd]))
        {
            $client = new swoole_http_client('127.0.0.1', 80);
            $client->set(array('keep_alive' => 0));
            HttpProxyServer::$frontends[$fd] = $client;
            $client->on('connect', function ($cli) use ($fd)
            {
                HttpProxyServer::$backends[$cli->sock] = $fd;
            });
            $client->on('close', function ($cli) use ($fd)
            {
                self::$backendCloseCount++;
                unset(HttpProxyServer::$backends[$cli->sock]);
                unset(HttpProxyServer::$frontends[$fd]);
                echo self::$backendCloseCount . "\tbackend[{$cli->sock}]#[{$fd}] close\n";
            });
        }
        return HttpProxyServer::$frontends[$fd];
    }
}
$host = isset($_ENV['PROXY_HOST']) ? $_ENV['PROXY_HOST'] : '127.0.0.1';
$port = isset($_ENV['PROXY_PORT']) ? $_ENV['PROXY_PORT'] : 9510;

$serv = new swoole_http_server($host , $port, SWOOLE_BASE);
//$serv = new swoole_http_server('127.0.0.1', 9510, SWOOLE_PROCESS);
//$serv->set(array('worker_num' => 8));

$serv->on('Close', function ($serv, $fd, $reactorId)
{
    HttpProxyServer::$frontendCloseCount++;
    echo HttpProxyServer::$frontendCloseCount . "\tfrontend[{$fd}] close\n";
    //清理掉后端连接
    if (isset(HttpProxyServer::$frontends[$fd]))
    {
        $backend_socket = HttpProxyServer::$frontends[$fd];
        $backend_socket->close();
        unset(HttpProxyServer::$backends[$backend_socket->sock]);
        unset(HttpProxyServer::$frontends[$fd]);
    }
});

$serv->on('Request', function (swoole_http_request $req, swoole_http_response $resp)
{
    if ($req->server['request_method'] == 'GET')
    {
        $client = HttpProxyServer::getClient($req->fd);
        $client->get($req->server['request_uri'], function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
        });
    }
    elseif ($req->server['request_method'] == 'POST')
    {
        $client = HttpProxyServer::getClient($req->fd);
        $postData = $req->rawContent();
        $client->post($req->server['request_uri'], $postData, function ($cli) use ($req, $resp)
        {
            $resp->end($cli->body);
        });
    }
    else
    {
        $resp->status(405);
        $resp->end("method not allow.");
    }
});

HttpProxyServer::$serv = $serv;
$serv->start();
