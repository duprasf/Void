<?php
namespace Void;

use Void\WebsocketServer\Client;
use Void\WebsocketServer\Socket;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
* WebSockets server
*
* Based on https://github.com/nekudo/php-websocket/tree/master/server/lib/WebSocket
* with a lot of midification
*
* @author Francois Durpas
*/
class WebsocketServer extends Socket implements EventManagerAwareInterface, LoggerAwareInterface
{
    // PHP 5.3 doesn't have traits so I have to copy/paste it manually
    //use \Psr\Log\LoggerAwareTrait;
    /**
    * The logger instance.
    *
    * @var LoggerInterface
    */
    protected $logger;

    /**
    * Sets a logger.
    *
    * @param LoggerInterface $logger
    */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    protected $eventManager;
    public function setEventManager(EventManagerInterface $eventManager){$eventManager->setIdentifiers(array(__CLASS__,get_called_class(),));$this->eventManager = $eventManager;return $this;}
    public function getEventManager()
    {
        if (null === $this->eventManager && class_exists('EventManager')) {
            $this->setEventManager(new EventManager());
        }
        return $this->eventManager;
    }
    protected function trigger($name, $obj=null, $param=array())
    {
        if($em = $this->getEventManager()) {
            if($obj == null) {
                $obj = $this;
            }
            $em->trigger($name, $obj, $param);
        }
        return $this;
    }

    protected $clients = array();
    protected $applications = array();
    protected $ipStorage = array();
    protected $requestStorage = array();

    // server settings:
    protected $forceCheckOrigin = true;
    protected $allowedOrigins = array();
    protected $maxClients = 100;
    protected $maxConnectionsPerIp = 5;
    protected $maxRequestsPerMinute = 70;

    const STATUS_SERVER_CREATED = 1;
    const STATUS_LIMIT_REACHED = 2;
    const STATUS_LIMIT_REACHED_IP = 4;
    const STATUS_UNKNOW_CONNECTION = 8;

    /**
    * Create a new WebSocket. By default this is inactive and needs to ->run()
    *
    * @param string $host
    * @param int $port
    * @param array|string $certData ['file'=>string, 'passphrase'=>null] or a path to the certificate file
    * @param int max number of user that can connect to the server at once
    * @return WebSocketServer
    */
    public function create($host, $port=null, $certData=null, $maxUsers=100)
    {
        if(is_array($host)) {
            $params = $host;
        }
        else {
            $params = compact('host', 'port', 'certData', 'maxUsers');
        }
        $this->trigger(__FUNCTION__, $this, $params);

        parent::create($params['host'], $params['port'], $params['certData']);
        $this->status(array(
            'code'=>self::STATUS_SERVER_CREATED,
            'message'=>'Server created ('.$params['host'].':'.$params['port'].')',
            'params'=>$params,
        ));

        $this->trigger(__FUNCTION__.'.post', $this, $params);

        return $this;
    }

    /**
    * Creates a client connection from a socket resource
    *
    * @param resource $resource A socket resource
    * @return Client
    */
    protected function createClientConnection($resource)
    {
        return new Client($this, $resource);
    }

    /**
    * Main server method. Listens for connections, handles connectes/disconnectes, e.g.
    */
    public function run()
    {
        $this->trigger(__FUNCTION__, $this);

        while(true)
        {
            $changed_sockets = $this->allsockets;
            $write = null;
            $except = null;
            $num_changed_streams = @stream_select($changed_sockets, $write, $except, 0, 5000);
            if($num_changed_streams === false) {
                /* Error handling */
            }
            else if($num_changed_streams > 0) {
                foreach($changed_sockets as $socket)
                {
                    if($socket == $this->master)
                    {
                        // new connection
                        $ressource = @stream_socket_accept($this->master);
                        if($ressource === false)
                        {
                            $errMsg = socket_strerror(socket_last_error());
                            if($errMsg != 'Success') {
                                $this->log('Socket error: ' . $errMsg);
                            }
                            continue;
                        }
                        else
                        {
                            $client = $this->createClientConnection($ressource);
                            $this->clients[(int)$ressource] = $client;
                            $this->allsockets[] = $ressource;

                            if(count($this->clients) > $this->maxClients)
                            {
                                // this event can be used to send a message to the client
                                $this->trigger('clientLimitReached', $this, compact('client'));

                                $this->status(array(
                                    'code'=>self::STATUS_LIMIT_REACHED,
                                    'message'=>'Client Limit Reached ('.$this->maxClients.')!',
                                    'params'=>array(
                                        'client'=>$client,
                                        'maxClients'=>$this->maxClients,
                                    ),
                                    ), 'warning');

                                $client->onDisconnect();

                                continue;
                            }

                            $this->addIpToStorage($client->getClientIp());
                            if($this->checkMaxConnectionsPerIp($client->getClientIp()) === false)
                            {
                                $client->onDisconnect();
                                $this->status(array(
                                    'code'=>self::STATUS_LIMIT_REACHED_IP,
                                    'message'=>'Connection/Ip limit for ip ' . $client->getClientIp() . ' was reached ('.$this->maxConnectionsPerIp.')!',
                                    'params'=>array(
                                        'client'=>$client,
                                        'maxConnectionsPerIp'=>$this->maxConnectionsPerIp,
                                    ),
                                    ), 'warning');
                                continue;
                            }
                        }
                    }
                    else
                    {
                        $client = isset($this->clients[(int)$socket]) ? $this->clients[(int)$socket] : null;
                        if(!$client instanceOf Client)
                        {
                            $this->status(array(
                                'code'=>self::STATUS_UNKNOW_CONNECTION,
                                'message'=>'Unknown connection... client does not exists',
                                'params'=>array(
                                ),
                                ), 'error');
                            if(isset($this->clients[(int)$socket])) {
                                unset($this->clients[(int)$socket]);
                            }
                            continue;
                        }
                        $data = $this->readBuffer($socket);
                        $bytes = strlen($data);

                        if($bytes === 0)
                        {
                            $client->onDisconnect();
                            continue;
                        }
                        elseif($data === false)
                        {
                            $this->removeClientOnError($client);
                            continue;
                        }
                        elseif($client->waitingForData === false && $this->checkRequestLimit($client->getClientId()) === false)
                        {
                            $client->onDisconnect();
                        }
                        elseif(!$client->handshaked) {
                            $client->handshake($data);
                            $this->onOpen($client);
                            $this->trigger('open', $this, compact('client'));
                        }
                        else {
                            $decodedData = $client->handle($data);
                            // this is sent during reload of page
                            if($decodedData['type'] == 'close') {
                                $client->onDisconnect();
                            }
                            else {
                                $this->onMessage($client, $decodedData['payload']);
                                $this->trigger('message', $this, array('client'=>$client, 'data'=>$decodedData['payload']));
                            }
                        }
                    }
                }
            }

            $this->onTick();
            $this->trigger('tick', $this);
        }
    }

    /**
    * Echos a message to standard output.
    *
    * @param string $message Message to display.
    * @param string $type Type of message.
    * @return WebsocketServer
    */
    public function log($message, $type = 'info')
    {
        //TODO: implement PSR-3 logger
        echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;

        return $this;
    }


    /**
    * Same as log except that is it directed at status instead of plain logging info
    *
    * @param array|string $message
    * @param string $type
    * @return WebsocketServer
    */
    public function status($data, $type = 'status')
    {
        if(!is_array($data)) {
            $data = array('code'=>0, 'message'=>$data);
        }
        if(!isset($data['code'])) {
            $data['code']=0;
        }
        if(!isset($data['message'])) {
            // if there is no message, there is no status to provide
            return false;
        }

        $this->log($data['message'], $type);
        $this->trigger('status', $this, $data);
        return $this;
    }

    /**
    * Removes a client from client storage.
    *
    * @param Object $client Client object.
    */
    public function removeClientOnClose($client)
    {
        $this->onClose($client);
        $this->trigger('close', $this, compact('client'));

        $clientId = $client->getClientId();
        $clientIp = $client->getClientIp();
        $clientPort = $client->getClientPort();
        $resource = $client->getClientSocket();

        $this->removeIpFromStorage($client->getClientIp());
        if(isset($this->requestStorage[$clientId]))
        {
            unset($this->requestStorage[$clientId]);
        }
        unset($this->clients[(int)$resource]);
        $index = array_search($resource, $this->allsockets);
        unset($this->allsockets[$index], $client);
        unset($clientId, $clientIp, $clientPort, $resource);

        return $this;
    }

    /**
    * Removes a client and all references in case of timeout/error.
    * @param object $client The client object to remove.
    */
    public function removeClientOnError($client)
    {
        // remove reference in clients app:
        if($client->getClientApplication() !== false)
        {
            $client->getClientApplication()->onDisconnect($client);
        }

        return $this->removeClientOnClose($client);
    }

    /**
    * Checks if the submitted origin (part of websocket handshake) is allowed
    * to connect. Allowed origins can be set at server startup.
    *
    * @param string $domain The origin-domain from websocket handshake.
    * @return bool If domain is allowed to connect method returns true.
    */
    public function checkOrigin($domain)
    {
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('www.', '', $domain);
        $domain = str_replace('/', '', $domain);

        return count($this->allowedOrigins) === 0 || isset($this->allowedOrigins[$domain]);
    }

    /**
    * Adds a new ip to ip storage.
    *
    * @param string $ip An ip address.
    */
    private function addIpToStorage($ip)
    {
        if(isset($this->ipStorage[$ip]))
        {
            $this->ipStorage[$ip]++;
        }
        else
        {
            $this->ipStorage[$ip] = 1;
        }

        return $this;
    }

    /**
    * Removes an ip from ip storage.
    *
    * @param string $ip An ip address.
    * @return WebSocketServer
    */
    private function removeIpFromStorage($ip)
    {
        if(isset($this->ipStorage[$ip])) {
            $this->ipStorage[$ip]--;
            if($this->ipStorage[$ip] === 0) {
                unset($this->ipStorage[$ip]);
            }
        }
        return $this;
    }

    /**
    * Checks if an ip has reached the maximum connection limit.
    *
    * @param string $ip An ip address.
    * @return bool False if ip has reached max. connection limit. True if connection is allowed.
    */
    private function checkMaxConnectionsPerIp($ip)
    {
        if($ip && !isset($this->ipStorage[$ip]))
        {
            return true;
        }
        return $ip && ($this->ipStorage[$ip] > $this->maxConnectionsPerIp) ? false : true;
    }

    /**
    * Checkes if a client has reached its max. requests per minute limit.
    *
    * @param string $clientId A client id. (unique client identifier)
    * @return bool True if limit was NOT reached yet. False if request limit was reached.
    */
    private function checkRequestLimit($clientId)
    {
        // no data in storage or more than 60 sec since the begining
        // it is not a perfect way to count since it would be possible for an
        // attacker to send 1 request wait 59 seconds and send 98 more at once
        // with a limit of 100/sec it would then allow another 99 request the
        // next second. But it is good enought
        if(!isset($this->requestStorage[$clientId]) || time() - $this->requestStorage[$clientId]['firstRequest'] > 60)
        {
            $this->requestStorage[$clientId] = array(
                'firstRequest' => time(),
                'totalRequests' => 0,
            );
        }

        $this->requestStorage[$clientId]['totalRequests']++;

        // True if limit was NOT reached yet. False if request limit was reached.
        return !($this->requestStorage[$clientId]['totalRequests'] > $this->maxRequestsPerMinute);
    }

    /**
    * Set whether the client origin should be checked on new connections.
    *
    * @param bool $doOriginCheck
    * @return WebSocketServer
    */
    public function setCheckOrigin($doOriginCheck)
    {
        $this->forceCheckOrigin = !!$doOriginCheck;
        return $this;
    }

    /**
    * Return value indicating if client origins are checked.
    * @return bool True if origins are checked.
    */
    public function getCheckOrigin()
    {
        return $this->forceCheckOrigin;
    }

    /**
    * Adds a domain to the allowed origin storage.
    *
    * @param sting $domain A domain name from which connections to server are allowed.
    * @return WebSocketServer
    */
    public function setAllowedOrigin($domain)
    {
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('www.', '', $domain);
        $domain = (strpos($domain, '/') !== false) ? substr($domain, 0, strpos($domain, '/')) : $domain;
        if(!empty($domain))
        {
            $this->allowedOrigins[$domain] = true;
        }
        return $this;
    }

    /**
    * Sets value for the max. connection per ip to this server.
    *
    * @param int $limit Connection limit for an ip.
    * @return WebSocketServer
    */
    public function setMaxConnectionsPerIp($limit)
    {
        $this->maxConnectionsPerIp = intval($limit);
        return $this;
    }

    /**
    * Returns the max. connections per ip value.
    *
    * @return int Max. simoultanous  allowed connections for an ip to this server.
    */
    public function getMaxConnectionsPerIp()
    {
        return $this->maxConnectionsPerIp;
    }

    /**
    * Sets how many requests a client is allowed to do per minute.
    *
    * @param int $limit Requets/Min limit (per client).
    * @return WebSocketServer
    */
    public function setMaxRequestsPerMinute($limit)
    {
        $this->maxRequestsPerMinute = intval($limit);
        return $this;
    }

    /**
    * Sets how many clients are allowed to connect to server until no more
    * connections are accepted.
    *
    * @param in $max Max. total connections to server.
    * @return WebSocketServer
    */
    public function setMaxClients($max)
    {
        $this->maxClients = intval($max);
        return $this;
    }

    /**
    * Returns total max. connection limit of server.
    *
    * @return int Max. connections to this server.
    */
    public function getMaxClients()
    {
        return $this->maxClients;
    }

    /**
    * Send a message to a specific client
    *
    * @param Client|int $client the client Object or the clientId
    * @param mixed $data if not string, json_encode is called
    * @return WebsocketServer
    */
    public function send($client, $data)
    {
        if(!$client instanceOf Client) {
            $client = isset($this->clients[(int)$client]) ? $this->clients[(int)$client] : null;
        }
        if($client instanceOf Client) {
            // Trying to json_encode an already json_encoded string
            // create bad results so check to see if it's already a
            // string before encoding it.
            if(!is_string($data)) {
                try {
                    $data = json_encode($data);
                }
                catch(\Exception $e) {
                    $data = null;
                }
            }
            if($data) {
                $client->send($data);
            }
        }
        return $this;
    }

    /**
    * Send a message to a specific client
    *
    * @param Client|int $client the client Object or the clientId
    * @param mixed $data if not string, json_encode is called
    * @return WebsocketServer
    */
    public function sendAll($data, $except=array())
    {
        if(!is_array($except)) {
            $except = array($except);
        }
        foreach($this->clients as $client) {
            if(!$client->handshaked || in_array($client, $except)) {
                continue;
            }
            $this->send($client, $data);
        }
        return $this;
    }

    /**
    * Event Call: When a new client connects to the server
    *
    * @param Client $client
    */
    public function onOpen(Client $client)
    {
        $this->log('New Client ('.$client->getClientId().') '.$client->getClientIp());
    }

    /**
    * Event Call: When a client leave with error or willingly
    *
    * @param Client $client
    */
    public function onClose(Client $client)
    {
        $this->log('Client left ('.$client->getClientId().') '.$client->getClientIp());
    }

    /**
    * Event Call: When a client sends some information
    *
    * @param Client $client
    * @param mixed $data
    */
    public function onMessage(Client $client, $data)
    {
        $this->log('Message received: '.$data);
    }

    /**
    * Event Call: Called during every loop. this can be used for management,
    * for time limit or a multiple of other ways.
    *
    */
    public function onTick()
    {
    }
}
