<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 */

namespace Horde\Wicked\Service;

use Horde\GithubApiClient\Auth\GitHubAppAuthenticationService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class GitHubFileFetcher
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly GitHubAppAuthenticationService $authService,
    ) {}

    public function fetch(string $repository, string $filePath, string $ref): ?string
    {
        $token = $this->authService->getInstallationToken();
        $url = sprintf(
            'https://api.github.com/repos/%s/contents/%s?ref=%s',
            $repository,
            rawurlencode($filePath),
            rawurlencode($ref),
        );

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/vnd.github.raw+json')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-GitHub-Api-Version', '2022-11-28');

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return (string) $response->getBody();
    }
}
