import base64
import hashlib
import hmac
import settings

__author__ = 'Joern'


class ScCameraValidator(object):
    def validate(self, username, password):

        camera_id = username.split('@')[0]
        expected_pw = base64.b64encode(hmac.new(settings.SECRET_KEY, camera_id, hashlib.sha256).digest())[:20]

        if password == expected_pw:
            return True
        return False
