import email
import logging
import os
import datetime
from secure_smtpd import SMTPServer, FakeCredentialValidator, LOG_NAME
import settings
from sc_camera_validator import ScCameraValidator

class SSLSMTPServer(SMTPServer):
    def process_message(self, peer, mailfrom, rcpttos, message_data):
        msg = email.message_from_string(message_data)
        logger.info("Receiving message from %s" % mailfrom)
        for part in msg.walk():
            # multipart/* are just containers
            if part.get_content_maintype() == 'multipart':
                continue
            if part.get_content_type() != 'application/octet-stream':
                continue
            # Applications should really sanitize the given filename so that an
            # email message can't be used to overwrite important files
            camera_id = username.split('@')[0]
            now = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
            filename = '%04d-%s.jpg' % (camera_id, now)
            logger.info("Storing image %s" % filename)
            fp = open(os.path.join(settings.IMAGE_DIR, filename), 'wb')
            fp.write(part.get_payload(decode=True))
            fp.close()

logger = logging.getLogger( LOG_NAME )
logger.setLevel(logging.INFO)

server = SSLSMTPServer(
    ('0.0.0.0', 1025),
    None,
    require_authentication=True,
    ssl=False,
    credential_validator=ScCameraValidator(),
    maximum_execution_time=1.0
    )

server.run()