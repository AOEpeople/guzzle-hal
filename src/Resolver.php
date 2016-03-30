<?php
namespace Aoe\Hateoas;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class Resolver
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Response $response
     * @return Response
     */
    public function resolve(Response $response)
    {
        $res = json_decode($response->getBody());

        foreach ($res as $rel => $links) {
            if ($rel === 'links' || $rel === '_links') {
                foreach ($links as $target => $link) {
                    if ($target !== 'self' && isset($link->href)) {
                        if (!isset($res->_embedded)) {
                            $res->_embedded = new \stdClass();
                        }
                        $tmp = $this->client->request(
                            $link->method,
                            $link->href
                        );
                        $res->_embedded->$target = json_decode($tmp->getBody());
                    }
                }
            }
        }

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            json_encode($res),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }
}
