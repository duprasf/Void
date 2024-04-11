<?php

namespace Void;

/**
* Based on:
* @see https://github.com/barbushin/php-imap
*/
class ImapMailbox implements \Iterator, \Countable
{
    /**
    * $valid is for the iterator
    *
    * @var $valid bool
    */
    private $valid = false;
    /**
    * Use to store data
    *
    * @var $mailIds array
    */
    private $mailIds = array();

    protected $imapPath;
    protected $login;
    protected $password;
    protected $mbox;
    protected $serverEncoding;
    protected $attachmentsDir;

    public function __construct($imapPath = null, $login = null, $password = null, $attachmentsDir = false, $serverEncoding = 'utf-8')
    {
        if($imapPath) {
            $this->connect($imapPath, $login, $password, $attachmentsDir, $serverEncoding);
        }
    }

    public function connect($imapPath = null, $login = null, $password = null, $attachmentsDir = false, $serverEncoding = 'utf-8')
    {
        if(!is_null($imapPath)) {
            $this->imapPath = $imapPath;
        }
        if(!is_null($login)) {
            $this->login = $login;
        }
        if(!is_null($password)) {
            $this->password = $password;
        }
        if(!is_null($serverEncoding)) {
            $this->serverEncoding = $serverEncoding;
        }
        if($attachmentsDir && is_dir($attachmentsDir)) {
            $this->attachmentsDir = realpath($attachmentsDir);
        } elseif(is_null($this->attachmentsDir)) {
            $this->attachmentsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "imapAttachment";
            if(!is_dir($this->attachmentsDir)) {
                mkdir($this->attachmentsDir, 0775, true);
            }
        }

        if(is_null($this->imapPath)) {
            throw new Exception\ImapMailbox('No connection information provided');
        }

        $this->mbox = $this->imap_open($this->imapPath, $this->login, $this->password);
        if(!$this->mbox) {
            throw new Exception\ImapMailbox('Connection error: ' . imap_last_error());
        }

        return $this;
    }

    protected function checkConnection()
    {
        if(!$this->imap_ping($this->mbox)) {
            $this->reconnect();
        }

        return $this;
    }

    protected function reconnect()
    {
        $this->closeConnection();
        $this->connect();

        return $this;
    }

    public function getCheck()
    {
        $this->checkConnection();
        $result = $this->imap_check($this->mbox);

        return $result;
    }

    public function searchMails($imapCriteria = 'ALL')
    {
        $this->checkConnection();
        $mailsIds = $this->imap_search($this->mbox, $imapCriteria, SE_UID, $this->serverEncoding);

        return $mailsIds ? array_reverse($mailsIds, true) : array();
    }

    public function deleteMail($id)
    {
        $this->checkConnection();
        $this->imap_delete($this->mbox, $id, FT_UID | CL_EXPUNGE);
        $this->imap_expunge($this->mbox);

        return $this;
    }

    public function setMailAsSeen($id)
    {
        $this->checkConnection();
        $this->setMailImapFlag($id, '\\Seen');

        return $this;
    }

    public function setMailImapFlag($id, $flag)
    {
        $this->imap_setflag_full($this->mbox, $id, $flag, ST_UID);

        return $this;
    }

    protected function getMailHeaders($id)
    {
        $this->checkConnection();
        $headers = $this->imap_fetchheader($this->mbox, $id, FT_UID);

        if(!$headers) {
            throw new Exception\ImapMailbox('Message with UID "' . $id . '" not found');
        }
        return $headers;
    }

    public function getMailInfo($id)
    {
        $header = $this->imap_rfc822_parse_headers($this->getMailHeaders($id));
        $fromInfo = $header->from[0];
        $replyInfo = $header->reply_to[0];

        $toStrings = array();
        foreach($header->to as $to) {
            $toEmail = strtolower($to->mailbox . '@' . $to->host);
            $toName = isset($to->personal) ? $this->decodeMimeStr($to->personal) : null;
            $toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
        }
        $to = implode(', ', $toStrings);

        $cc = array();
        if(isset($header->cc)) {
            foreach($header->cc as $cc) {
                $cc[] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal) : null;
            }
        }
        $cc = implode(', ', $cc);

        $details = array(
            "id" => $id,
            "fromAddr" => isset($fromInfo->mailbox) && isset($fromInfo->host) ? $fromInfo->mailbox . "@" . $fromInfo->host : "",
            "fromName" => isset($fromInfo->personal) ? $this->decodeMimeStr($fromInfo->personal) : "",
            "replyAddr" => isset($replyInfo->mailbox) && isset($replyInfo->host) ? $replyInfo->mailbox . "@" . $replyInfo->host : "",
            "replyName" => isset($replyTo->personal) ? $this->decodeMimeStr($replyto->personal) : "",
            "to" => $to,
            "cc" => $cc,
            "subject" => isset($header->subject) ? $this->decodeMimeStr($header->subject) : '',
            "date" => isset($header->date) ? $header->date : "",
            "udate" => isset($header->udate) ? $header->udate : ""
        );

        return $details;
    }

    public function getMail($id)
    {
        $this->checkConnection();
        $head = $this->imap_rfc822_parse_headers($this->getMailHeaders($id));

        $mail = new ImapMail();
        $mail->id = $id;
        $mail->date = date('Y-m-d H:i:s', isset($head->date) ? strtotime($head->date) : time());
        $mail->subject = $this->decodeMimeStr($head->subject);
        $mail->fromName = isset($head->from[0]->personal) ? $this->decodeMimeStr($head->from[0]->personal) : null;
        $mail->fromAddress = strtolower($head->from[0]->mailbox . '@' . $head->from[0]->host);

        $toStrings = array();
        foreach($head->to as $to) {
            $toEmail = strtolower($to->mailbox . '@' . $to->host);
            $toName = isset($to->personal) ? $this->decodeMimeStr($to->personal) : null;
            $toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
            $mail->to[$toEmail] = $toName;
        }
        $mail->toString = implode(', ', $toStrings);

        if(isset($head->cc)) {
            foreach($head->cc as $cc) {
                $mail->cc[strtolower($cc->mailbox . '@' . $cc->host)] = isset($cc->personal) ? $this->decodeMimeStr($cc->personal) : null;
            }
        }

        if(isset($head->reply_to)) {
            foreach($head->reply_to as $replyTo) {
                $mail->replyTo[strtolower($replyTo->mailbox . '@' . $replyTo->host)] = isset($replyTo->personal) ? $this->decodeMimeStr($replyTo->personal) : null;
            }
        }

        $struct = $this->imap_fetchstructure($this->mbox, $id, FT_UID);

        if(empty($struct->parts)) {
            $this->initMailPart($mail, $struct, 0);
        } else {
            foreach($struct->parts as $partNum => $partStruct) {
                $this->initMailPart($mail, $partStruct, $partNum + 1);
            }
        }

        $mail->textHtmlOriginal = $mail->textHtml;

        return $mail;
    }

    protected function quoteAttachmentFilename($filename)
    {
        $replace = array('/\s/' => '_', '/[^0-9a-zA-Z_\.]/' => '', '/_+/' => '_', '/(^_)|(_$)/' => '');

        return preg_replace(array_keys($replace), $replace, $filename);
    }

    protected function initMailPart(ImapMail $mail, $partStruct, $partNum)
    {
        $data = $partNum ? $this->imap_fetchbody($this->mbox, $mail->id, $partNum, FT_UID) : $this->imap_body($this->mbox, $mail->id, FT_UID);

        if($partStruct->encoding == 1) {
            $data = $this->imap_utf8($data);
        } elseif($partStruct->encoding == 2) {
            $data = $this->imap_binary($data);
        } elseif($partStruct->encoding == 3) {
            $data = $this->imap_base64($data);
        } elseif($partStruct->encoding == 4) {
            $data = $this->imap_qprint($data);
        }

        $params = array();
        if(!empty($partStruct->parameters)) {
            foreach($partStruct->parameters as $param) {
                $params[strtolower($param->attribute)] = $param->value;
            }
        }
        if(!empty($partStruct->dparameters)) {
            foreach($partStruct->dparameters as $param) {
                $params[strtolower($param->attribute)] = $param->value;
            }
        }
        if(!empty($params['charset'])) {
            $data = iconv($params['charset'], $this->serverEncoding, $data);
        }

        // attachments
        if($this->attachmentsDir) {
            $filename = false;
            $attachmentId = $partStruct->ifid ? trim($partStruct->id, " <>") : null;
            if(empty($params['filename']) && empty($params['name']) && $attachmentId) {
                $filename = $attachmentId . '.' . strtolower($partStruct->subtype);
            } elseif(!empty($params['filename']) || !empty($params['name'])) {
                $filename = !empty($params['filename']) ? $params['filename'] : $params['name'];
                $filename = $this->decodeMimeStr($filename);
                $filename = $this->quoteAttachmentFilename($filename);
            }
            if($filename) {
                if($this->attachmentsDir) {
                    $filepath = rtrim($this->attachmentsDir, '/\\') . DIRECTORY_SEPARATOR . $mail->id . DIRECTORY_SEPARATOR . $filename;
                    if(!is_dir(dirname($filepath))) {
                        mkdir(dirname($filepath), 0775, true);
                    }
                    file_put_contents($filepath, $data);
                    chmod($filepath, 0664);
                    $mail->attachments[$filename] = $filepath;
                } else {
                    $mail->attachments[$filename] = $filename;
                }
                if($attachmentId) {
                    $mail->attachmentsIds[$filename] = $attachmentId;
                }
            }
        }
        if($partStruct->type == 0 && $data) {
            if(strtolower($partStruct->subtype) == 'plain') {
                $mail->textPlain .= $data;
            } else {
                $mail->textHtml .= $data;
            }
        } elseif($partStruct->type == 2 && $data) {
            $mail->textPlain .= trim($data);
        }
        if(!empty($partStruct->parts)) {
            foreach($partStruct->parts as $subpartNum => $subpartStruct) {
                $this->initMailPart($mail, $subpartStruct, $partNum . '.' . ($subpartNum + 1));
            }
        }
    }

    protected function decodeMimeStr($string, $charset = 'UTF-8')
    {
        $newString = '';
        $elements = $this->imap_mime_header_decode($string);
        for($i = 0; $i < count($elements); $i++) {
            if($elements[$i]->charset == 'default') {
                $elements[$i]->charset = 'iso-8859-1';
            }
            $newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
        }
        return $newString;
    }

    protected function closeConnection()
    {
        if($this->mbox) {
            $errors = imap_errors();
            if($errors) {
                foreach($errors as $error) {
                    trigger_error($error);
                }
            }
            imap_close($this->mbox);
        }
    }

    public function __call($imapFunction, $args)
    {
        //array_unshift($args, $this->mbox);
        $result = call_user_func_array($imapFunction, $args);
        $errors = imap_errors();
        if($errors) {
            foreach($errors as $error) {
                trigger_error($error);
            }
        }
        return $result;
    }

    public function __destruct()
    {
        $this->closeConnection();
    }

    /**
    * Implementation of the Iterator interface
    */
    public function rewind()
    {
        $this->mailIds = $this->searchMails();
        $this->valid = (false !== reset($this->mailIds));
    }
    public function current()
    {
        return $this->getMailInfo(current($this->mailIds));
    }
    public function key()
    {
        return key($this->mailIds);
    }
    public function next()
    {
        $this->valid = (false !== next($this->mailIds));
    }
    public function valid()
    {
        return $this->valid;
    }

    /* Methods */
    public function count()
    {
        if(is_null($this->mailIds)) {
            $this->mailIds = $this->searchMails();
        } return count($this->mailIds);
    }
}
