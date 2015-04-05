from sqlalchemy.ext.automap import automap_base
from sqlalchemy.orm import Session
from sqlalchemy import create_engine
import settings

__author__ = 'Joern'

class ScCameraInformation:
    def __init__(self, camera_id):
        self._camera_id = camera_id
        self._base = automap_base()
        # engine, suppose it has two tables 'user' and 'address' set up
        self._engine = create_engine(settings.DATABASE_URL)
        # reflect the tables
        self._base.prepare(self._engine, reflect=True)
        self._camera = self._base.classes.front_camera
        self._camera_mapping = self._base.classes.front_camera_email_addresses
        self._email_address = self._base.classes.front_emailaddress
        self._session = Session(self._engine)

    def get_forward_addresses(self):
        query = self._session.query(self._email_address.address)\
            .filter(self._camera_mapping.emailaddress_id == self._email_address.id)\
            .filter(self._camera_mapping.camera_id == self._camera_id)
        return [row[0] for row in query.all()]

    def get_name(self):
        query = self._session.query(self._camera.name)\
            .filter(self._camera.id == self._camera_id)
        return query.first()[0]

if __name__ == "__main__":
    caminfo = ScCameraInformation(1)
    print caminfo.get_forward_addresses()
    print caminfo.get_name()