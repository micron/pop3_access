# Access a pop3 mailbox from php

This is an OOP version of: http://php.net/manual/de/book.imap.php#96414

## Usage

### Connection

To connect to your mailbox instantiate the POP3_Access class with a minimum of these parameters:

    use Kreativrudel\Email\POP3_Access;

    $pop3 = new POP3_Access( 'mail.example.com', 110, 'username', 'password' );

### Get Mailbox Stats

    $pop3->pop3_stat();
    
### Get list of messages

    $pop3->pop3_list();

### Get message headers

    $pop3->pop3_retrieve( $msg_id );
    
### Get message body

    $pop3->get_body( $msg_id );