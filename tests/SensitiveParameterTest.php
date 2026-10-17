<?php

declare(strict_types=1);

namespace KinetiStack\Sdk\Tests;

use KinetiStack\Sdk\AdminClient;
use KinetiStack\Sdk\AnalyticsClient;
use KinetiStack\Sdk\Client\AdminClientInterface;
use KinetiStack\Sdk\Client\AnalyticsClientInterface;
use KinetiStack\Sdk\Dto\ApiKeyCreatedDto;
use KinetiStack\Sdk\Dto\AuthTokenDto;
use KinetiStack\Sdk\KinetiClient;
use KinetiStack\Sdk\Transport\HttpTransport;
use KinetiStack\Sdk\Transport\Psr18Transport;
use KinetiStack\Sdk\Transport\SymfonyTransport;
use KinetiStack\Sdk\Transport\TransportInterface;
use KinetiStack\Sdk\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SensitiveParameterTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function sensitiveParametersProvider(): array
    {
        return [
            'KinetiClient::__construct $apiKey' => [KinetiClient::class, '__construct', 'apiKey'],
            'AdminClient::__construct $jwtToken' => [AdminClient::class, '__construct', 'jwtToken'],
            'AdminClient::withToken $jwtToken' => [AdminClient::class, 'withToken', 'jwtToken'],
            'AdminClient::login $password' => [AdminClient::class, 'login', 'password'],
            'AdminClient::verifyEmail $token' => [AdminClient::class, 'verifyEmail', 'token'],
            'AdminClient::verifyEmail $password' => [AdminClient::class, 'verifyEmail', 'password'],
            'AdminClient::resetPassword $token' => [AdminClient::class, 'resetPassword', 'token'],
            'AdminClient::resetPassword $newPassword' => [AdminClient::class, 'resetPassword', 'newPassword'],
            'AdminClient::updateMyPassword $currentPassword' => [AdminClient::class, 'updateMyPassword', 'currentPassword'],
            'AdminClient::updateMyPassword $newPassword' => [AdminClient::class, 'updateMyPassword', 'newPassword'],
            'AdminClientInterface::withToken $jwtToken' => [AdminClientInterface::class, 'withToken', 'jwtToken'],
            'AdminClientInterface::login $password' => [AdminClientInterface::class, 'login', 'password'],
            'AdminClientInterface::verifyEmail $token' => [AdminClientInterface::class, 'verifyEmail', 'token'],
            'AdminClientInterface::verifyEmail $password' => [AdminClientInterface::class, 'verifyEmail', 'password'],
            'AdminClientInterface::resetPassword $token' => [AdminClientInterface::class, 'resetPassword', 'token'],
            'AdminClientInterface::resetPassword $newPassword' => [AdminClientInterface::class, 'resetPassword', 'newPassword'],
            'AdminClientInterface::updateMyPassword $currentPassword' => [AdminClientInterface::class, 'updateMyPassword', 'currentPassword'],
            'AdminClientInterface::updateMyPassword $newPassword' => [AdminClientInterface::class, 'updateMyPassword', 'newPassword'],
            'AnalyticsClient::__construct $jwtToken' => [AnalyticsClient::class, '__construct', 'jwtToken'],
            'AnalyticsClient::withToken $jwtToken' => [AnalyticsClient::class, 'withToken', 'jwtToken'],
            'AnalyticsClientInterface::withToken $jwtToken' => [AnalyticsClientInterface::class, 'withToken', 'jwtToken'],
            'WebhookVerifier::verify $secret' => [WebhookVerifier::class, 'verify', 'secret'],
            'AuthTokenDto::__construct $token' => [AuthTokenDto::class, '__construct', 'token'],
            'AuthTokenDto::__construct $refreshToken' => [AuthTokenDto::class, '__construct', 'refreshToken'],
            'ApiKeyCreatedDto::__construct $token' => [ApiKeyCreatedDto::class, '__construct', 'token'],
            'TransportInterface::withAuthHeaderValue $authHeaderValue' => [TransportInterface::class, 'withAuthHeaderValue', 'authHeaderValue'],
            'HttpTransport::__construct $authHeaderName' => [HttpTransport::class, '__construct', 'authHeaderName'],
            'HttpTransport::__construct $authHeaderValueOrClient' => [HttpTransport::class, '__construct', 'authHeaderValueOrClient'],
            'HttpTransport::withAuthHeaderValue $authHeaderValue' => [HttpTransport::class, 'withAuthHeaderValue', 'authHeaderValue'],
            'SymfonyTransport::__construct $authHeaderName' => [SymfonyTransport::class, '__construct', 'authHeaderName'],
            'SymfonyTransport::__construct $authHeaderValueOrClient' => [SymfonyTransport::class, '__construct', 'authHeaderValueOrClient'],
            'SymfonyTransport::withAuthHeaderValue $authHeaderValue' => [SymfonyTransport::class, 'withAuthHeaderValue', 'authHeaderValue'],
            'Psr18Transport::__construct $authHeaderName' => [Psr18Transport::class, '__construct', 'authHeaderName'],
            'Psr18Transport::__construct $authHeaderValueOrClient' => [Psr18Transport::class, '__construct', 'authHeaderValueOrClient'],
            'Psr18Transport::withAuthHeaderValue $authHeaderValue' => [Psr18Transport::class, 'withAuthHeaderValue', 'authHeaderValue'],
            'AdminClient::createTransport $jwtToken' => [AdminClient::class, 'createTransport', 'jwtToken'],
            'AnalyticsClient::createTransport $jwtToken' => [AnalyticsClient::class, 'createTransport', 'jwtToken'],
            'HttpTransport::createDiscoveredTransport $authHeaderName' => [HttpTransport::class, 'createDiscoveredTransport', 'authHeaderName'],
            'HttpTransport::createDiscoveredTransport $authHeaderValue' => [HttpTransport::class, 'createDiscoveredTransport', 'authHeaderValue'],
        ];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('sensitiveParametersProvider')]
    public function testSensitiveParameterAttributePresent(string $className, string $methodName, string $parameterName): void
    {
        $refMethod = new \ReflectionMethod($className, $methodName);
        $found = false;

        foreach ($refMethod->getParameters() as $param) {
            if ($param->getName() === $parameterName) {
                $attributes = $param->getAttributes(\SensitiveParameter::class);
                $this->assertNotEmpty(
                    $attributes,
                    sprintf('Parameter $%s in %s::%s() must have #[\\SensitiveParameter] attribute.', $parameterName, $className, $methodName)
                );
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            sprintf('Parameter $%s not found in %s::%s().', $parameterName, $className, $methodName)
        );
    }

    public function testStackTraceRedactsSensitiveParameterOnPhp82(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            $this->markTestSkipped('Native SensitiveParameterValue stack trace redaction requires PHP 8.2+.');
        }

        $previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            $client = new AdminClient('https://api.test', 'super-secret-token');
            $client->verifyEmail('', 'super-secret-password');
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            $trace = $e->getTrace();
            $foundVerifyEmail = false;

            foreach ($trace as $frame) {
                if ($frame['function'] === 'verifyEmail') {
                    $args = $frame['args'] ?? [];
                    if (!empty($args)) {
                        $this->assertInstanceOf(\SensitiveParameterValue::class, $args[0]);
                        $this->assertInstanceOf(\SensitiveParameterValue::class, $args[1]);
                    }
                    $foundVerifyEmail = true;
                    break;
                }
            }

            $this->assertTrue($foundVerifyEmail, 'verifyEmail stack frame not found in exception trace.');
            $this->assertStringNotContainsString('super-secret-password', (string) $e);
            $this->assertStringNotContainsString('super-secret-token', (string) $e);
        } finally {
            if ($previousIgnoreArgs !== false) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    public function testAdminClientDebugInfoRedactsJwtToken(): void
    {
        $secretToken = 'secret-admin-jwt-token-xyz';
        $client = new AdminClient('https://api.test', $secretToken);

        $debugInfo = $client->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['jwtToken']);
        $this->assertStringNotContainsString($secretToken, var_export($debugInfo['jwtToken'], true));

        $printOutput = print_r($client, true);
        $this->assertStringNotContainsString($secretToken, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        // When empty token
        $clientEmpty = new AdminClient('https://api.test', '');
        $this->assertSame('', $clientEmpty->__debugInfo()['jwtToken']);
    }

    public function testAnalyticsClientDebugInfoRedactsJwtToken(): void
    {
        $secretToken = 'secret-analytics-jwt-token-xyz';
        $client = new AnalyticsClient('https://api.test', $secretToken);

        $debugInfo = $client->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['jwtToken']);
        $this->assertStringNotContainsString($secretToken, var_export($debugInfo['jwtToken'], true));

        $printOutput = print_r($client, true);
        $this->assertStringNotContainsString($secretToken, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        // When empty token
        $clientEmpty = new AnalyticsClient('https://api.test', '');
        $this->assertSame('', $clientEmpty->__debugInfo()['jwtToken']);
    }

    public function testHttpTransportDebugInfoRedactsAuthHeaderValue(): void
    {
        $secretValue = 'Bearer secret-auth-token-123';
        $transport = new HttpTransport('https://api.test', 'Authorization', $secretValue);

        $debugInfo = $transport->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['authHeaderValue']);
        $this->assertStringNotContainsString($secretValue, var_export($debugInfo['authHeaderValue'], true));

        $printOutput = print_r($transport, true);
        $this->assertStringNotContainsString($secretValue, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        // When empty value
        $transportEmpty = new HttpTransport('https://api.test', 'Authorization', '');
        $this->assertSame('', $transportEmpty->__debugInfo()['authHeaderValue']);
    }

    public function testPsr18TransportDebugInfoRedactsAuthHeaderValue(): void
    {
        $secretValue = 'Bearer secret-psr18-token-456';
        $transport = new Psr18Transport('https://api.test', 'Authorization', $secretValue);

        $debugInfo = $transport->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['authHeaderValue']);
        $this->assertStringNotContainsString($secretValue, var_export($debugInfo['authHeaderValue'], true));

        $printOutput = print_r($transport, true);
        $this->assertStringNotContainsString($secretValue, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        // When empty value
        $transportEmpty = new Psr18Transport('https://api.test', 'Authorization', '');
        $this->assertSame('', $transportEmpty->__debugInfo()['authHeaderValue']);
    }

    public function testSymfonyTransportDebugInfoRedactsAuthHeaderValue(): void
    {
        $secretValue = 'Bearer secret-symfony-token-789';
        $transport = new SymfonyTransport('https://api.test', 'Authorization', $secretValue);

        $debugInfo = $transport->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['authHeaderValue']);
        $this->assertStringNotContainsString($secretValue, var_export($debugInfo['authHeaderValue'], true));

        $printOutput = print_r($transport, true);
        $this->assertStringNotContainsString($secretValue, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        // When empty value
        $transportEmpty = new SymfonyTransport('https://api.test', 'Authorization', '');
        $this->assertSame('', $transportEmpty->__debugInfo()['authHeaderValue']);
    }

    public function testAuthTokenDtoDebugInfoRedactsSecrets(): void
    {
        $secretToken = 'secret-token-abc';
        $secretRefresh = 'secret-refresh-token-def';
        $dto = new AuthTokenDto($secretToken, $secretRefresh);

        $debugInfo = $dto->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['token']);
        $this->assertSame('[REDACTED]', $debugInfo['refreshToken']);

        $printOutput = print_r($dto, true);
        $this->assertStringNotContainsString($secretToken, $printOutput);
        $this->assertStringNotContainsString($secretRefresh, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        $varExportDebug = var_export($debugInfo, true);
        $this->assertStringNotContainsString($secretToken, $varExportDebug);
        $this->assertStringNotContainsString($secretRefresh, $varExportDebug);

        // When refreshToken is null
        $dtoNoRefresh = new AuthTokenDto($secretToken);
        $this->assertSame('[REDACTED]', $dtoNoRefresh->__debugInfo()['token']);
        $this->assertNull($dtoNoRefresh->__debugInfo()['refreshToken']);
    }

    public function testApiKeyCreatedDtoDebugInfoRedactsToken(): void
    {
        $secretToken = 'secret-apikey-token-ghi';
        $dto = new ApiKeyCreatedDto('key-1', 'Key Name', 'suffix', $secretToken);

        $debugInfo = $dto->__debugInfo();
        $this->assertSame('[REDACTED]', $debugInfo['token']);
        $this->assertSame('key-1', $debugInfo['id']);
        $this->assertSame('Key Name', $debugInfo['name']);
        $this->assertSame('suffix', $debugInfo['tokenSuffix']);

        $printOutput = print_r($dto, true);
        $this->assertStringNotContainsString($secretToken, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        $varExportDebug = var_export($debugInfo, true);
        $this->assertStringNotContainsString($secretToken, $varExportDebug);
    }

    public function testKinetiClientDebugInfoRecursivelyRedactsApiKeyViaTransport(): void
    {
        $secretApiKey = 'secret-kineti-api-key-999';
        $client = new KinetiClient('https://api.test', $secretApiKey);

        $printOutput = print_r($client, true);
        $this->assertStringNotContainsString($secretApiKey, $printOutput);
        $this->assertStringContainsString('[REDACTED]', $printOutput);

        $transport = $client->getTransport();
        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertSame('[REDACTED]', $transport->__debugInfo()['authHeaderValue']);
    }
}
