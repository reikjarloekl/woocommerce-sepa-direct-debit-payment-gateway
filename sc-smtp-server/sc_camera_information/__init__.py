from sqlalchemy import create_engine
from sqlalchemy.ext.automap import automap_base
import settings

__author__ = 'Joern'

_base = automap_base()
db_engine = create_engine(settings.DATABASE_URL, pool_recycle=3600)
# reflect the tables
_base.prepare(db_engine, reflect=True)

Camera = _base.classes.front_camera
Camera_mapping = _base.classes.front_camera_email_addresses
Email_address = _base.classes.front_emailaddress
Image = _base.classes.front_image
