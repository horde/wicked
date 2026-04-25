<?php

declare(strict_types=1);

namespace Horde\Wicked\Test\Unit\Service;

use Horde\GithubApiClient\Auth\GitHubAppAuthenticationService;
use Horde\Wicked\Service\GitHubFileFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(GitHubFileFetcher::class)]
class GitHubFileFetcherTest extends TestCase
{
    public function testFetchReturnsContentOnSuccess(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('# Hello');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $auth = $this->createMock(GitHubAppAuthenticationService::class);
        $auth->method('getInstallationToken')->willReturn('ghs_test_token');

        $fetcher = new GitHubFileFetcher($client, $requestFactory, $auth);
        $result = $fetcher->fetch('owner/repo', 'doc/README.md', 'main');

        $this->assertSame('# Hello', $result);
    }

    public function testFetchReturnsNullOnNon200(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $auth = $this->createMock(GitHubAppAuthenticationService::class);
        $auth->method('getInstallationToken')->willReturn('ghs_test_token');

        $fetcher = new GitHubFileFetcher($client, $requestFactory, $auth);
        $result = $fetcher->fetch('owner/repo', 'missing.md', 'main');

        $this->assertNull($result);
    }
}
