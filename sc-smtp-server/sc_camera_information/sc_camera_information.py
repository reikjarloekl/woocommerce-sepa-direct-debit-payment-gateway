from sqlalchemy.orm import Session
import settings
from __init__ import db_engine, Camera, Image, Email_address, Camera_mapping, User

__author__ = 'Joern'

class ScCameraInformation:
    def __init__(self, camera_id):
        self._camera_id = camera_id
        self._session = Session(db_engine)
        self._camera = self._session.query(Camera).filter(Camera.id == self._camera_id).first()
        if self._camera is None:
            raise IndexError('Camera with ID {} not found in database.'.format(camera_id))

    def get_forward_addresses(self):
        user_address = self._session.query(User.email)\
            .filter(User.id == self._camera.user_id).first().email
        linked_mail_addresses = self._session.query(Email_address.address)\
            .filter(Camera_mapping.emailaddress_id == Email_address.id)\
            .filter(Camera_mapping.camera_id == self._camera_id).all()
        return [row.address for row in linked_mail_addresses] + [user_address]

    def get_name(self):
        return self._camera.name

    def get_latest_image(self):
        return self._session.query(Image)\
            .filter(Image.camera_id == self._camera_id)\
            .order_by(Image.id.desc()).first()

    def add_image(self, received):
        self._session.add(Image(camera_id=self._camera_id, received=received))
        self._session.commit()

    def __repr__(self):
        template = "Camera #{}, Name: {}, Forward addresses: {}"
        return template.format(self._camera_id, self.get_name(), self.get_forward_addresses())

if __name__ == "__main__":
    print "settings.DATABASE_URL: {}".format(settings.DATABASE_URL)
    caminfo = ScCameraInformation(1)
    print "Forward addresses: {}".format(caminfo.get_forward_addresses())
    print "Name: {}".format(caminfo.get_name())
    print "Latest image from: {}".format(caminfo.get_latest_image().received)