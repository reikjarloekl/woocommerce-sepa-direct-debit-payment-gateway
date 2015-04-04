from gevent.event import Event
from slimta.policy.split import RecipientSplit
from slimta.relay.smtp.mx import MxSmtpRelay
from sc_camera_validator import ScValidators, ScAuth
from sc_forward_policy import ScForward
import logging

__author__ = 'Joern'

def start_slimta():
    from slimta.queue.dict import DictStorage
    from slimta.queue import Queue
    from slimta.edge.smtp import SmtpEdge

    relay = MxSmtpRelay()

    storage = DictStorage()
    queue = Queue(storage, relay)
    queue.start()

    queue.add_policy(ScForward())
    queue.add_policy(RecipientSplit())
    edge = SmtpEdge(('', 1025), queue,
                    validator_class=ScValidators,
                    command_timeout=20.0,
                    data_timeout=30.0, auth_class=ScAuth)
    edge.start()

logging.basicConfig()

start_slimta()

try:
    Event().wait()
except KeyboardInterrupt:
    print
