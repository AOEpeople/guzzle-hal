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
     * @var array
     */
    private $requestedLinks = [];

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
     * @param array $resolved
     * @return Response
     */
    public function resolve(ResponseInterface $response, array $resolved = [])
    {
        $res = json_decode($response->getBody());
        if (null === $res) {
            return $response;
        }

        if (is_array($res)) {
            foreach ($res as $resource) {
                $this->resolveResource($resource, $resolved);
            }
        } else {
            $this->resolveResource($res, $resolved);
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
     * @param array $config
     */
    public function addConfig(array $config)
    {
        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }
    }

    /**
     * @param $resource
     * @param array $resolved
     */
    private function resolveResource($resource, array $resolved = [])
    {
        foreach ($resource as $rel => $links) {
            if ($rel === 'links' || $rel === '_links') {
                foreach ($links as $target => $link) {
                    if (in_array($link->href, $this->requestedLinks)) {
                        continue;
                    }
                    $this->requestedLinks[] = $link->href;

                    if (!$this->isTargetResolvable($target)) {
                        continue;
                    }
                    if (!isset($resource->_embedded)) {
                        $resource->_embedded = new \stdClass();
                    }
                    if (isset($resource->_embedded->$target)) {
                        continue;
                    }
                    if ($target !== 'self') {
                        if (is_array($link)) {
                            foreach ($link as $linkItem) {
                                $res = $this->resolveLink($resource, $resolved, $target, $linkItem, $links);
                                $resource->_embedded->{$target}[] = $res;
                            }
                        } else {
                            $res = $this->resolveLink($resource, $resolved, $target, $link, $links);
                            $resource->_embedded->$target = $res;
                        }
                    }
                }
            }
            if ($rel === 'embedded' || $rel === '_embedded') {
                foreach ($links as $target => $embedded) {
                    if (is_array($embedded)) {
                        foreach ($embedded as $subEmbed) {
                            $this->resolveResource($subEmbed, $resolved);
                        }
                    } else {
                        $this->resolveResource($embedded, $resolved);
                    }
                }
            }
        }
    }

    /**
     * @param $resource
     * @param array $resolved
     * @param $target
     * @param $link
     * @param $links
     * @return object|array
     */
    private function resolveLink($resource, array $resolved, $target, $link, $links)
    {
        if (!isset($link->href)) {
            return null;
        }
        $tmp = $this->request($target, $link);

        if ($this->isRecursiveEnabled()) {
            /** @todo support recursion (be careful of circular dependencies) */
            if (!$this->isAlreadyResolved($link->href, $resolved)) {
                $resolved = array_merge($resolved, (array)$links);
                $tmp = $this->resolve($tmp, $resolved);
            }
        }
        return json_decode($tmp->getBody());
    }

    /**
     * @param string $href
     * @param array $resolved
     * @return bool
     */
    private function isAlreadyResolved($href, array $resolved)
    {
        foreach ($resolved as $resolve) {
            if ($resolve->href === $href) {
                return true;
            }
        }
        return false;
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
        if (!isset($this->config['links']) || !$this->isTargetFilterEnabled()) {
            return true;
        }
        return is_array($this->config['links']) && isset($this->config['links'][$target]);
    }

    /**
     * @return bool
     */
    private function isTargetFilterEnabled()
    {
        if (!isset($this->config['filter'])) {
            return true;
        }
        return (boolean)$this->config['filter'];
    }

    /**
     * @return bool
     */
    private function isRecursiveEnabled()
    {
        if (!isset($this->config['recursive'])) {
            return false;
        }
        return (boolean)$this->config['recursive'];
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