import smtplib

#with open("test/mail-no-img.txt", "rb") as fil:
with open("test/mail.txt", "rb") as fil:
        msg = fil.read()

server = smtplib.SMTP('localhost', port=1025)
server.set_debuglevel(1)
server.login('2@simplecam.de', 'nQ7rrIax22TBHivt+avO')
server.sendmail('2@simplecam.de', ['hub@simplecam.de'], msg)
server.quit()
