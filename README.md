# php-smtp-client
**SMTPClient** class allow you to connect to an SMTP server to send an email without any sendmail command.

### Requirements ###
You have to use your own SMTP account to connect to a server.


### Attach file to your email ###

- **Filename**: The filename is the file without the path information.
- **Content**: (`base64_encode`) File raw content can be given by file_get_content.
- **Content-Type**: MIME Type "application/octet-stream" seems to work for many file type. Change it to the real value if needed. (image/gif, image/png, etc.)
