import datetime
import logging
import re
import time
import os
from slimta.envelope import Envelope
from slimta.policy import QueuePolicy
from sc_camera_information import ScCameraInformation
import settings
from email import email
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.image import MIMEImage

__author__ = 'Joern'

LOGO_NAME = 'logo.jpg'
logger = logging.getLogger(__name__)

class ScForward(QueuePolicy):
    @staticmethod
    def get_content_id(name):
        return '{}@simplecam.de'.format(name)

    def get_mime_image(self, img_data, filename):
        img = MIMEImage(img_data, 'jpeg')
        img.add_header('Content-ID', '<{}>'.format(self.get_content_id(filename)))
        img.add_header('Content-Disposition', 'inline; filename="{}"'.format(filename))
        return img

    def get_image(self, camera_id, message_data):
        msg = email.message_from_string(message_data)
        for part in msg.walk():
            # multipart/* are just containers
            if part.get_content_maintype() == 'multipart':
                continue
            if part.get_content_type() != 'application/octet-stream':
                continue
            # Applications should really sanitize the given filename so that an
            # email message can't be used to overwrite important files

            now = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
            filename = '%04d-%s.jpg' % (camera_id, now)
            img_data = part.get_payload(decode=True)
            with open(os.path.join(settings.IMAGE_DIR, filename), 'wb') as fp:
                logger.debug('Writing attached image to {}'.format(fp.name))
                fp.write(img_data)

            img = self.get_mime_image(img_data, filename)
            return img, filename

    def get_message(self, img, img_filename):
        msg = MIMEMultipart()
        msg_alternative = MIMEMultipart('alternative')
        msg.attach(msg_alternative)
        msg.preamble = "This is a multi-part message in MIME format."
        msg_related = MIMEMultipart('related')
        msg_alternative.attach(MIMEText(settings.MAIL_CONTENT_ALTERNATIVE, 'plain'))
        msg_alternative.attach(msg_related)
        msg_html = MIMEText(settings.MAIL_CONTENT.format(self.get_content_id(img_filename),
                                                         self.get_content_id(LOGO_NAME)), 'html')
        msg_related.attach(msg_html)
        msg_related.attach(img)
        logo_data = open(settings.SC_LOGO_FILE, 'rb').read()
        logo = self.get_mime_image(logo_data, LOGO_NAME)
        msg_related.attach(logo)
        return msg

    def apply(self, envelope):
        """
        :type envelope: :class:`slimta.envelope.Envelope`
        """
        if envelope.sender == '':
            logger.debug('{}: Skipping envelope with empty sender (probably bounced email).'.format(id(envelope)))
            return
        logger.debug('{}: Processing envelope from {} to {}'.format(id(envelope), envelope.sender, envelope.recipients))

        camera_id = int(envelope.sender.split('@')[0])
        caminfo = ScCameraInformation(camera_id)
        logger.debug('{}: Retrieved camera information from db: {}'.format(id(envelope), caminfo))
        try:
            img, filename = self.get_image(camera_id, "".join(envelope.flatten()))
        except TypeError:
            img = None
        if img is None:
            logger.debug('{}: No image found in email. Forwarding mail to {}'.format(id(envelope),
                                                                                     settings.FORWARD_UNKNOWN_EMAILS_TO))
            envelope.recipients = [settings.FORWARD_UNKNOWN_EMAILS_TO]
            return

        msg = self.get_message(img, filename)
        recipients = caminfo.get_forward_addresses()

        ts = datetime.datetime.fromtimestamp(envelope.timestamp).strftime('%d.%m.%Y %H:%M:%S')
        new_env = Envelope(settings.SENDER_ADDRESS, recipients)
        new_env.parse(msg)
        new_env.prepend_header('Subject', 'SimpleCam {}: {}'.format(caminfo.get_name(), ts))
        new_env.prepend_header('To', ', '.join(recipients))
        new_env.prepend_header('From', '"{}" <{}>'.format(settings.SENDER_NAME, settings.SENDER_ADDRESS))
        new_env.message = re.sub('\r?\n', "\r\n", new_env.message)
        logger.debug('{}: Finished processing envelope. Queuing new envelope {}.'.format(id(envelope), id(new_env)))
        return [new_env]

if __name__ == "__main__":
    env = Envelope("1@simplecam.de")
    with open("test/mail.txt", "rb") as fil:
        env.parse(fil.read())

    env.timestamp = time.time()
    pol = ScForward()
    ne = pol.apply(env)
    print re.sub('\r\n', "<newline>", "".join(ne[0].flatten()))