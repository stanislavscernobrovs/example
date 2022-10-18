<?php

declare(strict_types=1);

namespace Akzo\SendCloudSms\Model\Request;

use Akzo\SendCloudSms\Api\ConfigurationValidatorInterface;
use Akzo\SendCloudSms\Logger\SendCloudLogger;
use Akzo\SendCloudSms\Model\Config;
use Akzo\SendSmsApi\Api\SmsServiceInterface;
use Akzonobel\SendCloudApi\Client;
use Akzonobel\SendCloudApi\Service\ClientFactory;
use Exception;
use Monolog\Logger;

class SmsRequest implements SmsServiceInterface
{
    public function __construct(
        private ClientFactory $clientFactory,
        private Config $config,
        private ConfigurationValidatorInterface $configurationValidator,
        private Logger $logger
    ) {
    }

    public function sendSms(array $data, ?string $websiteId): array|bool|null
    {
        try {
            $apiConfiguration = $this->getApiConfiguration($data, $websiteId);
            $params = array_merge($apiConfiguration, $data);
            $this->configurationValidator->validate($params);

            $client = $this->clientFactory->create(
                $this->config->isDebugModeEnabled($websiteId) ? $this->logger : null,
                [
                    'timeout' => $this->config->getTimeout($websiteId),
                ]
            );
            $smsApi = $client->api('sms');
            $response = $smsApi->execute($params);
            if ($this->config->isDebugModeEnabled($websiteId) && !$response['result']) {
                $this->logger->debug(
                    sprintf(
                        'API Response status error(%s): %s',
                        $response['statusCode'],
                        $response['message']
                    )
                );
            }

            return $response;
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
        }

        return false;
    }

    private function getApiConfiguration(array $data, ?string $websiteId): array
    {
        $configuration[Client::API_KEY] = $this->config->getApiKey($websiteId);
        $configuration[Client::SMS_USER] = $this->config->getSmsUser($websiteId);
        $configuration[Client::MESSAGE_TYPE] = $this->config->getMessageType($data, $websiteId);

        return $configuration;
    }
}
