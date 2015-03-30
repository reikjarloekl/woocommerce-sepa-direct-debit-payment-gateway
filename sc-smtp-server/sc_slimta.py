from gevent.event import Event
from sc_camera_validator import ScValidators, ScAuth

__author__ = 'Joern'

def start_slimta():
    from slimta.queue.dict import DictStorage
    from slimta.queue import Queue
    from slimta.edge.smtp import SmtpEdge

    storage = DictStorage()
    queue = Queue(storage)
    queue.start()

    edge = SmtpEdge(('', 1025), queue,
                    validator_class=ScValidators,
                    command_timeout=20.0,
                    data_timeout=30.0, auth_class=ScAuth)
    edge.start()

start_slimta()

try:
    Event().wait()
except KeyboardInterrupt:
    print