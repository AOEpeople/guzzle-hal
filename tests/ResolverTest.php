<?php
namespace Aoe\Hateoas;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class ResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldDoNothingOnInvalidJson()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = new Response(200, [], 'this is not a valid json');

        $resolver = new Resolver($client);
        $this->assertSame($response, $resolver->resolve($response));
    }

    /**
     * @see http://stateless.co/hal_specification.html
     *
     * @test
     *
     * @return Response
     */
    public function shouldReturnResponse()
    {
        $response = new Response(
            200,
            ['X-Custom' => '1'],
            file_get_contents(__DIR__ . '/fixture/company.json'),
            '1.2',
            'everything went fine'
        );

        $resolver = new Resolver($this->client());
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
    public function shouldKeepOriginalProperties(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('AOE GmbH', $actual->name);
        $this->assertSame('www.aoe.com', $actual->homepage);
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldHaveEmbeddedObject(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('Kirchgasse 6', $actual->_embedded->address->street);
        $this->assertSame('65185', $actual->_embedded->address->zip);
        $this->assertSame('Wiesbaden', $actual->_embedded->address->city);
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnResponse
     * @test
     */
    public function shouldHaveEmbeddedArray(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('Kevin Schu', $actual->_embedded->employees[0]->name);
        $this->assertSame('Bilal Arslan', $actual->_embedded->employees[1]->name);
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
        $this->assertSame('/company/address.json', $actual->_links->address->href);
        $this->assertSame('/company/employees.json', $actual->_links->employees->href);
        $this->assertSame('/company/customers.json', $actual->_links->customers->href);
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
     * @test
     *
     * @return Response
     */
    public function shouldPassOptions()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())->method('request')->with(
            'GET',
            '/company/address.json',
            ['query' => ['foo' => 'bar']]
        )->will($this->returnValue(
            $this->response(file_get_contents(__DIR__ . '/fixture/company/address.json'))
        ));
        $response = new Response(
            200,
            ['X-Custom' => '1'],
            file_get_contents(__DIR__ . '/fixture/company.json'),
            '1.2',
            'everything went fine'
        );
        $resolver = new Resolver($client, ['links' => ['address' => ['query' => ['foo' => 'bar']]]]);
        return $resolver->resolve($response);
    }

    /**
     * @param Response $response
     *
     * @depends shouldPassOptions
     * @test
     */
    public function shouldOnlyResolveLinksSetInOptions(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertTrue(isset($actual->_embedded->address));
        $this->assertFalse(isset($actual->_embedded->employees));
        $this->assertFalse(isset($actual->_embedded->customers));
    }

    /**
     * @see http://stateless.co/hal_specification.html
     *
     * @test
     *
     * @return Response
     */
    public function shouldReturnArrayResponse()
    {
        $response = new Response(
            200,
            ['X-Custom' => '1'],
            file_get_contents(__DIR__ . '/fixture/companies.json'),
            '1.2',
            'everything went fine'
        );

        $resolver = new Resolver($this->client(1));
        $resolvedResponse = $resolver->resolve($response);
        $this->assertInstanceOf(Response::class, $resolvedResponse);
        return $resolvedResponse;
    }

    /**
     * @param Response $response
     *
     * @depends shouldReturnArrayResponse
     * @test
     */
    public function shouldResolveArrayResponse(Response $response)
    {
        $actual = json_decode($response->getBody());
        $this->assertSame('AOE GmbH', $actual[0]->_embedded->company->name);
    }

    /**
     * @test
     *
     * @return Response
     */
    public function shouldRecursiveWithoutEndlessLoop()
    {
        $response = new Response(
            200,
            ['X-Custom' => '1'],
            file_get_contents(__DIR__ . '/fixture/company.json'),
            '1.2',
            'everything went fine'
        );

        $resolver = new Resolver($this->client(3), ['recursive' => true]);
        $resolvedResponse = $resolver->resolve($response);
        $this->assertInstanceOf(Response::class, $resolvedResponse);
        return $resolvedResponse;
    }

    /**
     * @param string $body
     * @param int $code
     * @param array $headers
     * @return Response
     */
    private function response($body, $code = 200, array $headers = [])
    {
        return new Response($code, $headers, $body);
    }

    /**
     * @param int $calls
     * @return Client|\PHPUnit_Framework_MockObject_MockObject
     */
    public function client($calls = 3)
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->exactly($calls))->method('request')->will($this->returnCallback(
            function ($method, $uri = null, array $options = []) {
                $json = file_get_contents(__DIR__ . '/fixture/' . $uri);
                return $this->response($json);
            }
        ));
        return $client;
    }
}
