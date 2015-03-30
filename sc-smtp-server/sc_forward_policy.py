import datetime
import os
from os.path import basename
from slimta.envelope import Envelope
from slimta.policy import QueuePolicy
import settings
from email import email
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
__author__ = 'Joern'


class ScForward(QueuePolicy):
    def get_image(self, sender, message_data):
        msg = email.message_from_string(message_data)
        for part in msg.walk():
            # multipart/* are just containers
            if part.get_content_maintype() == 'multipart':
                continue
            if part.get_content_type() != 'application/octet-stream':
                continue
            # Applications should really sanitize the given filename so that an
            # email message can't be used to overwrite important files
            camera_id = int(sender.split('@')[0])
            now = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
            filename = '%04d-%s.jpg' % (camera_id, now)
            #fp = open(os.path.join(settings.IMAGE_DIR, filename), 'wb')
            #fp.write(part.get_payload(decode=True))
            #fp.close()
            return part

    def apply(self, envelope):
        part = self.get_image(envelope.sender, "".join(envelope.flatten()))
        msg = MIMEMultipart()
        msg.attach(MIMEText("Mail von SimpleCam."))
        msg.attach(part)
        print msg.as_string()
        env = Envelope(settings.SENDER_ADDRESS, ["jb@kaspa.net"], None, msg)

env = Envelope("1@simplecam.de")
with open("test/mail.txt", "rb") as fil:
    env.parse(fil.read())
pol = ScForward()
pol.apply(env)