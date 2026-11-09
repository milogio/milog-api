<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionInfrastructureTest extends TestCase
{
    /**
     * Test the local TLS certificate meets current Nginx security requirements.
     *
     * @return void
     */
    public function testLocalTlsCertificateHasModernKeyAndLocalhostNames()
    {
        $root = dirname(__DIR__, 2);
        $certificate = openssl_x509_read(file_get_contents($root.'/docker/certs/localhost.crt'));
        $privateKey = openssl_pkey_get_private(file_get_contents($root.'/docker/certs/localhost.key'));
        $details = openssl_pkey_get_details($privateKey);
        $parsed = openssl_x509_parse($certificate);

        $this->assertGreaterThanOrEqual(2048, $details['bits']);
        $this->assertStringContainsString('DNS:localhost', $parsed['extensions']['subjectAltName']);
        $this->assertStringContainsString('IP Address:127.0.0.1', $parsed['extensions']['subjectAltName']);
    }

    /**
     * Test local infrastructure no longer references obsolete images.
     *
     * @return void
     */
    public function testLocalComposeUsesSupportedPinnedImages()
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/docker-compose.yml');

        $this->assertStringContainsString('postgres:17.9-alpine', $compose);
        $this->assertStringContainsString('redis:8.2.9-alpine', $compose);
        $this->assertStringContainsString('nginx:1.30.0-alpine', $compose);
        $this->assertStringNotContainsString('redis:5.', $compose);
        $this->assertStringNotContainsString('nginx:1.17', $compose);
        $this->assertGreaterThanOrEqual(3, substr_count($compose, 'healthcheck:'));
    }

    /**
     * Test production topology keeps data services private and secrets external.
     *
     * @return void
     */
    public function testProductionComposeSeparatesSecretsAndRuntimeData()
    {
        $compose = file_get_contents(dirname(__DIR__, 2).'/docker-compose.production.yml');

        $this->assertStringContainsString('MILOG_PHP_IMAGE:?', $compose);
        $this->assertStringContainsString('MILOG_NGINX_IMAGE:?', $compose);
        $this->assertStringContainsString('environment: MILOG_APP_KEY', $compose);
        $this->assertStringContainsString('environment: MILOG_DB_PASSWORD', $compose);
        $this->assertStringContainsString('environment: MILOG_PASSPORT_PRIVATE_KEY_B64', $compose);
        $this->assertStringContainsString('environment: MILOG_PASSPORT_PUBLIC_KEY_B64', $compose);
        $this->assertStringContainsString('internal: true', $compose);
        $this->assertStringNotContainsString('./:/var/www', $compose);
        $this->assertStringNotContainsString('3923:5432', $compose);
        $this->assertStringNotContainsString('6379:6379', $compose);

        $dockerIgnore = file_get_contents(dirname(__DIR__, 2).'/.dockerignore');
        $this->assertStringContainsString(".env\n", $dockerIgnore);
        $this->assertStringContainsString("docker/data\n", $dockerIgnore);
        $this->assertStringContainsString("vendor\n", $dockerIgnore);
        $this->assertStringContainsString("storage/oauth-*.key\n", $dockerIgnore);
        $this->assertStringContainsString("docker/production.env\n", $dockerIgnore);
        $this->assertStringContainsString("bootstrap/cache/*.php\n", $dockerIgnore);

        $phpDockerfile = file_get_contents(dirname(__DIR__, 2).'/docker/php-fpm/production.Dockerfile');
        $this->assertStringContainsString('php artisan package:discover', $phpDockerfile);
    }

    public function testProductionProxyAndReadinessConfiguration()
    {
        $root = dirname(__DIR__, 2);
        $compose = file_get_contents($root.'/docker-compose.production.yml');
        $nginx = file_get_contents($root.'/docker/nginx/production.conf');
        $fastcgi = file_get_contents($root.'/docker/nginx/production-fastcgi-params');
        $caddy = file_get_contents($root.'/docker/caddy/Caddyfile.production.example');

        $this->assertStringContainsString('127.0.0.1:8080:80', $compose);
        $this->assertStringContainsString('APP_URL: https://api.milog.ca', $compose);
        $this->assertStringContainsString('gw_priority: 1', $compose);
        $this->assertStringContainsString('http://127.0.0.1/ready', $compose);
        $this->assertStringContainsString('location = /health', $nginx);
        $this->assertStringContainsString('location = /ready', $nginx);
        $this->assertStringContainsString('fastcgi_pass_request_headers off', $fastcgi);
        $this->assertStringContainsString('fastcgi_param REMOTE_ADDR 127.0.0.1', $fastcgi);
        $this->assertStringContainsString('fastcgi_param HTTP_X_FORWARDED_PROTO https', $fastcgi);
        $this->assertStringContainsString('header_up X-Forwarded-For {remote_host}', $caddy);
        $this->assertStringContainsString('respond @readiness 404', $caddy);
        $this->assertStringNotContainsString('$request_uri', $nginx);
    }
}
