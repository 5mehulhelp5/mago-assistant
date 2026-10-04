<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\System;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use MagoAssistant\Mago\Service\Error\ErrorReporter;

class DetectUrl extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::config';

    private const CANDIDATES = [
        // Mark Shust docker-magento
        'https://app:8443',
        'http://app:8000',
        // Generic nginx
        'https://nginx:443',
        'https://nginx',
        'http://nginx:80',
        'http://nginx',
        // Generic web
        'https://web:443',
        'https://web',
        'http://web:80',
        'http://web',
        // Warden
        'https://nginx:443',
        'http://varnish:80',
    ];

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ErrorReporter $errorReporter
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        // DDEV: check DDEV_PRIMARY_URL env var first
        $ddevUrl = getenv('DDEV_PRIMARY_URL');
        if ($ddevUrl && $this->isReachable($ddevUrl)) {
            return $result->setData([
                'success' => true,
                'url' => rtrim($ddevUrl, '/'),
                'message' => 'Detected via DDEV: ' . $ddevUrl,
            ]);
        }

        // Try common Docker container hostnames
        foreach (self::CANDIDATES as $candidate) {
            if ($this->isReachable($candidate)) {
                return $result->setData([
                    'success' => true,
                    'url' => $candidate,
                    'message' => 'Detected: ' . $candidate,
                ]);
            }
        }

        // Fallback: use store base URL
        try {
            $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
            if ($this->isReachable($baseUrl)) {
                return $result->setData([
                    'success' => true,
                    'url' => $baseUrl,
                    'message' => 'Using store base URL (reachable)',
                ]);
            }
            return $result->setData([
                'success' => true,
                'url' => $baseUrl,
                'message' => 'Using store base URL (not verified)',
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'url' => '',
                'message' => 'Could not detect URL. Reference: ' . $this->errorReporter->log('DetectUrl', $e),
            ]);
        }
    }

    private function isReachable(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);

        // Any HTTP response (even 403/404) means the host is reachable
        return $error === 0 && $httpCode > 0;
    }
}
