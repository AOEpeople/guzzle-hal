<?php
namespace Aoe\Hateoas;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class ResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @see http://stateless.co/hal_specification.html
     *
     * @test
     */
    public function shouldReturnResponse()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $client->expects($this->any())->method('request')->will($this->returnValueMap([
            ['GET', '/foo/bar', [], $this->response(['title' => '/foo/bar'])],
            ['GET', '/foo/baz', [], $this->response(['title' => '/foo/baz'])]
        ]));

        $response = new Response(200, ['X-Custom' => '1'], json_encode([
            'title' => 'test',
            '_links' => [
                'bar' => [
                    'href' => '/foo/bar',
                    'method' => 'GET'
                ]
            ]
        ]), '1.2', 'everything went fine');

        $resolver = new Resolver($client);
        $resolvedResponse = $resolver->resolve($response);
        $this->assertInstanceOf(Response::class, $resolvedResponse);
        return $resolvedResponse;
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepOriginalProperty(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('test', $actual->title);
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldHaveEmbeddedBar(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('/foo/bar', $actual->_embedded->bar->title);
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepLinks(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('/foo/bar', $actual->_links->bar->href);
        $this->assertSame('GET', $actual->_links->bar->method);
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepOriginalResponseHeaders(Response $response)
    {
        $this->assertSame(['X-Custom' => ['1']], $response->getHeaders());
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepOriginalResponseStatusCode(Response $response)
    {
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepOriginalResponseProtocolVersion(Response $response)
    {
        $this->assertSame('1.2', $response->getProtocolVersion());
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldKeepOriginalResponseReasonPhrase(Response $response)
    {
        $this->assertSame('everything went fine', $response->getReasonPhrase());
    }

    /**
     * @param string $body
     * @param int $code
     * @param array $headers
     * @return Response
     */
    private function response($body, $code = 200, array $headers = [])
    {
        return new Response(200, $headers, json_encode($body));
    }
}
