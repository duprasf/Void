<?php
namespace Void\WebsocketServer;

use \Void\Exception\Websocket as Exception;

/**
* WebSockets server
*
* Based on https://github.com/nekudo/php-websocket/tree/master/server/lib/WebSocket
* with a lot of midification
*
* @author Francois Durpas
*/
class Socket
{
    /**
     * @var Socket Holds the master socket
     */
    protected $master;
    /**
     * @var array Holds all connected sockets
     */
    protected $allsockets = array();

    protected $context = null;
    protected $certData = null;
    protected $certPassphrase = null;
    protected $ssl = false;

    public function setCertData($file, $privatekeyfile=null, $passphrase = null)
    {
        if(file_exists($file)) {
            $this->certData = array("file"=>$file, "privatekey"=>$privatekeyfile);
            $this->certPassphrase = $passphrase;
            $this->ssl = true;
        }
        return $this;
    }

    public function create($host, $port, $certData=null)
    {
        ob_implicit_flush(true);
        $this->ssl = false;
        if(!is_null($certData)) {
            if(is_array($certData) && isset($certData['file'])) {
                $this->setCertData(
                    $certData['file'],
                    isset($certData['privatekey']) ? $certData['privatekey'] : null,
                    isset($certData['passphrase']) ? $certData['passphrase'] : null
                );
            }
            else if(is_string($certData)) {
                $this->setCertData($certData);
            }
        }
        $this->createSocket($host, $port);
    }

    /**
     * Create a socket on given host/port
     *
     * @param string $host The host/bind address to use
     * @param int $port The actual port to bind on
     */
    private function createSocket($host, $port)
    {
        $protocol = ($this->ssl === true) ? 'tls://' : 'tcp://';
        $url = $protocol.$host.':'.$port;
        $this->context = stream_context_create();
        if($this->ssl === true)
        {
            $this->applySecureContext();
        }

        $errno = $err = null;
        if(!$this->master = stream_socket_server($url, $errno, $err, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $this->context))
        {
            throw new Exception('Error creating socket: ' . $err);
        }

        $this->allsockets[] = $this->master;

        return $this;
    }

    public function createCertFile($certFile, array $dn)
    {
        if(file_exists($certFile)) {
            return $this;
        }

        if(!is_writable(dirname($certFile))) {
            throw new Exception('Certificate file is not writable');
        }
        $requiredData = array(
            "countryName" => "CA",
            "stateOrProvinceName" => "Quebec",
            "localityName" => "none",
            "organizationName" => "none",
            "organizationalUnitName" => "none",
            "commonName" => "voidphoto.com",
            "emailAddress" => "certadmin@voidphoto.com",
        );
        if(count(array_intersect_key($dn, $requiredData)) != count($requiredData)) {
            throw new Exception('missing required data');
        }
        $passphrase = isset($dn['passPhrase']) ? $dn['passPhrase'] : null;

        $privkey = openssl_pkey_new();
        $cert    = openssl_csr_new($dn, $privkey);
        $cert    = openssl_csr_sign($cert, null, $privkey, 365);
        $pem = array();
        openssl_x509_export($cert, $pem[0]);
        openssl_pkey_export($privkey, $pem[1], $passphrase ?: null);
        $pem = implode($pem);
        file_put_contents($certFile, $pem);

        $this->certData = $certFile;
        $this->certPassPhrase = $passphrase;

        return $this;
    }

    private function applySecureContext()
    {
        $certData = $this->certData;
        $passphrase = $this->certPassphrase;

        if(is_string($certData)) {
            $certData = array('file'=>$certData);
        }

        if(!file_exists($certData['file']))
        {
            throw new Exception('Certificate file does not exists');
        }

        // apply ssl context:
        stream_context_set_option($this->context, 'ssl', 'local_cert', $certData['file']);
        if(isset($certData['privatekey']) && $certData['privatekey']) {
            stream_context_set_option($this->context, 'ssl', 'local_pk', $certData['privatekey']);
        }
        if($passphrase) {
            stream_context_set_option($this->context, 'ssl', 'passphrase', $passphrase);
        }
        stream_context_set_option($this->context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($this->context, 'ssl', 'verify_peer', false);
        stream_context_set_option($this->context, 'ssl', 'verify_peer_name', false);

        return $this;
    }

    // method originally found in phpws project:
    protected function readBuffer($resource)
    {
        if($this->ssl === true)
        {
            $buffer = fread($resource, 8192);
            // extremely strange chrome behavior: first frame with ssl only contains 1 byte?!
            if(strlen($buffer) === 1)
            {
                $buffer .= fread($resource, 8192);
            }
            return $buffer;
        }
        else
        {
            $buffer = '';
            $buffsize = 8192;
            $metadata['unread_bytes'] = 0;
            do
            {
                if(feof($resource))
                {
                    return false;
                }
                $result = fread($resource, $buffsize);
                if($result === false || feof($resource))
                {
                    return false;
                }
                $buffer .= $result;
                $metadata = stream_get_meta_data($resource);
                $buffsize = ($metadata['unread_bytes'] > $buffsize) ? $buffsize : $metadata['unread_bytes'];
            } while($metadata['unread_bytes'] > 0);

            return $buffer;
        }
    }

    // method originally found in phpws project:
    public function writeBuffer($resource, $string)
    {
        $stringLength = strlen($string);
        for($written = 0; $written < $stringLength; $written += $fwrite)
        {
            $fwrite = @fwrite($resource, substr($string, $written));
            if($fwrite === false)
            {
                return false;
            }
            elseif($fwrite === 0)
            {
                return false;
            }
        }
        return $written;
    }
}