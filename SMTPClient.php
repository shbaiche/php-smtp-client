<?php
class SMTPClient {

	/* Server settings */
	private $smtpHost = "";
	private $smtpPort = 25;
	private $security = ""; 

	/* Sender settings */
	private $user = "";
	private $password = "";
	private $from = "";

	// security: (ssl, tls) if necessary.
	public function __construct($options) {
		$this->smtpHost = empty($options['security']) ? $options['smtpHost'] : $options['security'].'://'.$options['smtpHost'];
		$this->smtpPort = $options['smtpPort'];
		$this->user = $options['user'];
		$this->password = $options['password'];
		$this->from = $options['from'];
		$this->name = empty($options['name']) ? "" : $options['name'];
	}

	/** Connect to the SMTP server and send the email.
	    Set the mail content : recipients, subject message and content type and charset.
	    $to can be an array or a list of addresses separated by commas.
	    Change content-type if you wants to send an HTML page.
		If content type is not text/plain, it is sent as Multipart MIME and HTML
	    Change charset has needed.
		tags are stripped in the plain text version.
	    */
	public function sendMail($to, $subject, $message, $attachedFiles=null, $contentType = "text/html", $charset="utf8") {
		if (!is_array($to)) {
			//str_replace is called twice to delete spaces next to ','
			$to = explode(",", str_replace(", ",",",str_replace(" ,", ",", $to)));
		}
		$socket = null;
		try {
			if (!($socket = fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 15)))
				throw new Exception("Could not connect to SMTP host");

			$this->waitForPositiveCompletionReply($socket);

			fwrite($socket, "EHLO " . gethostname() . "\r\n");
			$this->waitForPositiveCompletionReply($socket);

			//Auth
			if ($this->user != "" && $this->password != "") {
				fwrite($socket, "AUTH LOGIN"."\r\n");
				$this->waitForPositiveIntermediateReply($socket);

				fwrite($socket, base64_encode($this->user)."\r\n");
				$this->waitForPositiveIntermediateReply($socket);

				fwrite($socket, base64_encode($this->password)."\r\n");
				$this->waitForPositiveCompletionReply($socket);
			}

			//From
			fwrite($socket, "MAIL FROM: <" . $this->from . ">"."\r\n");
			$this->waitForPositiveCompletionReply($socket);

			//To
			foreach ($to as $email) {
				fwrite($socket, "RCPT TO: <" . $email . ">" . "\r\n");
				$this->waitForPositiveCompletionReply($socket);
			}
			//Mail content
			fwrite($socket, "DATA"."\r\n");
			$this->waitForPositiveIntermediateReply($socket);

			$multiPartMessage = "";
			$mimeBoundary="__NextPart_" . md5(time());
			//Multipart MIME header
			$multiPartMessage .= "MIME-Version: 1.0" . "\r\n";
			$multiPartMessage .= "Content-Type: multipart/mixed;";
			$multiPartMessage .= " boundary=" . $mimeBoundary ."" . "\r\n";
			$multiPartMessage .= "\r\n";
			$multiPartMessage .= "This is a multi-part message in MIME format." . "\r\n";
			$multiPartMessage .= "\r\n";

			//Raw text mail version
			$multiPartMessage .= "--" . $mimeBoundary . "\r\n";
			$multiPartMessage .= "Content-Type: " . $contentType . "; charset=\"" . $charset . "\"" . "\r\n";
			$multiPartMessage .= "Content-Transfer-Encoding: quoted-printable" . "\r\n";
			$multiPartMessage .= "\r\n";
			$multiPartMessage .= quoted_printable_encode($message) . "\r\n";
			$multiPartMessage .= "\r\n";
			//Attached Files
			if ($attachedFiles) {
				foreach($attachedFiles as $attachedFile) {
					$multiPartMessage .= "--" . $mimeBoundary . "\r\n";
					$multiPartMessage .= "Content-Type: " . $attachedFile["Content-Type"] . ";" . "\r\n";
					$multiPartMessage .= "	name=\"" . $attachedFile["Filename"] . "\"" . "\r\n";
					$multiPartMessage .= "Content-Transfer-Encoding: base64" . "\r\n";
					$multiPartMessage .= "Content-Description: " . $attachedFile["Filename"] . "\r\n";
					$multiPartMessage .= "Content-Disposition: attachment;" . "\r\n";
					$multiPartMessage .= "	filename=\"" . $attachedFile["Filename"] . "\"" . "\r\n";
					$multiPartMessage .= "\r\n";
					$multiPartMessage .= $attachedFile["Content"] . "\r\n";
					$multiPartMessage .= "\r\n";
				}
			}
			$multiPartMessage .= "--" . $mimeBoundary . "--" . "\r\n";
			//Write content on socket
			fwrite($socket, "Subject: " . $subject . "\r\n");
			fwrite($socket, "To: <" . implode(">, <", $to) . ">" . "\r\n");
			fwrite($socket, "From: <" . $this->from . ">" . $this->name . "\r\n");
			fwrite($socket, $multiPartMessage . "\r\n");
			//Mail end
			fwrite($socket, "."."\r\n");
			$this->waitForPositiveCompletionReply($socket);
			//Close connection
			fwrite($socket, "QUIT"."\r\n");
			fclose($socket);
		} catch (Exception $e) {
			//echo "Error while sending email. Reason : \n" . $e->getMessage();
			return false;
		}
		return true;
	}

	/** Verify if server responds with a positive preliminary (1xx) status code */
	protected function waitForPositivePreliminaryReply($socket) {
		try {
			$this->_serverRespondedAsExpected($socket, 1);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** Verify if server responds with a positive completion (2xx) status code */
	protected function waitForPositiveCompletionReply($socket) {
		try {
			$this->_serverRespondedAsExpected($socket, 2);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** Verify if server responds with a positive intermediate (3xx) status code */
	protected function waitForPositiveIntermediateReply($socket) {
		try {
			$this->_serverRespondedAsExpected($socket, 3);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** Verify if server responds with a transient negative completion (4xx) status code */
	protected function waitForTransientNegativeCompletionReply($socket) {
		try {
			$this->_serverRespondedAsExpected($socket, 4);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** Verify if server responds with a permanent negative completion (5xx) status code */
	protected function waitForPermanentNegativeCompletionReply($socket) {
		try {
			$this->_serverRespondedAsExpected($socket, 5);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/** Check if the received response is the expected one.
	    Should not be called directly, use thes waitFor...() methods instead
	    */
	private function _serverRespondedAsExpected($socket, $expectedStatusCode) {
		$serverResponse = "";
		//SMTP server can send multiple response.
		//For example several 250 status code after EHLO
		while (substr($serverResponse, 3, 1) != " ") {
			$serverResponse = fgets($socket, 256);
			//echo $serverResponse.'<br>';
			if (!($serverResponse))
 				throw new Exception("Couldn\'t get mail server response codes.");
		}
		$statusCode = substr($serverResponse, 0, 3);
		$statusMessage = substr($serverResponse, 4);
		if (!(is_numeric($statusCode) && (int)($statusCode / 100) == $expectedStatusCode)) {
			throw new Exception($statusMessage);
		}
	}

}
