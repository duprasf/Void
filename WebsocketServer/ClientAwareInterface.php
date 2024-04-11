<?php

namespace Void\WebsocketServer;

use Void\WebsocketServer\Client;

interface ClientAwareInterface
{
    public function setClient(Client $client);
    public function getClient(Client $client);
}
