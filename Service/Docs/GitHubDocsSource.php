<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Docs;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Error\ErrorReporter;

class GitHubDocsSource
{
    private const TREES_URL = 'https://api.github.com/repos/%s/git/trees/%s?recursive=1';
    private const RAW_URL = 'https://raw.githubusercontent.com/%s/%s/%s';
    private const USER_AGENT = 'MagoAssistant-Mago';

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger,
        private readonly ErrorReporter $errorReporter
    ) {
    }

    private function isValidSource(string $repo, string $ref): bool
    {
        return (bool)preg_match('#^[\w.-]+/[\w.-]+$#', $repo)
            && $ref !== ''
            && (bool)preg_match('#^[\w.\-/]+$#', $ref);
    }

    /**
     * Returns ['sha' => <tree sha>, 'paths' => <help/**.md paths>] or null on failure.
     *
     * @return array{sha: string, paths: string[]}|null
     */
    public function fetchTree(string $repo, string $ref): ?array
    {
        if (!$this->isValidSource($repo, $ref)) {
            $this->errorLogger->addLog('DocsSource', 'Invalid repo/ref: ' . $repo . '@' . $ref);
            return null;
        }

        $url = sprintf(self::TREES_URL, $repo, rawurlencode($ref));
        $body = $this->get($url, ['Accept' => 'application/vnd.github+json']);
        if ($body === null) {
            return null;
        }

        try {
            $data = $this->json->unserialize($body);
        } catch (\Throwable $e) {
            $this->errorReporter->log('DocsSource: invalid tree JSON', $e);
            return null;
        }

        if (!empty($data['truncated'])) {
            $this->errorLogger->addLog('DocsSource', 'Tree truncated for ' . $repo . '@' . $ref . ' — aborting sync');
            return null;
        }

        if (empty($data['tree']) || !is_array($data['tree'])) {
            return null;
        }

        $paths = [];
        foreach ($data['tree'] as $node) {
            if (($node['type'] ?? '') !== 'blob') {
                continue;
            }
            $path = (string)($node['path'] ?? '');
            if (str_starts_with($path, 'help/') && str_ends_with($path, '.md')) {
                $paths[] = $path;
            }
        }

        return ['sha' => (string)($data['sha'] ?? ''), 'paths' => $paths];
    }

    public function fetchRaw(string $repo, string $ref, string $path): ?string
    {
        if (!$this->isValidSource($repo, $ref)) {
            return null;
        }
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = sprintf(self::RAW_URL, $repo, rawurlencode($ref), $encodedPath);
        return $this->get($url, ['Accept' => 'text/plain']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(string $url, array $headers = []): ?string
    {
        try {
            $curl = $this->curlFactory->create();
            $curl->setOptions([
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $curl->addHeader('User-Agent', self::USER_AGENT);
            foreach ($headers as $name => $value) {
                $curl->addHeader($name, $value);
            }
            $curl->get($url);

            $status = $curl->getStatus();
            if ($status !== 200) {
                $this->errorLogger->addLog('DocsSource', 'HTTP ' . $status . ' for ' . $url);
                return null;
            }

            return $curl->getBody();
        } catch (\Throwable $e) {
            $this->errorReporter->log('DocsSource: ' . $url, $e);
            return null;
        }
    }
}
