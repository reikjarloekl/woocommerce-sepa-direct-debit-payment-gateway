import base64
import hashlib
import hmac
import logging
import re
from slimta.edge.smtp import SmtpValidators
from slimta.smtp.auth import CredentialsInvalidError, Auth
from slimta.smtp.auth.standard import Login
from sc_camera_information.sc_camera_information import ScCameraInformation
import settings

__author__ = 'Joern'
logger = logging.getLogger(__name__)

class ScValidators(SmtpValidators):
    def handle_rcpt(self, reply, recipient):
        if recipient != "hub@simplecam.de":
            reply.code = '550'
            reply.message = '5.7.1 Recipient <{0}> Not allowed'.format(recipient)
            logger.info('Sending attempted to other recipient than "hub@simplecam.de".')
            return


class ScAuth(Auth):
    def get_password(self, user_id):
        camera_id = user_id.split('@')[0]
        try:
            ScCameraInformation(camera_id)
        except IndexError:
            logger.info('Log-in attempted from unknown camera ({}).'.format(user_id))
            raise CredentialsInvalidError()

        expected_pw = base64.b64encode(hmac.new(settings.SECRET_KEY, camera_id, hashlib.sha256).digest())[:20]
        return expected_pw

    def verify_secret(self, authcid, secret, authzid=None):
        expected_pw = self.get_password(authcid)

        if secret != expected_pw:
            raise CredentialsInvalidError()
        return authcid

    def get_secret(self, username, identity=None):
        pattern = re.compile('\d*@simplecam.de')
        if pattern.match(username):
            return self.get_password(username), username
        logger.info('Log-in attempted from unknown camera ({}).'.format(username))
        raise CredentialsInvalidError()

    def get_available_mechanisms(self, connection_secure=False):
        return [Login]