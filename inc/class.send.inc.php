<?php
/**
 * EGroupware API: Sending mail via egw_mailer
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage mail
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * @deprecated use egw_mailer class direct
 */
class send extends egw_mailer
{
	/**
	* Reset all Settings to send multiple Messages
	*/
	function ClearAll()
	{
		$this->err = array();

		$this->Subject = $this->Body = $this->AltBody = '';
		$this->IsHTML(False);
		$this->ClearAllRecipients();
		$this->ClearAttachments();
		$this->ClearCustomHeaders();

		$this->FromName = $GLOBALS['egw_info']['user']['account_fullname'];
		$this->From = $GLOBALS['egw_info']['user']['account_email'];

		$this->AddCustomHeader('X-Mailer:eGroupWare (http://www.eGroupWare.org)');
	}

	/**
	* Emulating the old send::msg interface for compatibility with existing code
	*
	* You can either use that code or the PHPMailer variables and methods direct.
	*/
	function msg($service, $to, $subject, $body, $msgtype='', $cc='', $bcc='', $from='', $sender='', $content_type='', $boundary='Message-Boundary')
	{
		if ($this->debug) error_log(__METHOD__." to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender'");
		unset($boundary);	// not used, but required by function signature
		//echo "<p>send::msg(,to='$to',subject='$subject',,'$msgtype',cc='$cc',bcc='$bcc',from='$from',sender='$sender','$content_type','$boundary')<pre>$body</pre>\n";
		$this->ClearAll();	// reset everything to its default, we might be called more then once !!!

		if ($service != 'email')
		{
			return False;
		}
		if ($from)
		{
			$matches = null;
			if (preg_match('/"?(.+)"?<(.+)>/',$from,$matches))
			{
				list(,$this->FromName,$this->From) = $matches;
			}
			else
			{
				$this->From = $from;
				$this->FromName = '';
			}
		}
		if ($sender)
		{
			$this->Sender = $sender;
		}
		foreach(array('to','cc','bcc') as $adr)
		{
			if ($$adr)
			{
				if (is_string($$adr) && preg_match_all('/"?(.+)"?<(.+)>,?/',$$adr,$matches))
				{
					$names = $matches[1];
					$addresses = $matches[2];
				}
				else
				{
					$addresses = is_string($$adr) ? explode(',',trim($$adr)) : explode(',',trim(array_shift($$adr)));
					$names = array();
				}
				$method = 'Add'.($adr == 'to' ? 'Address' : $adr);

				foreach($addresses as $n => $address)
				{
					$this->$method($address,$names[$n]);
				}
			}
		}
		if (!empty($msgtype))
		{
			$this->AddCustomHeader('X-eGW-Type: '.$msgtype);
		}
		if ($content_type)
		{
			$this->ContentType = $content_type;
		}
		$this->Subject = $subject;
		$this->Body = $body;

		//echo "PHPMailer = <pre>".print_r($this,True)."</pre>\n";
		if (!$this->Send())
		{
			$this->err = array(
				'code' => 1,	// we dont get a numerical code from PHPMailer
				'msg'  => $this->ErrorInfo,
				'desc' => $this->ErrorInfo,
			);
			return False;
		}
		return True;
	}

	/**
	* encode 8-bit chars in subject-line
	*
	* @deprecated This is not needed any more, as it is done be PHPMailer, but older code depend on it.
	*/
	function encode_subject($subject)
	{
		return $subject;
	}
}
