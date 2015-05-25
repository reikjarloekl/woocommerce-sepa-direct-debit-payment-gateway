from django.core.urlresolvers import reverse
from django.core.mail import send_mail
from django.shortcuts import get_object_or_404
from django.template.loader import render_to_string
import jwt
from front.models import EmailAddress
from sc_frontend import settings

__author__ = 'Joern'


def send_confirmation_email(request, camera, receiver, email_id):
    confirmation_token = jwt.encode({'email_id': email_id}, settings.JWT_AUTH_KEY, algorithm='HS256')
    confirmation_link = settings.CONFIRMATION_MAIL_URL.format(confirmation_token)
    params = {'confirmation_link': confirmation_link,
              'user': request.user,
              'camera': camera}
    msg_plain = render_to_string('front/confirmation_email.txt', params)
    msg_html = render_to_string('front/confirmation_email.html', params)
    send_mail(settings.CONFIRMATION_MAIL_SUBJECT.format(camera.name),
              msg_plain,
              settings.CONFIRMATION_MAIL_SENDER,
              [receiver],
              html_message=msg_html)


def check_confirmation(token):
    try:
        payload = jwt.decode(token, settings.JWT_AUTH_KEY, algorithms=['HS256'])
    except jwt.DecodeError:
        return None
    try:
        address = EmailAddress.objects.get(id=payload['email_id'])
    except EmailAddress.DoesNotExist:
        return None
    address.verified = True
    address.save()
    return address

