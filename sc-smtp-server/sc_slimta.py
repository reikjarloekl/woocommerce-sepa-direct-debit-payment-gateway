import base64
import hashlib
import hmac
from slimta.smtp.auth import Auth
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
        def verify_secret(self, authcid, secret, authzid=None):
            camera_id = authcid.split('@')[0]
            expected_pw = base64.b64encode(hmac.new(settings.SECRET_KEY, camera_id, hashlib.sha256).digest())[:20]

            if secret == expected_pw:
                return True
            return False

    class EdgeValidators(SmtpValidators):

        def handle_rcpt(self, reply, recipient):
            if recipient != "hub@simplecam.de":
                reply.code = '550'
                reply.message = '5.7.1 Recipient <{0}> Not allowed'.format(recipient)
                return

    edge = SmtpEdge(('', 1025), queue, max_size=10240,
                    validator_class=EdgeValidators, tls=tls,
                    command_timeout=20.0,
                    data_timeout=30.0, auth_class=ScAuth)
    edge.start()
