<?php

declare(strict_types=1);

namespace EasySwoole\EtcdClient;

enum Permission: int
{
    case Read = 0;
    case Write = 1;
    case ReadWrite = 2;
}
