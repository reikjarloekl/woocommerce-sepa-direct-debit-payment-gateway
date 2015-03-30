import datetime
import time
import os
from os.path import basename
from slimta.envelope import Envelope
from slimta.policy import QueuePolicy
import settings
from email import email
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.image import MIMEImage

__author__ = 'Joern'

ATTACHMENT_NAME = "simplecam.jpg"

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
            img_data = part.get_payload(decode=True)
            #fp = open(os.path.join(settings.IMAGE_DIR, filename), 'wb')
            #fp.write(img_data)
            #fp.close()

            img = MIMEImage(img_data, 'jpeg')
            img.add_header('Content-ID', ATTACHMENT_NAME)
            return img

    def apply(self, envelope):
        img = self.get_image(envelope.sender, "".join(envelope.flatten()))
        if img is None:
            envelope.recipients = []
            return
        msg = MIMEMultipart()
        ts = datetime.datetime.fromtimestamp(envelope.timestamp).strftime('%d.%m.%Y %H:%M:%S')
        msg['Subject'] = 'SimpleCam {}: {}'.format("#Hohe Kanzel", ts)
        msg['From'] = settings.SENDER_ADDRESS
        msg['To'] = 'jb@kaspa.net'
        msg_alternative = MIMEMultipart('alternative')
        msg.attach(msg_alternative)
        msg_alternative.attach(MIMEText("Ein Foto von Ihrer SimpleCam.", 'plain'))
        msg_text = MIMEText('<img src="cid:{}"><br/>Ein Foto von Ihrer SimpleCam.'.format(ATTACHMENT_NAME), 'html')
        msg_alternative.attach(msg_text)
        msg.attach(img)
        new_env = Envelope()
        new_env.parse(msg)
        print "".join(new_env.flatten())

env = Envelope("1@simplecam.de")
with open("test/mail.txt", "rb") as fil:
    env.parse(fil.read())

env.timestamp = time.time()
pol = ScForward()
pol.apply(env)