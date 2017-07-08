<?php
namespace Void;

class ImapMail {

	public $id;
	public $date;
	public $subject;

	public $fromName;
	public $fromAddress;

	public $to = array();
	public $toString;
	public $cc = array();
	public $replyTo = array();

	public $textPlain;
	public $textHtml;
	public $textHtmlOriginal;
	public $attachments = array();
	public $attachmentsIds = array();

	public function fetchMessageInternalLinks($baseUrl) {
		if($this->textHtml) {
			foreach($this->attachments as $filepath) {
				$filename = basename($filepath);
				if(isset($this->attachmentsIds[$filename])) {
					$this->textHtml = preg_replace('/(<img[^>]*?)src=["\']?ci?d:' . preg_quote($this->attachmentsIds[$filename]) . '["\']?/is', '\\1 src="' . $baseUrl . $filename . '"', $this->textHtml);
				}
			}
		}
		return $this;
	}

	public function fetchMessageHtmlTags($stripTags = array('html', 'body', 'head', 'meta')) {
		if($this->textHtml) {
			foreach($stripTags as $tag) {
				$this->textHtml = preg_replace('/<\/?' . $tag . '.*?>/is', '', $this->textHtml);
			}
			$this->textHtml = trim($this->textHtml, " \r\n");
		}
		return $this;
	}
	
	public function __toString() {
		return $this->textHtml;
	}
}
