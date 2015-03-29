import smtplib

msg = """From: foo@localhost
To: bar@localhost
Here's my message!
"""

server = smtplib.SMTP('localhost', port=1025)
server.set_debuglevel(1)
server.login('1@simplecam.de', 'Nkfm8201/ltNdjdaytrg')
server.sendmail('foo@localhost', ['bar@localhost'], msg)
server.quit()
