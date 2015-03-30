__author__ = 'Joern'

# HTML content of mail sent to recipients
MAIL_CONTENT =
"""
<img src="cid:{}">
<br/>
Ein Foto von Ihrer SimpleCam.
<br/>
<a href="http://www.simplecam.de">http://www.simplecam.de</a>
"""
# sender from which mails forwarding images shall originate
SENDER_ADDRESS = 'SimpleCam <noreply@simplecam.de>'

# Secret key used to calculate the password from the username
SECRET_KEY = 'To2PqIc8jd2X9MN0pnu1Ug2mcFhm3vs05qHQo1k8zArWU18Cg5vR3sUOw6sv'

# Directory to save the received images to.
IMAGE_DIR = '/var/opt/simplecam/images'