<?php
namespace Void;

class Crypt
{
    protected $pubkey;
    public function setPublicKey($key)
    {
        if(is_file($key)) {
            $key = file_get_contents($key);
        }
        $this->pubkey = openssl_pkey_get_public($key);
        return $this;
    }

    protected $privkey;
    public function setPrivateKey($key)
    {
        if(is_file($key)) {
            $key = file_get_contents($key);
        }
        $this->privkey = openssl_pkey_get_private($key);
        return $this;
    }

    public function encrypt($data, $publicKey = null)
    {
        if($publicKey) {
            $this->setPublicKey($publicKey);
        }
        if(!$this->pubkey) {
            throw new \Exception('Public key not set');
        }

        if (openssl_public_encrypt($data, $encrypted, $this->pubkey, OPENSSL_PKCS1_OAEP_PADDING)) {
            $data = base64_encode($encrypted);
        }
        else {
            throw new Exception('Unable to encrypt data. Perhaps it is bigger than the key size?');
        }

        return $data;
    }

    public function decrypt($data, $privateKey = null)
    {
        if($privateKey) {
            $this->setPrivateKey($privateKey);
        }
        if(!$this->pubkey) {
            throw new \Exception('Private key not set');
        }

        if(!openssl_private_decrypt(base64_decode($data), $decrypted, $this->privkey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new \Exception('Could not decrypt data');
        }

        return $decrypted;
    }

    public function makeKey(array $dn, &$pubkey, &$privkey)
    {
        // Fill in data for the distinguished name to be used in the cert
        // You must change the values of these keys to match your name and
        // company, or more precisely, the name and company of the person/site
        // that you are generating the certificate for.
        // For SSL certificates, the commonName is usually the domain name of
        // that will be using the certificate, but for S/MIME certificates,
        // the commonName will be the name of the individual who will use the
        // certificate.
        $dnDefault = array(
            "countryName" => "CA",
            "stateOrProvinceName" => "QUEBEC",
            "localityName" => "Gatineau",
            "organizationName" => "VoidPhoto",
            "organizationalUnitName" => "VoidPhoto",
            "commonName" => "VoidPhoto",
            "emailAddress" => "cert@tekoasis.com"
        );
        $dn = $dn + $dnDefault;

        // Generate a new private (and public) key pair
        $privatekey = openssl_pkey_new();

        // Generate a certificate signing request
        $csr = openssl_csr_new($dn, $privatekey);

        // You will usually want to create a self-signed certificate at this
        // point until your CA fulfills your request.
        // This creates a self-signed cert that is valid for 365 days
        $sscert = openssl_csr_sign($csr, null, $privatekey, 365);

        // Now you will want to preserve your private key, CSR and self-signed
        // cert so that they can be installed into your web server, mail server
        // or mail client (depending on the intended use of the certificate).
        // This example shows how to get those things into variables, but you
        // can also store them directly into files.
        // Typically, you will send the CSR on to your CA who will then issue
        // you with the "real" certificate.
        openssl_csr_export($csr, $csrout);
        openssl_x509_export($sscert, $pubkey);
        openssl_pkey_export($privatekey, $privkey);

        return $this;
    }

    public function makeAndWriteKey(array $dn, $publicKeyPath, $privateKeyPath)
    {
        if(
            (file_exists($publicKeyPath) && !is_writeable($publicKeyPath))
            ||
            (file_exists($privateKeyPath) && !is_writable($privateKeyPath))
        ) {
            throw new \Exception('Cannot write public or private key');
        }
        else if(!is_writeable(dirname($publicKeyPath)) || !is_writable(dirname($privateKeyPath))) {
            throw new \Exception('Cannot write in one of the folders for the public or private key');
        }
        $this->makeKey($dn, $pub, $priv);

        file_put_contents($publicKeyPath, $pub);
        file_put_contents($privateKeyPath, $priv);

        return $this;
    }
}

