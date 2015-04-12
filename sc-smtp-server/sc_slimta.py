from gevent.event import Event
from slimta.policy.split import RecipientSplit
from slimta import system
from slimta.relay.smtp.static import StaticSmtpRelay
from sc_camera_validator import ScValidators, ScAuth
from sc_forward_policy import ScForward
import logging
import settings

__author__ = 'Joern'

def start_slimta():
    from slimta.queue.dict import DictStorage
    from slimta.queue import Queue
    from slimta.edge.smtp import SmtpEdge

    relay = StaticSmtpRelay(settings.SMTP_HOST, credentials=[settings.SMTP_USER, settings.SMTP_PASS])

    storage = DictStorage()
    queue = Queue(storage, relay)
    queue.start()

    queue.add_policy(ScForward())
    queue.add_policy(RecipientSplit())
    edge = SmtpEdge(('', settings.SMTP_PORT), queue,
                    validator_class=ScValidators,
                    command_timeout=20.0,
                    data_timeout=30.0, auth_class=ScAuth)
    edge.start()

log_format = '%(asctime)s - %(levelname)s - %(thread)d:%(module)s.%(funcName)s: %(message)s'
logging.basicConfig(filename=settings.LOG_FILE, level=settings.LOG_LEVEL, format=log_format)

start_slimta()

system.daemonize()

try:
    Event().wait()
except KeyboardInterrupt:
    print