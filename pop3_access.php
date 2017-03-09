<?php

namespace Kreativrudel\Email;

/**
 * Allows the access to an pop3 mailbox.
 * This script is just an OOP version of: http://php.net/manual/de/book.imap.php#96414
 *
 * @package Kreativrudel\Email
 */
class POP3_Access {

	protected $connection;

	public function __construct($host, $port, $user, $pass, $folder = 'INBOX', $ssl = false) {

		$this->pop3_login($host, $port, $user, $pass, $folder, $ssl);
	}

	public function pop3_stat() {

		$check = imap_mailboxmsginfo( $this->get_connection() );

		return (array) $check;
	}

	public function get_body($mid, $options = FT_UID) {
		return imap_body($this->get_connection(), $mid, $options);
	}

	public function pop3_list( $message = '' ) {

		$result = [];

		if ( $message ) {
			$range = $message;
		} else {
			$MC    = imap_check( $this->get_connection() );
			$range = "1:" . $MC->Nmsgs;
		}

		$response = imap_fetch_overview( $this->get_connection(), $range );

		foreach ( $response as $msg ) {
			$result[ $msg->msgno ] = (array) $msg;
		}

		return $result;
	}

	public function pop3_retrieve( $message ) {

		return imap_fetchheader( $this->get_connection(), $message, FT_PREFETCHTEXT );
	}

	public function pop3_delete( $message ) {
		return imap_delete( $this->get_connection(), $message );
	}

	public function mail_mime_to_array( $mid, $parse_headers = false ) {
		$mail = imap_fetchstructure( $this->get_connection(), $mid );
		$mail = $this->mail_get_parts( $mid, $mail, 0 );

		if ( $parse_headers ) {
			$mail[0]['parsed'] = $this->mail_parse_headers( $mail[0]['data'] );
		}

		return $mail;
	}

	protected function get_connection() {
		return $this->connection;
	}

	protected function set_connection($connection) {
		if ($connection !== false) {
			$this->connection = $connection;
		} else {
			throw new \Exception('Could not connect to host');
		}
	}

	protected function pop3_login( $host, $port, $user, $pass, $folder , $ssl ) {

		$ssl = ( $ssl == true ) ? "/ssl/novalidate-cert" : "";

		$this->set_connection( imap_open( sprintf('{%s:%d/pop3%s}%s', $host, $port, $ssl, $folder), $user, $pass ) );
	}

	protected function mail_parse_headers( $headers ) {

		$result = [];
		$headers = preg_replace( '/\r\n\s+/m', '', $headers );

		preg_match_all( '/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m', $headers, $matches );

		foreach ( $matches[1] as $key => $value ) {
			$result[ $value ] = $matches[2][ $key ];
		}

		return $result;
	}


	protected function mail_get_parts( $mid, $part, $prefix ) {
		$attachments            = [];
		$attachments[ $prefix ] = $this->mail_decode_part( $mid, $part, $prefix );

		if ( isset( $part->parts ) ) // multipart
		{
			$prefix = ( $prefix == "0" ) ? "" : "$prefix.";
			foreach ( $part->parts as $number => $subpart ) {
				$attachments = array_merge( $attachments,
					$this->mail_get_parts( $mid, $subpart,
						$prefix . ( $number + 1 ) ) );
			}
		}

		return $attachments;
	}

	protected function mail_decode_part( $message_number, $part, $prefix ) {
		$attachment = [];

		if ( $part->ifdparameters ) {
			foreach ( $part->dparameters as $object ) {
				$attachment[ strtolower( $object->attribute ) ] = $object->value;
				if ( strtolower( $object->attribute ) == 'filename' ) {
					$attachment['is_attachment'] = true;
					$attachment['filename']      = $object->value;
				}
			}
		}

		if ( $part->ifparameters ) {
			foreach ( $part->parameters as $object ) {
				$attachment[ strtolower( $object->attribute ) ] = $object->value;
				if ( strtolower( $object->attribute ) == 'name' ) {
					$attachment['is_attachment'] = true;
					$attachment['name']          = $object->value;
				}
			}
		}
		$attachment['data'] = imap_fetchbody( $this->get_connection(), $message_number, $prefix );

		if ( $part->encoding == 3 ) { // 3 = BASE64
			$attachment['data'] = base64_decode( $attachment['data'] );
		} elseif ( $part->encoding == 4 ) { // 4 = QUOTED-PRINTABLE
			$attachment['data'] = quoted_printable_decode( $attachment['data'] );
		}

		return $attachment;
	}
}

