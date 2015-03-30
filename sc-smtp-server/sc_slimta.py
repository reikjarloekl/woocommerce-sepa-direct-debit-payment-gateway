from gevent.event import Event
import base64
import hashlib
import hmac
import re
from slimta.smtp.auth import Auth, CredentialsInvalidError
import settings

__author__ = 'Joern'

def start_slimta():
    from slimta.queue.dict import DictStorage
    from slimta.queue import Queue

    storage = DictStorage()
    queue = Queue(storage)
    queue.start()

    from slimta.edge.smtp import SmtpEdge, SmtpValidators

    class ScAuth(Auth):
        def get_password(self, user_id):
            camera_id = user_id.split('@')[0]
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
            raise CredentialsInvalidError()

    class EdgeValidators(SmtpValidators):

        def handle_rcpt(self, reply, recipient):
            if recipient != "hub@simplecam.de":
                reply.code = '550'
                reply.message = '5.7.1 Recipient <{0}> Not allowed'.format(recipient)
                return

    edge = SmtpEdge(('', 1025), queue, max_size=10240,
                    validator_class=EdgeValidators,
                    command_timeout=20.0,
                    data_timeout=30.0, auth_class=ScAuth)
    edge.start()

start_slimta()

try:
    Event().wait()
except KeyboardInterrupt:
    print
