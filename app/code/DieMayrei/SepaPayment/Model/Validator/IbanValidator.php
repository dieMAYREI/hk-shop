<?php

declare(strict_types=1);

namespace DieMayrei\SepaPayment\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

class IbanValidator
{
    private const VALIDATION_ENDPOINT = 'https://ibanvalidation.dlv-shop.de/validate/';
    private const QUERY = '?getBIC=true';

    public function __construct(
        private readonly Curl $curlClient,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function validate(string $iban): array
    {
        $url = self::VALIDATION_ENDPOINT . rawurlencode($iban) . self::QUERY;

        try {
            $this->curlClient->addHeader('Accept', 'application/json');
            $this->curlClient->setTimeout(15);
            $this->curlClient->get($url);

            $status = $this->curlClient->getStatus();
            $body = $this->curlClient->getBody();
        } catch (\Throwable $throwable) {
            $this->logger->error(
                'SEPA IBAN validation failed while performing the HTTP request.',
                ['exception' => $throwable]
            );
            throw new LocalizedException(__('Die IBAN konnte nicht geprüft werden. Bitte versuchen Sie es erneut.'));
        }

        if ($status !== 200 || !$body) {
            $this->logger->warning('SEPA IBAN validation service returned a non-success status.', [
                'status' => $status,
                'body' => $body,
            ]);
            throw new LocalizedException(__('Die IBAN konnte nicht geprüft werden. Bitte versuchen Sie es erneut.'));
        }

        try {
            $decoded = $this->jsonSerializer->unserialize($body);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Invalid JSON received from IBAN validation service.', ['body' => $body]);
            throw new LocalizedException(__('Die IBAN konnte nicht geprüft werden. Bitte versuchen Sie es erneut.'));
        }

        if (!is_array($decoded) || !array_key_exists('valid', $decoded)) {
            $this->logger->error('Unexpected payload received from IBAN validation service.', ['body' => $body]);
            throw new LocalizedException(__('Die IBAN konnte nicht geprüft werden. Bitte versuchen Sie es erneut.'));
        }

        return $decoded;
    }
}
