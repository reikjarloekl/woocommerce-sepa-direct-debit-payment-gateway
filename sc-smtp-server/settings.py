import os

__author__ = 'Joern'

BASE_DIR = os.path.dirname(__file__)

# Database connection string
DATABASE_URL = 'sqlite:///' + os.path.join(os.path.dirname(BASE_DIR), 'sc_frontend/db.sqlite3')

# HTML content of mail sent to recipients
MAIL_CONTENT = """
<img src="cid:{}">
<br/>
<img src="cid:{}">
<br/>
<a href="http://www.simplecam.de">http://www.simplecam.de</a>
"""

# Alternative text for email in case client cannot display html.
MAIL_CONTENT_ALTERNATIVE = 'Ein Foto von Ihrer SimpleCam.'

# The SimpleCam-Logo to be used in mails sent. Has to be a JPEG.
SC_LOGO_FILE = os.path.join(BASE_DIR, 'sc_logo.jpg')

# sender from which mails forwarding images shall originate
SENDER_ADDRESS = 'noreply@simplecam.de'
SENDER_NAME = 'SimpleCam'

# Secret key used to calculate the password from the username
SECRET_KEY = 'To2PqIc8jd2X9MN0pnu1Ug2mcFhm3vs05qHQo1k8zArWU18Cg5vR3sUOw6sv'

# Directory to save the received images to.
IMAGE_DIR = '/var/opt/simplecam/images'

# SMTP Forward Information
SMTP_HOST = 'wp228.webpack.hosteurope.de'
SMTP_USER = 'wp1089149-info'
SMTP_PASS = 'wS94piCr4jwFntUkKrB0'