<?php

namespace App\Service;

use LogicException;
use Redis;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RedisConnectionFactory
{
    public function __construct(
        #[Autowire(env: 'MESSENGER_TRANSPORT_DSN')]
        private string $transportDsn,
    ) {
    }

    public function connect(): Redis
    {
        $parts = parse_url($this->transportDsn);
        if (false === $parts || !isset($parts['host'])) {
            throw new LogicException('The Redis Messenger transport DSN must include a Redis host.');
        }

        if (!\in_array($parts['scheme'] ?? '', ['redis', 'rediss'], true)) {
            throw new LogicException('FileBot job history requires a Redis Messenger transport.');
        }

        $host = 'rediss' === $parts['scheme'] ? 'tls://'.$parts['host'] : $parts['host'];
        $redis = new Redis();
        if (!$redis->connect($host, $parts['port'] ?? 6379, 2.5)) {
            throw new RuntimeException('Unable to connect to Redis for FileBot job data.');
        }

        if (isset($parts['pass'])) {
            $password = rawurldecode($parts['pass']);
            $username = isset($parts['user']) ? rawurldecode($parts['user']) : '';
            $auth = '' !== $username && 'default' !== $username
                ? [$username, $password]
                : $password;

            if (!$redis->auth($auth)) {
                throw new RuntimeException('Unable to authenticate with Redis for FileBot job data.');
            }
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $database = filter_var($query['dbindex'] ?? 0, FILTER_VALIDATE_INT);
        if (false === $database || !$redis->select($database)) {
            throw new RuntimeException('Unable to select the Redis database for FileBot job data.');
        }

        return $redis;
    }
}
