<?php
namespace Aoe\Hateoas;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class Resolver
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @param Client $client
     * @param array $config
     */
    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @param ResponseInterface $response
     * @return Response
     */
    public function resolve(ResponseInterface $response)
    {
        $res = json_decode($response->getBody());
        if (null === $res) {
            return $response;
        }

        if (is_array($res)) {
            foreach ($res as $resource) {
                $this->resolveResource($resource);
            }
        } else {
            $this->resolveResource($res);
        }

        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            json_encode($res),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }

    /**
     * @param $resource
     */
    private function resolveResource($resource)
    {
        foreach ($resource as $rel => $links) {
            if ($rel === 'links' || $rel === '_links') {
                foreach ($links as $target => $link) {
                    if ($target !== 'self' && isset($link->href)) {
                        if (!$this->isTargetResolvable($target)) {
                            continue;
                        }
                        if (!isset($resource->_embedded)) {
                            $resource->_embedded = new \stdClass();
                        }
                        if (isset($resource->_embedded->$target)) {
                            continue;
                        }
                        $tmp = $this->request($target, $link);
                        /** @todo support recursion (be careful of circular dependencies) */
                        //$tmp = $this->resolve($tmp);
                        $resource->_embedded->$target = json_decode($tmp->getBody());
                    }
                }
            }
        }
    }

    /**
     * @param string $target
     * @param \stdClass $link
     * @return ResponseInterface
     */
    private function request($target, $link)
    {
        return $this->client->request(
            'GET',
            $link->href,
            $this->getTargetClientRequestOptions($target)
        );
    }

    /**
     * @param string $target
     * @return bool
     */
    private function isTargetResolvable($target)
    {
        if (!isset($this->config['links'])) {
            return true;
        }
        return is_array($this->config['links']) && isset($this->config['links'][$target]);
    }

    /**
     * @param string $target
     * @return bool
     */
    private function getTargetClientRequestOptions($target)
    {
        if (isset($this->config['links']) && isset($this->config['links'][$target])) {
            return $this->config['links'][$target];
        }
        return [];
    }
}
