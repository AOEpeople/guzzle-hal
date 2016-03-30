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
    private $options = [];

    /**
     * @param Client $client
     * @param array $options
     */
    public function __construct(Client $client, array $options = [])
    {
        $this->client = $client;
        $this->options = $options;
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

        foreach ($res as $rel => $links) {
            if ($rel === 'links' || $rel === '_links') {
                foreach ($links as $target => $link) {
                    if ($target !== 'self' && isset($link->href)) {
                        if (!$this->isTargetResolvable($target)) {
                            continue;
                        }
                        if (!isset($res->_embedded)) {
                            $res->_embedded = new \stdClass();
                        }
                        if (isset($res->_embedded->$target)) {
                            continue;
                        }
                        $tmp = $this->request($target, $link);
                        /** @todo support recursion (be careful of circular dependencies) */
                        //$tmp = $this->resolve($tmp);
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
        if (!isset($this->options['links'])) {
            return true;
        }
        return is_array($this->options['links']) && isset($this->options['links'][$target]);
    }

    /**
     * @param string $target
     * @return bool
     */
    private function getTargetClientRequestOptions($target)
    {
        if (isset($this->options['links']) && isset($this->options['links'][$target])) {
            return $this->options['links'][$target];
        }
        return [];
    }
}
