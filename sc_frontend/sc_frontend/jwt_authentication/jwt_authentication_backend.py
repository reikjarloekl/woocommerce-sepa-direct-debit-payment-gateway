from django.contrib.auth.backends import RemoteUserBackend
from django.contrib.auth.models import User

__author__ = 'Joern'

class JwtBackend(RemoteUserBackend):
    def authenticate(self, login, first_name, last_name, email, id):
        try:
            user = User.objects.get(email=email)
            print 'User already in DB.'
        except User.DoesNotExist:
            print 'User not found. Creating new.'
            user = User.objects.create(username=login,
                                       first_name=first_name,
                                       last_name=last_name,
                                       email=email)
        return user