<?php

namespace App\Controller;


use App\Config;
use App\Controller;
use App\Lib\Buffer;
use React\Dns\Resolver\Factory as DnsFactory;
use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Http\Response;
use React\HttpClient\Factory as ClientFactory;
use React\HttpClient\Response as ClientResponse;

class Provider implements Controller
{
    private $loop;
    private $config;

    public function __construct(LoopInterface $loop, Config $config)
    {
        $this->loop = $loop;
        $this->config = $config;
    }

    public function action(Request $request, Response $response, $parameters)
    {
        $response->writeHead(200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=86400'
        ]);

        $cache_file = $this->config->storage_dir . '/p/provider-' . $parameters['subPath'];

        if (!file_exists($cache_file)) {
            $loop = $this->loop;

            $resolverFactory = new DnsFactory();
            $resolver = $resolverFactory->create('8.8.8.8', $loop);
            $factory = new ClientFactory();
            $client = $factory->create($loop, $resolver);

            $request = $client->request(
                'GET',
                'https://packagist.org/p/provider-' . $parameters['subPath']
            );
            $request->on('response', function (ClientResponse $resp) use ($response, $loop, $cache_file) {
                $buf = new Buffer();
                $resp->on('data', function ($chunk) use ($response, &$buf) {
                    $response->write($chunk);
                    $buf->write($chunk);
                });
                $resp->on('end', function () use ($response, &$buf, $cache_file) {
                    if (!is_dir(dirname($cache_file)))
                        mkdir(dirname($cache_file), 0777, true);
                    file_put_contents($cache_file, $buf->read(), LOCK_EX);
                    $buf->clear();
                    $response->end();
                });
            });
            $request->end();
        } else {
            $response->end(file_get_contents($cache_file));
        }
    }
}

