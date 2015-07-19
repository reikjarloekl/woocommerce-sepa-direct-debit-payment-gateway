import base64
import hashlib
import hmac
from django.db import models
from django.contrib.auth.models import User
from sc_frontend import settings


class EmailAddress(models.Model):
    user = models.ForeignKey(User, null=True)
    name = models.CharField(max_length=60, default="")
    address = models.EmailField(max_length=254)
    verified = models.BooleanField(default=False)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return "{}: {} (verified: {})".format(self.user.username, self.address, self.verified)


class Camera(models.Model):
    user = models.ForeignKey(User)
    name = models.CharField(max_length=100)
    email_addresses = models.ManyToManyField(EmailAddress)

    @property
    def smtp_password(self):
        expected_pw = base64.b64encode(hmac.new(settings.SMTP_SECRET_KEY, str(self.id), hashlib.sha256).digest())[:20]
        return expected_pw

    def __str__(self):
        return self.user.username + ': ' + self.name;


class Image(models.Model):
    camera = models.ForeignKey(Camera)
    received = models.DateTimeField()

