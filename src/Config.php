<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    /**
     * @var string
     */
    protected $scheme = 'http';

    /**
     * 主机地址
     * @var string
     */
    protected $host = '127.0.0.1';

    /**
     * 端口号
     * @var int
     */
    protected $port = 2379;

    /**
     * API版本
     * @var string
     */
    protected $version = 'v3';

    /**
     * 是否只获取返回结果
     * @var bool
     */
    protected $pretty = false;

    /**
     * @var bool
     */
    protected $ssl = false;

    /**
     * SSL证书
     * @var string
     */
    protected $sslCert;

    /**
     * SSL证书
     * @var string
     */
    protected $sslKey;

    /**
     * 超时时间
     * @var int
     */
    protected $timeout = 30;

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return bool
     */
    public function isPretty(): bool
    {
        return $this->pretty;
    }

    /**
     * @param bool $pretty
     */
    public function setPretty(bool $pretty): void
    {
        $this->pretty = $pretty;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @param string $scheme
     */
    public function setScheme(string $scheme): void
    {
        $this->scheme = $scheme;
    }

    /**
     * @return string
     */
    public function getSslCert(): string
    {
        return $this->sslCert;
    }

    /**
     * @param string $sslCert
     */
    public function setSslCert(string $sslCert): void
    {
        $this->sslCert = $sslCert;
    }

    /**
     * @return string
     */
    public function getSslKey(): string
    {
        return $this->sslKey;
    }

    /**
     * @param string $sslKey
     */
    public function setSslKey(string $sslKey): void
    {
        $this->sslKey = $sslKey;
    }

    /**
     * @return bool
     */
    public function isSsl(): bool
    {
        return $this->ssl;
    }

    /**
     * @param bool $ssl
     */
    public function setSsl(bool $ssl): void
    {
        $this->ssl = $ssl;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }
}