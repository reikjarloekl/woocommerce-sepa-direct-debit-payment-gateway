import smtplib

with open("test/mail.txt", "rb") as fil:
        msg = fil.read()

server = smtplib.SMTP('localhost', port=1025)
server.set_debuglevel(1)
server.login('1@simplecam.de', 'Nkfm8201/ltNdjdaytrg')
server.sendmail('1@simplecam.de', ['hub@simplecam.de'], msg)
server.quit()
